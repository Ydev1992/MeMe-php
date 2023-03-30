<?php
if (IS_LOGGED == false) {
    exit("You ain't logged in!");
}

$track_id = 0;
if (!empty($_GET["id"])) {
    $track_id = secure($_GET["id"]);
}
if (empty($track_id)) {
    exit("Invalid Track ID");
}

$id = secure($_GET["id"]);
$getSong = $db->where("audio_id", $id)->getOne(T_SONGS);
if (empty($getSong)) {
    exit("Invalid Track ID");
}

$data["status"] = 400;

if (!isFavorated($getSong->id)) {
    $insertData = [
        "user_id" => $user->id,
        "track_id" => $getSong->id,
        "time" => time(),
    ];
    $create_fav = $db->insert(T_FOV, $insertData);
    if ($create_fav) {
        runPlugin('AfterSongFavorited', $insertData);
        $data["status"] = 200;
    }
} else {
    $delete_fav = $db
        ->where("user_id", $user->id)
        ->where("track_id", $getSong->id)
        ->delete(T_FOV);
    if ($delete_fav) {
        runPlugin('AfterSongUnFavorited', ["id" => $getSong->id]);
        $data["status"] = 300;
    }
}
?>