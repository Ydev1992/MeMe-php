<?php  
if (IS_LOGGED == false) {
    exit("You ain't logged in!");
}

$data['status'] = 400;
if ($option == 'get_request') {
	$html = '';
	$requests = $db->where('artist_id',$music->user->id)->where('approved',0)->orderBy('id', 'DESC')->get(T_ARTISTS_TAGS);
	$db->where('artist_id',$music->user->id)->update(T_ARTISTS_TAGS,array('seen' => time()));
	if (!empty($requests)) {
		foreach ($requests as $key => $value) {
			$user = userData($value->user_id);
			$song = songData($value->track_id);
			if (!empty($user) && !empty($song)) {
				$html .= loadPage('header/request-list', [
					'USER_DATA' => $user,
					'n_time' => time_Elapsed_String($value->time),
	                'ns_time' => date('c',$value->time),
					'n_text' => lang('Tagged You'),
					'n_url' => $song->url,
					'n_a_url' => 'track/'.$song->audio_id,
					'ID' => $value->id,
				]);
			}
		}
		if (!empty($html)) {
			$data = [
				'status' => 200,
				'html' => $html
			];
		}
	}
}
if ($option == 'get') {
	$countNotSeen = getNotifications('count', false);
	if ($countNotSeen > 0) {
		$getNotifications = getNotifications('fetch', false);
	} else {
		$getNotifications = getNotifications();
	}
	if (!empty($getNotifications)) {
		$html = '';
		foreach ($getNotifications as $key => $notification) {
			$music->t_thumbnail = '';
			$notifierData = userData($notification->notifier_id);
            $notificationtext = ($notification->type == 'admin_notification') ? $notification->text : getNotificationTextFromType($notification->type);
            if ($notification->type == 'your_song_is_ready') {
            	$pieces = explode("/", $notification->url);
            	if (!empty($pieces) && !empty($pieces[1])) {
            		$audio = $db->where('audio_id', $pieces[1])->getOne(T_SONGS);
            		if (!empty($audio)) {
            			$music->t_thumbnail = getMedia($audio->thumbnail);
            		}
            	}
            }

			$html .= loadPage('header/notification-list', [
				'USER_DATA' => $notifierData,
				'n_time' => time_Elapsed_String($notification->time),
                'ns_time' => date('c',$notification->time),
                'n_type' => $notification->type,
				'uri' => $notification->url,
				'n_text' => str_replace('%d',$notification->text, $notificationtext),
				'n_url' => ($notification->type == 'follow_user') ? $notifierData->url : getLink($notification->url),
				'n_a_url' => ($notification->type == 'follow_user') ? $notifierData->username : $notification->url,
			]); 
		}
		if (!empty($html)) {
			$db->where('recipient_id', $user->id)->update(T_NOTIFICATION, ['seen' => time()]);
			$data = [
				'status' => 200,
				'html' => $html
			];
		}
	}
}

if ($option == 'count_unseen') {
	$data = [
		'status' => 200,
		'count' => getNotifications('count', false),
        'msgs' => $db->where('to_id', $user->id)->where('seen', 0)->getValue(T_MESSAGES, "COUNT(*)"),
        'request' => $db->where('artist_id',$music->user->id)->where('seen',0)->getValue(T_ARTISTS_TAGS,'COUNT(*)')
	];

	$payment_data           = $db->objectBuilder()->where('user_id',$music->user->id)->where('method_name', 'coinpayments')->orderBy('id','DESC')->getOne(T_PENDING_PAYMENTS);
	$coinpayments_txn_id = '';
    if (!empty($payment_data)) {
        $coinpayments_txn_id = $payment_data->payment_data;
    }


	if (!empty($coinpayments_txn_id)) {
        $result = coinpayments_api_call(array('key' => $music->config->coinpayments_public_key,
                                              'version' => '1',
                                              'format' => 'json',
                                              'cmd' => 'get_tx_info',
                                              'full' => '1',
                                              'txid' => $coinpayments_txn_id));
        if (!empty($result) && $result['status'] == 200) {
            if ($result['data']['status'] == -1) {
                $db->where('user_id', $music->user->id)->where('payment_data', $coinpayments_txn_id)->delete(T_PENDING_PAYMENTS);
                $notif_data = array(
		            'recipient_id' => $music->user->id,
		            'type' => 'coinpayments_canceled',
		            'admin' => 1,
		            'url' => 'ads',
		            'time' => time()
		        );
		        $db->insert(T_NOTIFICATION,$notif_data);
            }
            elseif ($result['data']['status'] == 100) {
				$amount   = $result['data']['checkout']['amountf'];
				$updateUser = $db
                        ->where("id", $music->user->id)
                        ->update(T_USERS, ["wallet" => $db->inc($amount)]);
                CreatePayment([
                    "user_id" => $user->id,
                    "amount" => $amount,
                    "type" => "WALLET",
                    "pro_plan" => 0,
                    "info" => "Replenish My Balance",
                    "via" => "Coinpayments",
                ]);
		        $notif_data = array(
		            'recipient_id' => $music->user->id,
		            'type' => 'coinpayments_approved',
		            'admin' => 1,
		            'url' => 'ads',
		            'time' => time()
		        );
		        $db->insert(T_NOTIFICATION,$notif_data);
            }
        }
    }
}
?>