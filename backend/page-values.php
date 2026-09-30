<?php
/**
 * "Addons" tab of the page editor - see se_render_record_addons().
 * Per-page list of languages the DeepL hook must skip.
 *
 * @var array $record_data se_pages row (addon_string already reduced to this plugin's values)
 */

require_once SE_ROOT.'plugins/deepl/global/functions.php';

$plugin_form_tpl = dl_render_exclude_field(
    $record_data,
    (string) ($record_data['page_language'] ?? ''),
    (string) ($record_data['page_translation_urls'] ?? '')
);
