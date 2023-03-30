<?php
if ($music->config->event_system != 1) {
	header("Location: $site_url/404");
    exit;
}

runPlugin("OnEventsPage");

// if (IS_LOGGED) {
// 	$db->where(" `id` NOT IN (SELECT `event_id` FROM " . T_EVENTS_JOINED . " WHERE `user_id` = '".$music->user->id."') AND `end_date` >= CURDATE()");
// }
// else{
// 	$db->where(" `end_date` >= CURDATE()");
// }
$db->where(" `end_date` >= CURDATE()");
$music->events = $db->orderBy('id', 'DESC')->get(T_EVENTS,10,array('id'));
$html = '<div class="no-track-found bg_light"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M19,19H5V8H19M16,1V3H8V1H6V3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3H18V1M17,12H12V17H17V12Z"></path></svg>'.lang("No events found").'</div>';
if (!empty($music->events)) {
	$html = '';
	foreach ($music->events as $key => $value) {
		$event = $music->event = GetEventById($value->id);
		$music->is_joined = 0;
		if (IS_LOGGED) {
			$music->is_joined = $db->where('event_id',$event->id)->where('user_id',$music->user->id)->getValue(T_EVENTS_JOINED,'COUNT(*)');
		}
		$html .= loadPage('events/list',array('ID' => $event->id,
	                                         'URL' => $event->url,
	                                         'DATA_LOAD' => $event->data_load,
	                                         'NAME' => $event->name,
	                                         'START_DATE' => $event->start_date,
	                                         'IMAGE' => $event->image));
	}
}
if (IS_LOGGED) {	
	$music->have_events = $db->where('user_id',$music->user->id)->getValue(T_EVENTS,'COUNT(*)');
}
else{
	$music->have_events = 0;
}
$music->site_title = lang("Browse Events") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "events";
$music->site_content     = loadPage('events/content',array('HTML' => $html));