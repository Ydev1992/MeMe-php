<?php
if(!IS_LOGGED) {
	header("Location: $site_url/404");
    exit;
}
if ($music->config->store_system != 'on' || !$music->config->can_use_store_system) {
	header("Location: $site_url/404");
    exit;
}
runPlugin("OnCreateProductLoad");
$music->songCount = $db->where('user_id',$music->user->id)->getValue(T_SONGS,'COUNT(*)');
$music->site_title = lang("Create Product") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "create-product";
$music->site_content     = loadPage('create-product/content');