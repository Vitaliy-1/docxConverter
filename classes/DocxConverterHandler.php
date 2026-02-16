<?php

/**
 * @file plugins/generic/docxConverter/classes/DocxConverterHandler.php
 *
 * Copyright (c) 2021-2026 TIB Hannover
 * Copyright (c) 2021-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DocxConverterHandler
 *
 * @ingroup plugins_generic_docxconverter
 *
 * @brief Handler for the plugin.
 */

namespace APP\plugins\generic\docxConverter\classes;

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use APP\plugins\generic\docxConverter\DocxConverterPlugin;
use APP\submission\Submission;
use docx2jats\DOCXArchive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use PKP\db\DAORegistry;
use PKP\file\PrivateFileManager;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\submissionFile\SubmissionFile;

class DocxConverterHandler
{
    public DocxConverterPlugin $plugin;

    public function __construct(DocxConverterPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * This allows to add a route on the fly without defining an api controller.
     * Hook: APIHandler::endpoints::submissions
     * e.g. api/v1/submissions/docxConverter/{submission_file_id}/__action__
     */
    public function addRoute(string $hookName, PKPBaseController $apiController, APIHandler $apiHandler): bool
    {
        $apiHandler->addRoute(
            'GET',
            "docxConverter/{submission_file_id}/convert",
            fn(IlluminateRequest $request): JsonResponse => $this->convert($request),
            'docxConverter.convertDocx',
            DocxConverterPlugin::AUTHORIZED_ROLES
        );

        return Hook::CONTINUE;
    }

    /**
     * Converts a DOCX file associated with a submission into JATS XML format
     * and adds it as a new submission file, along with any supplementary files.
     */
    private function convert(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submissionFile = Repo::submissionFile()->get((int)$illuminateRequest->route('submission_file_id'));
        if (!$submissionFile) {
            return response()->json(
                ['error' => __('api.404.resourceNotFound')],
                Response::HTTP_NOT_FOUND
            );
        }

        $request = Application::get()->getRequest();

        $fileManager = new PrivateFileManager();
        $filePath = $fileManager->getBasePath() . '/' . $submissionFile->getData('path');

        $docxArchive = new DOCXArchive($filePath);
        $jatsXML = new DocxConverterDocument($docxArchive);

        $submissionId = $submissionFile->getData('submissionId');
        $submission = Repo::submission()->get($submissionId);
        $jatsXML->setDocumentMeta($submission);
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

        return response()->json([
            'submissionId' => $submissionId,
            'fileId' => $newSubmissionFile->getData('fileId'),
            'fileStage' => $newSubmissionFile->getData('fileStage'),
        ], Response::HTTP_OK
        );
    }

    /**
     * Attaches a supplementary file to a submission file.
     */
    private function attachSupplementaryFile(
        Request            $request, Submission $submission, SubmissionFile $newSubmissionFile,
        PrivateFileManager $fileManager, string $originalName, string $singleData): void
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
