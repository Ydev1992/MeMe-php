<?php
if(!IS_LOGGED) {
	header("Location: $site_url/404");
    exit;
}
if ($music->config->story_system != 'on') {
	header("Location: $site_url/404");
    exit;
}
if (!$music->config->can_use_story_system) {
	header("Location: $site_url/404");
    exit;
}
runPlugin("OnCreateStoryLoad");
$music->site_title = lang("Create Story") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "create-story";
$music->site_content     = loadPage('create_story/content');