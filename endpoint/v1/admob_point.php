<?php
if (IS_LOGGED == false) {
    $data = array('status' => 400, 'error' => 'You ain\'t logged in!');
    echo json_encode($data);
    exit();
}
if ($option == 'add') {
	RecordUserActivities("admob", ['audio_id' => '']);
	$data["status"] = 200;
    $data["message"] = "Points added";
}