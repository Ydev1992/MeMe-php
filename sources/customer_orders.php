<?php
if(!IS_LOGGED) {
	header("Location: $site_url/404");
    exit;
}
if ($music->config->store_system != 'on') {
	header("Location: $site_url/404");
    exit;
}
$music->orders = $db->where('user_id',$music->user->id)->orderBy('id', 'DESC')->groupBy('hash_id')->get(T_ORDERS,10);
$html = '';
runPlugin("OnOrdersPageLoad");
if (!empty($music->orders)) {
	foreach ($music->orders as $key => $order) {
		$count = $db->where('hash_id',$order->hash_id)->getValue(T_ORDERS,'count(*)');
		$items_count = $db->where('hash_id',$order->hash_id)->getValue(T_ORDERS,'sum(units)');
		$price = $db->where('hash_id',$order->hash_id)->getValue(T_ORDERS,'sum(price)');


		$html .= loadPage('customer_orders/list',array('hash' => $order->hash_id,
	                                          'url' => getLink("order/".$order->hash_id),
	                                          'count' => $count,
	                                          'price' => $price,
	                                          'items_count' => $items_count));
	}
}
$music->site_title = lang("My Orders") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "customer_orders";
$music->site_content     = loadPage('customer_orders/content',array('html' => $html));