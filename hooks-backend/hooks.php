<?php
/**
 * Backend hook callbacks. Loaded on every admin request, so this file only
 * registers closures - the plugin code, the DeepL library and the database
 * are loaded when a hook actually fires.
 *
 * The registration order per hook must match hooks-backend/meta.php.
 */

if (!function_exists('dl_run_hook')) {
    function dl_run_hook(callable $job): void {
        require_once SE_ROOT.'plugins/deepl/global/functions.php';

        // several languages x DeepL round trips
        @set_time_limit(300);

        try {
            dl_notify($job());
        } catch (\Throwable $e) {
            // never break the core's save request
            if (function_exists('show_toast')) {
                show_toast('DeepL: '.htmlspecialchars($e->getMessage()), 'danger');
            }
        }
    }
}

/* page.updated #0 - translate the whole page */
se_add_backend_hook('page.updated', function (array $context): void {
    dl_run_hook(fn() => dl_translate_page($context));
});

/* product.updated #0 - everything */
se_add_backend_hook('product.updated', function (array $context): void {
    dl_run_hook(fn() => dl_translate_product($context, 'all'));
});

/* product.updated #1 - title + teaser */
se_add_backend_hook('product.updated', function (array $context): void {
    dl_run_hook(fn() => dl_translate_product($context, 'teaser'));
});

/* product.updated #2 - descriptions */
se_add_backend_hook('product.updated', function (array $context): void {
    dl_run_hook(fn() => dl_translate_product($context, 'descriptions'));
});

/* product.updated #3 - meta title, meta description, tags */
se_add_backend_hook('product.updated', function (array $context): void {
    dl_run_hook(fn() => dl_translate_product($context, 'metas'));
});
