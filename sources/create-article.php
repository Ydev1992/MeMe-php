<?php
if (!IS_LOGGED) {
    header("Location: $site_url/404");
    exit;
}
if($music->config->allow_user_create_blog == 'off'){
    header("Location: $site_url/404");
    exit();
}
runPlugin("OnCreateArticleLoad");
$music->site_title = lang("Create New Article");
$music->site_description = $music->config->description;
$music->site_pagename = "new_article";
$music->site_content = loadPage("new_article/content");