<?php
if (IS_LOGGED == false) {
    $data = [
        "status" => 400,
        "error" => "Not logged in",
    ];
    echo json_encode($data);
    exit();
}
if ($option == "create") {

    if (empty($_POST['app_name']) || empty($_POST['app_website_url']) || empty($_POST['app_description'])) {
        $data["message"] = lang("Please check the details");;
    }
    if (!filter_var($_POST['app_website_url'], FILTER_VALIDATE_URL)) {
        $data["message"] = lang("_the_url_is_invalid._please_enter_a_valid_url_");
    }
    if (empty($data["message"])) {

        $re_app_data = array(
            'app_user_id' => secure($music->user->id),
            'app_name' => secure($_POST['app_name']),
            'app_website_url' => secure($_POST['app_website_url']),
            'app_description' => secure($_POST['app_description']),
            'app_callback_url' => secure($_POST['app_website_url'])
        );

        $id_str                          = sha1($re_app_data["app_user_id"] . microtime() . time());
        $re_app_data["app_id"]     = substr($id_str, 0, 20);
        $secret_str                      = sha1($re_app_data["app_user_id"] . generateKey(55, 55) . microtime());
        $re_app_data["app_secret"] = substr($secret_str, 0, 39);

        if (!empty($_FILES["app_avatar"]["name"])) {

            $fileInfo      = array(
                'file' => $_FILES["app_avatar"]["tmp_name"],
                'name' => $_FILES['app_avatar']['name'],
                'size' => $_FILES["app_avatar"]["size"],
                'type' => $_FILES["app_avatar"]["type"],
                'types' => 'jpeg,jpg,png,bmp,gif'
            );
            $media         = ShareFile($fileInfo);

            if (empty($media) || empty($media['filename'])) {
                $data["message"] = lang("Error found while uploading your image, please try again later.");
            }
            else{
                $re_app_data['app_avatar'] = $media['filename'];
            }
        }
        runPlugin('PreAppCreated', $re_app_data);
        if (empty($data["message"])) {
            $app_id      = $db->insert(T_APPS,$re_app_data);
            if ($app_id) {
                runPlugin('AfterAppCreated', $re_app_data);
                $data = array(
                    'status' => 200,
                    'location' => $site_url . "/app/".$app_id,
                    'message' => lang('app_created_successfully')
                );
            }
        }
    }
}
if ($option == "edit") {

    if (empty($_POST['app_name']) || empty($_POST['app_website_url']) || empty($_POST['app_description']) || empty($_POST['id'])) {
        $data["message"] = lang("Please check the details");;
    }
    if (!filter_var($_POST['app_website_url'], FILTER_VALIDATE_URL)) {
        $data["message"] = lang("_the_url_is_invalid._please_enter_a_valid_url_");
    }

    $app_data = $db->where('id',secure($_POST['id']))->where('app_user_id',$music->user->id)->getOne(T_APPS);
    if (empty($app_data)) {
        $data["message"] = lang("app_not_found");
    }

    if (empty($data["message"])) {

        $re_app_data = array(
            'app_name' => secure($_POST['app_name']),
            'app_website_url' => secure($_POST['app_website_url']),
            'app_description' => secure($_POST['app_description']),
            'app_callback_url' => secure($_POST['app_website_url'])
        );

        if (!empty($_FILES["app_avatar"]["name"])) {

            $fileInfo      = array(
                'file' => $_FILES["app_avatar"]["tmp_name"],
                'name' => $_FILES['app_avatar']['name'],
                'size' => $_FILES["app_avatar"]["size"],
                'type' => $_FILES["app_avatar"]["type"],
                'types' => 'jpeg,jpg,png,bmp,gif'
            );
            $media         = ShareFile($fileInfo);

            if (empty($media) || empty($media['filename'])) {
                $data["message"] = lang("Error found while uploading your image, please try again later.");
            }
            else{
                if ($app_data->app_avatar != 'upload/photos/app-default-icon.png') {
                    @unlink($app_data->app_avatar);
                    PT_DeleteFromToS3($app_data->app_avatar);
                }
                $re_app_data['app_avatar'] = $media['filename'];
            }
        }
        if (empty($data["message"])) {
            $db->where('id', $app_data->id)->update(T_APPS,$re_app_data);
            $re_app_data['app_id'] = $app_data->id;
            runPlugin('AfterAppUpdated', $re_app_data);
            $data = array(
                'status' => 200,
                'location' => $site_url . "/app/".$app_data->id,
                'message' => lang('app_edited_successfully')
            );
        }
    }
}
if ($option == "accept") {
    if (!empty($_POST['id'])) {
        $app = $db->where('app_id',secure($_POST['id']))->getOne(T_APPS);
        if (!empty($app)) {
            $permission = $db->where('app_id',$app->id)->where('user_id',$music->user->id)->getOne(T_APPS_PERMISSION);
            if (empty($permission)) {
                $insertData = [
                    'user_id' => $music->user->id,
                    'app_id' => $app->id
                ];
                $db->insert(T_APPS_PERMISSION, $insertData);
                runPlugin('AfterAppUserAcceptPermission', $insertData);
            }
            $data["status"] = 200;
            $data["message"] = lang("app_permission_accepted");
            $url = $app->app_website_url;
            if (!empty($app->app_callback_url)) {
                $url = $app->app_callback_url;
            }
            $import = GenrateCode($music->user->id, $app->id);
            $data["url"] = $url . "?code=" . $import;
        }
        else{
            $data["message"] = lang("app_not_found");
        }
    }
}