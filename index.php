<?php

/**
 * @file plugins/generic/docxConverter/index.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University Library
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2.
 *
 * @brief wrapper for driver plugin
 */

require_once('DocxToJatsPlugin.inc.php');

return new DocxToJatsPlugin();
