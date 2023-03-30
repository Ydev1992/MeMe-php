<?php
if (empty($_GET['app_id'])) {
    header("Location: $site_url/404");
    exit;
}

$app = $db->where('app_id',secure($_GET['app_id']))->getOne(T_APPS);
if (empty($app)) {
	header("Location: $site_url/404");
    exit;
}

$actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
if (!IS_LOGGED) {
    header("Location: " . $site_url . '/login?last_url=' . urlencode($actual_link));
    exit();
}

runPlugin("OnoAuthPage");



$have_permission = $db->where('app_id',$app->id)->where('user_id',$music->user->id)->getValue(T_APPS_PERMISSION,'COUNT(*)');
if ($have_permission == 0) {
	$music->site_title = lang("permission") . ' | ' . $music->config->title;
	$music->site_description = $music->config->description;
	$music->site_pagename = "permission";
	$music->site_content     = loadPage('developers/permission',[
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
else{
	$url = $app->app_website_url;
    if (isset($_GET['redirect_uri']) && !empty($_GET['redirect_uri'])) {
        $url = $_GET['redirect_uri'];
    } else if (!empty($app->app_callback_url)) {
        $url = $app->app_callback_url;
    }
    $import = GenrateCode($music->user->id, $app->id);
    header("Location: {$url}?code=$import");
    exit();
}