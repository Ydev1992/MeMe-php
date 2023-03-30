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
$music->events = $db->where("user_id",$music->user->id)->orderBy('id', 'DESC')->get(T_EVENTS,10,array('id'));
if (empty($music->events)) {
	header("Location: $site_url/404");
    exit;
}

runPlugin("OnManageEventsPage");

$html = '';
foreach ($music->events as $key => $value) {
	$event = $music->event = GetEventById($value->id);
	$music->is_joined = $db->where('event_id',$event->id)->where('user_id',$music->user->id)->getValue(T_EVENTS_JOINED,'COUNT(*)');
	$html .= loadPage('manage_events/list',array('ID' => $event->id,
                                         'URL' => $event->url,
                                         'DATA_LOAD' => $event->data_load,
                                         'EDIT_URL' => $event->edit_url,
                                         'EDIT_DATA_LOAD' => $event->edit_data_load,
                                         'NAME' => $event->name,
                                         'START_DATE' => $event->start_date,
                                         'IMAGE' => $event->image));
}
if (IS_LOGGED) {    
    $music->have_events = $db->where('user_id',$music->user->id)->getValue(T_EVENTS,'COUNT(*)');
}
else{
    $music->have_events = 0;
}
$music->site_title = lang("Manage Events") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "manage_events";
$music->site_content     = loadPage('manage_events/content',array('HTML' => $html));