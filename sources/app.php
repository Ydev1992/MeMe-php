<?php


if (!IS_LOGGED || empty($path['options']) || empty($path['options'][1])) {
    header("Location: $site_url/404");
    exit;
}
if ($music->config->developers_page != 'on') {
    header('Location: ' . $site_url);
    exit;
}

$id = secure($path['options'][1]);

$app          = $db->where('id',$id)->where('app_user_id',$user->id)->getOne(T_APPS);

if (empty($app)) {
    header('Location: ' . $site_url);
    exit;
}

runPlugin("OnAppLoad");

$music->site_title = lang("app") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "app";
$music->site_content     = loadPage('developers/app',array(
    'id' => $app->id,
    'app_id' => $app->app_id,
    'app_secret' => $app->app_secret,
    'app_name' => $app->app_name,
    'app_website_url' => $app->app_website_url,
    'app_description' => $app->app_description,
    'app_callback_url' => $app->app_callback_url,
    'app_avatar' => getMedia($app->app_avatar)
));