<?php
/**
 * Hook actions shown as checkboxes in the page/product editor.
 *
 * IMPORTANT: the core matches checked boxes to callbacks by POSITION, not by
 * name (see se_do_backend_hook_selected()) - the order of the entries per
 * hook here must be exactly the order of the se_add_backend_hook() calls in
 * hooks.php. Only ever append new entries at the end.
 */

$dl_meta_lang_file = __DIR__.'/../lang/'.basename((string) ($languagePack ?? 'en')).'.json';
if (!is_file($dl_meta_lang_file)) {
    $dl_meta_lang_file = __DIR__.'/../lang/en.json';
}
$dl_meta_lang = json_decode((string) file_get_contents($dl_meta_lang_file), true) ?: [];

$dl_meta = fn(string $key) => [
    'label' => $dl_meta_lang['hook_'.$key] ?? $key,
    'description' => $dl_meta_lang['hook_'.$key.'_desc'] ?? '',
    'category' => 'DeepL',
];

return [
    'page.updated' => [
        $dl_meta('page_all'),
    ],
    'product.updated' => [
        $dl_meta('product_all'),
        $dl_meta('product_teaser'),
        $dl_meta('product_descriptions'),
        $dl_meta('product_metas'),
    ],
];
