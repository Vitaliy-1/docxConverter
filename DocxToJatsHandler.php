<?php

/**
 * @file plugins/generic/docxToJats/DocxToJatsHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University Library
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2.
 *
 * @brief handler for the grid's conversion
 */

namespace APP\plugins\generic\docxToJats;

use APP\core\Services;
use APP\core\Request;
use PKP\core\JSONMessage;
use APP\handler\Handler;
use APP\facades\Repo;
use PKP\plugins\PluginRegistry;

use APP\plugins\generic\docxToJats\classes\DocxToJatsDocument;
use PKP\file\PrivateFileManager;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;
use APP\submission\Submission;
use PKP\submissionFile\SubmissionFile;
use PKP\genre\GenreDAO as GenreDAO; 
use DAORegistry;

require_once(__DIR__ . '/classes/DocxToJatsDocument.php');
require_once __DIR__ . "/docxToJats/vendor/autoload.php";
use docx2jats\DOCXArchive;


class DocxToJatsHandler extends Handler {

    /** @copydoc PKPHandler::_isBackendPage */
    var $_isBackendPage = true;

	function __construct() {
		parent::__construct();
		$this->_plugin = PluginRegistry::getPlugin('generic', 'DocxToJatsPlugin');
		$this->addRoleAssignment(
			array(Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT),
			array('parse')
		);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', (int)$request->getUserVar('stageId')));
		return parent::authorize($request, $args, $roleAssignments);
	}

	public function parse($args, $request) {
		
		$submissionFileId = (int) $request->getUserVar('submissionFileId');
		$submissionFile = Repo::submissionFile()->get($submissionFileId);

		$fileManager = new PrivateFileManager();
		$filePath = $fileManager->getBasePath() . '/' . $submissionFile->getData('path');
		

		$docxArchive = new DOCXArchive($filePath);
		$jatsXML = new DocxToJatsDocument($docxArchive);

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
				$this->_attachSupplementaryFile($request, $submission, $newSubmissionFile, $fileManager, $originalName, $singleData);
			}
		}
		
		return new JSONMessage(true, array(
			'submissionId' => $submissionId,
			'fileId' => $newSubmissionFile->getData('fileId'),
			'fileStage' => $newSubmissionFile->getData('fileStage'),
		));
	}


private function _attachSupplementaryFile(Request $request, Submission $submission, SubmissionFile $newSubmissionFile, PrivateFileManager $fileManager, string $originalName, string $singleData) {
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
	$newSupplementaryFile =  Repo::submissionFile()->newDataObject();
	$newSupplementaryFile->setAllData([
		'fileId' => $newFileId,
		'assocId' => $newSubmissionFile->getId(),
		'assocType' => ASSOC_TYPE_SUBMISSION_FILE,
		'fileStage' => SUBMISSION_FILE_DEPENDENT,
		'submissionId' => $submission->getId(),
		'genreId' => $supplGenreId,
		'name' => array_fill_keys(array_keys($newSubmissionFile->getData('name')), basename($originalName))
	]);

	Repo::submissionFile()->add($newSupplementaryFile, $request);
	unlink($tmpfnameSuppl);
}


}
