<?php
if (!empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0) {
	$album_id = secure($_POST['id']);
	$getAlbum = $db->where('id', $album_id)->getOne(T_ALBUMS);
	if (!empty($getAlbum)) {
		$related_albums = $db->where('category_id', $getAlbum->category_id)->where('id', $getAlbum->id, '<>')->orderBy('RAND()')->get(T_ALBUMS, 10);
		if (empty($related_albums)) {
			$related_albums = $db->orderBy('RAND()')->where('id', $getAlbum->id, '<>')->get(T_ALBUMS, 10);
		}
		$info = array();
		foreach ($related_albums as $key => $value){
	        $album = albumData($value->id, true, true, false);
	        if (!empty($album)) {
	        	unset($album->songs);
		        unset($album->publisher->password);
		        unset($album->publisher->email_code);
		        $info[] = $album;
	        } 
	    }
	    $data = [
	        'status' => 200,
	        'data' => $info
	    ];
	}
	else{
		$data = array('status' => 400, 'error' => 'album not found');
	    echo json_encode($data);
	    exit();
	}
}
else{
	$data = array('status' => 400, 'error' => 'id can not be empty');
    echo json_encode($data);
    exit();
}