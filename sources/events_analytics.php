<?php 
if (IS_LOGGED == false) {
	header("Location: $site_url");
	exit();
}
if ($music->config->event_system != 1) {
	header("Location: $site_url/404");
    exit;
}
if (!$music->config->can_use_event_system) {
	header("Location: $site_url/404");
    exit;
}
$data_array = array();
$currentDays = date('t');



$dayStart = "";
$playsThisMonth = '';
$LikesThisMonth = '';
$SalesThisMonth = '';

$data_array['joinThisMonth'] = '';
$data_array['SalesThisMonth'] = '';
$data_array['most_joined_events'] = '<div class="no-track-found"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-slash"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>' . lang("No events found") . '</div>';
$data_array['most_sold_events'] = '<div class="no-track-found"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-slash"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>' . lang("No events found") . '</div>';

for ($i=1; $i <= $currentDays; $i++) { 
	$f = sprintf("%02d", $i);
	$dayStart .= "'$f',";

	$thisMonthjoinCount = $db->where("event_id IN (SELECT `id` FROM " . T_EVENTS . " WHERE `user_id` = '".$music->user->id."') AND DAY(FROM_UNIXTIME(`time`)) = $i")->getValue(T_EVENTS_JOINED, 'count(*)');
	$data_array['joinThisMonth'] .= "'$thisMonthjoinCount',";

	$thisMonthSalesCount = $db->where("event_id IN (SELECT id FROM " . T_EVENTS . " WHERE user_id = ?) AND DAY(FROM_UNIXTIME(`time`)) = $i", [$user->id])->getValue(T_PURCHAES, 'count(*)');
	$data_array['SalesThisMonth'] .= "'$thisMonthSalesCount',";
}

$most_joined_events = $db->rawQuery("SELECT event_id ,COUNT(*) AS count FROM " . T_EVENTS_JOINED . " WHERE event_id IN (SELECT id FROM " . T_EVENTS . " WHERE user_id = ".$user->id.") GROUP BY event_id ORDER BY count DESC LIMIT 7");
if (!empty($most_joined_events)) {
	$data_array['most_joined_events'] = '';
	foreach ($most_joined_events as $key => $value) {
		$event = GetEventById($value->event_id);
		if (!empty($event)) {
			$data_array['most_joined_events'] .= loadPage("events_analytics/list", array('count' => number_format($value->count),
								                                                         'key' => '#' . $value->event_id,
								                                                         's_name' => $event->name,
								                                                         'load' => $event->data_load,
								                                                         's_url' => $event->url));
		}
	}
}

$most_sold_events = $db->rawQuery("SELECT event_id ,COUNT(*) AS count FROM " . T_PURCHAES . " WHERE event_id IN (SELECT id FROM " . T_EVENTS . " WHERE user_id = ".$user->id.") GROUP BY event_id ORDER BY count DESC LIMIT 7");
if (!empty($most_sold_events)) {
	$data_array['most_sold_events'] = '';
	foreach ($most_sold_events as $key => $value) {
		$event = GetEventById($value->event_id);
		if (!empty($event)) {
			$data_array['most_sold_events'] .= loadPage("events_analytics/list", array('count' => number_format($value->count),
								                                                         'key' => '#' . $value->event_id,
								                                                         's_name' => $event->name,
								                                                         'load' => $event->data_load,
								                                                         's_url' => $event->url));
		}
	}
}
$recentSalesHTML = '<div class="no-track-found"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M5,6H23V18H5V6M14,9A3,3 0 0,1 17,12A3,3 0 0,1 14,15A3,3 0 0,1 11,12A3,3 0 0,1 14,9M9,8A2,2 0 0,1 7,10V14A2,2 0 0,1 9,16H19A2,2 0 0,1 21,14V10A2,2 0 0,1 19,8H9M1,10H3V20H19V22H1V10Z" /></svg>' . lang("No sales found") . '</div>';
$recentSales = $db->where('event_id IN (SELECT id FROM ' . T_EVENTS . ' WHERE user_id = ?)', [$user->id])->get(T_PURCHAES, 50);
if (!empty($recentSales)) {
	$recentSalesHTML = "";
	foreach ($recentSales as $key => $userSale) {
		if (!empty($userSale->event_id)) {
			$event = GetEventById($userSale->event_id);
			$recentSalesHTML .= loadPage("events_analytics/recent_list", array('count' => '$' . number_format($userSale->final_price),
				                                                 'commission' => '$' . number_format($userSale->commission),
				                                                 'price' => '$' . number_format($userSale->price),
		                                                         'key' => '#' . $userSale->id,
		                                                         's_name' => $event->name,
		                                                         'type' => lang('Ticket'),
		                                                         'load' => $event->data_load,
		                                                         's_url' => $event->url));
		}
	}
}
$data_array['RECENT_SALES'] = $recentSalesHTML;
$data_array['total_sales'] = number_format($db->where("event_id IN (SELECT `id` FROM " . T_EVENTS . " WHERE `user_id` = '".$music->user->id."')")->getValue(T_PURCHAES, 'SUM(final_price)'));
$data_array['total_month_sales'] = number_format($db->where("event_id IN (SELECT `id` FROM " . T_EVENTS . " WHERE `user_id` = '".$music->user->id."') AND MONTH(`timestamp`) = MONTH(CURDATE())")->getValue(T_PURCHAES, 'SUM(final_price)'));
$data_array['total_today_sales'] = number_format($db->where("event_id IN (SELECT `id` FROM " . T_EVENTS . " WHERE `user_id` = '".$music->user->id."') AND DATE(`timestamp`) = CURDATE()")->getValue(T_PURCHAES, 'SUM(final_price)'));
$data_array['joinThisMonth'] =  "[" . $data_array['joinThisMonth'] . "]";
$data_array['SalesThisMonth'] =  "[" . $data_array['SalesThisMonth'] . "]";
$thisMonthDays =  "[" . $dayStart . "]";
$data_array['THIS_MONTH'] = $thisMonthDays;
$data_array['TOTAL_EVENTS'] = number_format($db->where('user_id', $user->id)->getValue(T_EVENTS, 'count(*)'));
$data_array['TOTAL_JOIN'] = number_format($db->where("event_id IN (SELECT `id` FROM " . T_EVENTS . " WHERE `user_id` = '".$music->user->id."')")->getValue(T_EVENTS_JOINED, 'count(*)'));
$data_array['TOTAL_Tickets'] = number_format($db->where('user_id', $user->id)->getValue(T_EVENTS, 'SUM(available_tickets)'));
$music->site_title = lang("Events Analytics") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "events_analytics";
$music->site_content     = loadPage('events_analytics/content',$data_array);