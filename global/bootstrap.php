<?php
/**
 * Backend bootstrap (admin nav tabs + admin-xhr reader/writer). The backend
 * hooks (hooks-backend/*.php) don't include this file - they are loaded on
 * every admin request, so they only open the database lazily, when a hook
 * actually fires (see dl_db()).
 */
global $addon_lang, $hidden_csrf_token;
use Medoo\Medoo;

$mod_root = SE_ROOT.'plugins/deepl/';
$dl_db_file = $mod_root.'data/deepl.sqlite3';

// Same reasoning as plugins/tracker/global/bootstrap.php: force $deepl_db
// into the real global scope regardless of which include chain reaches this
// file, so the dl_*() helpers can reach it via `global $deepl_db`.
global $deepl_db;

// $addon_info is only pre-set when this bootstrap is reached via the admin
// nav-tab router (acp/core/addons/edit-plugin.php). The admin-xhr reader/
// writer router does not set it.
if(!isset($addon_info)) {
    $addon_info = json_decode(file_get_contents($mod_root.'info.json'), true);
}

require_once __DIR__.'/functions.php';

if(is_file($dl_db_file)) {
    $deepl_db = new Medoo([
        'type' => 'sqlite',
        'database' => $dl_db_file
    ]);
} elseif(SE_SECTION === 'backend') {
    include __DIR__ . '/../install/installer.php';
}

if(isset($deepl_db)) {
    $dl_settings = dl_get_settings();

    if(SE_SECTION === 'backend') {
        if (version_compare($dl_settings['version'] ?? '0', $addon_info['addon']['version'], '<')) {
            include __DIR__ . '/../install/updater.php';
            $dl_settings = dl_get_settings();
        }
    }
}

if(!is_array($addon_lang) && function_exists('se_return_addon_translations')) {
    $addon_lang = se_return_addon_translations('deepl');
}
