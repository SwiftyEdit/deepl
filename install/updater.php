<?php
/**
 * @var \Medoo\Medoo $deepl_db from bootstrap.php
 * @var array $addon_info
 */
include_once __DIR__.'/schema.php';

echo '<p class="alert alert-info">We try to update version: '.$addon_info['addon']['version'].'</p>';

// 1. Update/Create all tables with current schema
foreach (DeeplSchema::getTables() as $table_name => $columns) {
    dl_updateOrCreateTable($table_name, $columns);
}

// 2. Backfill settings introduced after the initial install, without
// touching settings the admin already changed.
foreach (dl_get_default_settings() as $key => $value) {
    if (!$deepl_db->has('settings', ['key' => $key])) {
        $deepl_db->insert('settings', [
            'key' => $key,
            'value' => (string) $value,
        ]);
    }
}

// 3. Legacy key file (< 2.0.0), in case it was copied in after install
dl_import_legacy_key();

// 4. Update version
dl_save_setting('version', $addon_info['addon']['version']);

echo '<p class="alert alert-info">Updated to version: '.$addon_info['addon']['version'].'</p>';
