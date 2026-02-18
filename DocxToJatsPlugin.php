<?php

/**
 * @file plugins/generic/docxConverter/DocxToJatsPlugin.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University Library
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v2.
 *
 * @brief main class of the DOCX to JATS XML Converter Plugin
 */

namespace APP\plugins\generic\docxConverter;

use APP\core\Application;
use APP\facades\Repo;
use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\PostAndRedirectAction;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;

class DocxToJatsPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::getDisplayName()
     */
    function getDisplayName(): string
    {
        return __('plugins.generic.docxToJats.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    function getDescription(): string
    {
        return __('plugins.generic.docxToJats.description');
    }

    /**
     * @copydoc Plugin::register()
     */
    function register($category, $path, $mainContextId = null): bool
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled()) {
                require_once __DIR__ . "/docxToJats/vendor/autoload.php";
                Hook::add('TemplateManager::fetch', [$this, 'templateFetchCallback']);
                Hook::add('LoadHandler', [$this, 'callbackLoadHandler']);
                $this->_registerTemplateResource();
            }
            return true;
        }
        return false;
    }

    /**
     * Get plugin URL.
     */
    function getPluginUrl($request): string
    {
        return $request->getBaseUrl() . '/' . $this->getPluginPath();
    }

    /**
     * Handles the callback for loading specific page and operation route handlers.
     */
    public function callbackLoadHandler($hookName, $args): bool
    {
        $page = $args[0];
        $op = $args[1];

        if ($page === 'docxParser' && $op === 'parse') {
            define('HANDLER_CLASS', '\APP\plugins\generic\docxConverter\DOCXConverterHandler');
            return true;
        }
        return false;
    }

    /**
     * Adds additional links to submission files grid row.
     */
    public function templateFetchCallback($hookName, $params): void
    {
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
                $stageId = (int)$request->getUserVar('stageId');
                $submissionId = $submissionFile->getData('submissionId');
                $submission = Repo::submission()->get($submissionId);
                $submissionStageId = $submission->getData('stageId');
                $roles = $request->getUser()->getRoles($request->getContext()->getId());

                $accessAllowed = false;
                foreach ($roles as $role) {
                    if (in_array($role->getId(), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT])) {
                        $accessAllowed = true;
                        break;
                    }
                }
                if (in_array(strtolower($fileExtension), static::getSupportedMimetypes()) && // show only for files with docx extension
                    $accessAllowed && // only for those that have access according to the DOCXConverterHandler rules
                    in_array($stageId, $this->getAllowedWorkflowStages()) && // only for stage ids copyediting or higher
                    in_array($submissionStageId, $this->getAllowedWorkflowStages()) // only if submission has correspondent stage id
                ) {
                    $path = $dispatcher->url($request, Application::ROUTE_PAGE, null, 'docxParser', 'parse', null,
                        [
                            'submissionId' => $submissionId,
                            'submissionFileId' => $submissionFile->getId(),
                            'stageId' => $stageId
                        ]
                    );

                    $pathRedirect = $dispatcher->url($request, Application::ROUTE_PAGE, null, 'workflow', 'access', $submissionId);

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

    /**
     * Retrieves the list of allowed workflow stages.
     */
    public function getAllowedWorkflowStages(): array
    {
        return [
            WORKFLOW_STAGE_ID_EDITING,
            WORKFLOW_STAGE_ID_PRODUCTION
        ];
    }

    /**
     * MIME type supported by the plugin for conversion
     */
    public static function getSupportedMimetypes(): array
    {
        return [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            // OJS identifies Google Docs files exported in DOCX format as having this MIME type
            'application/vnd.openxmlformats-officedocument.wordprocessingml.documentapplication/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
    }
}

// For backwards compatibility -- expect this to be removed approx. OJS/OMP/OPS 3.6
if (!PKP_STRICT_MODE) {
    class_alias('APP\plugins\generic\docxConverter\DocxToJatsPlugin', '\DocxToJatsPlugin');
}
