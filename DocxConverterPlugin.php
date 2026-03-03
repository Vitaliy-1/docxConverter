<?php

/**
 * @file plugins/generic/docxConverter/DocxConverterPlugin.php
 *
 * Copyright (c) 2021-2026 TIB Hannover
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DocxConverterPlugin
 *
 * @ingroup plugins_generic_docxconverter
 *
 * @brief main class of the DOCX to JATS XML Converter Plugin
 */

namespace APP\plugins\generic\docxConverter;

use APP\core\Application;
use APP\core\Request;
use APP\plugins\generic\docxConverter\classes\DocxConverterHandler;
use APP\plugins\generic\docxConverter\classes\migration\upgrade\UpdateDocxConverterPluginName;
use APP\template\TemplateManager;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;

class DocxConverterPlugin extends GenericPlugin
{
    public const PLUGIN_NAME = 'docxConverter';

    /**
     * Authorized roles.
     */
    public const AUTHORIZED_ROLES = [
        Role::ROLE_ID_MANAGER,
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT
    ];

    /**
     * @copydoc Plugin::register
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled()) {
                require_once __DIR__ . "/docxToJats/vendor/autoload.php";

                $request = Application::get()->getRequest();
                $templateMgr = TemplateManager::getManager($request);

                $apiHandler = new DocxConverterHandler($this);
                Hook::add('APIHandler::endpoints::submissions', $apiHandler->addRoute(...));

                $userRoleIds = array_map(fn($role) => $role->getId(),
                    $request->getUser()?->getRoles($request->getContext()?->getId()) ?? []);
                if (!empty($userRoleIds) && !empty(array_intersect($userRoleIds, self::AUTHORIZED_ROLES))) {
                    $this->addResources($templateMgr, $request);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Add resources
     */
    public function addResources(TemplateManager $templateMgr, Request $request): void
    {
        $templateMgr->addJavaScript(
            'DocxConverterPluginJs',
            "{$request->getBaseUrl()}/{$this->getPluginPath()}/public/build/build.iife.js",
            [
                'inline' => false,
                'contexts' => ['backend'],
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST
            ]
        );
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.docxConverter.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription(): string
    {
        return __('plugins.generic.docxConverter.description');
    }

    /**
     * @copydoc Plugin::getInstallMigration()
     */
    public function getInstallMigration(): UpdateDocxConverterPluginName
    {
        return new UpdateDocxConverterPluginName();
    }
}

// For backwards compatibility -- expect this to be removed approx. OJS/OMP/OPS 3.6
if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\docxConverter\DocxConverterPlugin', '\DocxConverterPlugin');
}
