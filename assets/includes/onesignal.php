<?php
function SendPushNotification($data = array(), $push_type = 'chat') {
    global $sqlConnect, $music, $One_config, $Two_config, $Three_config, $four_config, $five_config, $guzzle;
    if (empty($data)) {
        return false;
    }
    if (empty($data['notification']['notification_content'])) {
        return false;
    }
    if (empty($data['send_to'])) {
        return false;
    }
    if ($music->config->push == 0) {
        return false;
    }
    $app_id = '';
    $app_key = '';
    if($push_type == 'android_native'){
        $app_id = $music->config->android_m_push_id;
        $app_key = $music->config->android_m_push_key;
    }
    else if($push_type == 'ios_native'){
        $app_id = $music->config->ios_n_push_id;
        $app_key = $music->config->ios_n_push_key;
    }
    else if($push_type == 'web'){
        $app_id = $music->config->web_push_id;
        $app_key = $music->config->web_push_key;
    }

    $config_data = $One_config;
    if (!empty($data['notification']['notification_data']['user_data'])) {
        $data['notification']['notification_data']['user_data'] = array(
            'id' => $data['notification']['notification_data']['user_data']->id,
            'username' => $data['notification']['notification_data']['user_data']->username,
            'email' => $data['notification']['notification_data']['user_data']->email,
            'avatar' => $data['notification']['notification_data']['user_data']->avatar,
            'cover' => $data['notification']['notification_data']['user_data']->cover,
            'url' => $data['notification']['notification_data']['user_data']->url,
            'name' => $data['notification']['notification_data']['user_data']->name,
        );
    }

    $data['notification']['notification_content'] = EmoPhone($data['notification']['notification_content']);
    $data['notification']['notification_content'] = EditMarkup($data['notification']['notification_content']);
    $final_request_data = array(
        'app_id' => $app_id,
        'include_player_ids' =>  $data['send_to'],
        'send_after' => new \DateTime('1 second'),
        'isChrome' => false,
        'contents' => array(
            'en' => $data['notification']['notification_content']
        ),
        'headings' => array(
            'en' => $data['notification']['notification_title']
        ),
        'android_led_color' => 'FF0000FF',
        'priority' => 10
    );
    if (!empty($data['notification']['notification_data'])) {
        $final_request_data['data'] = $data['notification']['notification_data'];
    }
    if (!empty($data['notification']['notification_image'])) {
        $final_request_data['large_icon'] = $data['notification']['notification_image'];
    }

    $fields = json_encode($final_request_data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
                                               'Authorization: Basic '.$app_key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $response = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($response);
    if ($response->id) {
        return $response->id;
    }
    return false;
}
