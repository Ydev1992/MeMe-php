<?php
if ($option == 'search_stations') {
	if (!empty($_POST['keyword'])) {
		$keyword = secure($_POST['keyword']);
		$country = (isset($_POST['country'])) ? secure($_POST['country']) : 'ALL';
		$genre = (isset($_POST['genre'])) ? secure($_POST['genre']) : 'ALL';
	    $data['status'] = 200;
	    $stations = GetRadioStations($keyword,$country,$genre);
	    $full = array();
	    $my_stations = $db->arrayBuilder()->where('user_id', $music->user->id)->where('src', 'radio')->get(T_SONGS,null,array('lyrics'));
        $stations_array = array();
        foreach ($my_stations as $key => $value){
            $stations_array[] = $value['lyrics'];
        }
        foreach ($stations as $key => $station) {
            if(!in_array($station['radio_id'],$stations_array)) {
                $full[] = $station;
            }
        }
        $data = [
            'status' => 200,
            'data' => $full
        ];
	}
	else{
		$errors[] = "keyword can not be empty";
	}
	if (!empty($errors)) {
		$data = array('status' => 400, 'error' => $errors);
	    echo json_encode($data);
	    exit();
	}
}
if ($option == 'add_stations') {
	if (!empty($_POST['id']) && is_numeric($_POST['id']) && !empty($_POST['station']) && !empty($_POST['url']) && !empty($_POST['logo']) && !empty($_POST['genre']) && !empty($_POST['country'])) {
		$checkIfStationExits = $db->where('user_id', $music->user->id)->where('lyrics', secure($_POST['id']))->where('src', 'radio')->getValue(T_SONGS, 'count(*)');
	    if (empty($checkIfStationExits)) {

	        $audio_id        = generateKey(15, 15);
	        $check_for_audio = $db->where('audio_id', $audio_id)->getValue(T_SONGS, 'count(*)');
	        if ($check_for_audio > 0) {
	            $audio_id = generateKey(15, 15);
	        }

	        if (!file_exists('upload/photos/' . date('Y'))) {
	            @mkdir('upload/photos/' . date('Y'), 0777, true);
	        }
	        if (!file_exists('upload/photos/' . date('Y') . '/' . date('m'))) {
	            @mkdir('upload/photos/' . date('Y') . '/' . date('m'), 0777, true);
	        }
	        $dir = "upload/photos/" . date('Y') . '/' . date('m');
	        $filename    = $dir . '/' . generateKey() . '_' . date('d') . '_' . md5(time()) . "_image.jpg";
	        $file_data = file_get_contents(secure($_POST['logo']));
	        file_put_contents($filename, $file_data);
	        if (($music->config->s3_upload == 'on' || $music->config->ftp_upload == 'on' || $music->config->spaces == 'on'  || $music->config->google_drive == 'on'  || $music->config->wasabi_storage == 'on'  || $music->config->backblaze_storage == 'on') && !empty($filename)) {
	            $upload_s3 = PT_UploadToS3($filename);
	        }
	        $data_insert = array(
	            'audio_id' => $audio_id,
	            'user_id' => $music->user->id,
	            'title' => secure($_POST['station']),
	            'description' => secure($_POST['country']),
	            'lyrics' => secure($_POST['id']),
	            'tags' => secure($_POST['genre']),
	            'duration' => '',
	            'audio_location' => secure($_POST['url']),
	            'category_id' => '',
	            'thumbnail' => $filename,
	            'time' => time(),
	            'registered' => date('Y') . '/' . intval(date('m')),
	            'size' => 0,
	            'availability' => 0,
	            'age_restriction' => 0,
	            'price' => 0,
	            'spotlight' => 0,
	            'ffmpeg' => 0,
	            'allow_downloads' => 0,
	            'display_embed' => 0,
	            'src' => 'radio',
				'converted' => 1
	        );
	        $addStation = $db->insert(T_SONGS, $data_insert);
	        if ($addStation) {
	            $create_activity = createActivity([
	                'user_id' => $music->user->id,
	                'type' => 'uploaded_track',
	                'track_id' => $insert,
	            ]);
	            $data_insert['id'] = $insert;
	            notifyUploadTrack($data_insert);

	            $data = array(
	                'status' => 200,
	                'audio_id' => $audio_id,
	                'song_location' => secure($_POST['url']),
	                'link' => getLink("track/$audio_id")
	            );
	        }
	        else{
	        	$errors[] = "something went wrong";
	        }
	    }
	    else{
	    	$errors[] = "station is already added";
	    }
	}
	else{
		$errors[] = "id , station , url , logo , genre , country can not be empty";
	}
	if (!empty($errors)) {
		$data = array('status' => 400, 'error' => $errors);
	    echo json_encode($data);
	    exit();
	}
}