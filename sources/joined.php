<?php
if(!IS_LOGGED) {
	header("Location: $site_url/404");
    exit;
}
if ($music->config->event_system != 1) {
	header("Location: $site_url/404");
    exit;
}
$music->events = $db->where('user_id',$music->user->id)->orderBy('event_id', 'DESC')->get(T_EVENTS_JOINED,10,array('event_id'));
$html = '<div class="no-track-found bg_light"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M19,19H5V8H19M16,1V3H8V1H6V3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3H18V1M17,12H12V17H17V12Z"></path></svg>'.lang("No events found").'</div>';
if (!empty($music->events)) {
	$html = '';
	foreach ($music->events as $key => $value) {
		$event = $music->event = GetEventById($value->event_id);
		$music->is_joined = $db->where('event_id',$event->id)->where('user_id',$music->user->id)->getValue(T_EVENTS_JOINED,'COUNT(*)');
		$html .= loadPage('events/list',array('ID' => $event->id,
	                                          'URL' => $event->url,
	                                          'DATA_LOAD' => $event->data_load,
	                                          'NAME' => $event->name,
	                                          'START_DATE' => $event->start_date,
	                                          'IMAGE' => $event->image));
	}
}
	
$music->have_events = $db->where('user_id',$music->user->id)->getValue(T_EVENTS,'COUNT(*)');
$music->site_title = lang("Joined Events") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "joined";
$music->site_content     = loadPage('joined/content',array('HTML' => $html));