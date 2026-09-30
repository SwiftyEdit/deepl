<?php
/**
 * "Addons" tab of the product editor - see se_render_record_addons().
 * Per-product list of languages the DeepL hooks must skip.
 *
 * @var array $record_data se_products row (addon_string already reduced to this plugin's values)
 */

require_once SE_ROOT.'plugins/deepl/global/functions.php';

$plugin_form_tpl = dl_render_exclude_field(
    $record_data,
    (string) ($record_data['product_lang'] ?? ''),
    (string) ($record_data['translation_urls'] ?? '')
);
