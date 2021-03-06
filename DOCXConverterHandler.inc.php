<?php

/**
 * @file plugins/generic/docxConverter/DOCXConverterHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University Library
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2.
 *
 * @brief handler for the grid's conversion
 */

import('classes.handler.Handler');
import('plugins.generic.docxConverter.classes.DOCXConverterDocument');
require_once __DIR__ . "/docxToJats/vendor/autoload.php";
use docx2jats\DOCXArchive;

class ConverterHandler extends Handler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->_plugin = PluginRegistry::getPlugin('generic', CONVERTER_PLUGIN_NAME);
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			array('parse')
		);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');
		$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_MODIFY));
		return parent::authorize($request, $args, $roleAssignments);
	}

	public function parse($args, $request) {

		$user = $request->getUser();
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		$filePath = $submissionFile->getFilePath();

		$docxArchive = new DOCXArchive($filePath);
		$jatsXML = new DOCXConverterDocument($docxArchive);

		$submissionDao = Application::getSubmissionDAO();
		$submissionId = $submissionFile->getSubmissionId();
		$submission = $submissionDao->getById($submissionId);
		$jatsXML->setDocumentMeta($request, $submission);
		$tmpfname = tempnam(sys_get_temp_dir(), 'docxConverter');
		file_put_contents($tmpfname, $jatsXML->saveXML());
		$genreId = $submissionFile->getGenreId();
		$fileSize = filesize($tmpfname);

		$originalFileInfo = pathinfo($submissionFile->getOriginalFileName());

		/* @var $newSubmissionFile SubmissionFile */
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$newSubmissionFile = $submissionFileDao->newDataObjectByGenreId($genreId);
		$newSubmissionFile->setSubmissionId($submission->getId());
		$newSubmissionFile->setSubmissionLocale($submission->getLocale());
		$newSubmissionFile->setGenreId($genreId);
		$newSubmissionFile->setFileStage($submissionFile->getFileStage());
		$newSubmissionFile->setDateUploaded(Core::getCurrentDate());
		$newSubmissionFile->setDateModified(Core::getCurrentDate());
		$newSubmissionFile->setOriginalFileName($originalFileInfo['filename'] . ".xml");
		$newSubmissionFile->setUploaderUserId($user->getId());
		$newSubmissionFile->setFileSize($fileSize);
		$newSubmissionFile->setFileType("text/xml");
		$newSubmissionFile->setSourceFileId($submissionFile->getFileId());
		$newSubmissionFile->setSourceRevision($submissionFile->getRevision());
		$newSubmissionFile->setRevision(1);
		$insertedSubmissionFile = $submissionFileDao->insertObject($newSubmissionFile, $tmpfname);
		unlink($tmpfname);

		$mediaData = $docxArchive->getMediaFilesContent();
		if (!empty($mediaData)) {
			foreach ($mediaData as $originalName => $singleData) {
				$this->_attachSupplementaryFile($request, $submission, $submissionFileDao, $newSubmissionFile, $originalName, $singleData);
			}
		}

		return new JSONMessage(true, array(
			'submissionId' => $insertedSubmissionFile->getSubmissionId(),
			'fileId' => $insertedSubmissionFile->getFileIdAndRevision(),
			'fileStage' => $insertedSubmissionFile->getFileStage(),
		));
	}

	private function _attachSupplementaryFile(Request $request, Submission $submission, SubmissionFileDAO $submissionFileDao, SubmissionFile $newSubmissionFile, string $originalName, string $singleData) {
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

		// Set file
		$supplementaryFile = $submissionFileDao->newDataObjectByGenreId($supplGenreId);
		$supplementaryFile->setSubmissionId($submission->getId());
		$supplementaryFile->setSubmissionLocale($submission->getLocale());
		$supplementaryFile->setGenreId($supplGenreId);
		$supplementaryFile->setFileStage(SUBMISSION_FILE_DEPENDENT);
		$supplementaryFile->setDateUploaded(Core::getCurrentDate());
		$supplementaryFile->setDateModified(Core::getCurrentDate());
		$supplementaryFile->setUploaderUserId($request->getUser()->getId());
		$supplementaryFile->setFileSize(filesize($tmpfnameSuppl));
		$supplementaryFile->setFileType($mimeType);
		$supplementaryFile->setAssocId($newSubmissionFile->getFileId());
		$supplementaryFile->setAssocType(ASSOC_TYPE_SUBMISSION_FILE);
		$supplementaryFile->setOriginalFileName(basename($originalName));

		$submissionFileDao->insertObject($supplementaryFile, $tmpfnameSuppl);
		unlink($tmpfnameSuppl);
	}

}
