<?php
if (!IS_LOGGED) {
    header("Location: $site_url/404");
    exit;
}
if ($music->config->developers_page != 'on') {
    header('Location: ' . $site_url);
    exit;
}

runPlugin("OnMyAppsPage");

$music->my_apps = $db->where('app_user_id',$music->user->id)->orderBy('id','DESC')->get(T_APPS);
$html = '<p>'.lang("no_apps_found").'</p>';
if (!empty($music->my_apps)) {
    $html = '';
    foreach ($music->my_apps as $key => $app) {
        $html .= loadPage('developers/apps_list',[
            'app_link' => $site_url . "/app/".$app->id,
            'id' => $app->id,
            'app_id' => $app->app_id,
            'app_secret' => $app->app_secret,
            'app_name' => $app->app_name,
            'app_website_url' => $app->app_website_url,
            'app_description' => $app->app_description,
            'app_callback_url' => $app->app_callback_url,
            'app_avatar' => getMedia($app->app_avatar)
        ]);
    }
}

$music->site_title = lang("my_apps") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "my_apps";
$music->site_content     = loadPage('developers/my_apps',[
    'html' => $html,
    'apps_header' => loadPage('developers/header')
]);