<?php
if (!empty($_GET["reset"])) {
    unset($_SESSION["songs"]);
} elseif (!empty($_POST["qdata"])) {
    $_SESSION["songs"] = !empty($_SESSION["songs"]) ? $_SESSION["songs"] : "{}";
    $decode = json_decode(html_entity_decode($_SESSION["songs"]), true);
    if (empty($decode)) {
        $_SESSION["songs"] = htmlentities(json_encode([$_POST["qdata"]]));
        exit();
    }
    $can_add = true;
    foreach ($decode as $key => $song) {
        if (array_diff($song, $_POST["qdata"])) {
            $can_add = false;
        }
    }
    if ($can_add == false) {
        $decode[] = $_POST["qdata"];
    }
    $_SESSION["songs"] = htmlentities(json_encode($decode));
    runPlugin('AfterSongIsAddedToQueue', $decode);
}
?>