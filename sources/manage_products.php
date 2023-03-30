<?php
if(!IS_LOGGED) {
	header("Location: $site_url/404");
    exit;
}
if ($music->config->store_system != 'on' || !$music->config->can_use_store_system) {
	header("Location: $site_url/404");
    exit;
}

runPlugin("OnManageProductsPage");

$html = '<div class="no-track-found"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12,18H6V14H12M21,14V12L20,7H4L3,12V14H4V20H14V14H18V20H20V14M20,4H4V6H20V4Z"></path></svg>'.lang("No products found").'</div>';
$music->products = $db->where('user_id',$music->user->id)->where('active',1)->orderBy('id', 'DESC')->get(T_PRODUCTS,10,array('id'));
if (!empty($music->products)) {
	$html = '';
	foreach ($music->products as $key => $value) {
		$music->product = GetProduct($value->id);
		$html .= loadPage('manage-products/list',array('id' => $music->product->id,
                                                      'url' => $music->product->url,
                                                      'data_load' => $music->product->data_load,
                                                      'image' => $music->product->images[0]['image'],
                                                      'title' => $music->product->title,
                                                      'rating' => $music->product->rating,
                                                      'edit_url' => $music->product->edit_url,
                                                      'edit_data_load' => $music->product->edit_data_load,
                                                      'f_price' => $music->product->formatted_price));
	}
}
$music->site_title = lang("Manage Products") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "manage-products";
$music->site_content     = loadPage('manage-products/content',array('HTML' => $html));