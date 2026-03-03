<?php

/**
 * @file plugins/generic/docxConverter/classes/migration/upgrade/UpdateDocxConverterPluginName.php
 *
 * Copyright (c) 2026 TIB Hannover
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateDocxConverterPluginName
 *
 * @ingroup plugins_generic_docxconverter
 *
 * @brief Fix the plugin name in plugin settings for this plugin.
 */

namespace APP\plugins\generic\docxConverter\classes\migration\upgrade;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;

class UpdateDocxConverterPluginName extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $product = 'docxConverter';
        $oldValue = 'DocxToJatsPlugin';
        $newValue = 'DocxConverterPlugin';

        /** plugin_settings */

        $upgradeRequired = DB::table('plugin_settings')
            ->where(DB::raw('LOWER(plugin_name)'), '=', strtolower($oldValue))
            ->count();

        if ($upgradeRequired > 0) {
            // Get all plugin settings and clear duplicates
            $records = DB::table('plugin_settings')
                ->where(DB::raw('LOWER(plugin_name)'), strtolower($oldValue))
                ->orderBy(DB::raw('LOWER(plugin_name)'), 'desc')
                ->get()
                ->keyBy('context_id');

            // Delete the old settings
            DB::table('plugin_settings')
                ->where(DB::raw('LOWER(plugin_name)'), strtolower($oldValue))
                ->delete();

            // Insert the settings with the correct plugin name
            foreach ($records as $record) {
                DB::table('plugin_settings')->insert(
                    [
                        'plugin_name' => strtolower($newValue),
                        'context_id' => $record->context_id,
                        'setting_name' => $record->setting_name,
                        'setting_value' => $record->setting_value,
                        'setting_type' => $record->setting_type
                    ]
                );
            }
        }

        /** versions */

        DB::table('versions')
            ->where(DB::raw('LOWER(product_type)'), 'plugins.generic')
            ->where(DB::raw('LOWER(product)'), strtolower($product))
            ->where(DB::raw('LOWER(product_class_name)'), strtolower($oldValue))
            ->update(['product_class_name' => $newValue]);
    }

    /**
     * Rollback the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
