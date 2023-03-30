<?php
require "assets/libs/getID3-1.9.14/getid3/getid3.php";

if (IS_LOGGED == false) {
    $data = [
        "status" => 400,
        "error" => "Not logged in",
    ];
    echo json_encode($data);
    exit();
} else {
    runPlugin('PreSongUploaded', $_REQUEST);
    $getID3 = new getID3();
    $featured = $user->is_pro == 1 ? 1 : 0;
    $filesize = 0;
    $error = false;
    $request = [];
    $request[] = empty($_POST["title"]) || empty($_POST["description"]);
    $request[] = empty($_POST["tags"]) || empty($_POST["song-thumbnail"]);
    if (in_array(true, $request)) {
        if (empty($_POST["song-thumbnail"])) {
            $error = lang("Please upload song thumbnail");
        }

        if (empty($_POST["tags"])) {
            //$error = lang("Please enter song tags");
        }

        if (empty($_POST["description"])) {
            $error = lang("Please enter song description");
        }
        if (empty($_POST["title"])) {
            $error = lang("Please enter song title");
        }
    } elseif (empty($_POST["song-location"])) {
        $error = lang(
            "Audio file not found, please refresh the page and try again."
        );
    } else {
        $request = [];
        $request[] = !in_array($_POST["song-location"], $_SESSION["uploads"]);
        $request[] = !in_array($_POST["song-thumbnail"], $_SESSION["uploads"]);
        if (
            $music->config->s3_upload != "on" &&
            $music->config->ftp_upload != "on" &&
            $music->config->spaces != "on" &&
            $music->config->google_drive != "on" &&
            $music->config->wasabi_storage != "on" &&
            $music->config->backblaze_storage != "on"
        ) {
            $request[] = !file_exists($_POST["song-location"]);
        }

        if (in_array(true, $request)) {
            $error = lang("Something went wrong Please try again later!");
        }
    }
    if (empty($error)) {
        $file = $getID3->analyze($_POST["song-location"]);
        $duration = "00:00";
        if (!empty($file["playtime_string"])) {
            $duration = secure($file["playtime_string"]);
        }
        if (!empty($file["filesize"])) {
            $filesize = $file["filesize"];
        }
        $audio_id = generateKey(15, 15);
        $check_for_audio = $db
            ->where("audio_id", $audio_id)
            ->getValue(T_SONGS, "count(*)");
        if ($check_for_audio > 0) {
            $audio_id = generateKey(15, 15);
        }
        $thumbnail = secure($_POST["song-thumbnail"], 0);

        $category_id = 0;
        $convert = true;
        if (
            !empty($_POST["category_id"]) &&
            is_numeric($_POST["category_id"]) &&
            $_POST["category_id"] > 0
        ) {
            $category_id = secure($_POST["category_id"]);
        }
        $link_regex = "/(http\:\/\/|https\:\/\/|www\.)([^\ ]+)/i";
        $i = 0;
        preg_match_all($link_regex, secure($_POST["description"]), $matches);
        foreach ($matches[0] as $match) {
            $match_url = strip_tags($match);
            $syntax = "[a]" . urlencode($match_url) . "[/a]";
            $_POST["description"] = str_replace(
                $match,
                $syntax,
                $_POST["description"]
            );
        }
        $audio_privacy = 0;
        if (!empty($_POST["privacy"])) {
            if (in_array($_POST["privacy"], [0, 1])) {
                $audio_privacy = secure($_POST["privacy"]);
            }
        }
        $age_restriction = 0;
        if (!empty($_POST["age_restriction"])) {
            if (in_array($_POST["age_restriction"], [0, 1])) {
                $age_restriction = secure($_POST["age_restriction"]);
            }
        }
        $song_price = 0;
        if (isset($_POST["song-price"])) {
            if (in_array($_POST["song-price"], $music->song_prices)) {
                $song_price = secure($_POST["song-price"]);
            }
        }

        $allow_downloads = 1;
        if (isset($_POST["allow_downloads"])) {
            if (in_array($_POST["allow_downloads"], [0, 1])) {
                $allow_downloads = secure($_POST["allow_downloads"]);
            }
        }
        $display_embed = 1;
        if (isset($_POST["display_embed"])) {
            if (in_array($_POST["display_embed"], [0, 1])) {
                $display_embed = secure($_POST["display_embed"]);
            }
        }

        $data_insert = [
            "audio_id" => $audio_id,
            "user_id" => $user->id,
            "title" => secure($_POST["title"]),
            "description" => secure($_POST["description"]),
            "lyrics" => secure($_POST["lyrics"]),
            "tags" => secure(str_replace("#", "", $_POST["tags"])),
            "duration" => $duration,
            "audio_location" => "",
            "category_id" => $category_id,
            "thumbnail" => $thumbnail,
            "time" => time(),
            "registered" => date("Y") . "/" . intval(date("m")),
            "size" => $filesize,
            "availability" => $audio_privacy,
            "age_restriction" => $age_restriction,
            "price" => $song_price,
            "spotlight" => $featured,
            "ffmpeg" => $music->config->ffmpeg_system == "on" ? 1 : 0,
            "converted" => $music->config->ffmpeg_system == "on" ? 0 : 1,
            "allow_downloads" => $allow_downloads,
            "display_embed" => $display_embed,
        ];
        $users = [];
        if (!empty($_POST["parts"]) && $music->config->tag_artist_system == 1) {
            $parts = explode(",", secure($_POST["parts"]));
            foreach ($parts as $key => $value) {
                if (
                    is_numeric($value) &&
                    $value > 0 &&
                    !in_array($value, $users)
                ) {
                    $users[] = secure($value);
                }
            }
        }
        if (file_exists($thumbnail)) {
            PT_UploadToS3($thumbnail);
        }
        if (
            $music->config->ffmpeg_system == "off" &&
            in_array($_POST["song-location"], $_SESSION["uploads"])
        ) {
            $data_insert["audio_location"] = secure($_POST["song-location"]);
            $db->where('file_name', secure($_POST["song-location"]))->delete(T_UPLOADS);
        }
        $insert = $db->insert(T_SONGS, $data_insert);
        if ($insert) {
            runPlugin('AfterSongUploaded', $data_insert);
            if (!empty($users)) {
                foreach ($users as $key => $value) {
                    $db->insert(T_ARTISTS_TAGS, [
                        "artist_id" => $value,
                        "user_id" => $user->id,
                        "track_id" => $insert,
                        "time" => time(),
                    ]);
                }
            }
            RecordUserActivities("upload", $data_insert);
            $create_activity = createActivity([
                "user_id" => $user->id,
                "type" => "uploaded_track",
                "track_id" => $insert,
            ]);

            $delete_files = [];

            $data_insert["id"] = $insert;
            $data = [
                "status" => 200,
                "audio_id" => $audio_id,
                "song_location" => $_POST["song-location"],
                "link" => getLink("track/$audio_id"),
            ];
            $_SESSION["album_songs"][] = $audio_id;
            ob_end_clean();
            header("Content-Encoding: none");
            header("Connection: close");
            ignore_user_abort();
            ob_start();
            if (!empty($data)) {
                header("Content-Type: application/json");
                echo json_encode($data);
            }
            $size = ob_get_length();
            header("Content-Length: $size");
            ob_end_flush();
            flush();
            session_write_close();
            if (is_callable("fastcgi_finish_request")) {
                fastcgi_finish_request();
            }
            notifyUploadTrack($data_insert);
            exit();
        }
    } else {
        $data = [
            "status" => 400,
            "message" => $error,
        ];
    }
}