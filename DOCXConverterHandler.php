<?php

/**
 * @file plugins/generic/docxConverter/DOCXConverterHandler.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University Library
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v2.
 *
 * @brief handler for the grid's conversion
 */

namespace APP\plugins\generic\docxConverter;

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\plugins\generic\docxConverter\classes\DOCXConverterDocument;
use APP\submission\Submission;
use DAORegistry;
use docx2jats\DOCXArchive;
use PKP\core\JSONMessage;
use PKP\file\PrivateFileManager;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class DOCXConverterHandler extends Handler
{
    /**
     * @copydoc PKPHandler::_isBackendPage
     */
    var $_isBackendPage = true;

    function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['parse']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    function authorize($request, &$args, $roleAssignments): bool
    {
        $this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', (int)$request->getUserVar('stageId')));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Parses a submission file, converts it to JATS XML, attaches supplementary files, and creates a new submission file record.
     */
    public function parse(array $args, Request $request): JSONMessage
    {
        $submissionFileId = (int)$request->getUserVar('submissionFileId');
        $submissionFile = Repo::submissionFile()->get($submissionFileId);

        $fileManager = new PrivateFileManager();
        $filePath = $fileManager->getBasePath() . '/' . $submissionFile->getData('path');

        $docxArchive = new DOCXArchive($filePath);
        $jatsXML = new DOCXConverterDocument($docxArchive);

        $submissionId = $submissionFile->getData('submissionId');
        $submission = Repo::submission()->get($submissionId);
        $jatsXML->setDocumentMeta($request, $submission);
        $tmpfname = tempnam(sys_get_temp_dir(), 'docxConverter');
        file_put_contents($tmpfname, $jatsXML->saveXML());
        $genreId = $submissionFile->getData('genreId');

        // Add new JATS XML file
        $submissionDir = Repo::submissionFile()->getSubmissionDir($submission->getData('contextId'), $submissionId);
        $newFileId = Services::get('file')->add(
            $tmpfname,
            $submissionDir . DIRECTORY_SEPARATOR . uniqid() . '.xml'
        );

        $newSubmissionFile = Repo::submissionFile()->newDataObject();
        $newName = [];
        foreach ($submissionFile->getData('name') as $localeKey => $name) {
            $newName[$localeKey] = pathinfo($name)['filename'] . '.xml';
        }

        $newSubmissionFile->setAllData(
            [
                'fileId' => $newFileId,
                'assocType' => $submissionFile->getData('assocType'),
                'assocId' => $submissionFile->getData('assocId'),
                'fileStage' => $submissionFile->getData('fileStage'),
                'mimetype' => 'application/xml',
                'locale' => $submissionFile->getData('locale'),
                'genreId' => $genreId,
                'name' => $newName,
                'submissionId' => $submissionId,
            ]
        );

        Repo::submissionFile()->add($newSubmissionFile, $request);

        unlink($tmpfname);

        $mediaData = $docxArchive->getMediaFilesContent();

        if (!empty($mediaData)) {
            foreach ($mediaData as $originalName => $singleData) {
                $this->attachSupplementaryFile($request, $submission, $newSubmissionFile, $fileManager, $originalName, $singleData);
            }
        }

        return new JSONMessage(true, [
            'submissionId' => $submissionId,
            'fileId' => $newSubmissionFile->getData('fileId'),
            'fileStage' => $newSubmissionFile->getData('fileStage')
        ]);
    }

    /**
     * Attaches a supplementary file to an existing submission by processing the provided file data.
     */
    private function attachSupplementaryFile(Request $request, Submission $submission, SubmissionFile $newSubmissionFile, PrivateFileManager $fileManager, string $originalName, string $singleData): void
    {
        $tmpfnameSuppl = tempnam(sys_get_temp_dir(), 'docxConverter');
        file_put_contents($tmpfnameSuppl, $singleData);
        $mimeType = mime_content_type($tmpfnameSuppl);

        // Determine genre
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByDependenceAndContextId(true, $request->getContext()->getId());
        $supplGenreId = null;
        while ($genre = $genres->next()) {
            if (($mimeType == "image/png" || $mimeType == "image/jpeg") && $genre->getKey() == "IMAGE") {
                $supplGenreId = $genre->getId();
            }
        }

        if (!$supplGenreId) {
            unlink($tmpfnameSuppl);
            return;
        }

        $submissionDir = Repo::submissionFile()->getSubmissionDir($submission->getData('contextId'), $submission->getId());
        $newFileId = Services::get('file')->add(
            $tmpfnameSuppl,
            $submissionDir . '/' . uniqid() . '.' . $fileManager->parseFileExtension($originalName)
        );

        // Set file
        $newSupplementaryFile = Repo::submissionFile()->newDataObject();
        $newSupplementaryFile->setAllData([
            'fileId' => $newFileId,
            'assocId' => $newSubmissionFile->getId(),
            'assocType' => Application::ASSOC_TYPE_SUBMISSION_FILE,
            'fileStage' => SubmissionFile::SUBMISSION_FILE_DEPENDENT,
            'submissionId' => $submission->getId(),
            'genreId' => $supplGenreId,
            'name' => array_fill_keys(array_keys($newSubmissionFile->getData('name')), basename($originalName))
        ]);

        Repo::submissionFile()->add($newSupplementaryFile, $request);
        unlink($tmpfnameSuppl);
    }
}

// For backwards compatibility -- expect this to be removed approx. OJS/OMP/OPS 3.6
if (!PKP_STRICT_MODE) {
    class_alias('APP\plugins\generic\docxConverter\DOCXConverterHandler', '\DOCXConverterHandler');
}
