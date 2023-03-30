<?php
$offset             = (isset($_POST['offset']) && is_numeric($_POST['offset']) && $_POST['offset'] > 0) ? secure($_POST['offset']) : 0;
$limit             = (isset($_POST['limit']) && is_numeric($_POST['limit']) && $_POST['limit'] > 0) ? secure($_POST['limit']) : 20;
if ($option == 'validate_ticket') {
	if (!empty($_POST['qr'])) {
		$qr = explode(':',$_POST['qr']);
		if (!empty($qr) && !empty($qr[0]) && !empty($qr[1]) && is_numeric($qr[0])) {
			$id = secure($qr[0]);
			$email = secure($qr[1]);
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		        $errors = 'This e-mail is invalid';
	        }
	        else{
	        	$user = $db->where('email',$email)->getOne(T_USERS,array('id'));
	        	if (!empty($user)) {
	        		$is_valid = $db->where('id',$id)->where('user_id',$user->id)->getValue(T_PURCHAES,'COUNT(*)');
	        		if ($is_valid > 0) {
	        			$user_data = userData($user->id);
	        			unset($user_data->password);
	        			unset($user_data->email_code);
	        			$data = array(
		                    'status' => 200,
		                    'message' => 'Ticket is valid',
		                    'user_data' => $user_data
		                );
	        		}
	        		else{
				        $errors = 'Ticket is invalid';
	        		}
	        	}
	        	else{
			        $errors = 'Wrong email';
	        	}
	        }
		}
		else{
	        $errors = 'wrong qr';
		}
	}
	else{
        $errors = 'qr can not be empty';
	}
}
if ($music->config->event_system != 1) {
	$errors = 'event system is off';
}
if ($option == 'create') {
	if (!empty($_POST['name']) && !empty($_POST['location']) && !empty($_POST['desc']) && !empty($_POST['location']) && in_array($_POST['location'], array('online','real')) && !empty($_POST['start_date']) && !empty($_POST['start_time']) && !empty($_POST['end_date']) && !empty($_POST['end_time']) && !empty($_POST['timezone']) && in_array($_POST['timezone'], array_keys($music->timezones)) && !empty($_POST['sell_tickets']) && in_array($_POST['sell_tickets'], array('no','yes')) && !empty($_FILES['image'])) {

		if(!empty($_FILES['image']) && file_exists($_FILES['image']['tmp_name']) && $_FILES['image']['size'] > $music->config->max_upload){
			$max   = size_format($music->config->max_upload);
			$errors = 'File is too big, Max upload size is : '.$max;
		}
		elseif ($_POST['location'] == 'online' && empty($_POST['online_url'])) {
			$errors = 'URL can not be empty';
		}
		elseif ($_POST['location'] == 'online' && !pt_is_url($_POST['online_url'])) {
			$errors = 'URL invalid';
		}
		elseif ($_POST['location'] == 'real' && empty($_POST['real_address'])) {
			$errors = 'Address can not be empty';
		}
		elseif ($_POST['sell_tickets'] == 'yes') {
			if (empty($_POST['available_tickets']) || !is_numeric($_POST['available_tickets']) || $_POST['available_tickets'] < 1 || empty($_POST['ticket_price']) || !is_numeric($_POST['ticket_price']) || $_POST['ticket_price'] < 1) {
				$errors = 'Tickets available and Ticket Price can not be empty';
			}
		}
		if (empty($errors)) {
			$insert_data = array(
				"user_id" => $music->user->id,
				"name" => Secure($_POST['name']),
				"desc" => Secure($_POST['desc']),
				"start_date" => Secure($_POST['start_date']),
				"start_time" => Secure($_POST['start_time']),
				"end_date" => Secure($_POST['end_date']),
				"end_time" => Secure($_POST['end_time']),
				"timezone" => Secure($_POST['timezone']),
				"time" => time(),
			);
			if ($_POST['location'] == 'online' && !empty($_POST['online_url'])) {
				$insert_data['online_url'] = Secure($_POST['online_url']);
			}
			elseif ($_POST['location'] == 'real' && !empty($_POST['real_address'])) {
				$insert_data['real_address'] = Secure($_POST['real_address']);
			}
			if ($_POST['sell_tickets'] == 'yes') {
				if (!empty($_POST['available_tickets']) && is_numeric($_POST['available_tickets']) && $_POST['available_tickets'] > 0 && !empty($_POST['ticket_price']) && is_numeric($_POST['ticket_price']) && $_POST['ticket_price'] > 0) {
					$insert_data['available_tickets'] = Secure($_POST['available_tickets']);
					$insert_data['ticket_price'] = Secure($_POST['ticket_price']);
				}
			}
	    	$file_info = array(
		        'file' => $_FILES['image']['tmp_name'],
		        'size' => $_FILES['image']['size'],
		        'name' => $_FILES['image']['name'],
		        'type' => $_FILES['image']['type']
		    );
		    $file_upload = ShareFile($file_info);
		    if (empty($file_upload) || empty($file_upload['filename'])) {
		    	$data = array('status' => 400, 'error' => 'Event Cover can not be empty');
			    echo json_encode($data);
			    exit();
		    }
		    $insert_data['image'] = $file_upload['filename'];

		    if (!empty($_FILES['video'])) {
		    	$file_info = array(
			        'file' => $_FILES['video']['tmp_name'],
			        'size' => $_FILES['video']['size'],
			        'name' => $_FILES['video']['name'],
			        'type' => $_FILES['video']['type'],
			        'file_type' => 'video'
			    );
			    if ($music->config->ffmpeg_system != 'on') {
			    	$file_info['allowed'] = 'mp4,m4v,webm,flv,mov,mpeg,mkv';
			    }
			    if ($music->config->ffmpeg_system == 'on') {
			    	$amazone_s3 = $music->config->s3_upload;
                    $ftp_upload = $music->config->ftp_upload;
                    $spaces = $music->config->spaces;
                    $wasabi_storage = $music->config->wasabi_storage;
                    $backblaze_storage = $music->config->backblaze_storage;
                    $google_drive = $music->config->google_drive;
                    $music->config->s3_upload = 'off';
                    $music->config->ftp_upload = 'off';
                    $music->config->spaces = 'off';
                    $music->config->wasabi_storage = 'off';
                    $music->config->google_drive = 'off';
                    $music->config->backblaze_storage = 'off';
			    }
			    $video_file_upload = ShareFile($file_info);
			    if ($music->config->ffmpeg_system == 'on') {
                    $music->config->s3_upload = $amazone_s3;
                    $music->config->ftp_upload = $ftp_upload;
                    $music->config->spaces = $spaces;
                    $music->config->wasabi_storage = $wasabi_storage;
                    $music->config->backblaze_storage = $backblaze_storage;
                    $music->config->google_drive = $google_drive;
			    }
			    if (empty($video_file_upload) || empty($video_file_upload['filename'])) {
			    	$data = array('status' => 400, 'error' => 'Event Video can not be empty');
				    echo json_encode($data);
				    exit();
			    }
			    if ($music->config->ffmpeg_system != 'on') {
			    	$insert_data['video'] = $video_file_upload['filename'];
				}
		    }
		    $insert  = $db->insert(T_EVENTS,$insert_data);
	    	if (!empty($insert)) {
	    		$create_activity = createActivity([
                    'user_id' => $music->user->id,
                    'type' => 'created_event',
                    'event_id' => $insert,
                ]);
	    		$db->where('id',$insert)->update(T_EVENTS,array('hash_id' => uniqid($insert)));
	    		$data['status'] = 200;
	    		$data['message'] = 'Your event has been published successfully';
	    		$event = GetEventById($insert);
	    		unset($event->user_data->password);
	    		unset($event->user_data->email_code);
	    		$data['url'] = $event->url;
	    		$data['data'] = $event;
	    		if ($music->config->ffmpeg_system == 'on') {
	    			RunInBackground($data);
	    			FFMPEGUpload(array('filename' => $video_file_upload['filename'],
	    				               'id' => $insert));
				    
	    		}
	    	}
	    	else{
	    		$errors = 'Error 500 internal server error!';
	    	}
		}
	}
	else{
		if (empty($_POST['name'])) {
			$errors = 'Event name can not be empty';
		}
		elseif (empty($_POST['location'])) {
			$errors = 'Event location can not be empty';
		}
		elseif (empty($_POST['desc'])) {
			$errors = 'Event description can not be empty';
		}
		elseif (empty($_POST['start_date'])) {
			$errors = 'Start date can not be empty';
		}
		elseif (empty($_POST['start_time'])) {
			$errors = 'Start time can not be empty';
		}
		elseif (empty($_POST['end_date'])) {
			$errors = 'End Date can not be empty';
		}
		elseif (empty($_POST['end_time'])) {
			$errors = 'End time can not be empty';
		}
		elseif (empty($_POST['timezone'])) {
			$errors = 'Timezone can not be empty';
		}
		elseif (empty($_FILES['image'])) {
			$errors = 'Event image can not be empty';
		}
		else{
			$errors = 'Please check the details';
		}
	}
}
if ($option == 'edit') {
	if (!empty($_POST['name']) && !empty($_POST['location']) && !empty($_POST['desc']) && !empty($_POST['location']) && in_array($_POST['location'], array('online','real')) && !empty($_POST['start_date']) && !empty($_POST['start_time']) && !empty($_POST['end_date']) && !empty($_POST['end_time']) && !empty($_POST['timezone']) && in_array($_POST['timezone'], array_keys($music->timezones)) && !empty($_POST['sell_tickets']) && in_array($_POST['sell_tickets'], array('no','yes')) && !empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0) {
		$id = secure($_POST['id']);

		$event = GetEventById($id);
		if (empty($event)) {
			$errors = 'Event not found';
		}
		elseif ($user->id != $event->user_data->id && !isAdmin()) {
			$errors = 'You are not the owner';
		}
		elseif(!empty($_FILES['image']) && file_exists($_FILES['image']['tmp_name']) && $_FILES['image']['size'] > $music->config->max_upload){
			$max   = size_format($music->config->max_upload);
    		$errors = 'File is too big, Max upload size is : '.$max;
		}
		elseif ($_POST['location'] == 'online' && empty($_POST['online_url'])) {
			$errors = 'URL can not be empty';
		}
		elseif ($_POST['location'] == 'online' && !pt_is_url($_POST['online_url'])) {
			$errors = 'URL invalid';
		}
		elseif ($_POST['location'] == 'real' && empty($_POST['real_address'])) {
			$errors = 'Address can not be empty';
		}
		elseif ($_POST['sell_tickets'] == 'yes') {
			if (empty($_POST['available_tickets']) || !is_numeric($_POST['available_tickets']) || $_POST['available_tickets'] < 1 || empty($_POST['ticket_price']) || !is_numeric($_POST['ticket_price']) || $_POST['ticket_price'] < 1) {
				$errors = 'Tickets available and Ticket Price can not be empty';
			}
		}
		if (empty($errors)) {
			$insert_data = array(
				"name" => Secure($_POST['name']),
				"desc" => Secure($_POST['desc']),
				"start_date" => Secure($_POST['start_date']),
				"start_time" => Secure($_POST['start_time']),
				"end_date" => Secure($_POST['end_date']),
				"end_time" => Secure($_POST['end_time']),
				"timezone" => Secure($_POST['timezone']),
			);
			if ($_POST['location'] == 'online' && !empty($_POST['online_url'])) {
				$insert_data['online_url'] = Secure($_POST['online_url']);
			}
			elseif ($_POST['location'] == 'real' && !empty($_POST['real_address'])) {
				$insert_data['real_address'] = Secure($_POST['real_address']);
			}
			if ($_POST['sell_tickets'] == 'yes') {
				if (!empty($_POST['available_tickets']) && is_numeric($_POST['available_tickets']) && $_POST['available_tickets'] > 0 && !empty($_POST['ticket_price']) && is_numeric($_POST['ticket_price']) && $_POST['ticket_price'] > 0) {
					$insert_data['available_tickets'] = Secure($_POST['available_tickets']);
					$insert_data['ticket_price'] = Secure($_POST['ticket_price']);
				}
			}
			if (!empty($_FILES['image'])) {
				$file_info = array(
			        'file' => $_FILES['image']['tmp_name'],
			        'size' => $_FILES['image']['size'],
			        'name' => $_FILES['image']['name'],
			        'type' => $_FILES['image']['type']
			    );
			    $file_upload = ShareFile($file_info);
			    if (empty($file_upload) || empty($file_upload['filename'])) {
			    	$data = array('status' => 400, 'error' => 'Event Cover can not be empty');
				    echo json_encode($data);
				    exit();
			    }
			    $insert_data['image'] = $file_upload['filename'];
			}
		    	

		    if (!empty($_FILES['video'])) {
		    	$file_info = array(
			        'file' => $_FILES['video']['tmp_name'],
			        'size' => $_FILES['video']['size'],
			        'name' => $_FILES['video']['name'],
			        'type' => $_FILES['video']['type'],
			        'file_type' => 'video'
			    );
			    if ($music->config->ffmpeg_system != 'on') {
			    	$file_info['allowed'] = 'mp4,m4v,webm,flv,mov,mpeg,mkv';
			    }
			    if ($music->config->ffmpeg_system == 'on') {
			    	$amazone_s3 = $music->config->s3_upload;
                    $ftp_upload = $music->config->ftp_upload;
                    $spaces = $music->config->spaces;
                    $wasabi_storage = $music->config->wasabi_storage;
                    $backblaze_storage = $music->config->backblaze_storage;
                    $google_drive = $music->config->google_drive;
                    $music->config->s3_upload = 'off';
                    $music->config->ftp_upload = 'off';
                    $music->config->spaces = 'off';
                    $music->config->wasabi_storage = 'off';
                    $music->config->backblaze_storage = 'off';
                    $music->config->google_drive = 'off';
			    }
			    $video_file_upload = ShareFile($file_info);
			    if ($music->config->ffmpeg_system == 'on') {
                    $music->config->s3_upload = $amazone_s3;
                    $music->config->ftp_upload = $ftp_upload;
                    $music->config->spaces = $spaces;
                    $music->config->wasabi_storage = $wasabi_storage;
                    $music->config->backblaze_storage = $backblaze_storage;
                    $music->config->google_drive = $google_drive;
			    }
			    if (empty($video_file_upload) || empty($video_file_upload['filename'])) {
			    	$data = array('status' => 400, 'error' => 'Event Video can not be empty');
				    echo json_encode($data);
				    exit();
			    }
			    if ($music->config->ffmpeg_system == 'on') {
			    	$db->where('id',$event->id)->update(T_EVENTS,array('240p' => 0,
			                                                           '360p' => 0,
			                                                           '480p' => 0,
			                                                           '720p' => 0,
			                                                           '1080p' => 0,
			                                                           '2048p' => 0,
			                                                           '4096p' => 0));
				    FFMPEGUpload(array('filename' => $video_file_upload['filename'],
				                       'id' => $id));
				    // $explode_video = explode('_video', $video_file_upload['filename']);
				    // $video_file_full_path = dirname(dirname(__DIR__)).'/'.$video_file_upload['filename'];
				    // $dir         = dirname(dirname(__DIR__));
				    // $video_path_240 = $explode_video[0] . "_video_240p_converted.mp4";
				    // $insert_data['video'] = $video_path_240;
				}
				else{
					$insert_data['video'] = $video_file_upload['filename'];
				}
		    }
		    $insert  = $db->where('id',$event->id)->update(T_EVENTS,$insert_data);
	    	if (!empty($insert)) {
	    		$event = GetEventById($id);
	    		$data['status'] = 200;
	    		$data['message'] = lang('Your event has been updated successfully');
	    		$data['url'] = $event->url;
	    		unset($event->user_data->password);
	    		unset($event->user_data->email_code);
	    		$data['data'] = $event;
	    	}
	    	else{
	    	  $errors = 'Error 500 internal server error!';
	    	}
		}
	}
	else{
		if (empty($_POST['name'])) {
			$errors = 'Event name can not be empty';
		}
		elseif (empty($_POST['location'])) {
			$errors = 'Event location can not be empty';
		}
		elseif (empty($_POST['desc'])) {
			$errors = 'Event description can not be empty';
		}
		elseif (empty($_POST['start_date'])) {
			$errors = 'Start date can not be empty';
		}
		elseif (empty($_POST['start_time'])) {
			$errors = 'Start time can not be empty';
		}
		elseif (empty($_POST['end_date'])) {
			$errors = 'End Date can not be empty';
		}
		elseif (empty($_POST['end_time'])) {
			$errors = 'End time can not be empty';
		}
		elseif (empty($_POST['timezone'])) {
			$errors = 'Timezone can not be empty';
		}
		else{
			$errors = 'Please check the details';
		}
	}
}
if ($option == 'buy') {
	if (!empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0) {
		$id = Secure($_POST['id']);
		$event = GetEventById($id);
		if (!empty($event)) {
			if ($event->ticket_price > 0) {
				if ($music->user->org_wallet >= $event->ticket_price && $event->available_tickets > 0) {
					
					$commission = 0;
					if (!empty($music->config->event_commission)) {
						$commission = round((($music->config->event_commission * $event->ticket_price) / 100), 2);
					}
					$db->where('id',$music->user->id)->update(T_USERS,array('wallet' => $db->dec($event->ticket_price)));
					$db->where('id',$event->user_id)->update(T_USERS,array('wallet' => $db->inc($event->ticket_price - $commission)));
					$db->where('id',$id)->update(T_EVENTS,array('available_tickets' => $db->dec(1)));
						
					$purchase_id = $db->insert(T_PURCHAES,array('user_id' => $music->user->id,
	                                             'event_id' => $id,
	                                             'price' => $event->ticket_price,
	                                             'title' => $event->name,
	                                             'commission' => $commission,
	                                             'final_price' => $event->ticket_price - $commission,
	                                             'time' => time()));
					$create_activity = createActivity([
	                    'user_id' => $music->user->id,
	                    'type' => 'ticket_event',
	                    'event_id' => $id,
	                ]);
					$create_notification = createNotification([
                            'notifier_id' => $music->user->id,
                            'recipient_id' => $event->user_id,
                            'type' => 'bought_ticket',
                            'url' => $event->url
                        ]);
					$data['status'] = 200;
					$data['message'] = lang('payment successfully done');
					$data['purchase_id'] = $purchase_id;
				}
				else{
					if ($event->available_tickets < 1) {
						$errors = "There is no available tickets";
					}
					elseif ($music->user->org_wallet < $event->ticket_price) {
						$errors = "You don't have enough wallet Please top up your wallet";
					}
				}
			}
			else{
				$errors = 'This event is free';
			}
		}
		else{
			$errors = 'Event not found';
		}
	}
	else{
		$errors = 'id can not be empty';
	}
}
if ($option == 'join') {
	if (!empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0 && !empty($_POST['type']) && in_array($_POST['type'], array('join','unjoin'))) {
		$id = Secure($_POST['id']);
		$event = GetEventById($id);
		$is_joined = $db->where('event_id',$id)->where('user_id',$music->user->id)->getValue(T_EVENTS_JOINED,'COUNT(*)');
		if (!empty($event)) {
			if ($is_joined > 0) {
				$db->where('event_id',$id)->where('user_id',$music->user->id)->delete(T_EVENTS_JOINED);
				deleteActivity([
				    'user_id' => $music->user->id,
					'type' => 'joined_event',
					'event_id' => $id,
				]);
				$data['type'] = 'unjoin';
			}
			else{
				$db->insert(T_EVENTS_JOINED,array('user_id' => $music->user->id,
				                                          'event_id' => $id,
				                                          'time' => time()));
				$create_activity = createActivity([
                    'user_id' => $music->user->id,
                    'type' => 'joined_event',
                    'event_id' => $id,
                ]);
				$create_notification = createNotification([
                            'notifier_id' => $music->user->id,
                            'recipient_id' => $event->user_id,
                            'type' => 'event_joined',
                            'url' => $event->url
                        ]);
				$data['type'] = 'join';
			}
		}
			
		$data['status'] = 200;

	}
	else{
		$errors = 'id , type can not be empty';
	}
}
if ($option == 'download') {
	if (!empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0) {
		$id = secure($_POST['id']);
		$music->purchase = $db->where('id',$id)->where('user_id',$music->user->id)->getOne(T_PURCHAES);
		if (!empty($music->purchase)) {
			$music->event = GetEventById($music->purchase->event_id);
			if (!empty($music->event)) {
				$html = loadPage('pdf/ticket_api',[
					'theme_url' => $config['theme_url']
				]);
				if (!file_exists('upload/files')) {
			        @mkdir('upload/files', 0777, true);
			    }
			    if (!file_exists('upload/files/tickets')) {
			        @mkdir('upload/files/tickets', 0777, true);
			    }
			    $dir         = "upload/files/tickets";
			    $hash    = $dir . '/' . generateKey() . '_' . date('d') . '_' . md5(time()) . "_file.html";
			    $file = fopen($hash, 'w');
			    fwrite($file, $html);
			    fclose($file);
				$data['status'] = 200;
				$data['link'] = $music->config->site_url.'/'.$hash;
				$data['html'] = $html;
			}
			else{
				$errors = 'Event not found';
			}
		}
		else{
			$errors = 'You are not purchased';
		}
	}
	else{
		$errors = 'id can not be empty';
	}		
}
if ($option == 'get_events') {
	$query_text = "";
	if (!empty($offset)) {
		$query_text = " AND `id` < '". $offset ."' ";
	}

    $results = $db->rawQuery("SELECT * FROM `".T_EVENTS."` WHERE `end_date` >= CURDATE() ".$query_text." ORDER BY `id` DESC LIMIT ".$limit.";");
    $array = array();
    foreach($results as $key => $value){
    	$event = GetEventById($value->id);
    	unset($event->user_data->password);
	    unset($event->user_data->email_code);
	    $array[] = $event;
    }
    $data['status'] = 200;
    $data['data'] = $array;
}
if ($option == 'get_my_events') {
	$user_id = $music->user->id;
	if (!empty($_POST['user_id']) && is_numeric($_POST['user_id']) && $_POST['user_id'] > 0) {
		$user_id = secure($_POST['user_id']);
	}
	$query_text = "";
	if (!empty($offset)) {
		$query_text = " AND `id` < '". $offset ."' ";
	}
	$results = $db->rawQuery("SELECT * FROM `".T_EVENTS."` WHERE `user_id` = '". $user_id ."' ".$query_text." ORDER BY `id` DESC LIMIT ".$limit.";");
    $array = array();
    foreach($results as $key => $value){
    	$event = GetEventById($value->id);
		unset($event->user_data->password);
	    unset($event->user_data->email_code);
	    $array[] = $event;
    }
    $data['status'] = 200;
    $data['data'] = $array;
}
if ($option == 'get_event_by_id') {
	if (!empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0) {
		$id = secure($_POST['id']);
		$event = GetEventById($id);
		if (!empty($event)) {
			unset($event->user_data->password);
		    unset($event->user_data->email_code);
		}
		$data['status'] = 200;
	    $data['data'] = $event;
	}
	else{
		$errors = 'id can not be empty';
	}
}
if ($option == 'get_joined') {
	$query_text = "";
	if (!empty($offset)) {
		$query_text = " AND `event_id` < '". $offset ."' ";
	}
	$results = $db->rawQuery("SELECT * FROM `".T_EVENTS_JOINED."` WHERE `user_id` = '".$music->user->id."' ".$query_text." ORDER BY `event_id` DESC LIMIT 10;");
    $array = array();
    foreach($results as $key => $value){
    	$event = GetEventById($value->event_id);
		unset($event->user_data->password);
	    unset($event->user_data->email_code);
	    $array[] = $event;
    }
    $data['status'] = 200;
    $data['data'] = $array;
}
if ($option == 'delete') {
	if (!empty($_POST["id"]) && is_numeric($_POST["id"]) && $_POST["id"] > 0) {
        $id = secure($_POST["id"]);

        $event = GetEventById($id);
        if (empty($event)) {
        	$errors = "Event not found";
        } elseif ($user->id != $event->user_data->id && !isAdmin()) {
        	$errors = "You are not the owner";
        }
        if (empty($errors)) {
            if (!empty($event->org_image)) {
                @unlink($event->org_image);
                PT_DeleteFromToS3($event->org_image);
            }
            if (!empty($event->org_video)) {
                @unlink($event->org_video);
                PT_DeleteFromToS3($event->org_video);
            }
            $db->where("event_id", $event->id)->delete(T_EVENTS_JOINED);
            $db->where("id", $event->id)->delete(T_EVENTS);
            deleteActivity([
                "user_id" => $music->user->id,
                "type" => "created_event",
                "event_id" => $event->id,
            ]);
            $data["status"] = 200;
            $data['message'] = 'Your event has been deleted successfully';
        }
    } else {
        $errors = 'id can not be empty';
    }
}



if (!empty($errors)) {
	$data = array('status' => 400, 'error' => $errors);
	header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}