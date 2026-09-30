<?php

require __DIR__.'/../global/bootstrap.php';

$show = $_GET['show'] ?? '';

/* ---------------------------------------------------------------
 * Overview: character usage of the current billing period
 * -------------------------------------------------------------- */
if ($show === 'usage') {

    try {
        $translator = dl_translator($dl_settings['api_key'] ?? '');
    } catch (\Throwable $e) {
        $translator = null;
    }

    if ($translator === null) {
        echo '<div class="alert alert-info mb-0">'.$addon_lang['text_set_auth_key'].'</div>';
        echo '<a href="/admin/addons/plugin/deepl/settings/" class="btn btn-sm btn-default mt-2">'.$addon_lang['nav_settings'].'</a>';
        exit;
    }

    try {
        $usage = $translator->getUsage();
    } catch (\Throwable $e) {
        echo '<div class="alert alert-danger mb-0">'.htmlspecialchars($e->getMessage()).'</div>';
        exit;
    }

    if ($usage->character) {
        $count = $usage->character->count;
        $limit = $usage->character->limit;
        $percent = $limit > 0 ? min(100, round($count / $limit * 100, 1)) : 0;
        $bar = $percent >= 90 ? 'bg-danger' : ($percent >= 70 ? 'bg-warning' : 'bg-success');

        echo '<p class="mb-1">'.sprintf($addon_lang['text_usage'], number_format($count, 0, ',', '.'), number_format($limit, 0, ',', '.')).'</p>';
        echo '<div class="progress mb-2" role="progressbar" aria-valuenow="'.$percent.'" aria-valuemin="0" aria-valuemax="100">';
        echo '<div class="progress-bar '.$bar.'" style="width:'.$percent.'%">'.$percent.' %</div>';
        echo '</div>';
    }

    if ($usage->anyLimitReached()) {
        echo '<div class="alert alert-danger mb-0">'.$addon_lang['msg_limit_reached'].'</div>';
    }
    exit;
}

/* ---------------------------------------------------------------
 * Overview: test translation form
 * -------------------------------------------------------------- */
if ($show === 'test_form') {

    if (($dl_settings['api_key'] ?? '') === '') {
        echo '<p class="text-muted mb-0">'.$addon_lang['text_set_auth_key'].'</p>';
        exit;
    }

    $languages = [];
    try {
        foreach (dl_translator($dl_settings['api_key'])->getTargetLanguages() as $language) {
            $languages[$language->code] = $language->name;
        }
    } catch (\Throwable $e) {
        echo '<div class="alert alert-danger mb-0">'.htmlspecialchars($e->getMessage()).'</div>';
        exit;
    }

    echo '<form hx-post="/admin-xhr/addons/plugin/deepl/write/" hx-target="#dlTestResult" hx-swap="innerHTML">';
    echo '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES).'">';
    echo '<input type="hidden" name="test_translate" value="1">';
    echo '<div class="mb-2">';
    echo '<textarea class="form-control" name="text" rows="4" maxlength="2000" required></textarea>';
    echo '</div>';
    echo '<div class="input-group mb-2">';
    echo '<select name="target_lang" class="form-select">';
    foreach ($languages as $code => $name) {
        echo '<option value="'.htmlspecialchars($code, ENT_QUOTES).'">'.htmlspecialchars($name).' ('.htmlspecialchars($code).')</option>';
    }
    echo '</select>';
    echo '<button type="submit" class="btn btn-primary">'.$addon_lang['btn_translate'].'</button>';
    echo '</div>';
    echo '</form>';
    echo '<div id="dlTestResult"></div>';
    exit;
}

/* ---------------------------------------------------------------
 * Overview: translation log
 * -------------------------------------------------------------- */
if ($show === 'log') {

    $rows = $deepl_db->select('log', '*', ['ORDER' => ['id' => 'DESC'], 'LIMIT' => 50]);

    if (empty($rows)) {
        echo '<p class="text-muted mb-0">'.$addon_lang['msg_log_empty'].'</p>';
        exit;
    }

    $badges = ['ok' => 'text-bg-success', 'error' => 'text-bg-danger', 'skipped' => 'text-bg-secondary'];

    echo '<div class="table-responsive" style="max-height:600px;overflow-y:auto">';
    echo '<table class="table table-sm table-striped mb-0">';
    echo '<thead><tr>';
    echo '<th>'.$addon_lang['th_date'].'</th>';
    echo '<th>'.$addon_lang['th_record'].'</th>';
    echo '<th>'.$addon_lang['th_languages'].'</th>';
    echo '<th class="text-end">'.$addon_lang['th_characters'].'</th>';
    echo '<th>'.$addon_lang['th_status'].'</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $type = $row['type'];
        if ($type === 'page' && $row['target_id']) {
            $record = '<a href="/admin/pages/edit/'.(int) $row['target_id'].'/">'.$addon_lang['label_page'].' #'.(int) $row['target_id'].'</a>';
        } elseif ($type === 'product' && $row['target_id']) {
            $record = '<a href="/admin/shop/edit/'.(int) $row['target_id'].'/">'.$addon_lang['label_product'].' #'.(int) $row['target_id'].'</a>';
        } else {
            $record = htmlspecialchars($addon_lang['label_'.$type] ?? $type);
        }
        if ($row['action'] && $row['action'] !== 'all') {
            $record .= ' <small class="text-muted">('.htmlspecialchars($addon_lang['label_action_'.$row['action']] ?? $row['action']).')</small>';
        }

        $status = '<span class="badge '.($badges[$row['status']] ?? 'text-bg-secondary').'">'.htmlspecialchars($row['status']).'</span>';
        if ($row['message']) {
            $status .= '<div class="small text-muted">'.htmlspecialchars($row['message']).'</div>';
        }

        echo '<tr>';
        echo '<td class="text-nowrap small">'.date('d.m.Y H:i', (int) $row['ts']).'<div class="text-muted">'.htmlspecialchars((string) $row['user']).'</div></td>';
        echo '<td>'.$record.'</td>';
        echo '<td class="text-nowrap">'.htmlspecialchars(strtoupper((string) $row['source_lang'])).' &rarr; '.htmlspecialchars(strtoupper((string) $row['target_lang'])).'</td>';
        echo '<td class="text-end">'.number_format((int) $row['characters'], 0, ',', '.').'</td>';
        echo '<td>'.$status.'</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
    exit;
}

/* ---------------------------------------------------------------
 * Settings form
 * -------------------------------------------------------------- */
if ($show === 'settings_form') {

    $token = htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES);

    echo dl_render_key_block($dl_settings);

    echo '<hr class="my-4">';

    echo '<div id="dlSettingsResponse"></div>';
    echo '<form hx-post="/admin-xhr/addons/plugin/deepl/write/" hx-target="#dlSettingsResponse" hx-swap="innerHTML">';
    echo '<input type="hidden" name="save_settings" value="1">';
    echo '<input type="hidden" name="csrf_token" value="'.$token.'">';

    // target languages
    $enabled = json_decode((string) ($dl_settings['target_langs'] ?? ''), true);
    $enabled = is_array($enabled) ? $enabled : [];

    echo '<div class="mb-3">';
    echo '<label class="form-label d-block">'.$addon_lang['label_target_langs'].dl_hint_icon($addon_lang['hint_target_langs']).'</label>';
    foreach (dl_available_languages() as $sign => $desc) {
        $id = 'dlLang_'.preg_replace('/[^a-z0-9_-]/i', '', $sign);
        $checked = (empty($enabled) || in_array($sign, $enabled, true)) ? ' checked' : '';
        echo '<div class="form-check form-check-inline">';
        echo '<input class="form-check-input" type="checkbox" name="target_langs[]" id="'.$id.'" value="'.htmlspecialchars($sign, ENT_QUOTES).'"'.$checked.'>';
        echo '<label class="form-check-label" for="'.$id.'">'.htmlspecialchars($desc).' <code>'.htmlspecialchars($sign).'</code> &rarr; <code>'.htmlspecialchars(dl_target_code($sign, $dl_settings)).'</code></label>';
        echo '</div>';
    }
    echo '</div>';

    // regional variants
    echo '<div class="row">';
    echo '<div class="col-md-6 mb-3">';
    echo '<label class="form-label">'.$addon_lang['label_en_variant'].'</label>';
    echo '<select class="form-select" name="en_variant">';
    foreach (['EN-US' => 'English (American)', 'EN-GB' => 'English (British)'] as $code => $name) {
        $sel = ($dl_settings['en_variant'] ?? 'EN-US') === $code ? ' selected' : '';
        echo '<option value="'.$code.'"'.$sel.'>'.$name.' ('.$code.')</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '<div class="col-md-6 mb-3">';
    echo '<label class="form-label">'.$addon_lang['label_pt_variant'].'</label>';
    echo '<select class="form-select" name="pt_variant">';
    foreach (['PT-PT' => 'Português (Portugal)', 'PT-BR' => 'Português (Brasil)'] as $code => $name) {
        $sel = ($dl_settings['pt_variant'] ?? 'PT-PT') === $code ? ' selected' : '';
        echo '<option value="'.$code.'"'.$sel.'>'.$name.' ('.$code.')</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    // formality
    echo '<div class="mb-3">';
    echo '<label class="form-label">'.$addon_lang['label_formality'].dl_hint_icon($addon_lang['hint_formality']).'</label>';
    echo '<select class="form-select" name="formality" style="max-width:320px">';
    foreach (['default', 'prefer_more', 'prefer_less'] as $f) {
        $sel = ($dl_settings['formality'] ?? 'default') === $f ? ' selected' : '';
        echo '<option value="'.$f.'"'.$sel.'>'.$addon_lang['formality_'.$f].'</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<hr>';

    // HTML handling
    echo '<div class="mb-3">';
    echo '<label class="form-label">'.$addon_lang['label_ignore_tags'].dl_hint_icon($addon_lang['hint_ignore_tags']).'</label>';
    echo '<input type="text" class="form-control" name="ignore_tags" value="'.htmlspecialchars((string) ($dl_settings['ignore_tags'] ?? ''), ENT_QUOTES).'">';
    echo '</div>';

    echo '<div class="form-check form-switch mb-3">';
    echo '<input class="form-check-input" type="checkbox" role="switch" id="dlProtectShortcodes" name="protect_shortcodes" value="1"'.(!empty($dl_settings['protect_shortcodes']) ? ' checked' : '').'>';
    echo '<label class="form-check-label" for="dlProtectShortcodes">'.$addon_lang['label_protect_shortcodes'].dl_hint_icon($addon_lang['hint_protect_shortcodes']).'</label>';
    echo '</div>';

    echo '<button type="submit" class="btn btn-primary">'.$addon_lang['btn_save'].'</button>';
    echo '</form>';
    exit;
}

/* ---------------------------------------------------------------
 * Help
 * -------------------------------------------------------------- */
if ($show === 'docs_nav') {

    // se_parse_docs_file() needs a global $Parsedown instance
    $Parsedown = new Parsedown();

    $docs_root = SE_ROOT.'plugins/deepl/docs/en';
    if (is_dir(SE_ROOT.'plugins/deepl/docs/'.$languagePack)) {
        $docs_root = SE_ROOT.'plugins/deepl/docs/'.$languagePack;
    }

    $current_file = basename($_GET['file'] ?? 'index.md');
    $parsed_files = [];

    foreach (glob($docs_root.'/*.md') as $doc) {
        $parsed_file = se_parse_docs_file($doc);
        $parsed_files[] = [
            'title' => $parsed_file['header']['title'],
            'priority' => $parsed_file['header']['priority'],
            'btn' => $parsed_file['header']['btn'],
            'file' => $doc,
        ];
    }

    $sorted_parsed_files = se_array_multisort($parsed_files, 'priority', SORT_ASC);

    $list = '<div class="card mb-3">';
    $list .= '<div class="list-group list-group-flush">';
    foreach ($sorted_parsed_files as $v) {
        $active = basename($v['file']) === $current_file ? ' active' : '';
        $hx_get = '/admin-xhr/addons/plugin/deepl/read/?show=docs_content&file='.basename($v['file']);
        $list .= '<button class="list-group-item list-group-item-action'.$active.'" hx-get="'.$hx_get.'" hx-target="#docsContent"
            hx-on:click="this.closest(\'.list-group\').querySelectorAll(\'.active\').forEach(function(el){ el.classList.remove(\'active\'); }); this.classList.add(\'active\')">';
        $list .= $v['btn'];
        $list .= '</button>';
    }
    $list .= '</div>';
    $list .= '</div>';
    echo $list;
    exit;
}

if ($show === 'docs_content') {

    $Parsedown = new Parsedown();

    $df = basename($_GET['file'] ?? 'index.md');

    $doc_file = SE_ROOT.'plugins/deepl/docs/'.$languagePack.'/'.$df;
    if (!is_file($doc_file)) {
        $doc_file = SE_ROOT.'plugins/deepl/docs/en/'.$df;
    }

    if (is_file($doc_file)) {
        $parsed_file = se_parse_docs_file($doc_file);
        echo $parsed_file['content'];
    }
    exit;
}

exit;
