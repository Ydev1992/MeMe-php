<?php

$getAlbums = $db->orderBy('id', 'DESC')->get(T_ALBUMS, 10);

runPlugin("OnAlbumsLoad");

$records = 0;
$html_list = '';
if (!empty($getAlbums)) {
    $records = count($getAlbums);
    $html_list = '';
    foreach ($getAlbums as $key => $album) {
        $songCount = $db->where('availability ', 0)->where('album_id', $album->id)->getValue(T_SONGS, 'count(*)');
        if (!empty($album) && $songCount > 0) {
            $publisher = userData($album->user_id);
            $html_list .= loadPage('store/albums', [
                'id' => $album->id,
                'album_id' => $album->album_id,
                'user_id' => $album->user_id,
                'artist' => $publisher->username,
                'artist_name' => $publisher->name,
                'title' => $album->title,
                'description' => $album->description,
                'category_id' => $album->category_id,
                'thumbnail' => getMedia($album->thumbnail),
                'time' => $album->time,
                'registered' => $album->registered,
                'price' => $album->price,
                'songs' => number_format_mm($db->where('availability ', 0)->where('album_id', $album->id)->getValue(T_SONGS, 'count(*)'))
            ]);
        }
    }
}

$is_pro = false;
if (IS_LOGGED === true) {
    if ($user->is_pro == 1 && $music->config->go_pro == 'on') {
        $is_pro = true;
    }
}

$ad_html = '';
$artist_sidebar_html = '';
$artist_sidebar_html_list = '';

if (IS_LOGGED) {
    
    $db->where("id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)");
    $artist_sidebar = $db->where('artist', '1')->
    where("id NOT IN (SELECT following_id FROM " . T_FOLLOWERS . " WHERE follower_id = '{$music->user->id}') AND id <> '{$music->user->id}'")->get(T_USERS, 8);

    $time = strtotime(date('l').", ".date('M')." ".date('d').", ".date('Y'));
    if (date('l') == 'Saturday') {
        $start = strtotime(date('M')." ".date('d').", ".date('Y')." 12:00am");
    }
    else{
        $start = strtotime('last saturday, 12:00am', $time);
    }

    if (date('l') == 'Friday') {
        $end = strtotime(date('M')." ".date('d').", ".date('Y')." 11:59pm");
    }
    else{
        $end = strtotime('next Friday, 11:59pm', $time);
    }
    $sql = 'SELECT 
    '.T_USERS.'.id
    FROM
    '.T_VIEWS.'
    INNER JOIN '.T_SONGS.' ON ('.T_VIEWS.'.track_id = '.T_SONGS.'.id)
    INNER JOIN '.T_USERS.' ON ('.T_SONGS.'.user_id = '.T_USERS.'.id)
    WHERE
    '.T_USERS.'.artist = 1 AND '.T_VIEWS.'.`time` >= '. $start .' AND '.T_VIEWS.'.`time` <= '. $end .'
    GROUP BY
    '.T_USERS.'.id
    LIMIT 8';
    $artist_sidebar = $db->rawQuery($sql);


    if (!empty($artist_sidebar)) {
        foreach ($artist_sidebar as $key => $value) {
            $artist_sidebar_html_list .= loadPage('feed/sidebar_artists_list', ['USER_DATA' => userData($value->id)]);
        }
        $artist_sidebar_html = loadPage('feed/sidebar_artists', ['html' => $artist_sidebar_html_list]);
    }


}

$last_ads = 0;
$ads_sys = ($music->config->user_ads == 'on') ? true : false;
if (!empty($_COOKIE['last_ads_seen']) && !$is_pro) {
    if ($_COOKIE['last_ads_seen'] > (time() - 600)) {
        $last_ads = 1;
    }
}


if ($last_ads == 0 && !$is_pro && $ads_sys) {
    $rand = (rand(0, 1)) ? rand(0, 1) : (rand(0, 1) ?: rand(0, 1));

    //if ($rand == 0) {
        $ad_data = get_user_ads(2);

        if (!empty($ad_data)) {
            $user_data      = UserData($ad_data->user_id);
            $_SESSION['pagead'] = $ad_data->id;
            $ad_html   = loadPage('ads/view',array(
                'USERDATA' => $user_data,
                'ADDATA' => $ad_data,
                'type' => 'pagead'
            ));
            if ($ad_data->type == 2 && $ad_data->user_id !== $user->id) {
                register_ad_views($ad_data->id, $ad_data->user_id);
            }
        }
}
$music->site_title = lang("Albums");
$music->site_description = $music->config->description;
$music->site_pagename = "albums";
$music->site_content = loadPage("albums/content", [
    'records' => $records,
    'html_content' => $html_list,
    'artist_sidebar' => $artist_sidebar_html,
    'ads' => $ad_html
]);