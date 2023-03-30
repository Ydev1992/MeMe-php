<?php
if (!IS_LOGGED) {
    header("Location: $site_url/404");
    exit;
}
if ($music->config->developers_page != 'on') {
    header('Location: ' . $site_url);
    exit;
}
runPlugin("OnDevelopersLoad");
$music->site_title = lang("developers") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "developers";
$music->site_content     = loadPage('developers/content',[
    'apps_header' => loadPage('developers/header')
]);