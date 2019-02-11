<?php

import('classes.handler.Handler');
require_once __DIR__ . "/docxToJats/vendor/autoload.php";
use docx2jats\DOCXArchive;
use docx2jats\jats\Document;

class ConverterHandler extends Handler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->_plugin = PluginRegistry::getPlugin('generic', CONVERTER_PLUGIN_NAME);
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR),
			array('parse')
		);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');
		$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_READ));
		return parent::authorize($request, $args, $roleAssignments);
	}

	public function parse($args, $request) {

		$user = $request->getUser();
		$stageId = (int) $request->getUserVar('stageId');
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		$filePath = $submissionFile->getFilePath();

		$docxArchive = new DOCXArchive($filePath);
		$jatsXML = new Document($docxArchive);

		$tmpfname = tempnam(sys_get_temp_dir(), 'docxConverter');
		file_put_contents($tmpfname, $jatsXML->saveXML());

		// temp file to submission file
		$submissionDao = Application::getSubmissionDAO();
		$submissionId = $submissionFile->getSubmissionId();
		$submission = $submissionDao->getById($submissionId);
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

		return new JSONMessage(true, array(
			'submissionId' => $insertedSubmissionFile->getSubmissionId(),
			'fileId' => $insertedSubmissionFile->getFileIdAndRevision(),
			'fileStage' => $insertedSubmissionFile->getFileStage(),
		));
	}

}
