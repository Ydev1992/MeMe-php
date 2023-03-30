<?php
runPlugin("OnProductPage");
if ($music->config->store_system != 'on') {
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

$product = GetProduct($id,'hash');

if (empty($product)) {
	header("Location: $site_url/404");
	exit();
}
$product->user_data->owner  = false;
$music->can_review = 0;
$music->is_reviewed = 0;

if ($music->loggedin == true) {
    $product->user_data->owner  = ($user->id == $product->user_data->id || isAdmin()) ? true : false;
    $music->can_review = $db->where('user_id',$music->user->id)->where('product_id',$product->id)->where('status','delivered')->getValue(T_ORDERS,'COUNT(*)');
	$music->is_reviewed = $db->where('user_id',$music->user->id)->where('product_id',$product->id)->getValue(T_REVIEW,'COUNT(*)');
}
$music->may_like = $db->where("(`title` LIKE '%".$product->title."%' OR `desc` LIKE '%".$product->title."%' OR `tags` LIKE '%".$product->tags."%' OR `cat_id` LIKE '%".$product->cat_id."%')")->where('id',$product->id,'!=')->orderBy('RAND()')->get(T_PRODUCTS,6);
$may_like_html = '';
if (!empty($music->may_like)) {
	foreach ($music->may_like as $key => $value) {
		$music->may_product = GetProduct($value->id);
		$may_like_html .= loadPage('product/may_like',array('url' => $music->may_product->url,
	                                                        'data_load' => $music->may_product->data_load,
	                                                        'user_url' => $music->may_product->url,
	                                                        'username' => $music->may_product->data_load,
	                                                        'avatar' => $music->may_product->images[0]['image'],
	                                                        'title' => $music->may_product->title,
	                                                        'rating' => $music->may_product->rating,
	                                                        'formatted_price' => $music->may_product->formatted_price));
	}
}
$music->reviews = $db->where('product_id',$product->id)->orderBy('id', 'DESC')->get(T_REVIEW,10);
$reviews_html = '<div class="no-track-found bg_light"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" width="24px" height="24px" preserveAspectRatio="xMidYMid meet" viewBox="0 0 24 24"><path d="M18 26h8v2h-8z" fill="currentColor"/><path d="M18 22h12v2H18z" fill="currentColor"/><path d="M18 18h12v2H18z" fill="currentColor"/><path d="M20.549 11.217L16 2l-4.549 9.217L1.28 12.695l7.36 7.175L6.902 30L14 26.269v-2.26l-4.441 2.335l1.052-6.136l.178-1.037l-.753-.733l-4.458-4.347l6.161-.895l1.04-.151l.466-.943L16 6.519l2.755 5.583l.466.943l1.04.151l7.454 1.085L28 12.3l-7.451-1.083z" fill="currentColor"/></svg>'.lang("No reviews found").'</div>';
$one   = 0;
$two   = 0;
$three = 0;
$four  = 0;
$five  = 0;
$total_stars = 0;
if (!empty($music->reviews)) {
	$reviews_html = "";
	foreach ($music->reviews as $key => $value) {
		$review_class = 'five_star';
		$review_stars = '5 ★★★★★';
		if ($value->star == 1) {
			$review_class = 'one_star';
			$review_stars = '1 ★';
	        $one += $value->star;
	    } else if ($value->star == 2) {
	    	$review_stars = '2 ★★';
	    	$review_class = 'two_star';
	        $two += $value->star;
	    } else if ($value->star == 3) {
	    	$review_stars = '3 ★★★';
	    	$review_class = 'three_star';
	        $three += $value->star;
	    } else if ($value->star == 4) {
	    	$review_stars = '4 ★★★★';
	    	$review_class = 'four_star';
	        $four += $value->star;
	    } else {
	        $five += $value->star;
	    }
		$music->review = GetReview($value->id);
		$reviews_html .= loadPage('product/review',array('id' => $music->review->id,
	                                                     'avatar' => $music->review->user_data->avatar,
	                                                     'name' => $music->review->user_data->name,
	                                                     'username' => $music->review->user_data->username,
	                                                     'review_stars' => $review_stars,
	                                                     'review_class' => $review_class,
	                                                     'review_class' => $review_class,
	                                                     'desc' => $music->review->review,
	                                                     'time' => time_Elapsed_String($music->review->time),
	                                                     'user_url' => $music->review->user_data->url));
	}
	$total_stars = round(($five * 5 + $four * 4 + $three * 3 + $two * 2 + $one * 1) / ($five + $four + $three + $two + $one));
}
$music->product = $product;
$music->site_title = $product->title . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "product";
$music->site_content     = loadPage('product/content',array('reviews_html' => $reviews_html,
                                                            'total_stars' => $total_stars,
                                                            'may_like_html' => $may_like_html));
