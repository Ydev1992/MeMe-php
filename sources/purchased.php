<?php
runPlugin("OnPurchasedPage");
if (IS_LOGGED == false) {
	header("Location: $site_url");
	exit();
}

$html = "<div class='no-songs-found text-center'>" . lang("No purchased tracks found") . "</div>";

$getPurchased = $db->where('user_id', $user->id)->orderBy('time', 'DESC')->get(T_PURCHAES, 20);

$can_download = false;
$notPurchased = false;

$music->have_purchased = false;
if (!empty($getPurchased)) {
	$music->have_purchased = true;
	$html = '';
	foreach ($getPurchased as $key => $song) {
		if (!empty($song->track_id)) {
			$songData = songData($song->track_id);
			$music->songData = $songData;

	        $isPurchased = isTrackPurchased($songData->id);

	//        if (IS_LOGGED) {
	//            if($songData->owner == true || isAdmin()) {
	//                $can_download = true;
	//            }
	//            if ($songData->price > 0) {
	//                if ($isPurchased) {
	//                    $can_download = true;
	//                } else {
	//                    $notPurchased = true;
	//                }
	//            }
	//            if ($music->config->go_pro == 'on') {
	//                if ($user->is_pro == 1 && $isPurchased) {
	//                    $can_download = true;
	//                }
	//            } else if ($notPurchased == false) {
	//                $can_download = true;
	//            }
	//        }
	        if (IS_LOGGED) {
	            if ($songData->price > 0) {
	            	if ($isPurchased) {
	            		$can_download = true;
	            	}
	            }
	            else{
	            	if ($music->config->can_use_download) {
	            		$can_download = true;
	            	}
	            }
	            if ($songData->owner == true || isAdmin()) {
                    $can_download = true;
                }
	        }

	        $music->can_download = $can_download;

			$html .= loadPage('purchased/list', [
				't_thumbnail' => !empty($songData) && !empty($songData->thumbnail) ? $songData->thumbnail : '',
				't_id' => !empty($songData) && !empty($songData->id) ? $songData->id : '',
				't_title' => !empty($song->title) ? $song->title : (!empty($songData->title) ? $songData->title : ''),
				't_artist' => !empty($songData) && !empty($songData->publisher) ? $songData->publisher->name : '',
				't_url' => !empty($songData->url) ? $songData->url : '',
				't_artist_url' => !empty($songData) && !empty($songData->publisher) ? $songData->publisher->url : '',
				't_audio_id' => !empty($songData) && !empty($songData->audio_id) ? $songData->audio_id : '',
				't_time' => $song->time,
				't_price' => number_format($song->price),
				't_duration' => !empty($songData) && !empty($songData->duration) ? $songData->duration : '',
				't_purchased_on' => date('m/d/Y', strtotime($song->timestamp)),
				't_key' => $song->id
			]);
		}
		elseif (!empty($song->event_id)) {
			$event = GetEventById($song->event_id);
			// if (!empty($event)) {
				$html .= loadPage('purchased/ticket_list', [
					't_key' => $song->id,
					't_title' => !empty($song->title) ? $song->title : '',
					'URL' => !empty($event) && !empty($event->url) ? $event->url : '',
	                'DATA_LOAD' => !empty($event) && !empty($event->data_load) ? $event->data_load : '',
					't_purchased_on' => date('m/d/Y', strtotime($song->timestamp)),
					't_price' => number_format($song->price),
					'type' => lang('Ticket'),
					'id' => $song->id
				]);
			// }
		}
		elseif (!empty($song->order_hash_id)) {
			// $music->orders = $db->where('user_id',$music->user->id)->where('hash_id',$song->order_hash_id)->get(T_ORDERS);
			// if (!empty($music->orders)) {
			// 	foreach ($music->orders as $key => $music->order) {
			// 		$music->order->product = GetProduct($music->order->product_id);
			// 		if (!empty($music->order->product)) {
			// 			break;
			// 		}
			// 	}
				$html .= loadPage('purchased/order_list', [
					't_key' => $song->id,
					't_title' => !empty($song->title) ? $song->title : '',
					't_purchased_on' => date('m/d/Y', strtotime($song->timestamp)),
					't_price' => number_format($song->price),
					'type' => lang('Order'),
					'id' => $song->order_hash_id
				]);
			// }




		}

	}
}
$music->site_title = lang("Purchased Songs");
$music->site_description = $music->config->description;
$music->site_pagename = "purchased";
$music->site_content = loadPage("purchased/content", ['html' => $html]);
