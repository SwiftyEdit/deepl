<?php
/**
 * @var string $dl_db_file from bootstrap.php
 * @var array $addon_info
 */
use Medoo\Medoo;
include_once __DIR__.'/schema.php';


/* INSTALL */

if(!is_file("$dl_db_file")) {

    if(!is_dir(dirname($dl_db_file))) {
        mkdir(dirname($dl_db_file), 0755, true);
    }

    $deepl_db = new Medoo([
        'type' => 'sqlite',
        'database' => $dl_db_file
    ]);

    $tables = DeeplSchema::getTables();

    foreach ($tables as $table_name => $columns) {
        $col_definitions = [];
        foreach ($columns as $col_name => $col_type) {
            $col_definitions[] = "$col_name $col_type";
        }

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (" .
            implode(', ', $col_definitions) . ")";

        $deepl_db->query($sql)->execute();
    }

    $deepl_db->query("CREATE INDEX IF NOT EXISTS idx_log_ts ON log (ts)")->execute();

    $deepl_db->insert('settings', [
        'key' => 'version',
        'value' => $addon_info['addon']['version']
    ]);

    foreach (dl_get_default_settings() as $key => $value) {
        if (!$deepl_db->has('settings', ['key' => $key])) {
            $deepl_db->insert('settings', [
                'key' => $key,
                'value' => (string) $value,
            ]);
        }
    }

    // Plugin versions < 2.0.0 stored the key in a plain PHP file in the
    // plugin root - move it into the database.
    dl_import_legacy_key();

    echo '<p class="alert alert-info">Generated SQLite File: '.basename($dl_db_file).'</p>';
}
