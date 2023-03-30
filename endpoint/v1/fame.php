<?php
if ($music->config->fame_system != 1) {
	$data = [
        'status' => 400,
        'error' => 'fame system is off'
    ];
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
$limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
$views_count             = (isset($_POST['views_count'])) ? secure($_POST['views_count']) : 0;
$more_text = '';
if (!empty($views_count) && !empty($_POST['v_ids'])) {
	$v_ids = secure($_POST['v_ids']);
	$more_text = ' AND count <= '.$views_count.' AND v.id NOT IN ('. $v_ids .') ';
}
$result = $db->rawQuery('SELECT v.id,v.track_id , COUNT(*) AS count FROM '.T_VIEWS.' v  , '.T_SONGS.' s WHERE s.id = v.track_id GROUP BY s.user_id HAVING count >= '.$music->config->views_count.$more_text.' ORDER BY count DESC LIMIT '.$limit);

$all_data = array();

foreach ($result as $key => $value) {
	$song = songData($value->track_id);
	if (!empty($song)) {
		$song->total_views = $value->count;
		$song->v_ids = $value->id;
		$all_data[] = $song;
	}
}
$data['status'] = 200;
$data['data'] = $all_data;