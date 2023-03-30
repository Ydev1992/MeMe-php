<?php
if(!IS_LOGGED) {
	header("Location: $site_url/404");
    exit;
}
if ($music->config->store_system != 'on' || !$music->config->can_use_store_system) {
	header("Location: $site_url/404");
    exit;
}
if (empty($path['options'][1])) {
	header("Location: $site_url/404");
    exit;
}
$id = secure($path['options'][1]);
$id = GetPostIdFromUrl($id);
if (empty($id)) {
    header("Location: $site_url/404");
    exit;
}
$product = GetProduct($id,'hash');

if (empty($product)) {
	header("Location: $site_url/404");
	exit();
}
$product->user_data->owner  = false;

if ($music->loggedin == true) {
    $product->user_data->owner  = ($user->id == $music->user->id || isAdmin()) ? true : false;
}
if (!$product->user_data->owner) {
	header("Location: $site_url/404");
	exit();
}
runPlugin("OnEditProductLoad");
$product->desc = br2nl($product->desc);
$music->product = $product;
$music->site_title = lang("Edit Product") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "edit_product";
$music->site_content     = loadPage('edit_product/content');