<?php
runPlugin("OnOrderPage");
if(!IS_LOGGED) {
	header("Location: $site_url/404");
    exit;
}
if ($music->config->store_system != 'on') {
	header("Location: $site_url/404");
    exit;
}
if (empty($path['options'][1])) {
	header("Location: $site_url/404");
    exit;
}
$hash_id = secure($path['options'][1]);
$music->orders = $db->where('product_owner_id',$music->user->id)->where('hash_id',$hash_id)->get(T_ORDERS);
$html = '';
if (empty($music->orders)) {
	header("Location: $site_url/404");
    exit;
}
$total = 0;
$total_commission = 0;
$total_final_price = 0;
$address_id = 0;
foreach ($music->orders as $key => $music->order) {
	// $count = $db->where('hash_id',$order->hash_id)->getValue(T_ORDERS,'count(*)');
	// $items_count = $db->where('hash_id',$order->hash_id)->getValue(T_ORDERS,'sum(units)');
	// $price = $db->where('hash_id',$order->hash_id)->getValue(T_ORDERS,'sum(price)');
	$music->order->product = GetProduct($music->order->product_id);
    $total += $music->order->price;
    $total_commission += $music->order->commission;
    $total_final_price += $music->order->final_price;
    $address_id = $music->order->address_id;


	$html .= loadPage('order/list',array('hash' => $hash_id,
                                         'url' => $music->order->product->url,
                                         'product_id' => $music->order->product->id,
                                         'image' => $music->order->product->images[0]['image'],
                                         'title' => $music->order->product->title,
                                         'price' => number_format($music->order->price),
                                         'commission' => number_format($music->order->commission),
                                         'final_price' => number_format($music->order->final_price),
                                         'items_count' => $music->order->units));
}
$music->address = $db->where('id',$address_id)->getOne(T_ADDRESS);

$music->site_title = lang("Order") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "order";
$music->site_content     = loadPage('order/content',array('html' => $html,
                                                          'hash' => $hash_id,
                                                          'total' => number_format($total),
                                                          'total_commission' => number_format($total_commission),
                                                          'total_final_price' => number_format($total_final_price)));