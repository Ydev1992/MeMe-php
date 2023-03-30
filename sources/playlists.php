<?php
runPlugin("OnPlaylistsPage");
$html = '<div class="no-track-found bg_light"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M19,9H2V11H19V9M19,5H2V7H19V5M2,15H15V13H2V15M17,13V19L22,16L17,13Z" /></svg>' . lang("No playlists found") . '</div>';

if (IS_LOGGED)
{
    $db->where("user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)");
}
$music->playlist_count = 0;
$getPlayLists = $db->where('privacy', 0)
    ->orderBy('id', 'DESC')
    ->get(T_PLAYLISTS, 30);

if (!empty($getPlayLists))
{
    $html = '';
    foreach ($getPlayLists as $key => $playlist)
    {
        $music->playlist_count++;
        $playlist = getPlayList($playlist, false);
        $html .= loadPage('user/playlist-list', ['t_thumbnail' => $playlist->thumbnail_ready, 't_id' => $playlist->id, 't_uid' => $playlist->uid, 'USER_DATA' => $playlist->publisher, 't_title' => $playlist->name, 't_privacy' => $playlist->privacy_text, 't_url' => $playlist->url, 't_songs' => $playlist->songs, 't_key' => ($key + 1) ]);
    }
}

$artist_sidebar_html = '';
$artist_sidebar_html_list = '';
if (IS_LOGGED)
{
    $db->where("id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)");
    $artist_sidebar = $db->where('artist', '1')
        ->where("id NOT IN (SELECT following_id FROM " . T_FOLLOWERS . " WHERE follower_id = '{$music
        ->user->id}') AND id <> '{$music
        ->user->id}'")
        ->get(T_USERS, 8);

    $time = strtotime(date('l') . ", " . date('M') . " " . date('d') . ", " . date('Y'));
    if (date('l') == 'Saturday')
    {
        $start = strtotime(date('M') . " " . date('d') . ", " . date('Y') . " 12:00am");
    }
    else
    {
        $start = strtotime('last saturday, 12:00am', $time);
    }

    if (date('l') == 'Friday')
    {
        $end = strtotime(date('M') . " " . date('d') . ", " . date('Y') . " 11:59pm");
    }
    else
    {
        $end = strtotime('next Friday, 11:59pm', $time);
    }
    $sql = 'SELECT 
  ' . T_USERS . '.id
FROM
  ' . T_VIEWS . '
  INNER JOIN ' . T_SONGS . ' ON (' . T_VIEWS . '.track_id = ' . T_SONGS . '.id)
  INNER JOIN ' . T_USERS . ' ON (' . T_SONGS . '.user_id = ' . T_USERS . '.id)
WHERE
  ' . T_USERS . '.artist = 1 AND ' . T_VIEWS . '.`time` >= ' . $start . ' AND ' . T_VIEWS . '.`time` <= ' . $end . '
GROUP BY
  ' . T_USERS . '.id
LIMIT 8';
    $artist_sidebar = $db->rawQuery($sql);

    if (!empty($artist_sidebar))
    {
        foreach ($artist_sidebar as $key => $value)
        {
            $artist_sidebar_html_list .= loadPage('feed/sidebar_artists_list', ['USER_DATA' => userData($value->id) ]);
        }
        $artist_sidebar_html = loadPage('feed/sidebar_artists', ['html' => $artist_sidebar_html_list]);
    }
}

$music->site_title = lang("Playlists");
$music->site_description = $music
    ->config->description;
$music->site_pagename = "playlists";
$music->site_content = loadPage("playlists/public", ['html' => $html, 'artist_sidebar' => $artist_sidebar_html, ]);

