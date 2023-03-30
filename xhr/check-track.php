<?php
if (empty($_POST["id"]) || IS_LOGGED == false) {
    exit();
}

$getSongData = $db->where("audio_id", secure($_POST["id"]))->getOne(T_SONGS);

if ($getSongData->converted == 1) {
    $data = ['status' => 200];
} else {
    $data = ['status' => 300];
}
?>