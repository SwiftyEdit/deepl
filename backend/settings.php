<?php

require __DIR__.'/../global/bootstrap.php';

echo '<h1>'.$addon_lang['title_settings'].'</h1>';

echo '<div class="row">';
echo '<div class="col-md-8">';
echo '<div class="card">';
echo '<div class="card-body" hx-get="/admin-xhr/addons/plugin/deepl/read/?show=settings_form" hx-trigger="load">LOADING ...</div>';
echo '</div>';
echo '</div>';
echo '</div>';
