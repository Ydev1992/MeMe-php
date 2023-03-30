<?php
function GetFollowers($userid,$limit=20,$offset=0){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_FOLLOWERS.'` ';
    $where = '
            WHERE
                `following_id` = '.secure($userid). '
            AND 
                `follower_id` NOT IN (SELECT `blocked_id` FROM `'.T_BLOCKS.'` WHERE `user_id` = '.secure($userid).')
            ';
    $order = ' ORDER BY `id` DESC ';
    $position = ' LIMIT '.$limit.';';
    $data['count'] = count($db->rawQuery($sql . $where));
    $data['data'] = $db->rawQuery($sql . $where . ' AND `id` > ' . $offset. $order . $position);
    foreach ($data['data'] as $key => $value){
        $data['data'][$key] = userData($value->follower_id);
        unset($data['data'][$key]->password);
    }
    return $data;
}
function GetPurchased($userid,$limit=20,$offset=0){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_PURCHAES.'` ';
    $where = '
            WHERE
                `user_id` = '.secure($userid);
    $order = ' ORDER BY `id` DESC ';
    $position = ' LIMIT '.$limit.';';
    $data['count'] = count($db->rawQuery($sql . $where));
    $data['data'] = $db->rawQuery($sql . $where . ' AND `id` > ' . $offset. $order . $position);
    foreach ($data['data'] as $key => $value){
        $data['data'][$key] = songData($value->track_id);
        $data['data'][$key]->is_purchased = true;
    }
    return $data;
}
function GetFollowing($userid,$limit=20,$offset=0){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_FOLLOWERS.'` ';
    $where = '
            WHERE
                `follower_id` = '.secure($userid). '
            AND 
                `following_id` NOT IN (SELECT `blocked_id` FROM `'.T_BLOCKS.'` WHERE `user_id` = '.secure($userid).')
            ';
    $order = ' ORDER BY `id` DESC ';
    $position = ' LIMIT '.$limit.';';
    $data['count'] = count($db->rawQuery($sql . $where));
    $data['data'] = $db->rawQuery($sql . $where . ' AND `id` > ' . $offset. $order . $position);
    foreach ($data['data'] as $key => $value){
        $data['data'][$key] = userData($value->following_id);
        unset($data['data'][$key]->password);
    }
    return $data;
}
function GetAlbums($userid,$limit=20,$offset=0){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_ALBUMS.'` ';
    if($userid !== NULL ) {
        $where = ' WHERE `user_id` = '.secure($userid);
    }else{
        $userid = $music->user->id;
        $where = ' WHERE `user_id` = '.secure($userid);
    }
    $order = ' ORDER BY `id` DESC ';
    $position = ' LIMIT '.$limit.';';
    $data['count'] = count($db->rawQuery($sql . $where));
    $data['data'] = $db->rawQuery($sql . $where . ' AND `id` > ' . $offset . $order . $position);
    foreach ($data['data'] as $key => $value){
        $data['data'][$key] = albumData($value->id, true, true, false);
        unset($data['data'][$key]->songs);
    }
    return $data;
}
function GetPlaylists($userid,$limit=20,$offset=0){
    global $db,$music;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_PLAYLISTS.'` ';
    $where = '';
    if( $userid > 0 ) {
        if ($userid !== $music->user->id) {
            $where = ' WHERE `user_id` = ' . secure($userid) . ' AND privacy = 0';
        } else {
            $where = ' WHERE `user_id` = ' . secure($userid) . '';
        }
    } else {
        $where = ' WHERE id <> 0 AND privacy = 0';
    }
    $order = ' ORDER BY `id` DESC ';
    $position = ' LIMIT '.$limit.';';
    $data['count'] = count($db->rawQuery($sql . $where));
    $data['data'] = $db->rawQuery($sql . $where . ' AND `id` > ' . $offset . $order . $position);
    foreach ($data['data'] as $key => $value){
        $lst = getPlayList($value->id);
        if( $lst !== false ) {
            $data['data'][$key] = $lst;
        }
    }
    return $data;
}
function GetRandomPlaylist(){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_PLAYLISTS.'` ';
    $where = ' WHERE privacy = 0 ';
    $order = ' ORDER BY RAND() ';
    $position = ' LIMIT 1 OFFSET 0;';
    $data = $db->rawQuery($sql . $where . $order . $position);
    foreach ($data as $key => $value){
        $lst = getPlayList($value->id);
        if( $lst !== false ) {
            $data[$key] = $lst;
        }
    }
    return $data;
}
function GetRandomSong(){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_SONGS.'` ';
    $where = ' WHERE availability = 0 ';
    $order = ' ORDER BY RAND() ';
    $position = ' LIMIT 1 OFFSET 0;';
    $data = $db->rawQuery($sql . $where . $order . $position);
    foreach ($data as $key => $value){
        $data = songData($value->id);
    }
    return $data;
}
function GetRandomAlbum(){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_ALBUMS.'` ';
    $where = '  ';
    $order = ' ORDER BY RAND() ';
    $position = ' LIMIT 1 OFFSET 0;';
    $data = $db->rawQuery($sql . $where . $order . $position);
    foreach ($data as $key => $value){
        $data[$key] = albumData($value->id, true, true, false);
        unset($data[$key]->songs);
    }
    return $data;
}
function GetBlocks($userid,$limit=20,$offset=0){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_BLOCKS.'` ';
    $where = ' WHERE `user_id` = '.secure($userid);
    $order = ' ORDER BY `id` DESC ';
    $position = ' LIMIT '.$limit.';';
    $data['count'] = count($db->rawQuery($sql . $where));
    $data['data'] = $db->rawQuery($sql . $where . ' AND `id` > ' . $offset . $order . $position);
    foreach ($data['data'] as $key => $value){
        $song_data = userData($value->blocked_id);
        if($song_data !== false) {
            $data['data'][$key] = $song_data;
        }else{
            unset($data['data'][$key]);
        }
    }
    return $data;
}
function GetFavourites($userid,$limit=20,$offset=0){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_FOV.'` ';
    $where = ' WHERE `user_id` = '.secure($userid);
    $order = ' ORDER BY `id` DESC ';
    $position = ' LIMIT '.$limit.';';
    $data['count'] = count($db->rawQuery($sql . $where));
    $data['data'] = $db->rawQuery($sql . $where . ' AND `id` > ' . $offset . $order . $position);
    foreach ($data['data'] as $key => $value){
        $song_data = songData($value->track_id);
        if($song_data !== false) {
            $data['data'][$key] = $song_data;
        }else{
            unset($data['data'][$key]);
        }
    }
    return $data;
}
function GetLiked($userid,$limit=20,$offset=0){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_LIKES.'` ';
    $where = ' WHERE `user_id` = '.secure($userid);
    $order = ' ORDER BY `id` DESC ';
    $position = ' LIMIT '.$limit.';';
    $data['count'] = count($db->rawQuery($sql . $where));
    $data['data'] = $db->rawQuery($sql . $where . ' AND `id` > ' . $offset . $order . $position);
    foreach ($data['data'] as $key => $value){
        $song_data = songData($value->track_id);
        if($song_data !== false) {
            $data['data'][$key] = $song_data;
        }else{
            unset($data['data'][$key]);
        }
    }
    return $data;
}
function GetArtists($limit=20,$offset=0){
    global $db;
    $data = [];
    if (!empty($offset)) {
        $db->where('id',secure($offset),'<');
    }
    $data['data'] = $db->where('artist',1)->orderBy('id','DESC')->get(T_USERS,$limit);
    $data['count'] = $db->where('artist',1)->getValue(T_USERS,'COUNT(*)');
    foreach ($data['data'] as $key => $value){
        $song_data = userData($value->id);
        if($song_data !== false) {
            unset($song_data->password);
            unset($song_data->email_code);
            $data['data'][$key] = $song_data;
        }else{
            unset($data['data'][$key]);
        }
    }
    return $data;
}
function GetRecentlyPlayed($userid,$limit=20,$offset=0){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_VIEWS.'` ';
    if( $userid !== NULL ) {
        $where = ' WHERE `user_id` = ' . secure($userid);
    }else{
        return [];
    }
    $order = ' GROUP BY track_id ORDER BY `time` DESC ';
    $position = ' LIMIT '.$limit.';';
    $data['count'] = count($db->rawQuery($sql . $where));
    $info = $db->rawQuery($sql . $where . ' AND `id` > ' . $offset . $order . $position);
    foreach ($info as $key => $value){
        $song_data = songData($value->track_id);
        if($song_data !== false) {
            $data['data'][] = $song_data;
        }else{
            // unset($data['data'][$key]);
        }
    }
    return $data;
}
function GetTracksByGenres($genreid,$limit=20,$offset=0){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_SONGS.'` ';
    $where = ' WHERE `category_id` = '.secure($genreid);
    $order = ' ORDER BY `id` DESC ';
    $position = ' LIMIT '.$limit.';';
    $data['count'] = count($db->rawQuery($sql . $where));
    $data['data'] = array();
    $offset_text = '';
    if ($offset > 0) {
        $offset_text = ' AND `id` < ' . $offset;
    }
    $songs = $db->rawQuery($sql . $where . $offset_text . $order . $position);
    foreach ($songs as $key => $value){
        $song_data = songData($value->id);
        if($song_data !== false) {
            $data['data'][] = $song_data;
        }else{
            // unset($data['data'][$key]);
        }
    }
    return $data;
}
function GetCommentByTrackid($trackid,$limit=20,$offset=0){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_COMMENTS.'` ';
    $where = ' WHERE `track_id` = '.secure($trackid);
    $order = ' GROUP BY id ORDER BY `time` DESC ';
    $position = ' LIMIT '.$limit.';';
    $data['count'] = count($db->rawQuery($sql . $where));
    $data['data'] = $db->rawQuery($sql . $where . ' AND `id` > ' . $offset. $order . $position);
    foreach ($data['data'] as $key => $value){
        $song_data = getComment($value->id);
        if($song_data !== false) {
            $data['data'][$key] = $song_data;
        }else{
            unset($data['data'][$key]);
        }
        $data['data'][$key]->userData = userData($data['data'][$key]->user_id);
    }
    return $data;
}
function GetNewReleases($limit=12,$offset=0){
    global $db;
    $data = [];
    $sql   = 'SELECT * FROM `'.T_SONGS.'` ';
    $where = ' WHERE `availability` = 0 ';
    $order = ' ORDER BY `id` DESC ';
    $position = ' LIMIT '.$limit.';';
    $offset_text = '';
    if (!empty($offset) && is_numeric($offset)) {
        $offset_text = ' AND `id` < ' . $offset;
    }
    $data['count'] = count($db->rawQuery($sql . $where));
    $data['data'] = $db->rawQuery($sql . $where . $offset_text . $order . $position);
    foreach ($data['data'] as $key => $value){
        $song_data = songData($value->id);
        if($song_data !== false) {
            $data['data'][$key] = $song_data;
        }else{
            unset($data['data'][$key]);
        }
    }
    return $data;
}
function GetMostPopularWeek(){
    global $db,$user;
    $data = [];
    $time_week = time() - 604800;
    $query = "SELECT " . T_SONGS . ".*, COUNT(" . T_VIEWS . ".id) AS " . T_VIEWS . "
            FROM " . T_SONGS . " LEFT JOIN " . T_VIEWS . " ON " . T_SONGS . ".id = " . T_VIEWS . ".track_id
            WHERE " . T_SONGS . ".time > $time_week AND " . T_SONGS . ".availability = '0'";

    if (IS_LOGGED) {
        $query .= " AND " . T_SONGS . ".user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)";
    }

    $query .= " GROUP BY " . T_SONGS . ".id ORDER BY " . T_VIEWS . " DESC LIMIT 10";

    $getMostWeek = $db->rawQuery($query);
    foreach ($getMostWeek as $key => $value){
        $song_data = songData($value->id);
        if($song_data !== false) {
            $data['data'][$key] = $song_data;
        }else{
            unset($data['data'][$key]);
        }
    }
    return $data;
}
function GetActivities($user_id,$filter = []){
    $data = [];
    $getActivties = getActivties(10, 0, $user_id,$filter);
    foreach ($getActivties as $key => $activity) {
        $data['data'][$key] = getActivity($activity, false);
    }
    $data['count'] = count($data['data']);
    return $data;
}
function GetLatestSongs($user_id){
    global $db;
    $data = [];
    $db->where('user_id', $user_id);
    $getUserSongs = $db->orderby('id', 'DESC')->get(T_SONGS, 10, 'id');
    if (!empty($getUserSongs)) {
        foreach ($getUserSongs as $key => $userSong) {
            $data['data'][$key] = songData($userSong->id);
        }
    }
    $data['count'] = count($data['data']);
    return $data;
}
function GetTopSongs($user_id){
    global $db;
    $data = [];
    $getUserSongs = $getLatestSongs = $db->rawQuery("
        SELECT " . T_SONGS . ".*, COUNT(" . T_VIEWS . ".id) AS " . T_VIEWS . "
        FROM " . T_SONGS . " LEFT JOIN " . T_VIEWS . " ON " . T_SONGS . ".id = " . T_VIEWS . ".track_id
        WHERE " . T_SONGS . ".user_id = " . $user_id . "
        GROUP BY " . T_SONGS . ".id
        ORDER BY " . T_VIEWS . " DESC LIMIT 20");
    if (!empty($getUserSongs)) {
        foreach ($getUserSongs as $key => $userSong) {
            $data['data'][$key] = songData($userSong->id);
        }
    }
    $data['count'] = count($data['data']);
    return $data;
}
function GetStore($user_id){
    global $db;
    $data = [];
    $getUserSongs = $db->where('user_id', $user_id)->where('price', '0', '<>')->orderBy('id', 'DESC')->get(T_SONGS, 10);
    if (!empty($getUserSongs)) {
        foreach ($getUserSongs as $key => $userSong) {
            $data['data'][$key] = songData($userSong->id);
        }
    }
    $data['count'] = 0;
    if (!empty($data['data'])) {
        $data['count'] = count($data['data']);
    }
    return $data;
}
function GetTotalTopSong($limit=12,$offset=0){
    global $db;
    $data = [];
    $position = ' LIMIT '.$limit.';';
    $having = "";
    if (!empty($offset)) {
        $having = " HAVING " . T_VIEWS . " < '".$offset."' ";
    }
    $getUserSongs = $getLatestSongs = $db->rawQuery("
        SELECT " . T_SONGS . ".*, COUNT(" . T_VIEWS . ".id) AS " . T_VIEWS . "
        FROM " . T_SONGS . " LEFT JOIN " . T_VIEWS . " ON " . T_SONGS . ".id = " . T_VIEWS . ".track_id
        GROUP BY " . T_SONGS . ".id ".$having." ORDER BY " . T_VIEWS . " DESC ".$position);
    if (!empty($getUserSongs)) {
        foreach ($getUserSongs as $key => $userSong) {
            $data['data'][$key] = songData($userSong->id);
        }
    }
    $data['count'] = count($data['data']);
    return $data;
}
function GetTotalTopAlbum($limit = 50,$album_ids = '',$album_views = ''){
    global $db,$music;
    $data = [];
    if (!empty($album_ids) && !empty($album_views)) {
        $query = "SELECT " . T_ALBUMS . ".*, COUNT(" . T_VIEWS . ".id) AS " . T_VIEWS . "
                FROM " . T_ALBUMS . " LEFT JOIN " . T_VIEWS . " ON " . T_ALBUMS . ".id = " . T_VIEWS . ".album_id WHERE " . T_ALBUMS . ".id NOT IN (".implode(',', $album_ids).")";
        $query .= " GROUP BY " . T_ALBUMS . ".id HAVING " . T_VIEWS . " <= ".$album_views." ORDER BY " . T_VIEWS . " DESC LIMIT ".$limit;
    }
    else{
        $query = "SELECT " . T_ALBUMS . ".*, COUNT(" . T_VIEWS . ".id) AS " . T_VIEWS . "
                    FROM " . T_ALBUMS . " LEFT JOIN " . T_VIEWS . " ON " . T_ALBUMS . ".id = " . T_VIEWS . ".album_id";
        $query .= " GROUP BY " . T_ALBUMS . ".id ORDER BY " . T_VIEWS . " DESC LIMIT ".$limit;
    }
        
    $top_first_albums = $db->rawQuery($query);
    if (!empty($top_first_albums)) {
        foreach ($top_first_albums as $key => $album) {
            $album_data = albumData($album->id, true, true, false);
            $album_data->views = $album->views;
            $data['data'][$key] = $album_data;
        }
    }
    $data['count'] = count($data['data']);
    return $data;
}