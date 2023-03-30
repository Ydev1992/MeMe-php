<?php
if ($music->config->story_system != 'on') {
	header('Location: ' . $site_url);
	exit;
}
$data['status'] = 400;
if ($option == 'create' && IS_LOGGED) {
	runPlugin('PreStoryCreate', $_REQUEST);
	if (!empty($_POST['who']) && in_array($_POST['who'], array('followers','all')) && !empty($_FILES['image']) && !empty($_FILES['audio'])) {
		if(!empty($_FILES['image']) && file_exists($_FILES['image']['tmp_name']) && $_FILES['image']['size'] > $music->config->max_upload){
			$max   = size_format($music->config->max_upload);
    		$data['message'] = (lang('File is too big, Max upload size is').": $max");
		}
		elseif(!empty($_FILES['audio']) && file_exists($_FILES['audio']['tmp_name']) && $_FILES['audio']['size'] > $music->config->max_upload){
			$max   = size_format($music->config->max_upload);
    		$data['message'] = (lang('File is too big, Max upload size is').": $max");
		}
		elseif (!empty($_POST['url']) && !pt_is_url($_POST['url'])) {
			$data['message'] = lang('URL not valid');
		}
		elseif ($_POST['who'] == 'all' && $music->user->org_wallet < $music->config->story_price) {
			$data['message'] = lang("You don't have enough wallet")." <a href='".getLink("settings/".$music->user->username."/wallet")."'>".lang("Please top up your wallet")."</a>";
		}
		if (empty($data['message'])) {
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
		    	$data['message'] = lang('Event Cover can not be empty');
		    	header('Content-Type: application/json');
				echo json_encode($data);
				exit();
		    }
		    $insert_data['image'] = $file_upload['filename'];

			$insertToMediaHistory = $db->insert(T_UPLOADS, [
				"file_name" => $file_upload['filename'],
				"user_id" => $user->id,
				"original_name" => $file_upload['name']
			]);

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
		        $data = array('status' => 400, 'message' => $file_upload['error']);
		        header('Content-Type: application/json');
				echo json_encode($data);
				exit();
		    }
		    if ($_POST['who'] == 'all') {
		    	$insert_data['paid'] = 1;
		    	if ($music->config->story_price == 0) {
		    		$insert_data['active'] = 1;
		    	}
		    }
		    else{
		    	$insert_data['active'] = 1;
		    }
		    if (!empty($_POST['url']) && pt_is_url($_POST['url'])) {
		    	$insert_data['url'] = Secure($_POST['url']);
		    }


		    $insert  = $db->insert(T_STORY,$insert_data);
	    	if (!empty($insert)) {
				runPlugin('AfterStoryCreated', $insert_data);
				$insertToMediaHistory = $db->insert(T_UPLOADS, [
					"file_name" => $file_upload['filename'],
					"user_id" => $user->id,
					"original_name" => $file_upload['name']
				]);
	    		$data['status'] = 200;
	    		$data['story_id'] = $insert;
	    		$data['audio'] = $insert_data['audio'];
	    		if ($_POST['who'] != 'all') {
	    			$data['show_modal'] = 'no';
		    		$data['message'] = lang('Your story has been published successfully');
		    	}
		    	else{
		    		if ($music->config->story_price == 0) {
		    			$data['show_modal'] = 'no';
			    		$data['message'] = lang('Your story has been published successfully');
		    		}
		    		else{
		    			$data['show_modal'] = 'yes';
			    		$data['message'] = lang('Your story has been uploaded successfully to publish it please pay');
		    		}
		    	}
	    	}
	    	else{
	    	  $data['message'] = lang('Error 500 internal server error!');
	    	}
		}
	}
	else{
		if (empty($_POST['who'])) {
            $data['message'] = lang('Please who can see the story');
        }
        elseif(empty($_FILES['image'])){
            $data['message'] = lang('Please select a story image');
        }
        elseif(empty($_FILES['audio'])){
            $data['message'] = lang('Please select a story song');
        }
        else{
        	$data['message'] = lang('Please check the details');
        }
	}
}
if ($option == 'pay' && IS_LOGGED) {
	if (!empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0 && $music->user->org_wallet >= $music->config->story_price) {
		$id = Secure($_POST['id']);
		$story = $db->where('id',$id)->where('user_id',$music->user->id)->where('active',0)->getOne(T_STORY);
		if (!empty($story)) {
			$updateUser = $db->where('id', $music->user->id)->update(T_USERS, ['wallet' => $db->dec($music->config->story_price)]);
			$db->where('id',$id)->where('user_id',$music->user->id)->update(T_STORY,array('active' => 1));
			$data['message'] = lang('payment successfully done');
			$data['status'] = 200;
		}
		else{
			$data['message'] = lang('Story not found or its not active');
		}
	}
	else{
		if ($music->user->org_wallet < $music->config->story_price) {
			$data['message'] = lang("You don't have enough wallet")." <a href='".getLink("settings/".$music->user->username."/wallet")."'>".lang("Please top up your wallet")."</a>";
		}
		else{
			$data['message'] = lang('Please check the details');
		}
	}
}
if ($option == 'start') {
	if (!empty($_POST['user_id']) && is_numeric($_POST['user_id']) && $_POST['user_id'] > 0 && !empty($_POST['story_id']) && is_numeric($_POST['story_id']) && $_POST['story_id'] > 0) {
		$user_id = Secure($_POST['user_id']);
		$story_id = Secure($_POST['story_id']);
		setcookie('next_user_id',0, time() + (60 * 60),'/');
		setcookie('next_story_id',0, time() + (60 * 60),'/');
		$html = '';
		$music->story = StartFollowStories(array('user_id' => $user_id));
		if (!empty($music->story)) {
			$html = LoadPage('story_box/content');
		}
		$data['status'] = 200;
		$data['html'] = $html;
	}
	else{
		$data['message'] = lang('Please check the details');
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
		$html = '';
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
			
		if (!empty($music->story)) {
			$html = LoadPage('story_box/content');
		}
		$data['status'] = 200;
		$data['html'] = $html;
	}
	else{
		$data['message'] = lang('Please check the details');
	}
}
if ($option == 'previous') {
	if (!empty($_POST['user_id']) && is_numeric($_POST['user_id']) && $_POST['user_id'] > 0 && !empty($_POST['story_id']) && is_numeric($_POST['story_id']) && $_POST['story_id'] > 0 && !empty($_POST['pre_user_id']) && is_numeric($_POST['pre_user_id']) && $_POST['pre_user_id'] > 0 && !empty($_POST['pre_story_id']) && is_numeric($_POST['pre_story_id']) && $_POST['pre_story_id'] > 0) {
		$user_id = Secure($_POST['user_id']);
		$story_id = Secure($_POST['story_id']);
		$pre_user_id = Secure($_POST['pre_user_id']);
		$pre_story_id = Secure($_POST['pre_story_id']);
		$html = '';
		$music->story = PreviousFollowStories(array('user_id' => $user_id,
			                                        'story_id' => $story_id,
			                                        'pre_user_id' => $pre_user_id,
	                                                'pre_story_id' => $pre_story_id));
		if (!empty($music->story)) {
			$html = LoadPage('story_box/content');
		}
		$data['status'] = 200;
		$data['html'] = $html;
	}
	else{
		$data['message'] = lang('Please check the details');
	}
}
if ($option == 'story_views') {
	$data['status'] = 400;
    if (!empty($_POST['story_id']) && is_numeric($_POST['story_id']) && $_POST['story_id'] > 0 && !empty($_POST['last_view']) && is_numeric($_POST['last_view']) && $_POST['last_view'] > 0) {
    	$users = GetViewsUsers(array('story_id' => Secure($_POST['story_id']),
                                     'offset' => Secure($_POST['last_view'])));
        $html = '';
        if (!empty($users)) {
            foreach ($users as $key => $music->view) {
                $html .= loadPage("story_box/views_list");
            }
            $data['status'] = 200;
            $data['html'] = $html;
        }
    }
    else{
		$data['message'] = lang('Please check the details');
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
			runPlugin('AfterStoryDeleted', ["id" => $_POST['id']]);
    	}
    	$data['status'] = 200;
    }
    else{
		$data['message'] = lang('Please check the details');
	}
}