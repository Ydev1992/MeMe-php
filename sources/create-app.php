<?php
if (!IS_LOGGED) {
    header("Location: $site_url/404");
    exit;
}
if ($music->config->developers_page != 'on') {
    header('Location: ' . $site_url);
    exit;
}
runPlugin("OnCreateAppLoad");
$music->site_title = lang("create_app") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "create-app";
$music->site_content     = loadPage('developers/create-app');