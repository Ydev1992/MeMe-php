<?php
if(!IS_LOGGED) {
	header("Location: $site_url/404");
    exit;
}
if ($music->config->event_system != 1) {
	header("Location: $site_url/404");
    exit;
}
if (!$music->config->can_use_event_system) {
	header("Location: $site_url/404");
    exit;
}
if (empty($path['options'][1])) {
	header("Location: $site_url/404");
    exit;
}
$id = secure($path['options'][1]);
$id = GetPostIdFromUrl($id);
if (empty($id)) {
    header("Location: $site_url/404");
    exit;
}
$event = GetEventById($id,'hash');

if (empty($event)) {
	header("Location: $site_url/404");
	exit();
}
$event->user_data->owner  = false;

if ($music->loggedin == true) {
    $event->user_data->owner  = ($user->id == $event->user_data->id || isAdmin()) ? true : false;
}
if (!$event->user_data->owner) {
	header("Location: $site_url/404");
	exit();
}

runPlugin("OnEditEventLoad");

$event->desc = br2nl($event->desc);
$music->userData = $event->user_data;
$music->event = $event;
$music->site_title = lang("Edit Event") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "edit_event";
$music->site_content     = loadPage('edit_event/content',array('IMAGE' => $event->image,
                                                          'NAME' => $event->name,
                                                          'URL' => $event->url,
                                                          'ID' => $event->id));