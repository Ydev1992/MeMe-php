<?php 
if (IS_LOGGED == false) {
	header("Location: $site_url");
	exit();
}
if ($music->config->store_system != 'on' || !$music->config->can_use_store_system) {
	header("Location: $site_url/404");
    exit;
}
$data_array = array();
$currentDays = date('t');

$dayStart = "";
$playsThisMonth = '';
$LikesThisMonth = '';
$SalesThisMonth = '';

$data_array['SalesThisMonth'] = '';
$data_array['ChartToTalSalesThisMonth'] = '';
$data_array['most_sold_product'] = '<div class="no-track-found"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-slash"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>' . lang("No products found") . '</div>';

for ($i=1; $i <= $currentDays; $i++) { 
	$f = sprintf("%02d", $i);
	$dayStart .= "'$f',";


	$thisMonthSalesCount = $db->where("product_id IN (SELECT id FROM " . T_PRODUCTS . " WHERE user_id = ?) AND DAY(FROM_UNIXTIME(`time`)) = $i", [$user->id])->getValue(T_ORDERS, 'count(*)');
	$data_array['SalesThisMonth'] .= "'$thisMonthSalesCount',";

	$ChartToTalSalesThisMonth = $db->where("product_id IN (SELECT id FROM " . T_PRODUCTS . " WHERE user_id = ?) AND DAY(FROM_UNIXTIME(`time`)) = $i", [$user->id])->getValue(T_ORDERS, 'SUM(final_price)');
	$ChartToTalSalesThisMonth = ($ChartToTalSalesThisMonth > 0) ? $ChartToTalSalesThisMonth : 0;
	$data_array['ChartToTalSalesThisMonth'] .= "'$ChartToTalSalesThisMonth',";
}

$data_array['ChartToTalSales'] = '';
$ChartToTalSales = $db->where("product_id IN (SELECT id FROM " . T_PRODUCTS . " WHERE user_id = ?) GROUP BY DAY(FROM_UNIXTIME(`time`))", [$user->id])->get(T_ORDERS, null, 'SUM(final_price)');
foreach ($ChartToTalSales as $key => $ChartToTalSalesQ) {
	$ChartToTalSalesQ = ($ChartToTalSalesQ->{'SUM(final_price)'} > 0) ? $ChartToTalSalesQ->{'SUM(final_price)'} : 0;
	$data_array['ChartToTalSales'] .= "'" . $ChartToTalSalesQ . "',";
}

$ChartTodaySales = $db->where("product_id IN (SELECT id FROM " . T_PRODUCTS . " WHERE user_id = ?) AND DATE(FROM_UNIXTIME(`time`)) = CURDATE()", [$user->id])->getValue(T_ORDERS, 'SUM(final_price)');

$data_array['ChartTodaySales'] = "'$ChartTodaySales',";

$most_sold_product = $db->rawQuery("SELECT product_id ,COUNT(*) AS count FROM " . T_ORDERS . " WHERE product_id IN (SELECT id FROM " . T_PRODUCTS . " WHERE user_id = ".$user->id.") GROUP BY product_id ORDER BY count DESC LIMIT 7");
if (!empty($most_sold_product)) {
	$data_array['most_sold_product'] = '';
	foreach ($most_sold_product as $key => $value) {
		$product = GetProduct($value->product_id);
		if (!empty($product)) {
			$data_array['most_sold_product'] .= loadPage("store_analytics/list", array('count' => number_format($value->count),
								                                                         'key' => '#' . $value->product_id,
								                                                         's_name' => $product->title,
								                                                         'load' => $product->data_load,
								                                                         's_url' => $product->url));
		}
	}
}
$recentSalesHTML = '<div class="no-track-found"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M5,6H23V18H5V6M14,9A3,3 0 0,1 17,12A3,3 0 0,1 14,15A3,3 0 0,1 11,12A3,3 0 0,1 14,9M9,8A2,2 0 0,1 7,10V14A2,2 0 0,1 9,16H19A2,2 0 0,1 21,14V10A2,2 0 0,1 19,8H9M1,10H3V20H19V22H1V10Z" /></svg>' . lang("No sales found") . '</div>';
$recentSales = $db->where('product_owner_id', $user->id)->orderBy('id', 'DESC')->get(T_ORDERS, 50);
if (!empty($recentSales)) {
	$recentSalesHTML = "";
	foreach ($recentSales as $key => $userSale) {
		if (!empty($userSale->product_id)) {
			$product = GetProduct($userSale->product_id);
			$recentSalesHTML .= loadPage("store_analytics/recent_list", array('count' => '$' . number_format($userSale->final_price),
				                                                 'commission' => '$' . number_format($userSale->commission),
				                                                 'price' => '$' . number_format($userSale->price),
		                                                         'key' => '#' . $userSale->id,
		                                                         's_name' => $product->title,
		                                                         'type' => lang('Product'),
		                                                         'load' => $product->data_load,
		                                                         's_url' => $product->url));
		}
	}
}
$data_array['RECENT_SALES'] = $recentSalesHTML;



$data_array['total_month_sales'] = number_format($db->where("product_owner_id = ".$user->id." AND MONTH(FROM_UNIXTIME(`time`)) = MONTH(CURDATE())")->getValue(T_ORDERS, 'SUM(final_price)'));
$data_array['total_today_sales'] = number_format($db->where("product_owner_id = ".$user->id." AND DATE(FROM_UNIXTIME(`time`)) = CURDATE()")->getValue(T_ORDERS, 'SUM(final_price)'));
$data_array['SalesThisMonth'] =  "[" . $data_array['SalesThisMonth'] . "]";
$thisMonthDays =  "[" . $dayStart . "]";
$data_array['THIS_MONTH'] = $thisMonthDays;
$data_array['TOTAL_PRODUCTS'] = number_format($db->where('user_id', $user->id)->getValue(T_PRODUCTS, 'count(*)'));
$data_array['total_earned'] = number_format($db->where('product_owner_id', $user->id)->getValue(T_ORDERS, 'sum(price)'));
$data_array['total_commission'] = number_format($db->where('product_owner_id', $user->id)->getValue(T_ORDERS, 'sum(commission)'));
$data_array['total_final_price'] = number_format($db->where('product_owner_id', $user->id)->getValue(T_ORDERS, 'sum(final_price)'));

$music->site_title = lang("Store Analytics") . ' | ' . $music->config->title;
$music->site_description = $music->config->description;
$music->site_pagename = "store_analytics";
$music->site_content     = loadPage('store_analytics/content',$data_array);