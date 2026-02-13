<?php

/**
 * @file plugins/generic/docxConverter/classes/migration/upgrade/updateDocxConverterPluginName.php
 *
 * Copyright (c) 2025 TIB Hannover
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class updateDocxConverterPluginName
 *
 * @ingroup plugins_generic_docxconverter
 *
 * @brief Fix the plugin name in plugin settings for the Portico export plugin.
 */

namespace APP\plugins\generic\docxConverter\classes\migration\upgrade;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;

class updateDocxConverterPluginName extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
    }

    /**
     * Rollback the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
