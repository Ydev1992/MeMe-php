<?php
if ($music->config->event_system != 1) {
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

runPlugin("OnEventPage");

$event->user_data->owner  = false;

if ($music->loggedin == true) {
    $event->user_data->owner  = ($user->id == $event->user_data->id || isAdmin()) ? true : false;
}
$music->event = $event;
$music->event->video_240 = 0;
$music->event->video_360 = 0;
$music->event->video_480 = 0;
$music->event->video_720 = 0;
$music->event->video_1080 = 0;
$music->event->video_2048 = 0;
$music->event->video_4096 = 0;
// demo video
if ($music->config->ffmpeg_system == 'on') {
    $explode_video = explode('_video', $music->event->video);
    if ($music->event->{"240p"} == 1) {
        $music->event->video_240 = $explode_video[0] . '_video_240p_converted.mp4';
    }
    if ($music->event->{"360p"} == 1) {
        $music->event->video_360 = $explode_video[0] . '_video_360p_converted.mp4';
    }
    if ($music->event->{"480p"} == 1) {
        $music->event->video_480 = $explode_video[0] . '_video_480p_converted.mp4';
    }
    if ($music->event->{"720p"} == 1) {
        $music->event->video_720 = $explode_video[0] . '_video_720p_converted.mp4';
    }
    if ($music->event->{"1080p"} == 1) {
        $music->event->video_1080 = $explode_video[0] . '_video_1080p_converted.mp4';
    }
    if ($music->event->{"4096p"} == 1) {
        $music->event->video_4096 = $explode_video[0] . '_video_4096p_converted.mp4';
    }
    if ($music->event->{"2048p"} == 1) {
        $music->event->video_2048 = $explode_video[0] . '_video_2048p_converted.mp4';
    }
}
$music->is_joined = 0;
if (IS_LOGGED) {
    $music->is_joined = $db->where('event_id',$event->id)->where('user_id',$music->user->id)->getValue(T_EVENTS_JOINED,'COUNT(*)');
}
$music->userData = $event->user_data;
$music->event->going      = TotalGoingUsers($event->id);
$music->site_title = lang("Event") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "event";
$music->site_content     = loadPage('event/content',array('IMAGE' => $event->image,
                                                          'NAME' => $event->name,
                                                          'URL' => $event->url,
                                                          'ID' => $event->id));