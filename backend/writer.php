<?php

require __DIR__.'/../global/bootstrap.php';

/* ---------------------------------------------------------------
 * API key - checked against DeepL before it is stored
 * -------------------------------------------------------------- */
if (isset($_POST['save_key'])) {

    $key = trim((string) ($_POST['api_key'] ?? ''));

    if ($key === '') {
        echo dl_render_key_block($dl_settings, '<div class="alert alert-warning">'.$addon_lang['msg_key_empty'].'</div>');
        exit;
    }

    try {
        dl_translator($key)->getUsage();
    } catch (\Throwable $e) {
        $msg = '<div class="alert alert-danger">'.$addon_lang['msg_key_invalid'].'<br><small>'.htmlspecialchars($e->getMessage()).'</small></div>';
        echo dl_render_key_block($dl_settings, $msg);
        exit;
    }

    dl_save_setting('api_key', $key);
    record_log($_SESSION['user_nick'] ?? '', 'DeepL: API key changed', '5');

    echo dl_render_key_block(dl_get_settings(), '<div class="alert alert-success">'.$addon_lang['msg_key_saved'].'</div>');
    exit;
}

if (isset($_POST['remove_key'])) {
    dl_save_setting('api_key', '');
    record_log($_SESSION['user_nick'] ?? '', 'DeepL: API key removed', '5');

    echo dl_render_key_block(dl_get_settings(), '<div class="alert alert-success">'.$addon_lang['msg_key_removed'].'</div>');
    exit;
}

/* ---------------------------------------------------------------
 * Settings
 * -------------------------------------------------------------- */
if (isset($_POST['save_settings'])) {

    $available = array_keys(dl_available_languages());
    $posted = array_values(array_intersect($available, (array) ($_POST['target_langs'] ?? [])));

    if (empty($posted)) {
        echo '<div class="alert alert-danger">'.$addon_lang['msg_no_target_langs'].'</div>';
        exit;
    }

    // all checked = no restriction (languages activated later are included too)
    $target_langs = count($posted) === count($available) ? '' : json_encode($posted);

    $formality = in_array($_POST['formality'] ?? '', ['default', 'prefer_more', 'prefer_less'], true) ? $_POST['formality'] : 'default';
    $en_variant = ($_POST['en_variant'] ?? '') === 'EN-GB' ? 'EN-GB' : 'EN-US';
    $pt_variant = ($_POST['pt_variant'] ?? '') === 'PT-BR' ? 'PT-BR' : 'PT-PT';

    $tags = array_filter(array_map(
        fn($t) => strtolower(preg_replace('/[^a-z0-9-]/i', '', $t)),
        explode(',', (string) ($_POST['ignore_tags'] ?? ''))
    ));

    dl_save_setting('target_langs', $target_langs);
    dl_save_setting('formality', $formality);
    dl_save_setting('en_variant', $en_variant);
    dl_save_setting('pt_variant', $pt_variant);
    dl_save_setting('ignore_tags', implode(',', array_unique($tags)));
    dl_save_setting('protect_shortcodes', isset($_POST['protect_shortcodes']) ? 1 : 0);

    echo '<div class="alert alert-success">'.$addon_lang['msg_saved'].'</div>';
    exit;
}

/* ---------------------------------------------------------------
 * Test translation (overview tab)
 * -------------------------------------------------------------- */
if (isset($_POST['test_translate'])) {

    $text = mb_substr(trim((string) ($_POST['text'] ?? '')), 0, 2000);
    $target_lang = strtoupper(preg_replace('/[^a-z-]/i', '', (string) ($_POST['target_lang'] ?? '')));

    if ($text === '' || $target_lang === '') {
        exit;
    }

    try {
        $translator = dl_translator($dl_settings['api_key'] ?? '');
        if ($translator === null) {
            throw new \RuntimeException($addon_lang['msg_no_api_key']);
        }
        $options = [];
        if (in_array($dl_settings['formality'] ?? '', ['prefer_more', 'prefer_less'], true)) {
            $options['formality'] = $dl_settings['formality'];
        }
        $result = $translator->translateText($text, null, $target_lang, $options);
    } catch (\Throwable $e) {
        echo '<div class="alert alert-danger">'.htmlspecialchars($e->getMessage()).'</div>';
        exit;
    }

    dl_log([
        'type' => 'test',
        'source_lang' => strtolower($result->detectedSourceLang),
        'target_lang' => strtolower($target_lang),
        'characters' => (int) $result->billedCharacters,
    ]);

    echo '<div class="alert alert-success mb-0">';
    echo nl2br(htmlspecialchars($result->text));
    echo '<div class="small text-muted mt-2">'.sprintf($addon_lang['text_test_meta'], htmlspecialchars(strtoupper($result->detectedSourceLang)), (int) $result->billedCharacters).'</div>';
    echo '</div>';
    exit;
}

exit;
