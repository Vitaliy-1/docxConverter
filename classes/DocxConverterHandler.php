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
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\file\PrivateFileManager;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\plugins\interfaces\HasAuthorizationPolicy;
use PKP\security\authorization\SubmissionFileAccessPolicy;
use PKP\submissionFile\SubmissionFile;

class DocxConverterHandler implements HasAuthorizationPolicy
{
    public DocxConverterPlugin $plugin;

    public function __construct(DocxConverterPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * This allows adding a route on the fly without defining an api controller.
     * Hook: APIHandler::endpoints::submissions
     * e.g. api/v1/submissions/docxConverter/{submissionId}/{submissionFileId}/convert
     */
    public function addRoute(string $hookName, PKPBaseController $apiController, APIHandler $apiHandler): bool
    {
        // Skip PKPSubmissionFileController: its handler path already contains {submissionId}, which would duplicate the placeholder.
        if ($apiController->getHandlerPath() !== 'submissions') {
            return Hook::CONTINUE;
        }

        $apiHandler->addRoute(
            'GET',
            DocxConverterPlugin::PLUGIN_NAME . "/{submissionId}/{submissionFileId}/convert",
            fn(IlluminateRequest $request): JsonResponse => $this->convert(),
            DocxConverterPlugin::PLUGIN_NAME . '.convert',
            DocxConverterPlugin::AUTHORIZED_ROLES,
            $this
        );

        return Hook::CONTINUE;
    }

    /**
     * Ensure the caller may access the specific submission file, not just hold an editorial role.
     */
    public function getPolicies(PKPRequest $request, array &$args, array $roleAssignments): array
    {
        $submissionFileId = (int) PKPBaseController::getRequestedRoute()->parameter('submissionFileId');

        return [
            new SubmissionFileAccessPolicy(
                $request,
                $args,
                $roleAssignments,
                SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_MODIFY,
                $submissionFileId
            ),
        ];
    }

    /**
     * Converts a DOCX file associated with a submission into JATS XML format
     * and adds it as a new submission file, along with any supplementary files.
     */
    private function convert(): JsonResponse
    {
        $routeController = PKPBaseController::getRouteController();
        /** @var SubmissionFile $submissionFile */
        $submissionFile = $routeController->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);
        /** @var Submission $submission */
        $submission = $routeController->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $routeSubmissionId = (int) PKPBaseController::getRequestedRoute()->parameter('submissionId');
        if ((int) $submissionFile->getData('submissionId') !== $routeSubmissionId) {
            return response()->json(
                ['error' => __('api.403.unauthorized')],
                Response::HTTP_FORBIDDEN
            );
        }

        $request = Application::get()->getRequest();

        $fileManager = new PrivateFileManager();
        $filePath = $fileManager->getBasePath() . '/' . $submissionFile->getData('path');

        $docxArchive = new DOCXArchive($filePath);
        if (!$docxArchive->getDocument()) {
            return response()->json(
                ['error' => __('plugins.generic.docxConverter.conversionError')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        $jatsXML = new DocxConverterDocument($docxArchive);

        $submissionId = $submission->getId();
        $jatsXML->setDocumentMeta($submission);
        $tmpName = tempnam(sys_get_temp_dir(), DocxConverterPlugin::PLUGIN_NAME);
        file_put_contents($tmpName, $jatsXML->saveXML());
        $genreId = $submissionFile->getData('genreId');

        // Add new JATS XML file
        $submissionDir = Repo::submissionFile()->getSubmissionDir($submission->getData('contextId'), $submissionId);
        $newFileId = Services::get('file')->add(
            $tmpName,
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

        unlink($tmpName);

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
        $tmpNameSuppl = tempnam(sys_get_temp_dir(), DocxConverterPlugin::PLUGIN_NAME);
        file_put_contents($tmpNameSuppl, $singleData);
        $mimeType = mime_content_type($tmpNameSuppl);

        // Determine genre
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByDependenceAndContextId(true, $request->getContext()->getId());
        $supplGenreId = null;
        while ($genre = $genres->next()) {
            if (($mimeType === 'image/png' || $mimeType === 'image/jpeg') && $genre->getKey() === 'IMAGE') {
                $supplGenreId = $genre->getId();
            }
        }

        if (!$supplGenreId) {
            unlink($tmpNameSuppl);
            return;
        }

        $submissionDir = Repo::submissionFile()->getSubmissionDir($submission->getData('contextId'), $submission->getId());
        $newFileId = Services::get('file')->add(
            $tmpNameSuppl,
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

        unlink($tmpNameSuppl);
    }
}
