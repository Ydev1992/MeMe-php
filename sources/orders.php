<?php
runPlugin("OnOrdersPage");
if(!IS_LOGGED) {
	header("Location: $site_url/404");
    exit;
}
if ($music->config->store_system != 'on' || !$music->config->can_use_store_system) {
	header("Location: $site_url/404");
    exit;
}
$music->orders = $db->where('product_owner_id',$music->user->id)->orderBy('id', 'DESC')->groupBy('hash_id')->get(T_ORDERS,10);
$html = '';

if (!empty($music->orders)) {
	foreach ($music->orders as $key => $order) {
		$count = $db->where('hash_id',$order->hash_id)->getValue(T_ORDERS,'count(*)');
		$items_count = $db->where('hash_id',$order->hash_id)->getValue(T_ORDERS,'sum(units)');
		$price = $db->where('hash_id',$order->hash_id)->getValue(T_ORDERS,'sum(price)');
		$status = $db->where('hash_id',$order->hash_id)->getValue(T_ORDERS,'status');


		$html .= loadPage('orders/list',array('hash' => $order->hash_id,
			                                  'ID' => $order->id,
	                                          'url' => getLink("order/".$order->hash_id),
	                                          'count' => $count,
	                                          'price' => number_format($price),
	                                          'items_count' => $items_count,
											  'status' => $status
											  ));
	}
}
$music->site_title = lang("Orders") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "orders";
$music->site_content     = loadPage('orders/content',array('html' => $html));