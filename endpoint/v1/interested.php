<?php
if ($option == 'add') {
	if (!empty($_POST['song_id']) && is_numeric($_POST['song_id'])) {
		$id = secure($_POST['song_id']);
		$userSong = songData($id);
		if (!empty($userSong)) {
			$info = $db->where('user_id',$music->user->id)->where('audio_id',$id)->getOne(T_INTERESTED);
            if (!empty($info)) {
            	$data = [
			        'status' => 400,
			        'error' => 'song already added'
			    ];
            }
            else{
                $db->insert(T_INTERESTED,array('user_id' => $music->user->id,
                                               'audio_id' => $userSong->id,
                                               'time' => time()));
                $data['status'] = 200;
    			$data['data'] = 'song added to interested list';
            }
		}
		else{
			$data = [
		        'status' => 400,
		        'error' => 'song not found'
		    ];
		}
	}
	else{
		$data = [
	        'status' => 400,
	        'error' => 'song_id can not be empty'
	    ];
	}
}
if ($option == 'delete') {
	if (!empty($_POST['song_id']) && is_numeric($_POST['song_id'])) {
		$id = secure($_POST['song_id']);
		$db->where('user_id',$music->user->id)->where('audio_id',$id)->delete(T_INTERESTED);

		$data['status'] = 200;
    	$data['data'] = 'song deleted from interested list';
    }
	else{
		$data = [
	        'status' => 400,
	        'error' => 'song_id can not be empty'
	    ];
	}
}
if ($option == 'fetch') {
	$limit = (!empty($_POST['limit']) && is_numeric($_POST['limit']) && $_POST['limit'] > 0 && $_POST['limit'] <= 50) ? secure($_POST['limit']) : 20;
    $offset = (!empty($_POST['offset']) && is_numeric($_POST['offset']) && $_POST['offset'] > 0) ? secure($_POST['offset']) : 0;
    if (!empty($offset)) {
        $db->where('audio_id',secure($offset),'<');
    }
    $list = $db->where('user_id',$music->user->id)->orderBy('audio_id','DESC')->get(T_INTERESTED,$limit);
    $songs_data = array();
    foreach ($list as $key => $value) {
    	$userSong = songData($value->audio_id);
        unset($userSong->publisher->password);
        unset($userSong->publisher->email_code);
        foreach ($userSong->songArray as $key => $value) {
            unset($userSong->songArray->{$key}->USER_DATA->password);
            unset($userSong->songArray->{$key}->USER_DATA->email_code);
        }
        $songs_data[] = $userSong;
    }
    $data = [
        'status' => 200,
        'data' => $songs_data
    ];
}