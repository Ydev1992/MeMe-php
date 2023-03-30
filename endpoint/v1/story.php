<?php
$errors = '';
if ($music->config->story_system != 'on') {
	$errors = 'story system is off';
}
if ($option == 'create') {
	if (!empty($_POST['who']) && in_array($_POST['who'], array('followers','all')) && !empty($_FILES['image']) && !empty($_FILES['audio'])) {
		if(!empty($_FILES['image']) && file_exists($_FILES['image']['tmp_name']) && $_FILES['image']['size'] > $music->config->max_upload){
			$max   = size_format($music->config->max_upload);
    		$errors = ('File is too big, Max upload size is'.": $max");
		}
		elseif(!empty($_FILES['audio']) && file_exists($_FILES['audio']['tmp_name']) && $_FILES['audio']['size'] > $music->config->max_upload){
			$max   = size_format($music->config->max_upload);
    		$errors = ('File is too big, Max upload size is'.": $max");
		}
		elseif (!empty($_POST['url']) && !pt_is_url($_POST['url'])) {
			$errors = 'URL not valid';
		}
		elseif ($_POST['who'] == 'all' && $music->user->org_wallet < $music->config->story_price) {
			$errors = "You don't have enough wallet Please top up your wallet";
		}
		if (empty($errors)) {
			$insert_data = array(
				"user_id" => $music->user->id,
				"time" => time(),
			);
	    	$file_info = array(
		        'file' => $_FILES['image']['tmp_name'],
		        'size' => $_FILES['image']['size'],
		        'name' => $_FILES['image']['name'],
		        'type' => $_FILES['image']['type']
		    );
		    $file_upload = ShareFile($file_info);
		    if (empty($file_upload) || empty($file_upload['filename'])) {
		    	$data = array('status' => 400, 'error' => 'Something went wrong when upload Event Cover');
			    echo json_encode($data);
			    exit();
		    }
		    $insert_data['image'] = $file_upload['filename'];



		    $file_info = array(
		        'file' => $_FILES['audio']['tmp_name'],
		        'size' => $_FILES['audio']['size'],
		        'name' => $_FILES['audio']['name'],
		        'type' => $_FILES['audio']['type'],
		        'allowed' => 'mp3,ogg,wav,opus,oga'
		    );
		    if ($music->config->ffmpeg_system == "off") {
		        $file_info['allowed'] = 'mp3';
		    }
		    if ($music->config->ffmpeg_system == "on") {
		        $music->config->s3_upload = 'off';
		        $music->config->ftp_upload = 'off';
		        $music->config->spaces = 'off';
		        $music->config->backblaze_storage = 'off';
		        $music->config->wasabi_storage = 'off';
		        $music->config->google_drive = 'off';
		    }
		    $file_upload = shareFile($file_info);
		    if (!empty($file_upload['filename'])) {
		    	$insert_data['audio'] = $file_upload['filename'];
		    } else if (!empty($file_upload['error'])) {
		    	$data = array('status' => 400, 'error' => $file_upload['error']);
			    echo json_encode($data);
			    exit();
		    }
		    if ($_POST['who'] == 'all') {
		    	$insert_data['paid'] = 1;
		    }
		    else{
		    	$insert_data['active'] = 1;
		    }
		    if (!empty($_POST['url']) && pt_is_url($_POST['url'])) {
		    	$insert_data['url'] = Secure($_POST['url']);
		    }


		    $insert  = $db->insert(T_STORY,$insert_data);
	    	if (!empty($insert)) {
	    		$_SESSION['uploads'][] = $file_upload['filename'];
	    		$data['status'] = 200;
	    		$data['story_id'] = $insert;
	    		$data['audio'] = getMedia($insert_data['audio']);
	    		$story = GetStory($insert);
	    		unset($story->user_data->password);
	    		unset($story->user_data->email_code);
	    		unset($story->views_users);
	    		$data['data'] = $story;
	    		if ($_POST['who'] != 'all') {
	    			$data['payment_modal'] = 'no';
		    		$data['message'] = 'Your story has been published successfully';
		    	}
		    	else{
		    		$data['payment_modal'] = 'yes';
		    		$data['message'] = 'Your story has been uploaded successfully to publish it please pay';
		    	}
	    	}
	    	else{
	    	  $errors = 'Something went wrong';
	    	}
		}
	}
	else{
		if (empty($_POST['who'])) {
            $errors = 'Please who can see the story';
        }
        elseif(empty($_FILES['image'])){
        	$errors = 'Please select a story image';
        }
        elseif(empty($_FILES['audio'])){
        	$errors = 'Please select a story song';
        }
        else{
        	$errors = 'Please check the details';
        }
	}
}
if ($option == 'pay') {
	if (!empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0 && $music->user->org_wallet >= $music->config->story_price) {
		$id = Secure($_POST['id']);
		$story = $db->where('id',$id)->where('user_id',$music->user->id)->where('active',0)->getOne(T_STORY);
		if (!empty($story)) {
			$updateUser = $db->where('id', $music->user->id)->update(T_USERS, ['wallet' => $db->dec($music->config->story_price)]);
			$db->where('id',$id)->where('user_id',$music->user->id)->update(T_STORY,array('active' => 1));
			$data['message'] = 'payment successfully done';
			$data['status'] = 200;
		}
		else{
			$errors = 'Story not found or its not active';
		}
	}
	else{
		if ($music->user->org_wallet < $music->config->story_price) {
			$errors = "You don't have enough wallet Please top up your wallet";
		}
		else{
			$errors = 'id can not be empty';
		}
	}
}
if ($option == 'delete') {
	$data['status'] = 400;
    if (!empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0) {
    	$story = GetStory(Secure($_POST['id']));
    	if (!empty($story) && $story->user_id == $music->user->id) {
    		@unlink($story->org_image);
    		@unlink($story->org_audio);
    		PT_DeleteFromToS3($story->org_image);
    		PT_DeleteFromToS3($story->org_audio);
    		$db->where('id',$story->id)->delete(T_STORY);
    		$db->where('story_id',$story->id)->delete(T_STORY_SEEN);
    		$data['status'] = 200;
    	}
    	else{
    		$errors = 'story not found or you are not the owner';
    	}
    }
    else{
		$errors = 'id can not be empty';
	}
}
if ($option == 'get') {
	$stories = GetAllFollowStories();
	$info = array();
	if (!empty($stories)) {
		foreach ($stories as $key => $value) {
			unset($value->user_data->password);
			unset($value->user_data->email_code);
			unset($value->views_users);
			$info[] = $value;
		}
	}
	$data['status'] = 200;
	$data['data'] = $info;
}
if ($option == 'start') {
	if (!empty($_POST['user_id']) && is_numeric($_POST['user_id']) && $_POST['user_id'] > 0 && !empty($_POST['story_id']) && is_numeric($_POST['story_id']) && $_POST['story_id'] > 0) {
		$user_id = Secure($_POST['user_id']);
		$story_id = Secure($_POST['story_id']);
		setcookie('next_user_id',0, time() + (60 * 60),'/');
		setcookie('next_story_id',0, time() + (60 * 60),'/');
		$info = array();
		$music->story = StartFollowStories(array('user_id' => $user_id));
		if (!empty($music->story->user_data)) {
			unset($music->story->user_data->password);
			unset($music->story->user_data->email_code);
		}
		if (!empty($music->story->next) && !empty($music->story->next->user_data)) {
			unset($music->story->next->user_data->password);
			unset($music->story->next->user_data->email_code);
			unset($music->story->next->views_users);
		}
		if (!empty($music->story->pre) && !empty($music->story->pre->user_data)) {
			unset($music->story->pre->user_data->password);
			unset($music->story->pre->user_data->email_code);
			unset($music->story->pre->views_users);
		}
		unset($music->story->views_users);
		$data['status'] = 200;
		$data['data'] = $music->story;
	}
	else{
		$errors = 'user_id , story_id can not be empty';
	}
}
if ($option == 'next') {
	if (!empty($_POST['user_id']) && is_numeric($_POST['user_id']) && $_POST['user_id'] > 0 && !empty($_POST['story_id']) && is_numeric($_POST['story_id']) && $_POST['story_id'] > 0 && !empty($_POST['next_user_id']) && is_numeric($_POST['next_user_id']) && $_POST['next_user_id'] > 0 && !empty($_POST['next_story_id']) && is_numeric($_POST['next_story_id']) && $_POST['next_story_id'] > 0) {
		$user_id = Secure($_POST['user_id']);
		$story_id = Secure($_POST['story_id']);
		$next_user_id = Secure($_POST['next_user_id']);
		$next_story_id = Secure($_POST['next_story_id']);
		$paid = false;
		$hour = time() - (60 * 60);
		if (IS_LOGGED) {
			$follow_seen_count = $db->where(" `story_owner_id` IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') AND `user_id` = '".$music->user->id."' AND `time` > '".$hour."' ")->getValue(T_STORY_SEEN,'COUNT(*)');
		    if (intval($follow_seen_count) >= 5) {
		    	$can_see_paid = $db->where("`story_owner_id` NOT IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') AND `user_id` = '".$music->user->id."' AND `story_owner_id` != '".$music->user->id."' AND `user_id` = '".$music->user->id."' AND `paid` = '1' AND `time` > '".$hour."'")->getValue(T_STORY_SEEN,'COUNT(*)');
		        if (intval($can_see_paid) < 4) {
		            $paid_stories = $db->where(" `user_id` NOT IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') AND `id` NOT IN (SELECT `story_id` FROM " . T_STORY_SEEN . " WHERE `user_id` = '".$music->user->id."') AND `paid` = '1'")->orderBy("RAND()")->get(T_STORY,2);
		            if (!empty($paid_stories) && !empty($paid_stories[0])) {
		            	if ($_COOKIE['next_user_id'] == 0 && $_COOKIE['next_story_id'] == 0) {
		            		setcookie('next_user_id',$next_user_id, time() + (60 * 60),'/');
							setcookie('next_story_id',$next_story_id, time() + (60 * 60),'/');
		            	}
		            	
			
		            	$paid = true;
				        $db->insert(T_STORY_SEEN,array('user_id' => $music->user->id,
				                                       'story_id' => $paid_stories[0]->id,
				                                       'story_owner_id' => $paid_stories[0]->user_id,
				                                       'time' => time(),
				                                       'paid' => $paid_stories[0]->paid));
				        if (count($paid_stories) > 1) {
				            $next = $paid_stories[1];
				        }
				        else{
				            $next = $db->where(" `user_id` IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') AND `user_id` != '".$paid_stories[0]->user_id."'")->orderBy('id', 'DESC')->groupBy("user_id")->getOne(T_STORY);
				            if (!empty($next)) {
				                $next = $db->where(" `user_id` IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') AND user_id = '".$next->user_id."'")->orderBy('id', 'DESC')->getOne(T_STORY);
				            }
				        }
				        $music->story = GetStory($paid_stories[0]->id);
				        if (!empty($next)) {
				            $music->story->next = GetStory($next->id);
				        }
				    }
		        }
		        else{
		        	if (!empty($_COOKIE['next_user_id']) && is_numeric($_COOKIE['next_user_id']) && $_COOKIE['next_user_id'] > 0 && !empty($_COOKIE['next_story_id']) && is_numeric($_COOKIE['next_story_id']) && $_COOKIE['next_story_id'] > 0) {
	            		$next_user_id = Secure($_COOKIE['next_user_id']);
	            		$next_story_id = Secure($_COOKIE['next_story_id']);
	            		setcookie('next_user_id',0, time() + (60 * 60),'/');
						setcookie('next_story_id',0, time() + (60 * 60),'/');
	            	}
		        }
		    }
		}
		if (!$paid) {
			if (!empty($_COOKIE['next_user_id']) && is_numeric($_COOKIE['next_user_id']) && $_COOKIE['next_user_id'] > 0 && !empty($_COOKIE['next_story_id']) && is_numeric($_COOKIE['next_story_id']) && $_COOKIE['next_story_id'] > 0) {

        		$next_user_id = Secure($_COOKIE['next_user_id']);
        		$next_story_id = Secure($_COOKIE['next_story_id']);
        		setcookie('next_user_id',0, time() + (60 * 60),'/');
				setcookie('next_story_id',0, time() + (60 * 60),'/');
        	}
			$music->story = NextFollowStories(array('user_id' => $user_id,
		                                            'story_id' => $story_id,
		                                            'next_user_id' => $next_user_id,
		                                            'next_story_id' => $next_story_id));
		}
		$data['data'] = array();
			
		if (!empty($music->story)) {
			if (!empty($music->story->user_data)) {
				unset($music->story->user_data->password);
				unset($music->story->user_data->email_code);
			}
			if (!empty($music->story->next) && !empty($music->story->next->user_data)) {
				unset($music->story->next->user_data->password);
				unset($music->story->next->user_data->email_code);
				unset($music->story->next->views_users);
			}
			if (!empty($music->story->pre) && !empty($music->story->pre->user_data)) {
				unset($music->story->pre->user_data->password);
				unset($music->story->pre->user_data->email_code);
				unset($music->story->pre->views_users);
			}
			unset($music->story->views_users);
			$data['data'] = $music->story;
		}
		$data['status'] = 200;
	}
	else{
		$errors = 'user_id , story_id , next_user_id , next_story_id can not be empty';
	}
}
if ($option == 'previous') {
	if (!empty($_POST['user_id']) && is_numeric($_POST['user_id']) && $_POST['user_id'] > 0 && !empty($_POST['story_id']) && is_numeric($_POST['story_id']) && $_POST['story_id'] > 0 && !empty($_POST['pre_user_id']) && is_numeric($_POST['pre_user_id']) && $_POST['pre_user_id'] > 0 && !empty($_POST['pre_story_id']) && is_numeric($_POST['pre_story_id']) && $_POST['pre_story_id'] > 0) {
		$user_id = Secure($_POST['user_id']);
		$story_id = Secure($_POST['story_id']);
		$pre_user_id = Secure($_POST['pre_user_id']);
		$pre_story_id = Secure($_POST['pre_story_id']);
		$music->story = PreviousFollowStories(array('user_id' => $user_id,
			                                        'story_id' => $story_id,
			                                        'pre_user_id' => $pre_user_id,
	                                                'pre_story_id' => $pre_story_id));
		$data['data'] = array();
		if (!empty($music->story)) {
			if (!empty($music->story->user_data)) {
				unset($music->story->user_data->password);
				unset($music->story->user_data->email_code);
			}
			if (!empty($music->story->next) && !empty($music->story->next->user_data)) {
				unset($music->story->next->user_data->password);
				unset($music->story->next->user_data->email_code);
				unset($music->story->next->views_users);
			}
			if (!empty($music->story->pre) && !empty($music->story->pre->user_data)) {
				unset($music->story->pre->user_data->password);
				unset($music->story->pre->user_data->email_code);
				unset($music->story->pre->views_users);
			}
			unset($music->story->views_users);
			$data['data'] = $music->story;
		}
		$data['status'] = 200;
	}
	else{
		$errors = 'user_id , story_id , pre_user_id , pre_story_id can not be empty';
	}
}
if ($option == 'story_views') {
	$offset             = (isset($_POST['offset']) && is_numeric($_POST['offset']) && $_POST['offset'] > 0) ? secure($_POST['offset']) : 0;
    $limit             = (isset($_POST['limit']) && is_numeric($_POST['limit']) && $_POST['limit'] > 0) ? secure($_POST['limit']) : 20;
	$data['status'] = 400;
    if (!empty($_POST['story_id']) && is_numeric($_POST['story_id']) && $_POST['story_id'] > 0) {
    	$users = GetViewsUsers(array('story_id' => Secure($_POST['story_id']),
                                     'offset' => $offset,
                                     'limit' => $limit));
        $info = array();
        if (!empty($users)) {
            foreach ($users as $key => $music->view) {
            	unset($music->view->user_data->password);
				unset($music->view->user_data->email_code);
            	$info[] = $music->view;
            }
        }
        $data['status'] = 200;
        $data['data'] = $info;
    }
    else{
		$errors = 'story_id , last_view can not be empty';
	}
}

if (!empty($errors)) {
	$data = array('status' => 400, 'error' => $errors);
    echo json_encode($data);
    exit();
}