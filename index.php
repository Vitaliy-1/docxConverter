<?php

/**
 * @defgroup plugins_generic_DocxToJats
 */

/**
 * @file plugins/generic/docxToJats/index.php
 *
 * Copyright (c) 2019 Vitalii Bezsheiko
 * Distributed under the GNU GPL v3.
 *
 * @ingroup plugins_generic_docxToJats
 * @brief Wrapper for Docx to JATS plugin.
 *
 */
require_once('DocxToJatsPlugin.inc.php');

return new DocxToJatsPlugin();
