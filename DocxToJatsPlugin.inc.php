<?php

/**
 * @file plugins/generic/docxConverter/DocxToJatsPlugin.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University Library
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2.
 *
 * @brief main class of the DOCX to JATS XML Converter Plugin
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class DocxToJatsPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.docxToJats.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.docxToJats.description');
	}


	/**
	 * Register the plugin
	 *
	 * @param $category string Plugin category
	 * @param $path string Plugin path
	 * @param $mainContextId ?integer
	 * @return bool True on successful registration false otherwise
	 */
	function register($category, $path, $mainContextId = null) {
		if (parent::register($category, $path, $mainContextId)) {
			if ($this->getEnabled()) {
				// Register callbacks.
				HookRegistry::register('TemplateManager::fetch', array($this, 'templateFetchCallback'));
				HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));
				$this->_registerTemplateResource();
			}
			return true;
		}
		return false;
	}

	/**
	 * Get plugin URL
	 * @param $request PKPRequest
	 * @return string
	 */
	function getPluginUrl($request) {
		return $request->getBaseUrl() . '/' . $this->getPluginPath();
	}

	public function callbackLoadHandler($hookName, $args) {
		$page = $args[0];
		$op = $args[1];

		if ($page == "docxParser" && $op == "parse") {
			define('HANDLER_CLASS', 'ConverterHandler');
			define('CONVERTER_PLUGIN_NAME', $this->getName());
			$args[2] = $this->getPluginPath() . '/' . 'DOCXConverterHandler.inc.php';
		}

		return false;
	}

	/**
	 * Adds additional links to submission files grid row
	 * @param $hookName string The name of the invoked hook
	 * @param $args array Hook parameters
	 */
	public function templateFetchCallback($hookName, $params) {
		$request = $this->getRequest();
		$dispatcher = $request->getDispatcher();

		$templateMgr = $params[0];
		$resourceName = $params[1];
		if ($resourceName == 'controllers/grid/gridRow.tpl') {
			/* @var $row GridRow */
			$row = $templateMgr->getTemplateVars('row');
			$data = $row->getData();
			if (is_array($data) && (isset($data['submissionFile']))) {
				$submissionFile = $data['submissionFile'];
				$fileExtension = strtolower($submissionFile->getData('mimetype'));

				// Ensure that the conversion is run on the appropriate workflow stage
				$stageId = (int) $request->getUserVar('stageId');
				$submissionId = $submissionFile->getData('submissionId');
				$submission = Services::get('submission')->get($submissionId); /** @var $submission Submission */
				$submissionStageId = $submission->getData('stageId');
				$roles = $request->getUser()->getRoles($request->getContext()->getId());

				$accessAllowed = false;
				foreach ($roles as $role) {
					if (in_array($role->getId(), [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT])) {
						$accessAllowed = true;
						break;
					}
				}
				if (strtolower($fileExtension) == 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' && // show only for files with docx extension
					$accessAllowed && // only for those that have access according to the DOCXConverterHandler rules
					in_array($stageId, $this->getAllowedWorkflowStages()) && // only for stage ids copyediting or higher
					in_array($submissionStageId, $this->getAllowedWorkflowStages()) // only if submission has correspondent stage id
					) {

					$path = $dispatcher->url($request, ROUTE_PAGE, null, 'docxParser', 'parse', null,
						array(
							'submissionId' => $submissionId,
							'fileId' => $submissionFile->getData('fileId'),
							'stageId' => $stageId
						));
					$pathRedirect = $dispatcher->url($request, ROUTE_PAGE, null, 'workflow', 'access',
						array(
							'submissionId' => $submissionId,
							'fileId' => $submissionFile->getData('fileId'),
							'stageId' => $stageId
						));

					import('lib.pkp.classes.linkAction.request.AjaxAction');
					$linkAction = new LinkAction(
						'parse',
						new PostAndRedirectAction($path, $pathRedirect),
						__('plugins.generic.docxToJats.button.parseDocx')
					);
					$row->addAction($linkAction);
				}
			}
		}
	}

	public function getAllowedWorkflowStages() {
		return [
			WORKFLOW_STAGE_ID_EDITING,
			WORKFLOW_STAGE_ID_PRODUCTION
		];
	}
}
