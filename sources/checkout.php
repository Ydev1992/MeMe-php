<?php
if(!IS_LOGGED) {
	header("Location: $site_url/404");
    exit;
}
if ($music->config->store_system != 'on') {
	header("Location: $site_url/404");
    exit;
}
$music->items = $db->where('user_id',$music->user->id)->get(T_CARD);
$html = '';
$total = 0;

runPlugin("OnCheckOutLoad");

if (!empty($music->items)) {
	foreach ($music->items as $key => $music->item) {
		$music->item->product = GetProduct($music->item->product_id);
		$total += ($music->item->product->price * $music->item->units);
		$html .= loadPage('checkout/item',array('url' => $music->item->product->url,
	                                            'product_id' => $music->item->product->id,
	                                            'title' => $music->item->product->title,
	                                            'user_url' => $music->item->product->user_data->url,
	                                            'username' => $music->item->product->user_data->username,
	                                            'name' => $music->item->product->user_data->name,
	                                            'price' => number_format($music->item->product->price),
	                                            'image' => $music->item->product->images[0]['image']));
	}
}
$music->topup = ($music->user->org_wallet < $total ? 'show' : 'hide');
$music->site_title = lang("Checkout") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "checkout";
$music->site_content     = loadPage('checkout/content',array('html' => $html,
                                                             'total' => number_format($total)));
