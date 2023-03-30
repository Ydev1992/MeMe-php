<?php
if (IS_LOGGED == false) {
    header("Location: $site_url");
    exit();
}
if ($music->user->upload_import == 0) {
    header("Location: $site_url");
    exit();
}
if ($music->config->soundcloud_import == 'off' && $music->config->itunes_import == 'off' && $music->config->deezer_import == 'off' && $music->config->youtube_import == 'off' && $music->config->kkbox_import == 'off') {
	header("Location: $site_url");
    exit();
}
runPlugin("OnImportPage");
$added = array();
$text = '';
if ($music->config->soundcloud_import == 'on') {
	$added[] = 'SoundCloud';
}
if ($music->config->itunes_import == 'on') {
	$added[] = ' Itunes';
}
if ($music->config->deezer_import == 'on') {
	$added[] = ' Deezer';
}
if ($music->config->kkbox_import == 'on') {
	$added[] = ' Kkbox';
}
if ($music->config->youtube_import == 'on' && !empty($music->config->youtube_key)) {
	$added[] = ' Youtube';
}
if (!empty($added)) {
	$text = implode(',', $added);
}

$music->site_title = lang("Import");
$music->site_pagename = 'import';
$music->site_description = $music->config->description;
$music->site_content = loadPage("import/content", ['TEXT' => $text]);