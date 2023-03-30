<?php
require "assets/init.php";

$db->where('name', 'cronjob_last_run')->update(T_CONFIG, array('value' => time()));

$db->where('time',time() - (60 * 60 * 2),'<')->delete(T_PENDING_PAYMENTS);

$update_information = UpdateAdminDetails();

$users = $db->where('is_pro','1')->get(T_USERS);
foreach ($users as $key => $user) {
	if (!empty($user) && !empty($music->pro_packages[$user->pro_type]) && $music->pro_packages[$user->pro_type]['ex_time'] != 0) {
	    $end_time = $user->pro_time + $music->pro_packages[$user->pro_type]['ex_time'];
	    if ($end_time <= time()) {
            runPlugin('AfterProExpired', $user);
	        $db->where('id',$user->id)->update(T_USERS,array('is_pro' => 0,
	                                                         'pro_type' => 0,
	                                                         'pro_time' => 0));
	    }
	}
}

runPlugin("RunCronJob");

if ($music->config->script_version >= '1.4') {
    $expired_stories = $db->where('time',time() - (60 * 60 * 24),'<')->get(T_STORY);
    foreach ($expired_stories as $key => $value) {
        $story = GetStory($value->id);
        if (!empty($story)) {
            runPlugin('AfterStoryDeleted', $value);
            @unlink($story->org_image);
            @unlink($story->org_audio);
            PT_DeleteFromToS3($story->org_image);
            PT_DeleteFromToS3($story->org_audio);
            $db->where('id',$story->id)->delete(T_STORY);
            $db->where('story_id',$story->id)->delete(T_STORY_SEEN);
        }
    }
}

if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == "on" || $music->config->spaces == "on" || $music->config->google_drive == "on" || $music->config->wasabi_storage == "on" || $music->config->backblaze_storage == "on") {
    $downloads = $db->where('expire',time(),'<')->where('expire',0,'!=')->get(T_DOWNLOADS);
    foreach ($downloads as $key => $value) {
        $getSong = $db->where('id', $value->track_id)->getOne(T_SONGS);
        if (!empty($getSong) && file_exists($getSong->audio_location)) {
            @unlink($getSong->audio_location);
            $db->where('id', $value->id)->update(T_DOWNLOADS,array(
                'expire' => 0
            ));
        }
    }
}

if (file_exists('upload/files/tickets')) {
    $all_pages = scandir('upload/files/tickets');
    if (!empty($all_pages)) {
        unset($all_pages[0]);
        unset($all_pages[1]);
        if (!empty($all_pages)) {
            foreach ($all_pages as $key => $value) {
                @unlink('upload/files/tickets/' . $value);
            }
        }
    }
}



$getCompletedAudio = $db->rawQuery("SELECT * FROM " . T_UPLOADS . " WHERE timestamp < NOW() - INTERVAL 1 DAY;");
foreach ($getCompletedAudio as $key => $audio) {
    $deleteFile = $db->where("id", $audio->id)->delete(T_UPLOADS);
    @unlink($audio->file_name);
}
