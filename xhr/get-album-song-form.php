<?php
$gener_id = 0;
if (isset($_GET["genre"])) {
    $gener_id = (int) $_GET["genre"];
}
$form_id = sha1(microtime() + rand(111,222));
exit();

$data = [
    "status" => 200,
    "html" => loadPage("upload-song/upload-album-form", [
        "form_id" => $form_id,
        "genre_id" => $gener_id,
        "HASH" => bin2hex(random_bytes(18))
    ]),
    "form_id" => $form_id,
];
?>