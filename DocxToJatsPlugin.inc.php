<?php

/**
 * @file plugins/generic/docxToJats/DocxToJatsPlugin.inc.php
 *
 * @class DocxToJatsPlugin
 * @ingroup plugins_generic_docxToJats
 *
 * @brief DOCX to JATS XML parser
 *
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
	 *
	 * @return bool True on successful registration false otherwise
	 */
	function register($category, $path) {
		if (parent::register($category, $path)) {
			if ($this->getEnabled()) {
				// Register callbacks.
				HookRegistry::register('TemplateManager::fetch', array($this, 'templateFetchCallback'));

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

	/**
	 * Adds additional links to submission files grid row
	 * @param $hookName string The name of the invoked hook
	 * @param $args array Hook parameters
	 */
	public function templateFetchCallback($hookName, $params) {
		$request = $this->getRequest();
		$router = $request->getRouter();

		$templateMgr = $params[0];
		$resourceName = $params[1];
		if ($resourceName == 'controllers/grid/gridRow.tpl') {
			/* @var $row GridRow */
			$row = $templateMgr->get_template_vars('row');
			$data = $row->getData();

			if (is_array($data) && (isset($data['submissionFile']))) {
				$submissionFile = $data['submissionFile'];
				$fileExtension = strtolower($submissionFile->getExtension());

				if (strtolower($fileExtension) == 'docx') {

					$actionArgs = $request->getUserVars();
					$path = $router->url($request, null, null, 'parseDocx', null, $actionArgs);

					import('lib.pkp.classes.linkAction.request.AjaxAction');
					$linkAction = new LinkAction(
						'parseDocx',
						new AjaxAction($path),
						__('plugins.generic.docxToJats.button.parseDocx')
					);
					$row->addAction($linkAction);
				}
			}
		}
	}
}
