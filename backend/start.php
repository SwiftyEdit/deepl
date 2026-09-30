<?php

require __DIR__.'/../global/bootstrap.php';

echo '<h1>'.$addon_lang['title_overview'].'</h1>';

echo '<div class="row">';

echo '<div class="col-md-5">';
echo '<div class="card mb-3">';
echo '<div class="card-header">'.$addon_lang['title_usage'].'</div>';
echo '<div class="card-body" hx-get="/admin-xhr/addons/plugin/deepl/read/?show=usage" hx-trigger="load">LOADING ...</div>';
echo '</div>';

echo '<div class="card mb-3">';
echo '<div class="card-header">'.$addon_lang['title_test'].'</div>';
echo '<div class="card-body" hx-get="/admin-xhr/addons/plugin/deepl/read/?show=test_form" hx-trigger="load">LOADING ...</div>';
echo '</div>';
echo '</div>';

echo '<div class="col-md-7">';
echo '<div class="card mb-3">';
echo '<div class="card-header">'.$addon_lang['title_log'].'</div>';
echo '<div class="card-body" hx-get="/admin-xhr/addons/plugin/deepl/read/?show=log" hx-trigger="load">LOADING ...</div>';
echo '</div>';
echo '</div>';

echo '</div>';
