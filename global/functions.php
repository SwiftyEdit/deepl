<?php
/**
 * Helpers for the DeepL plugin. Three groups:
 *
 * 1. Settings / database (dl_db, dl_get_settings, dl_save_setting, ...)
 * 2. DeepL access (dl_translator, dl_target_code, dl_translate_fields)
 * 3. Record translation (dl_translate_page, dl_translate_product) - called
 *    from the backend hooks in hooks-backend/hooks.php after a page or
 *    product has been saved.
 *
 * This file is loaded from global/bootstrap.php (plugin tabs) AND lazily
 * from the hook callbacks - it must not rely on anything bootstrap.php sets
 * up besides what dl_db() opens on its own.
 */

use Medoo\Medoo;

/* -------------------------------------------------------------------
 * Settings / database
 * ---------------------------------------------------------------- */

/**
 * The plugin database, opened lazily. Returns null as long as the plugin
 * has never been opened in the backend (the installer runs from there).
 */
function dl_db(): ?Medoo {
    global $deepl_db;

    if (isset($deepl_db) && $deepl_db instanceof Medoo) {
        return $deepl_db;
    }

    $file = SE_ROOT.'plugins/deepl/data/deepl.sqlite3';
    if (!is_file($file)) {
        return null;
    }

    $deepl_db = new Medoo([
        'type' => 'sqlite',
        'database' => $file
    ]);
    return $deepl_db;
}

function dl_get_settings(): array {
    $db = dl_db();
    if ($db === null) {
        return [];
    }
    $result = $db->select('settings', ['key', 'value']);
    return array_column($result, 'value', 'key');
}

function dl_save_setting(string $key, $value): void {
    $db = dl_db();
    if ($db === null) {
        return;
    }

    if ($db->has('settings', ['key' => $key])) {
        $db->update('settings', ['value' => (string) $value], ['key' => $key]);
    } else {
        $db->insert('settings', ['key' => $key, 'value' => (string) $value]);
    }
}

function dl_get_default_settings(): array {
    return [
        'api_key' => '',
        // default | prefer_more | prefer_less - the "prefer_" variants
        // silently fall back to default for languages without formality
        // support, so they are safe for every target language.
        'formality' => 'default',
        // DeepL needs a regional variant for these two target languages
        'en_variant' => 'EN-US',
        'pt_variant' => 'PT-PT',
        // JSON list of SwiftyEdit language codes the hooks may translate
        // into - empty = all active languages.
        'target_langs' => '',
        // Content inside these HTML tags is never translated
        'ignore_tags' => 'code,pre,kbd,samp,script,style',
        // Keep SwiftyEdit shortcodes ([snippet]..[/snippet], [plugin=..]..)
        // untouched
        'protect_shortcodes' => 1,
    ];
}

/**
 * Ensure table exists and matches current schema.
 */
function dl_updateOrCreateTable(string $table_name, array $expected_columns): void {
    $db = dl_db();

    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name = '$table_name'")->fetchAll();

    if (empty($tables)) {
        $col_definitions = [];
        foreach ($expected_columns as $col_name => $col_type) {
            $col_definitions[] = "$col_name $col_type";
        }
        $db->query("CREATE TABLE IF NOT EXISTS $table_name (" . implode(', ', $col_definitions) . ")");
        return;
    }

    $tableInfo = $db->query("PRAGMA table_info($table_name)")->fetchAll();
    $existing_columns = array_column($tableInfo, 'name');

    foreach ($expected_columns as $col_name => $col_type) {
        if (!in_array($col_name, $existing_columns, true)) {
            $db->query("ALTER TABLE $table_name ADD COLUMN $col_name $col_type");
        }
    }
}

/**
 * Plugin versions < 2.0.0 wrote the key into plugins/deepl/deepl_auth.php
 * ($deepl_auth_key = "..."). Move it into the database and delete the file.
 * The file is parsed, never included - it's plain user-writable PHP.
 */
function dl_import_legacy_key(): void {
    $legacy_file = SE_ROOT.'plugins/deepl/deepl_auth.php';
    if (!is_file($legacy_file)) {
        return;
    }

    $content = (string) file_get_contents($legacy_file);
    if (preg_match('/\$deepl_auth_key\s*=\s*["\']([^"\']+)["\']/', $content, $m)) {
        $settings = dl_get_settings();
        if (($settings['api_key'] ?? '') === '') {
            dl_save_setting('api_key', trim($m[1]));
        }
    }

    @unlink($legacy_file);
}

/**
 * Addon translations - also usable from the hook callbacks, where the
 * plugin's $addon_lang isn't loaded.
 */
function dl_lang(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    global $languagePack;
    $root = SE_ROOT.'plugins/deepl/lang/';
    $file = $root.basename((string) ($languagePack ?? 'en')).'.json';
    if (!is_file($file)) {
        $file = $root.'en.json';
    }
    $cache = json_decode((string) file_get_contents($file), true) ?: [];
    return $cache;
}

/**
 * Active SwiftyEdit languages: [lang_sign => description]. Same logic as
 * acp/index.php (skip folders listed in deactivated_languages).
 */
function dl_available_languages(): array {
    global $se_settings;

    $deactivated = json_decode((string) ($se_settings['deactivated_languages'] ?? ''), true);
    $deactivated = is_array($deactivated) ? $deactivated : [];

    $langs = [];
    foreach (glob(SE_ROOT.'languages/*/index.php') as $lang_file) {
        if (in_array(basename(dirname($lang_file)), $deactivated, true)) {
            continue;
        }
        $lang_sign = '';
        $lang_desc = '';
        $lang = [];
        include $lang_file;
        if ($lang_sign !== '') {
            $langs[$lang_sign] = $lang_desc ?: $lang_sign;
        }
    }

    ksort($langs);
    return $langs;
}

/**
 * Is the hook allowed to translate into this SwiftyEdit language?
 */
function dl_target_enabled(string $se_lang, array $settings): bool {
    $enabled = json_decode((string) ($settings['target_langs'] ?? ''), true);
    if (!is_array($enabled) || empty($enabled)) {
        return true;
    }
    return in_array($se_lang, $enabled, true);
}

/**
 * Map a SwiftyEdit language code to a DeepL target language code.
 */
function dl_target_code(string $se_lang, array $settings): string {
    $code = strtolower(trim($se_lang));

    switch ($code) {
        case 'en':
            return ($settings['en_variant'] ?? '') === 'EN-GB' ? 'EN-GB' : 'EN-US';
        case 'pt':
            return ($settings['pt_variant'] ?? '') === 'PT-BR' ? 'PT-BR' : 'PT-PT';
        case 'gr': // legacy SwiftyEdit code, now "el"
            return 'EL';
        case 'no':
            return 'NB';
        case 'zh':
            return 'ZH-HANS';
    }

    return strtoupper($code);
}

/**
 * Map a SwiftyEdit language code to a DeepL source language code (source
 * languages have no regional variants).
 */
function dl_source_code(string $se_lang): ?string {
    $code = strtolower(trim($se_lang));
    if ($code === '') {
        return null;
    }
    $map = ['gr' => 'el', 'no' => 'nb'];
    return strtoupper($map[$code] ?? $code);
}

/* -------------------------------------------------------------------
 * DeepL access
 * ---------------------------------------------------------------- */

/**
 * @throws \DeepL\DeepLException on an invalid key
 */
function dl_translator(?string $api_key = null): ?\DeepL\Translator {
    if ($api_key === null) {
        $api_key = (string) (dl_get_settings()['api_key'] ?? '');
    }
    if (trim($api_key) === '') {
        return null;
    }

    require_once SE_ROOT.'plugins/deepl/vendor/autoload.php';

    return new \DeepL\Translator(trim($api_key), [
        'app_info' => new \DeepL\AppInfo('SwiftyEdit-DeepL', '2'),
        'timeout' => 30,
        'max_retries' => 2,
    ]);
}

function dl_mask_key(string $key): string {
    if ($key === '') {
        return '';
    }
    return substr($key, 0, 4).str_repeat('•', 8).substr($key, -4);
}

/**
 * Wrap SwiftyEdit shortcodes in <span translate="no"> so DeepL leaves them
 * alone, and unwrap them again afterwards. The original shortcode stays
 * inside the span, so even if DeepL moved the span the content is intact.
 */
function dl_protect_shortcodes(string $html): string {
    return preg_replace_callback(
        '/\[([a-z][a-z0-9_-]*)(=[^\]]*)?\](.*?)\[\/\1\]/si',
        fn($m) => '<span translate="no" class="dl-keep">'.$m[0].'</span>',
        $html
    ) ?? $html;
}

function dl_unprotect_shortcodes(string $html): string {
    return preg_replace('/<span translate="no" class="dl-keep">(.*?)<\/span>/s', '$1', $html) ?? $html;
}

/**
 * Translate a set of fields in (at most) two requests: one for plain text,
 * one for HTML. Empty values are not sent and stay empty.
 *
 * @param array $fields [key => ['value' => string, 'html' => bool]]
 * @return array{texts: array<string,string>, characters: int}
 * @throws \DeepL\DeepLException
 */
function dl_translate_fields(\DeepL\Translator $translator, array $fields, ?string $source_lang, string $target_lang, array $settings): array {

    $out = [];
    $characters = 0;
    $groups = ['plain' => [], 'html' => []];

    foreach ($fields as $key => $field) {
        $value = (string) $field['value'];
        if (trim($value) === '') {
            $out[$key] = '';
            continue;
        }
        $groups[$field['html'] ? 'html' : 'plain'][$key] = $value;
    }

    $base_options = [];
    $formality = $settings['formality'] ?? 'default';
    if (in_array($formality, ['prefer_more', 'prefer_less'], true)) {
        $base_options['formality'] = $formality;
    }

    $protect = !empty($settings['protect_shortcodes']);

    foreach ($groups as $group => $texts) {
        if (empty($texts)) {
            continue;
        }

        $options = $base_options;
        if ($group === 'html') {
            $options['tag_handling'] = 'html';
            $ignore = array_filter(array_map('trim', explode(',', (string) ($settings['ignore_tags'] ?? ''))));
            if (!empty($ignore)) {
                $options['ignore_tags'] = array_values($ignore);
            }
            if ($protect) {
                $texts = array_map('dl_protect_shortcodes', $texts);
            }
        }

        $results = $translator->translateText(array_values($texts), $source_lang, $target_lang, $options);

        $i = 0;
        foreach (array_keys($texts) as $key) {
            $text = $results[$i]->text;
            if ($group === 'html' && $protect) {
                $text = dl_unprotect_shortcodes($text);
            }
            $out[$key] = $text;
            $characters += (int) $results[$i]->billedCharacters;
            $i++;
        }
    }

    return ['texts' => $out, 'characters' => $characters];
}

function dl_log(array $entry): void {
    $db = dl_db();
    if ($db === null) {
        return;
    }

    $db->insert('log', array_merge([
        'ts' => time(),
        'type' => '',
        'action' => null,
        'source_id' => null,
        'target_id' => null,
        'source_lang' => null,
        'target_lang' => null,
        'characters' => 0,
        'status' => 'ok',
        'message' => null,
        'user' => $_SESSION['user_nick'] ?? null,
    ], $entry));

    // keep the log small - the newest 1000 entries are plenty
    if (random_int(1, 20) === 1) {
        $max_id = (int) $db->max('log', 'id');
        if ($max_id > 1000) {
            $db->delete('log', ['id[<]' => $max_id - 1000]);
        }
    }
}

/**
 * The translation_urls map of a record: [lang => permalink/slug], without
 * empty entries and without the record's own language.
 */
function dl_translation_targets(string $json, string $source_lang): array {
    $urls = json_decode(html_entity_decode($json, ENT_QUOTES, 'UTF-8'), true);
    if (!is_array($urls)) {
        return [];
    }

    $targets = [];
    foreach ($urls as $lang => $url) {
        $lang = (string) $lang;
        $url = trim((string) $url);
        if ($lang === '' || $url === '' || $lang === $source_lang) {
            continue;
        }
        $targets[$lang] = $url;
    }
    return $targets;
}

/**
 * Languages excluded for one record via the "Addons" tab of the page/product
 * editor (backend/{page|product}-values.php). The core stores the values in
 * the record's addon_string, prefixed with the plugin folder name.
 */
function dl_record_excluded_langs(array $row): array {
    $values = json_decode((string) ($row['addon_string'] ?? ''), true);
    $langs = $values['deepl__exclude_langs'] ?? [];
    if (!is_array($langs)) {
        return [];
    }
    return array_values(array_filter(array_map('strval', $langs), fn($l) => $l !== ''));
}

/**
 * Checkbox list for backend/{page|product}-values.php. Field names are
 * prefixed with "deepl__" by se_render_record_addons(), and $record_data's
 * addon_string arrives already un-prefixed.
 */
function dl_render_exclude_field(array $record_data, string $record_lang, string $translation_urls_json): string {
    $l = dl_lang();
    $values = json_decode((string) ($record_data['addon_string'] ?? ''), true);
    $excluded = is_array($values['exclude_langs'] ?? null) ? $values['exclude_langs'] : [];
    $linked = dl_translation_targets($translation_urls_json, $record_lang);

    $html = '<label class="form-label d-block">'.$l['label_exclude_langs'].dl_hint_icon($l['hint_exclude_langs']).'</label>';
    // Always posted, so unticking the last box is saved too (pages only
    // update the addon_string column when addon_values is part of the POST).
    $html .= '<input type="hidden" name="addon_values[exclude_langs][]" value="">';

    foreach (dl_available_languages() as $sign => $desc) {
        if ($sign === $record_lang) {
            continue;
        }
        $id = 'dlExclude_'.preg_replace('/[^a-z0-9_-]/i', '', $sign);
        $checked = in_array($sign, $excluded, true) ? ' checked' : '';
        $hint = isset($linked[$sign]) ? '' : ' <small class="text-muted">('.$l['label_no_translation_url'].')</small>';

        $html .= '<div class="form-check form-check-inline">';
        $html .= '<input class="form-check-input" type="checkbox" name="addon_values[exclude_langs][]" id="'.$id.'" value="'.htmlspecialchars($sign, ENT_QUOTES).'"'.$checked.'>';
        $html .= '<label class="form-check-label" for="'.$id.'">'.htmlspecialchars($desc).' <code>'.htmlspecialchars($sign).'</code>'.$hint.'</label>';
        $html .= '</div>';
    }

    return $html;
}

/**
 * Why a linked language is not translated: null = translate it.
 */
function dl_skip_reason(string $lang, array $settings, array $record_excluded): ?string {
    if (in_array($lang, $record_excluded, true)) {
        return 'record';
    }
    if (!dl_target_enabled($lang, $settings)) {
        return 'settings';
    }
    return null;
}

/* -------------------------------------------------------------------
 * Record translation
 * ---------------------------------------------------------------- */

/**
 * Translate a saved page into every linked translation (page_translation_urls).
 * The source is read back from the database after the core saved it, so
 * page_content is always the final HTML (never a block-editor JSON envelope).
 *
 * @param \DeepL\Translator|null $translator only passed in by tests
 * @return array list of result rows ['lang', 'status', 'message', 'characters']
 */
function dl_translate_page(array $context, ?\DeepL\Translator $translator = null): array {
    global $db_content;

    $settings = dl_get_settings();
    $translator ??= dl_translator($settings['api_key'] ?? '');
    if ($translator === null) {
        return [['lang' => '', 'status' => 'error', 'message' => dl_lang()['msg_no_api_key'] ?? 'No API key', 'characters' => 0]];
    }

    $source = null;
    if (!empty($context['page_id']) && is_numeric($context['page_id'])) {
        $source = $db_content->get('se_pages', '*', ['page_id' => (int) $context['page_id']]);
    }
    if (!$source && !empty($context['data']['page_permalink'])) {
        // new page - the core doesn't pass the id of a freshly inserted page
        $source = $db_content->get('se_pages', '*', [
            'page_permalink' => se_clean_permalink($context['data']['page_permalink']),
            'page_language' => (string) ($context['data']['page_language'] ?? ''),
            'ORDER' => ['page_id' => 'DESC'],
        ]);
    }
    if (!$source) {
        return [];
    }

    $source_lang = (string) $source['page_language'];
    $targets = dl_translation_targets((string) $source['page_translation_urls'], $source_lang);
    $excluded = dl_record_excluded_langs($source);

    $decode = fn($v) => html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $encode = fn($v) => function_exists('se_return_clean_value') ? se_return_clean_value($v) : htmlentities($v, ENT_QUOTES, 'UTF-8');

    $fields = [
        'page_title' => ['value' => $decode($source['page_title']), 'html' => false],
        'page_linkname' => ['value' => $decode($source['page_linkname']), 'html' => false],
        'page_meta_description' => ['value' => $decode($source['page_meta_description']), 'html' => false],
        'page_meta_keywords' => ['value' => $decode($source['page_meta_keywords']), 'html' => false],
        'page_content' => ['value' => (string) $source['page_content'], 'html' => true],
    ];

    $results = [];

    foreach ($targets as $lang => $permalink) {

        $skip = dl_skip_reason($lang, $settings, $excluded);
        if ($skip !== null) {
            $results[] = ['lang' => $lang, 'status' => 'excluded', 'message' => $skip, 'characters' => 0];
            continue;
        }

        $target = $db_content->get('se_pages', ['page_id', 'page_permalink', 'page_content_source'], [
            'page_permalink' => $permalink,
            'page_language' => $lang,
        ]);

        if (!$target || (int) $target['page_id'] === (int) $source['page_id']) {
            $msg = sprintf(dl_lang()['msg_target_not_found'] ?? 'Target not found: %s', $permalink);
            $results[] = ['lang' => $lang, 'status' => 'skipped', 'message' => $msg, 'characters' => 0];
            dl_log(['type' => 'page', 'action' => 'all', 'source_id' => $source['page_id'], 'source_lang' => $source_lang, 'target_lang' => $lang, 'status' => 'skipped', 'message' => $msg]);
            continue;
        }

        try {
            $translated = dl_translate_fields($translator, $fields, dl_source_code($source_lang), dl_target_code($lang, $settings), $settings);
        } catch (\Throwable $e) {
            $results[] = ['lang' => $lang, 'status' => 'error', 'message' => $e->getMessage(), 'characters' => 0];
            dl_log(['type' => 'page', 'action' => 'all', 'source_id' => $source['page_id'], 'target_id' => $target['page_id'], 'source_lang' => $source_lang, 'target_lang' => $lang, 'status' => 'error', 'message' => $e->getMessage()]);
            continue;
        }

        $t = $translated['texts'];
        $update = [
            'page_title' => $encode($t['page_title']),
            'page_linkname' => $encode($t['page_linkname']),
            'page_meta_description' => $encode($t['page_meta_description']),
            'page_meta_keywords' => $encode($t['page_meta_keywords']),
            'page_content' => $t['page_content'],
            'page_lastedit' => time(),
            'page_lastedit_from' => 'DeepL | '.($_SESSION['user_nick'] ?? ''),
        ];

        // A target page edited with a block editor keeps its content as
        // JSON in page_content_source - the editor would load the old
        // (untranslated) blocks next time and overwrite the translation on
        // save. Switch it to plain HTML instead.
        $message = '';
        if (trim((string) $target['page_content_source']) !== '') {
            $update['page_content_source'] = '';
            $message = dl_lang()['msg_editor_reset'] ?? '';
        }

        $db_content->update('se_pages', $update, ['page_id' => (int) $target['page_id']]);

        if (function_exists('se_snapshot_page')) {
            se_snapshot_page((int) $target['page_id']);
        }
        if (function_exists('se_delete_smarty_cache')) {
            se_delete_smarty_cache(md5($target['page_permalink']));
        }

        $results[] = ['lang' => $lang, 'status' => 'ok', 'message' => $message, 'characters' => $translated['characters']];
        dl_log(['type' => 'page', 'action' => 'all', 'source_id' => $source['page_id'], 'target_id' => $target['page_id'], 'source_lang' => $source_lang, 'target_lang' => $lang, 'characters' => $translated['characters'], 'status' => 'ok', 'message' => $message ?: null]);
    }

    // page_linkname is the navigation label
    if (function_exists('se_build_navigation_cache')) {
        foreach ($results as $r) {
            if ($r['status'] === 'ok') {
                se_build_navigation_cache();
                break;
            }
        }
    }

    return $results;
}

/**
 * Product field sets per hook action.
 * html = translate with tag_handling=html
 * meta = stored double-encoded by the core (see se_prepareProductData())
 */
function dl_product_fields(string $action): array {
    $sets = [
        'teaser' => [
            'title' => 'plain',
            'teaser' => 'html',
        ],
        'descriptions' => [
            'text' => 'html',
            'text_label' => 'plain',
            'text_additional1' => 'html', 'text_additional1_label' => 'plain',
            'text_additional2' => 'html', 'text_additional2_label' => 'plain',
            'text_additional3' => 'html', 'text_additional3_label' => 'plain',
            'text_additional4' => 'html', 'text_additional4_label' => 'plain',
            'text_additional5' => 'html', 'text_additional5_label' => 'plain',
            'text_scope_of_delivery' => 'html',
        ],
        'metas' => [
            'meta_title' => 'meta',
            'meta_description' => 'meta',
            'tags' => 'plain',
        ],
    ];

    if ($action === 'all') {
        return array_merge($sets['teaser'], $sets['descriptions'], $sets['metas'], [
            'product_features_label' => 'plain',
            'product_related_label' => 'plain',
            'product_accessories_label' => 'plain',
            'product_addons_label' => 'plain',
        ]);
    }

    return $sets[$action] ?? [];
}

/**
 * Normalize a translation URL / permalink to a comparable path:
 * no scheme/host, no install sub folder, no leading slash, trailing slash,
 * entities decoded, lower case - e.g. "https://example.com/en/shop/shoe/"
 * and "/en/shop/shoe" both become "en/shop/shoe/".
 */
function dl_normalize_path(string $url): string {
    $path = strtolower(trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $path = preg_replace('#^[a-z][a-z0-9+.-]*://[^/]*#', '', $path);  // scheme + host
    $path = preg_replace('#[?\#].*$#', '', $path);                    // query / fragment
    $path = ltrim($path, '/');

    if (defined('SE_INCLUDE_PATH') && trim((string) SE_INCLUDE_PATH, '/') !== '') {
        $prefix = strtolower(trim((string) SE_INCLUDE_PATH, '/')).'/';
        if (str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
        }
    }

    $path = preg_replace('#/+#', '/', $path);
    return $path === '' ? '' : rtrim($path, '/').'/';
}

/**
 * Permalinks of all pages of a language that can display products - used for
 * products whose main_catalog_slug is empty/"default" (the frontend then
 * shows them under whichever catalog page was requested). Same page
 * criteria as the core's "default" resolution in se_prepareProductData().
 */
function dl_catalog_pages(string $lang): array {
    global $db_content;
    static $cache = [];

    if (!isset($cache[$lang])) {
        $permalinks = $db_content->select('se_pages', 'page_permalink', [
            'page_language' => $lang,
            'OR' => [
                'page_type_of_use' => 'display_product',
                'page_posts_types[~]' => 'p',
            ],
        ]);
        $cache[$lang] = array_values(array_filter(array_map('dl_normalize_path', (array) $permalinks)));
    }
    return $cache[$lang];
}

/**
 * Find the product a translation URL points to. The URL has to contain the
 * main catalog page AND the slug ("shop/shoe/") - slugs alone are not unique.
 *
 * @return array{id: ?int, message: string}
 */
function dl_find_target_product(string $url, string $lang, int $source_id): array {
    global $db_posts;
    $l = dl_lang();

    $path = dl_normalize_path($url);
    $candidates = $db_posts->select('se_products', ['id', 'type', 'slug', 'main_catalog_slug'], [
        'product_lang' => $lang,
        'id[!]' => $source_id,
    ]);

    $matches = [];
    foreach ($candidates as $c) {
        $slug = dl_normalize_path((string) $c['slug']);
        if ($slug === '' || !str_ends_with($path, $slug)) {
            continue;
        }

        $catalog = trim((string) $c['main_catalog_slug']);
        $catalogs = ($catalog === '' || $catalog === 'default')
            ? dl_catalog_pages($lang)
            : [dl_normalize_path($catalog)];

        foreach ($catalogs as $cat) {
            if ($cat.$slug === $path) {
                $matches[] = $c;
                break;
            }
        }
    }

    // variants can share catalog page + slug with their parent - the
    // parent (the product itself) wins
    if (count($matches) > 1) {
        $main = array_values(array_filter($matches, fn($m) => $m['type'] !== 'v'));
        if (count($main) === 1) {
            $matches = $main;
        }
    }

    if (count($matches) === 1) {
        return ['id' => (int) $matches[0]['id'], 'message' => ''];
    }

    if (count($matches) > 1) {
        $ids = implode(', ', array_map(fn($m) => '#'.$m['id'], $matches));
        return ['id' => null, 'message' => sprintf($l['msg_target_ambiguous'] ?? 'Several products at "%s": %s', $url, $ids)];
    }

    // only a slug entered (old style) - tell the admin what's missing
    if (substr_count(rtrim($path, '/'), '/') === 0) {
        return ['id' => null, 'message' => sprintf($l['msg_target_needs_catalog'] ?? '"%s" contains no catalog page.', $url)];
    }

    return ['id' => null, 'message' => sprintf($l['msg_target_not_found'] ?? 'Target not found: %s', $url)];
}

/**
 * Translate a saved product into every linked translation (translation_urls,
 * matched by main catalog page + slug + product_lang).
 */
function dl_translate_product(array $context, string $action, ?\DeepL\Translator $translator = null): array {
    global $db_posts;

    $settings = dl_get_settings();
    $translator ??= dl_translator($settings['api_key'] ?? '');
    if ($translator === null) {
        return [['lang' => '', 'status' => 'error', 'message' => dl_lang()['msg_no_api_key'] ?? 'No API key', 'characters' => 0]];
    }

    if (empty($context['product_id']) || !is_numeric($context['product_id'])) {
        return [];
    }

    $source = $db_posts->get('se_products', '*', ['id' => (int) $context['product_id']]);
    if (!$source) {
        return [];
    }

    $source_lang = (string) $source['product_lang'];
    $targets = dl_translation_targets((string) $source['translation_urls'], $source_lang);
    $excluded = dl_record_excluded_langs($source);

    // The core stores every product string htmlspecialchars()-encoded (and
    // meta_title/meta_description additionally through se_return_clean_value()),
    // the frontend decodes it again - mirror that exactly.
    $field_modes = dl_product_fields($action);
    $fields = [];
    foreach ($field_modes as $col => $mode) {
        $value = (string) ($source[$col] ?? '');
        if ($mode === 'meta') {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $value = htmlspecialchars_decode($value, ENT_QUOTES);
        $fields[$col] = ['value' => $value, 'html' => $mode === 'html'];
    }

    $results = [];

    foreach ($targets as $lang => $url) {

        $skip = dl_skip_reason($lang, $settings, $excluded);
        if ($skip !== null) {
            $results[] = ['lang' => $lang, 'status' => 'excluded', 'message' => $skip, 'characters' => 0];
            continue;
        }

        $found = dl_find_target_product($url, $lang, (int) $source['id']);
        $target_id = $found['id'];

        if ($target_id === null) {
            $msg = $found['message'];
            dl_log(['type' => 'product', 'action' => $action, 'source_id' => $source['id'], 'source_lang' => $source_lang, 'target_lang' => $lang, 'status' => 'skipped', 'message' => $msg]);
            continue;
        }

        try {
            $translated = dl_translate_fields($translator, $fields, dl_source_code($source_lang), dl_target_code($lang, $settings), $settings);
        } catch (\Throwable $e) {
            $results[] = ['lang' => $lang, 'status' => 'error', 'message' => $e->getMessage(), 'characters' => 0];
            dl_log(['type' => 'product', 'action' => $action, 'source_id' => $source['id'], 'target_id' => $target_id, 'source_lang' => $source_lang, 'target_lang' => $lang, 'status' => 'error', 'message' => $e->getMessage()]);
            continue;
        }

        $update = [];
        foreach ($field_modes as $col => $mode) {
            $value = htmlspecialchars($translated['texts'][$col], ENT_QUOTES);
            if ($mode === 'meta' && function_exists('se_return_clean_value')) {
                $value = se_return_clean_value($value);
            }
            $update[$col] = $value;
        }
        $update['lastedit'] = time();
        $update['lastedit_from'] = 'DeepL | '.($_SESSION['user_nick'] ?? '');

        $db_posts->update('se_products', $update, ['id' => (int) $target_id]);

        // refresh the frontend cache file of the target product
        if (function_exists('se_updateProductCache')) {
            $row = $db_posts->get('se_products', '*', ['id' => (int) $target_id]);
            if ($row) {
                se_updateProductCache((int) $target_id, $row);
            }
        }

        $results[] = ['lang' => $lang, 'status' => 'ok', 'message' => '', 'characters' => $translated['characters']];
        dl_log(['type' => 'product', 'action' => $action, 'source_id' => $source['id'], 'target_id' => $target_id, 'source_lang' => $source_lang, 'target_lang' => $lang, 'characters' => $translated['characters'], 'status' => 'ok']);
    }

    return $results;
}

/**
 * One toast per hook run, summarizing all target languages.
 */
function dl_notify(array $results): void {
    if (!function_exists('show_toast')) {
        return;
    }

    $l = dl_lang();

    if (empty($results)) {
        show_toast($l['msg_no_targets'] ?? 'DeepL: no linked translations found.', 'info');
        return;
    }

    $ok = [];
    $chars = 0;
    $problems = [];
    $skipped = [];
    foreach ($results as $r) {
        if ($r['status'] === 'excluded') {
            $skipped[] = strtoupper($r['lang']);
        } elseif ($r['status'] === 'ok') {
            $ok[] = strtoupper($r['lang']);
            $chars += $r['characters'];
            if ($r['message'] !== '') {
                $problems[] = strtoupper($r['lang']).': '.htmlspecialchars($r['message']);
            }
        } else {
            $problems[] = ($r['lang'] !== '' ? strtoupper($r['lang']).': ' : '').htmlspecialchars($r['message']);
        }
    }

    if (!empty($ok)) {
        show_toast(sprintf($l['msg_translated'] ?? 'DeepL: translated into %s (%s characters)', implode(', ', $ok), number_format($chars, 0, ',', '.')), 'success');
    }
    if (!empty($problems)) {
        show_toast('DeepL:<br>'.implode('<br>', $problems), empty($ok) ? 'danger' : 'info');
    }
    if (!empty($skipped)) {
        show_toast(sprintf($l['msg_skipped_langs'] ?? 'DeepL: not translated (excluded): %s', implode(', ', $skipped)), 'info');
    }
}

/**
 * API key block of the settings tab. Rendered by reader.php and re-rendered
 * by writer.php after a key change (hx-swap="outerHTML"), so the masked key
 * and the status always match what's stored.
 */
function dl_render_key_block(array $settings, string $message = ''): string {
    $l = dl_lang();
    $key = (string) ($settings['api_key'] ?? '');
    $token = htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES);

    $html = '<div id="dlKeyBlock">';
    $html .= $message;
    $html .= '<h5>'.$l['title_api_key'].'</h5>';

    if ($key !== '') {
        $plan = str_ends_with($key, ':fx') ? $l['label_plan_free'] : $l['label_plan_pro'];
        $html .= '<p class="mb-2"><code>'.htmlspecialchars(dl_mask_key($key)).'</code> <span class="badge text-bg-secondary">'.$plan.'</span></p>';
        $html .= '<p class="small text-muted">'.$l['text_keep_auth_key_private'].'</p>';
    } else {
        $html .= '<div class="alert alert-info">'.$l['text_set_auth_key'].'</div>';
    }

    $html .= '<form hx-post="/admin-xhr/addons/plugin/deepl/write/" hx-target="#dlKeyBlock" hx-swap="outerHTML">';
    $html .= '<input type="hidden" name="csrf_token" value="'.$token.'">';
    $html .= '<div class="input-group mb-2">';
    $html .= '<input type="password" class="form-control" name="api_key" autocomplete="off" placeholder="'.htmlspecialchars($key !== '' ? $l['placeholder_new_key'] : $l['label_auth_key'], ENT_QUOTES).'">';
    $html .= '<input type="hidden" name="save_key" value="1">';
    $html .= '<button type="submit" class="btn btn-primary">'.$l['btn_save_key'].'</button>';
    $html .= '</div>';
    $html .= '<div class="form-text">'.$l['hint_auth_key'].'</div>';
    $html .= '</form>';

    if ($key !== '') {
        $html .= '<form class="mt-2" hx-post="/admin-xhr/addons/plugin/deepl/write/" hx-target="#dlKeyBlock" hx-swap="outerHTML" hx-confirm="'.htmlspecialchars($l['confirm_remove_key'], ENT_QUOTES).'">';
        $html .= '<input type="hidden" name="csrf_token" value="'.$token.'">';
        $html .= '<input type="hidden" name="remove_key" value="1">';
        $html .= '<button type="submit" class="btn btn-sm btn-outline-danger">'.$l['btn_remove_key'].'</button>';
        $html .= '</form>';
    }
    $html .= '</div>';

    return $html;
}

function dl_hint_icon(string $hint): string {
    if ($hint === '') {
        return '';
    }

    return ' <a href="#" onclick="return false;" tabindex="0" class="text-muted"'
        .' data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="'.htmlspecialchars($hint, ENT_QUOTES).'">'
        .'<i class="bi bi-question-circle"></i></a>';
}
