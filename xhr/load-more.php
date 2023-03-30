<?php
if (empty($_REQUEST["id"])) {
    exit("No ID sent, what to load?");
}
if (!is_numeric($_REQUEST["id"])) {
    exit("ID is not numaric, hmm?");
}
$id = secure($_REQUEST["id"]);
$user_id = 0;
if (!empty($_REQUEST["userID"])) {
    if (is_numeric($_REQUEST["userID"])) {
        $user_id = secure($_REQUEST["userID"]);
    }
}
if ($option == "songs") {
    $get_data = !empty($_REQUEST["get_data"])
        ? secure($_REQUEST["get_data"])
        : "songs";
    if ($get_data == "songs") {
        $db->where("id", $id, "<");
        if (!empty($user_id)) {
            $db->where("user_id", secure($_REQUEST["userID"]));
        }
        if (!IS_LOGGED) {
            $db->where("availability", "0");
        } else {
            $db->where(
                "user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)"
            );
            if ($user->id != $user_id) {
                $db->where("availability", "0");
            }
        }
        $getUserSongs = $db->orderby("id", "DESC")->get(T_SONGS, 20);
    } elseif ($get_data == "top-songs") {
        $getLastSongView = $db
            ->where("track_id", $id)
            ->getValue(T_VIEWS, "count(*)");
        $query =
            "SELECT " .
            T_SONGS .
            ".*, COUNT(" .
            T_VIEWS .
            ".id) AS " .
            T_VIEWS .
            " FROM " .
            T_SONGS .
            " LEFT JOIN " .
            T_VIEWS .
            " ON " .
            T_SONGS .
            ".id = " .
            T_VIEWS .
            ".track_id WHERE " .
            T_SONGS .
            ".id <> $id";
        if (!empty($user_id)) {
            $query .= " AND " . T_SONGS . ".user_id = " . $user_id;
        }
        if (!IS_LOGGED) {
            $query .= " AND availability = 0";
        } else {
            if ($user->id != $user_id) {
                $query .= " AND availability = 0";
            }
        }
        $query .=
            " GROUP BY " .
            T_SONGS .
            ".id HAVING $getLastSongView >= views ORDER BY " .
            T_VIEWS .
            " DESC LIMIT 20";
        $getUserSongs = $db->rawQuery($query);
    } elseif ($get_data == "store") {
        $text = "`id` < '" . $id . "' ";
        if (!empty($user_id)) {
            $text .=
                " AND (`track_id` IN (SELECT `id` FROM `" .
                T_SONGS .
                "` WHERE `user_id` = '" .
                $user_id .
                "' AND `price` <> '0') OR `product_id` IN (SELECT `id` FROM `" .
                T_PRODUCTS .
                "` WHERE `user_id` = '" .
                $user_id .
                "')) AND `user_id` = '" .
                $user_id .
                "' ";
        }
        if (IS_LOGGED) {
            $text .= " AND `user_id` NOT IN (SELECT `user_id` FROM blocks WHERE `blocked_id` = '$user->id') ";
        }
        $getUserSongs = $db->rawQuery(
            "SELECT * FROM `" .
                T_ACTIVITIES .
                "` WHERE " .
                $text .
                " AND (`type` = 'uploaded_track' || `type` = 'created_product') ORDER BY `id` DESC LIMIT 10;"
        );
    }
    $html = "";
    if (!empty($getUserSongs)) {
        foreach ($getUserSongs as $key => $userSong) {
            if (!empty($userSong->id)) {
                $a_id = $userSong->id;
            }
            if (!empty($userSong->audio_id)) {
                $userSong = songData($userSong->id);
                $music->a_type = "uploaded_track";
                if (!empty($a_id) && !empty($userSong)) {
                    $userSong->songArray["a_id"] = $a_id;
                }
            } else {
                $userSong = GetProduct($userSong->product_id);
                $userSong->publisher = $userSong->user_data;
                $userSong->songArray = [
                    "s_id" => $userSong->id,
                    "USER_DATA" => $userSong->user_data,
                    "s_time" => time_Elapsed_String($userSong->time),
                    "s_name" => $userSong->title,
                    "s_duration" =>
                        $music->config->currency_symbol . $userSong->price,
                    "s_thumbnail" => $userSong->images[0]["image"],
                    "s_url" => $userSong->url,
                    "s_audio_id" => "",
                    "s_price" => $userSong->price,
                    "s_category" =>
                        $music->products_categories[$userSong->cat_id],
                ];
                $music->a_type = "created_product";
                if (!empty($a_id) && !empty($userSong)) {
                    $userSong->songArray["a_id"] = $a_id;
                }
            }
            $music->isSongOwner = false;
            if (!empty($userSong)) {
                if (IS_LOGGED == true) {
                    $music->isSongOwner =
                        $user->id == $userSong->publisher->id ? true : false;
                }
                $music->songData = $userSong;
                $html .= loadPage("user/posts", $userSong->songArray);
            }
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "activities") {
    $filterBy = [];
    if (!empty($_REQUEST["get_data"])) {
        if ($_REQUEST["get_data"] == "liked") {
            $filterBy["likes"] = true;
        }
        if ($_REQUEST["get_data"] == "spotlight") {
            $filterBy["spotlight"] = true;
        }
        if ($_REQUEST["get_data"] == "events") {
            $filterBy["events"] = true;
        }
    }
    $getActivities = getActivties(10, $id, $user_id, $filterBy);
    if (!empty($_REQUEST["get_data"]) && $_REQUEST["get_data"] == "spotlight") {
        $getActivities = getSpotlights(10, $id);
    }
    $html = "";
    if (!empty($getActivities)) {
        $html = "";
        foreach ($getActivities as $key => $activity) {
            if (
                !empty($_REQUEST["get_data"]) &&
                $_REQUEST["get_data"] == "spotlight"
            ) {
                $getActivity = getSpotlight($activity, false);
            } else {
                $getActivity = getActivity($activity, false);
            }
            $html .= loadPage("user/activity", $getActivity);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "latest_music") {
    $db->where("id", $id, "<")->where("availability", 0);
    if (IS_LOGGED) {
        $db->where(
            "user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)"
        );
    }
    $getNewRelease = $db->orderby("id", "DESC")->get(T_SONGS, 21);
    $html = "";
    if (!empty($getNewRelease)) {
        foreach ($getNewRelease as $key => $song) {
            $songData = songData($song, false, false);
            $html .= loadPage("discover/recently-list", [
                "url" => $songData->url,
                "title" => $songData->title,
                "thumbnail" => $songData->thumbnail,
                "id" => $songData->id,
                "audio_id" => $songData->audio_id,
                "USER_DATA" => $songData->publisher,
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "albums") {
    $db->where("id", $id, "<");
    if (!empty($user_id)) {
        $db->where("user_id", secure($_REQUEST["userID"]));
    }
    if (IS_LOGGED) {
        $db->where(
            "user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)"
        );
    }
    $html = "";
    $getAlbums = $db->orderBy("id", "DESC")->get(T_ALBUMS, 10);
    if (!empty($getAlbums)) {
        $html = "";
        foreach ($getAlbums as $key => $album) {
            $key = $key + 1;
            $html .= loadPage("user/album-list", [
                "url" => getLink("album/$album->album_id"),
                "title" => $album->title,
                "thumbnail" => getMedia($album->thumbnail),
                "id" => $album->id,
                "album_id" => $album->album_id,
                "USER_DATA" => userData($album->user_id),
                "key" => $key,
                "songs" => $db
                    ->where("album_id", $album->id)
                    ->getValue(T_SONGS, "COUNT(*)"),
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "playlists") {
    $db->where("id", $id, "<");
    if (!empty($user_id)) {
        $db->where("user_id", secure($_REQUEST["userID"]));
    }
    if (IS_LOGGED) {
        $db->where(
            "user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)"
        );
        if (!empty($user_id)) {
            if ($user_id == $user->id) {
                $db->where("privacy", 0);
            }
        }
    }
    $html = "";
    $getPlayLists = $db->orderBy("id", "DESC")->get(T_PLAYLISTS, 20);
    if (!empty($getPlayLists)) {
        foreach ($getPlayLists as $key => $playlist) {
            $playlist = getPlayList($playlist, false);
            $html .= loadPage("user/playlist-list", [
                "t_thumbnail" => $playlist->thumbnail_ready,
                "t_id" => $playlist->id,
                "s_artist" => $playlist->publisher->name,
                "t_uid" => $playlist->uid,
                "t_title" => $playlist->name,
                "t_privacy" => $playlist->privacy_text,
                "t_url" => $playlist->url,
                "t_url_original" => $playlist->url,
                "t_songs" => $playlist->songs,
                "USER_DATA" => $playlist->publisher,
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "recently_played") {
    if (IS_LOGGED == false) {
        exit("You ain't logged in!");
    }
    $db->where("time", $id, "<");
    if (!empty($_SESSION["fingerPrint"])) {
        $db->where("fingerprint", secure($_SESSION["fingerPrint"]));
    } else {
        $db->where("user_id", secure($user->id));
    }
    $getRecentPlayes = $db
        ->groupBy("track_id")
        ->orderby("time", "DESC")
        ->get(T_VIEWS, 20);
    $html = "";
    if (!empty($getRecentPlayes)) {
        $html = "";
        foreach ($getRecentPlayes as $key => $song) {
            $songData = songData($song->track_id);
            if (!empty($songData)) {
                $html .= loadPage("recently_played/list", [
                    "t_thumbnail" => $songData->thumbnail,
                    "t_id" => $songData->id,
                    "t_title" => $songData->title,
                    "t_artist" => $songData->publisher->name,
                    "t_url" => $songData->url,
                    "t_artist_url" => $songData->publisher->url,
                    "t_audio_id" => $songData->audio_id,
                    "t_time" => $song->time,
                ]);
            }
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "categories") {
    if (IS_LOGGED == false) {
        exit("You ain't logged in!");
    }
    if (IS_LOGGED) {
        $db->where(
            "user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)"
        );
    }
    $db->where("id", $id, "<");
    $category_id = !empty($_REQUEST["get_data"])
        ? secure($_REQUEST["get_data"])
        : "0";
    $db->where("category_id", $category_id);
    $getSongs = $db->orderby("id", "DESC")->get(T_SONGS, 30);
    $html = "";
    if (!empty($getSongs)) {
        $html = "";
        foreach ($getSongs as $key => $song) {
            $key = "";
            $songData = songData($song, false, false);
            $html .= loadPage("discover/recommended-list", [
                "url" => $songData->url,
                "title" => $songData->title,
                "thumbnail" => $songData->thumbnail,
                "id" => $songData->id,
                "audio_id" => $songData->audio_id,
                "USER_DATA" => $songData->publisher,
                "key" => $key,
                "fav_button" => getFavButton($songData->id, "fav-icon"),
                "duration" => $songData->duration,
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "blog_comments") {
    if (IS_LOGGED == false) {
        exit("You ain't logged in!");
    }
    $getSong = $db->where("id", secure($_REQUEST["track_id"]))->getOne(T_BLOG);
    $getSongComments = $db
        ->where("id", $id, "<")
        ->where("article_id", $getSong->id)
        ->orderBy("id", "DESC")
        ->get(T_BLOG_COMMENTS, 20);
    $comment_html = "";
    if (!empty($getSongComments)) {
        foreach ($getSongComments as $key => $comment) {
            $comment = getComment($comment, false);
            $commentUser = userData($comment->user_id);
            $music->comment = $comment;
            $comment_html .= loadPage("blogs/comment-list", [
                "comment_id" => $comment->id,
                "USER_DATA" => $commentUser,
                "comment_text" => $comment->value,
                "comment_posted_time" => $comment->time,
                "tcomment_posted_time" => date("c", $comment->time),
                "comment_song_article_id" => secure($_REQUEST["track_id"]),
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $comment_html;
}
if ($option == "comments") {
    if (IS_LOGGED == false) {
        exit("You ain't logged in!");
    }
    $getSong = $db
        ->where("audio_id", secure($_REQUEST["track_id"]))
        ->getOne(T_SONGS);
    if (IS_LOGGED) {
        $db->where(
            "user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)"
        );
    }
    $getSongComments = $db
        ->where("id", $id, "<")
        ->where("track_id", $getSong->id)
        ->orderBy("id", "DESC")
        ->get(T_COMMENTS, 20);
    $comment_html = "";
    if (!empty($getSongComments)) {
        foreach ($getSongComments as $key => $comment) {
            $comment = getComment($comment, false);
            $commentUser = userData($comment->user_id);
            $music->comment = $comment;
            $comment_html .= loadPage("track/comment-list", [
                "comment_id" => $comment->id,
                "comment_seconds" => $comment->songseconds,
                "comment_percentage" => $comment->songpercentage,
                "USER_DATA" => $commentUser,
                "comment_text" => $comment->value,
                "comment_posted_time" => $comment->posted,
                "tcomment_posted_time" => date(
                    "c",
                    strtotime($comment->posted)
                ),
                "comment_seconds_formatted" => $comment->secondsFormated,
                "comment_song_id" => $getSong->audio_id,
                "comment_song_track_id" => $comment->track_id,
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $comment_html;
}
if ($option == "followers") {
    $html = "";
    $db->where("id", $id, "<");
    if (IS_LOGGED) {
        $db->where(
            "follower_id NOT IN (SELECT blocked_id FROM blocks WHERE user_id = $user->id)"
        );
    }
    $getFollowers = $db
        ->where("following_id", $user_id)
        ->orderBy("id", "DESC")
        ->get(T_FOLLOWERS, 9);
    if (!empty($getFollowers)) {
        foreach ($getFollowers as $key => $follower) {
            $key = $key + 1;
            $html .= loadPage("user/follower-list", [
                "f_id" => $follower->id,
                "USER_DATA" => userData($follower->follower_id),
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "followings") {
    $html = "";
    $db->where("id", $id, "<");
    if (IS_LOGGED) {
        $db->where(
            "following_id NOT IN (SELECT blocked_id FROM blocks WHERE user_id = $user->id)"
        );
    }
    $getFollowings = $db
        ->where("follower_id", $user_id)
        ->orderBy("id", "DESC")
        ->get(T_FOLLOWERS, 9);
    if (!empty($getFollowings)) {
        $html = "";
        foreach ($getFollowings as $key => $following) {
            $key = $key + 1;
            $html .= loadPage("user/following-list", [
                "f_id" => $following->id,
                "USER_DATA" => userData($following->following_id),
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "songs_search") {
    $search_keyword = !empty($_REQUEST["get_data"])
        ? secure($_REQUEST["get_data"])
        : "";
    $results = $db->rawQuery(
        "SELECT * FROM `" .
            T_SONGS .
            "` WHERE `id` < " .
            $id .
            " AND (`title` LIKE '%" .
            $search_keyword .
            "%' OR `description` LIKE '%" .
            $search_keyword .
            "%') ORDER BY `id` DESC LIMIT 10;"
    );
    $html = "";
    foreach ($results as $song) {
        $pagedata = ["SONG_DATA" => songData($song->id)];
        $html = loadPage("search/song-list", $pagedata);
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "artists_search") {
    $search_keyword = !empty($_REQUEST["get_data"])
        ? secure($_REQUEST["get_data"])
        : "";
    $results = $db->rawQuery(
        "SELECT * FROM `" .
            T_USERS .
            "` WHERE `id` < " .
            $id .
            " AND (`name` LIKE '%" .
            $search_keyword .
            "%' OR `username` LIKE '%" .
            $search_keyword .
            "%') AND `artist` = 1 ORDER BY `id` DESC LIMIT 10;"
    );
    $html = "";
    foreach ($results as $artists) {
        $pagedata = ["ARTIST_DATA" => userData($artists->id)];
        $html = loadPage("search/artists-list", $pagedata);
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "albums_search") {
    $search_keyword = !empty($_REQUEST["get_data"])
        ? secure($_REQUEST["get_data"])
        : "";
    $results = $db->rawQuery(
        "SELECT * FROM `" .
            T_ALBUMS .
            "` WHERE `id` < " .
            $id .
            " AND (`title` LIKE '%" .
            $search_keyword .
            "%' OR `description` LIKE '%" .
            $search_keyword .
            "%') ORDER BY `id` DESC LIMIT 10;"
    );
    $html = "";
    foreach ($results as $album) {
        $pagedata = [
            "ALBUM_DATA" => $album,
            "PUBLISHER_DATA" => userData($album->user_id),
            "thumbnail" => GetMedia($album->thumbnail),
        ];
        $html = loadPage("search/albums-list", $pagedata);
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "playlists_search") {
    $search_keyword = !empty($_REQUEST["get_data"])
        ? secure($_REQUEST["get_data"])
        : "";
    $results = $db->rawQuery(
        "SELECT * FROM `" .
            T_PLAYLISTS .
            "` WHERE `id` < " .
            $id .
            " AND (`name` LIKE '%" .
            $search_keyword .
            "%' AND `privacy` = 0 ) ORDER BY `id` DESC LIMIT 10;"
    );
    $html = "";
    foreach ($results as $key => $playlists) {
        $playlist = getPlayList($playlists->id);
        $html = loadPage("user/playlist-list", [
            "t_id" => $playlist->id,
            "t_uid" => $playlist->uid,
            "t_title" => $playlist->name,
            "t_songs" => $playlist->songs,
            "t_url" => $playlist->url,
            "USER_DATA" => userData($playlist->user_id),
            "t_thumbnail" => GetMedia($playlist->thumbnail),
        ]);
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "products_search") {
    $search_keyword = !empty($_REQUEST["get_data"])
        ? secure($_REQUEST["get_data"])
        : "";
    $text = "";
    if (!empty($search_keyword)) {
        $text = " AND (`title` LIKE '%$search_keyword%' OR `desc` LIKE '%$search_keyword%') ";
    }
    if (!empty($_REQUEST["params"])) {
        if (
            !empty($_REQUEST["params"]["geners"]) &&
            is_array($_REQUEST["params"]["geners"])
        ) {
            $category = $_REQUEST["params"]["geners"];
            $text .=
                " AND (`cat_id` IN ('" . implode("','", $category) . "')) ";
        }
    }
    if (!empty($_REQUEST["params"])) {
        if (!empty($_REQUEST["params"]["current_tag"])) {
            $current_tag = $_REQUEST["params"]["current_tag"];
            $text = " AND (`tags` LIKE '%$current_tag%') ";
        }
    }
    if (!empty($_REQUEST["params"])) {
        if (
            !empty($_REQUEST["params"]["price"]) &&
            is_array($_REQUEST["params"]["price"])
        ) {
            $price_from =
                !empty($_REQUEST["params"]["price"][0]) &&
                is_numeric($_REQUEST["params"]["price"][0])
                    ? $_REQUEST["params"]["price"][0]
                    : 1;
            $price_to =
                !empty($_REQUEST["params"]["price"][1]) &&
                is_numeric($_REQUEST["params"]["price"][1])
                    ? $_REQUEST["params"]["price"][1]
                    : 10000;
            $text .=
                " AND (`price` BETWEEN " .
                $price_from .
                " AND " .
                $price_to .
                ") ";
        }
    }
    $results = $db->rawQuery(
        "SELECT * FROM `" .
            T_PRODUCTS .
            "` WHERE `id` < " .
            $id .
            $text .
            " ORDER BY `id` DESC LIMIT 10;"
    );
    $html = "";
    foreach ($results as $key => $value) {
        $music->product = GetProduct($value->id);
        if (!empty($music->product)) {
            $html .= loadPage("store/product_list", [
                "id" => $music->product->id,
                "url" => $music->product->url,
                "data_load" => $music->product->data_load,
                "image" => $music->product->images[0]["image"],
                "title" => $music->product->title,
                "rating" => $music->product->rating,
                "f_price" => $music->product->formatted_price,
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "orders") {
    $db->where("id", $id, "<");
    $music->orders = $db
        ->where("product_owner_id", $music->user->id)
        ->orderBy("id", "DESC")
        ->groupBy("hash_id")
        ->get(T_ORDERS, 10);
    $html = "";
    if (!empty($music->orders)) {
        foreach ($music->orders as $key => $order) {
            $count = $db
                ->where("hash_id", $order->hash_id)
                ->getValue(T_ORDERS, "count(*)");
            $items_count = $db
                ->where("hash_id", $order->hash_id)
                ->getValue(T_ORDERS, "sum(units)");
            $price = $db
                ->where("hash_id", $order->hash_id)
                ->getValue(T_ORDERS, "sum(price)");
            $status = $db
                ->where("hash_id", $order->hash_id)
                ->getValue(T_ORDERS, "status");
            $html .= loadPage("orders/list", [
                "hash" => $order->hash_id,
                "ID" => $order->id,
                "url" => getLink("order/" . $order->hash_id),
                "count" => $count,
                "price" => number_format($price),
                "items_count" => $items_count,
                "status" => $status,
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "events_search") {
    $search_keyword = !empty($_REQUEST["get_data"])
        ? secure($_REQUEST["get_data"])
        : "";
    $results = $db->rawQuery(
        "SELECT * FROM `" .
            T_EVENTS .
            "` WHERE `id` < " .
            $id .
            " AND (`name` LIKE '%" .
            $search_keyword .
            "%' OR `desc` LIKE '%" .
            $search_keyword .
            "%' ) ORDER BY `id` DESC LIMIT 10;"
    );
    $html = "";
    foreach ($results as $key => $value) {
        $event = $music->event = GetEventById($value->id);
        $music->is_joined = $db
            ->where("event_id", $event->id)
            ->where("user_id", $music->user->id)
            ->getValue(T_EVENTS_JOINED, "COUNT(*)");
        $html .= loadPage("events/list", [
            "ID" => $event->id,
            "URL" => $event->url,
            "DATA_LOAD" => $event->data_load,
            "NAME" => $event->name,
            "START_DATE" => $event->start_date,
            "IMAGE" => $event->image,
        ]);
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "events") {
    $query_text = "";

    $results = $db->rawQuery(
        "SELECT * FROM `" .
            T_EVENTS .
            "` WHERE `id` < '" .
            $id .
            "' AND `end_date` >= CURDATE() ORDER BY `id` DESC LIMIT 10;"
    );
    $html = "";
    foreach ($results as $key => $value) {
        $event = $music->event = GetEventById($value->id);
        $music->is_joined = 0;
        if (IS_LOGGED) {
            $music->is_joined = $db
                ->where("event_id", $event->id)
                ->where("user_id", $music->user->id)
                ->getValue(T_EVENTS_JOINED, "COUNT(*)");
        }
        $html .= loadPage("events/list", [
            "ID" => $event->id,
            "URL" => $event->url,
            "DATA_LOAD" => $event->data_load,
            "NAME" => $event->name,
            "START_DATE" => $event->start_date,
            "IMAGE" => $event->image,
        ]);
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "manage_events") {
    $results = $db->rawQuery(
        "SELECT * FROM `" .
            T_EVENTS .
            "` WHERE `user_id` = '" .
            $music->user->id .
            "' AND `id` < " .
            $id .
            " ORDER BY `id` DESC LIMIT 10;"
    );
    $html = "";
    foreach ($results as $key => $value) {
        $event = $music->event = GetEventById($value->id);
        $music->is_joined = $db
            ->where("event_id", $event->id)
            ->where("user_id", $music->user->id)
            ->getValue(T_EVENTS_JOINED, "COUNT(*)");
        $html .= loadPage("manage_events/list", [
            "ID" => $event->id,
            "URL" => $event->url,
            "DATA_LOAD" => $event->data_load,
            "EDIT_URL" => $event->edit_url,
            "EDIT_DATA_LOAD" => $event->edit_data_load,
            "NAME" => $event->name,
            "START_DATE" => $event->start_date,
            "IMAGE" => $event->image,
        ]);
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "joined_events") {
    $results = $db->rawQuery(
        "SELECT * FROM `" .
            T_EVENTS_JOINED .
            "` WHERE `event_id` < " .
            $id .
            " AND `user_id` = '" .
            $music->user->id .
            "' ORDER BY `event_id` DESC LIMIT 10;"
    );
    $html = "";
    foreach ($results as $key => $value) {
        $event = $music->event = GetEventById($value->event_id);
        $music->is_joined = $db
            ->where("event_id", $event->id)
            ->where("user_id", $music->user->id)
            ->getValue(T_EVENTS_JOINED, "COUNT(*)");
        $html .= loadPage("events/list", [
            "ID" => $event->id,
            "URL" => $event->url,
            "DATA_LOAD" => $event->data_load,
            "NAME" => $event->name,
            "START_DATE" => $event->start_date,
            "IMAGE" => $event->image,
        ]);
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "purchased_events") {
    $results = $db->rawQuery(
        "SELECT * FROM `" .
            T_PURCHAES .
            "` WHERE `id` < " .
            $id .
            " ORDER BY `id` DESC LIMIT 10;"
    );
    $html = "";
    foreach ($results as $key => $value) {
        $event = $music->event = GetEventById($value->event_id);
        $music->is_joined = $db
            ->where("event_id", $event->id)
            ->where("user_id", $music->user->id)
            ->getValue(T_EVENTS_JOINED, "COUNT(*)");
        $html .= loadPage("purchased_tickets/list", [
            "ID" => $event->id,
            "URL" => $event->url,
            "DATA_LOAD" => $event->data_load,
            "T_ID" => $value->id,
            "NAME" => $event->name,
            "START_DATE" => $event->start_date,
            "IMAGE" => $event->image,
        ]);
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "store_albums") {
    $db->where("id", $id, "<");
    if (!empty($user_id)) {
        $db->where("user_id", secure($_REQUEST["userID"]));
    }
    if (IS_LOGGED) {
        $db->where(
            "user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)"
        );
    }
    $html = "";

    $price_from = 0.99;
    $price_to = 40;
    $categories = "";
    if (isset($_REQUEST["params"])) {
        if (isset($_REQUEST["params"]["price"])) {
            if (isset($_REQUEST["params"]["price"][0])) {
                $price_from = (int) $_REQUEST["params"]["price"][0];
            }
            if (isset($_REQUEST["params"]["price"][1])) {
                $price_to = (int) $_REQUEST["params"]["price"][1];
            }
            $db->where("price", [$price_from, $price_to], "BETWEEN");
        }
        if (
            isset($_REQUEST["params"]["geners"]) &&
            !empty($_REQUEST["params"]["geners"])
        ) {
            $categories = implode(",", $_REQUEST["params"]["geners"]);
            $db->where("category_id", $_REQUEST["params"]["geners"], "IN");
        }
        $data["price_from"] = $price_from;
        $data["price_to"] = $price_to;
        $data["categories"] = $categories;
    }
    $db->orderBy("id", "DESC");
    $getAlbums = $db->get(T_ALBUMS, 10);
    if (!empty($getAlbums)) {
        $records = count($getAlbums);
        foreach ($getAlbums as $key => $album) {
            if (!empty($album)) {
                $publisher = userData($album->user_id);
                $html .= loadPage("store/albums", [
                    "id" => $album->id,
                    "album_id" => $album->album_id,
                    "user_id" => $album->user_id,
                    "artist" => $publisher->name,
                    "title" => $album->title,
                    "description" => $album->description,
                    "category_id" => $album->category_id,
                    "thumbnail" => getMedia($album->thumbnail),
                    "time" => $album->time,
                    "registered" => $album->registered,
                    "price" => $album->price,
                    "songs" => number_format_mm(
                        $db
                            ->where("album_id", $album->id)
                            ->getValue(T_SONGS, "count(*)")
                    ),
                    "artist_name" => $publisher->name,
                ]);
            }
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "reviews") {
    if (
        empty($_GET["product_id"]) ||
        !is_numeric($_GET["product_id"]) ||
        $_GET["product_id"] < 1
    ) {
        exit("product_id missing");
    }
    $music->reviews = $db
        ->where("product_id", secure($_GET["product_id"]))
        ->where("id", $id, "<")
        ->orderBy("id", "DESC")
        ->get(T_REVIEW, 10);
    $reviews_html = "";
    $one = 0;
    $two = 0;
    $three = 0;
    $four = 0;
    $five = 0;
    if (!empty($music->reviews)) {
        foreach ($music->reviews as $key => $value) {
            $review_class = "five_star";
            $review_stars = "5 ★★★★★";
            if ($value->star == 1) {
                $review_class = "one_star";
                $review_stars = "1 ★";
                $one += $value->star;
            } elseif ($value->star == 2) {
                $review_stars = "2 ★★";
                $review_class = "two_star";
                $two += $value->star;
            } elseif ($value->star == 3) {
                $review_stars = "3 ★★★";
                $review_class = "three_star";
                $three += $value->star;
            } elseif ($value->star == 4) {
                $review_stars = "4 ★★★★";
                $review_class = "four_star";
                $four += $value->star;
            } else {
                $five += $value->star;
            }
            $music->review = GetReview($value->id);
            $reviews_html .= loadPage("product/review", [
                "id" => $music->review->id,
                "avatar" => $music->review->user_data->avatar,
                "name" => $music->review->user_data->name,
                "username" => $music->review->user_data->username,
                "review_stars" => $review_stars,
                "review_class" => $review_class,
                "review_class" => $review_class,
                "desc" => $music->review->review,
                "time" => time_Elapsed_String($music->review->time),
                "user_url" => $music->review->user_data->url,
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $reviews_html;
}
if ($option == "manage_products") {
    $html = "";
    $music->products = $db
        ->where("user_id", $music->user->id)
        ->where("active", 1)
        ->where("id", $id, "<")
        ->orderBy("id", "DESC")
        ->get(T_PRODUCTS, 10, ["id"]);
    if (!empty($music->products)) {
        foreach ($music->products as $key => $value) {
            $music->product = GetProduct($value->id);
            $html .= loadPage("manage-products/list", [
                "id" => $music->product->id,
                "url" => $music->product->url,
                "data_load" => $music->product->data_load,
                "image" => $music->product->images[0]["image"],
                "title" => $music->product->title,
                "rating" => $music->product->rating,
                "edit_url" => $music->product->edit_url,
                "edit_data_load" => $music->product->edit_data_load,
                "f_price" => $music->product->formatted_price,
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "products") {
    $db->where("id", $id, "<");
    if (IS_LOGGED) {
        $db->where(
            "user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)"
        );
    }
    $html = "";
    $db->where("price", 0, ">");
    $db->where("id", $id, "<");
    $price_from = 1;
    $price_to = 10000;
    $categories = "";
    if (isset($_REQUEST["params"])) {
        if (isset($_REQUEST["params"]["price"])) {
            if (isset($_REQUEST["params"]["price"][0])) {
                $price_from = (int) $_REQUEST["params"]["price"][0];
            }
            if (isset($_REQUEST["params"]["price"][1])) {
                $price_to = (int) $_REQUEST["params"]["price"][1];
            }
            $db->where("price", [$price_from, $price_to], "BETWEEN");
        }
        if (
            isset($_REQUEST["params"]["geners"]) &&
            !empty($_REQUEST["params"]["geners"])
        ) {
            $categories = implode(",", $_REQUEST["params"]["geners"]);
            $db->where("cat_id", $_REQUEST["params"]["geners"], "IN");
        }
        $data["price_from"] = $price_from;
        $data["price_to"] = $price_to;
        $data["categories"] = $categories;
    }
    $db->orderBy("id", "DESC");
    $products = $db->get(T_PRODUCTS, 10);
    $html_list = "";
    if (!empty($products)) {
        $records = count($products);
        $html_list = "";
        foreach ($products as $key => $value) {
            $music->product = GetProduct($value->id);
            if (!empty($music->product)) {
                $html_list .= loadPage("store/product_list", [
                    "id" => $music->product->id,
                    "url" => $music->product->url,
                    "data_load" => $music->product->data_load,
                    "image" => $music->product->images[0]["image"],
                    "title" => $music->product->title,
                    "rating" => $music->product->rating,
                    "f_price" => $music->product->formatted_price,
                ]);
            }
        }
    }
    $data["status"] = 200;
    $data["html"] = $html_list;
}
if ($option == "store_songs") {
    $db->where("id", $id, "<");
    if (!empty($user_id)) {
        $db->where("user_id", secure($_REQUEST["userID"]));
    }
    if (IS_LOGGED) {
        $db->where(
            "user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)"
        );
    }
    $html = "";
    $db->where("price", 0, ">");
    $db->where("album_id", 0);
    $db->where("id", $id, "<");
    $price_from = 0.99;
    $price_to = 40;
    $categories = "";
    if (isset($_REQUEST["params"])) {
        if (isset($_REQUEST["params"]["price"])) {
            if (isset($_REQUEST["params"]["price"][0])) {
                $price_from = (int) $_REQUEST["params"]["price"][0];
            }
            if (isset($_REQUEST["params"]["price"][1])) {
                $price_to = (int) $_REQUEST["params"]["price"][1];
            }
            $db->where("price", [$price_from, $price_to], "BETWEEN");
        }
        if (
            isset($_REQUEST["params"]["geners"]) &&
            !empty($_REQUEST["params"]["geners"])
        ) {
            $categories = implode(",", $_REQUEST["params"]["geners"]);
            $db->where("category_id", $_REQUEST["params"]["geners"], "IN");
        }
        $data["price_from"] = $price_from;
        $data["price_to"] = $price_to;
        $data["categories"] = $categories;
    }
    $db->orderBy("id", "DESC");
    $getSongs = $db->get(T_SONGS, 10);
    if (!empty($getSongs)) {
        $records = count($getSongs);
        foreach ($getSongs as $key => $song) {
            $songData = songData($song, false, false);
            if (!empty($songData)) {
                $music->songData = $songData;
                $html .= loadPage("store/song-list", [
                    "t_thumbnail" => $songData->thumbnail,
                    "t_id" => $songData->id,
                    "t_title" => $songData->title,
                    "t_artist" => $songData->publisher->name,
                    "t_uartist" => $songData->publisher->username,
                    "t_url" => $songData->url,
                    "t_artist_url" => $songData->publisher->url,
                    "t_price" => $songData->price,
                    "t_audio_id" => $songData->audio_id,
                    "t_duration" => $songData->duration,
                    "t_posted" => $songData->time_formatted,
                    "t_key" => $key + 1,
                ]);
            }
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "blogs") {
    $sql = "SELECT * FROM `" . T_BLOG . "` WHERE `id` < " . $id . " ";
    if (
        isset($_REQUEST["params"]["filter_type"]) &&
        !empty($_REQUEST["params"]["filter_type"])
    ) {
        $sql .=
            " AND `category` = " . Secure($_REQUEST["params"]["filter_type"]);
    }
    if (
        isset($_REQUEST["params"]["current_tag"]) &&
        !empty($_REQUEST["params"]["current_tag"])
    ) {
        $keyword = Secure($_REQUEST["params"]["current_tag"]);
        $sql .=
            ' AND (`title` LIKE \'%' .
            $keyword .
            '%\' OR `content` LIKE \'%' .
            $keyword .
            '%\' OR `description` LIKE \'%' .
            $keyword .
            '%\' OR `tags` LIKE \'%' .
            $keyword .
            '%\')';
    }
    $sql .= " ORDER BY `id` DESC LIMIT 10;";
    $articles = $db->objectBuilder()->rawQuery($sql);
    $html = "";
    if (!empty($articles)) {
        $records = count($articles);
        foreach ($articles as $key => $art) {
            $articleData = GetArticle($art->id);
            $html .= loadPage("blogs/article-list", [
                "thumbnail" => getMedia($articleData["thumbnail"]),
                "id" => $articleData["id"],
                "title" => $articleData["title"],
                "content" => $articleData["content"],
                "description" => $articleData["description"],
                "posted" => $articleData["posted"],
                "category" => $articleData["category"],
                "category_text" => lang($articleData["category"]),
                "view" => $articleData["view"],
                "shared" => $articleData["shared"],
                "tags" => $articleData["tags"],
                "created_at" => time_Elapsed_String($articleData["created_at"]),
                "key" => $key + 1,
            ]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "stations") {
    $db->where("id", $id, "<");
    if (!empty($user_id)) {
        $db->where("user_id", secure($_REQUEST["userID"]));
    }
    if (IS_LOGGED) {
        $db->where(
            "user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = $user->id)"
        );
    }
    $html = "";
    $stationData = $db
        ->orderBy("id", "DESC")
        ->where("src", "radio")
        ->get(T_SONGS, 20);
    if (!empty($stationData)) {
        $html = "";
        foreach ($stationData as $key => $station) {
            $station = songData($station->id);
            $music->station_count++;
            $html .= loadPage("stations/list", ["STATION_DATA" => $station]);
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "fame_page") {
    $html = "";
    if (isset($_GET["views"]) && is_numeric($_GET["views"])) {
        $views_count = 0;
        if (
            !empty($_GET["views"]) &&
            is_numeric($_GET["views"]) &&
            $_GET["views"] > 0
        ) {
            $views_count = secure($_GET["views"]);
        }
        $result = $db->rawQuery(
            "SELECT v.id,v.track_id , COUNT(*) AS count FROM " .
                T_VIEWS .
                " v  , " .
                T_SONGS .
                " s WHERE s.id = v.track_id AND s.user_id != " .
                $user_id .
                " GROUP BY s.user_id HAVING count >= " .
                $music->config->views_count .
                " AND count <= " .
                $views_count .
                " AND v.id NOT IN (" .
                implode(",", $_GET["v_ids"]) .
                ") ORDER BY count DESC LIMIT 20"
        );
        if (!empty($result)) {
            $html = "";
            foreach ($result as $key => $value) {
                $song = songData($value->track_id);
                if (!empty($song)) {
                    $data = [
                        'ARTIST_DATA' => userData($song->user_id),
                        'VIEWS' => $value->count,
                        'VIEWS_FORMAT' => number_format($value->count),
                        'ID' => $value->id,
                        'COUNT_FOLLOWERS' => number_format($db->where('following_id', $song->user_id)->where("follower_id NOT IN (SELECT blocked_id FROM blocks WHERE user_id = $song->user_id)")->getValue(T_FOLLOWERS, 'COUNT(*)')),
                        'COUNT_FOLLOWING' => number_format($db->where('follower_id', $song->user_id)->where("following_id NOT IN (SELECT blocked_id FROM blocks WHERE user_id = $song->user_id)")->getValue(T_FOLLOWERS, 'COUNT(*)')),
                        'COUNT_TRACKS' => number_format($db->where('user_id', $song->user_id)->getValue(T_SONGS, 'COUNT(*)')),
                    ];
                    $html .= loadPage("fame/list", $data);
                }
            }
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}