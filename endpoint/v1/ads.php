<?php
if (IS_LOGGED == false) {
    $data = array('status' => 400, 'error' => 'You ain\'t logged in!');
    echo json_encode($data);
    exit();
}
if ($option == 'add') {
	$data = ["status" => 400];
    $error = false;
    $request = [];
    $request[] =
        empty($_POST["name"]) ||
        empty($_POST["url"]) ||
        empty($_POST["title"]);
    $request[] = empty($_POST["desc"]);
    $request[] =
        empty($_POST["audience-list"]);
    $request[] = empty($_POST["cost"]);
    $request[] =
        empty($_POST["placement"]) &&
        (empty($_POST["type"]) ||
            (!empty($_POST["type"]) && $_POST["type"] != "audio"));
    $request[] =
        empty($_FILES["media"]) &&
        (empty($_POST["type"]) ||
            (!empty($_POST["type"]) && $_POST["type"] != "audio"));
    $request[] =
        $music->user->wallet == "0.00" || $music->user->wallet == "0"
            ? true
            : false;
    $request = in_array(true, $request, true) === true;

    if ($request) {
        $data["error"] = "Please check the details";
    } else {
        if (
            mb_strlen($_POST["name"]) < 5 ||
            mb_strlen($_POST["name"]) > 100
        ) {
            $data["error"] = "Name must be between 5/32";
        } elseif (!pt_is_url($_POST["url"])) {
            $data["error"] = "The URL is invalid. Please enter a valid URL";
        } elseif (
            mb_strlen($_POST["title"]) < 10 ||
            mb_strlen($_POST["title"]) > 150
        ) {
            $data["error"] = "Ad title must be between 5/100";
        } elseif (
            !empty($_FILES["media"]) &&
            file_exists($_FILES["media"]["tmp_name"]) &&
            $_FILES["media"]["size"] > $music->config->max_upload
        ) {
            $max = size_format($music->config->max_upload);
            $data["error"] = "File is too big, Max upload size is" . ": $max";
        } elseif (
            !empty($_POST["placement"]) &&
            !in_array($_POST["placement"], [1, 2])
        ) {
            $data["error"] = "Something went wrong Please try again later!";
        } elseif (!in_array($_POST["cost"], [1, 2])) {
            $data["error"] = "Something went wrong Please try again later!";
        }
    }

    if (empty($data["error"])) {
        $ad_type = "image";
        if ($music->config->audio_ads == "on") {
            if (!empty($_POST["type"]) && $_POST["type"] == "audio") {
                $ad_type = "audio";
                if (!empty($_FILES["audio"])) {
                    if ($music->config->can_use_audio_ads) {
                        if (
                            !file_exists($_FILES["audio"]["tmp_name"]) ||
                            !in_array(
                                $_FILES["audio"]["type"],
                                $music->ads_audio_types
                            )
                        ) {
                            $data["error"] = "Media file is invalid. Please select a valid audio";
                        }
                    } else {
                        $data["error"] = "Media file is invalid. Please select a valid image / video";
                    }
                } else {
                    if (
                        empty($_FILES["audio"]) ||
                        !file_exists($_FILES["audio"]["tmp_name"]) ||
                        !in_array(
                            $_FILES["audio"]["type"],
                            $music->ads_audio_types
                        )
                    ) {
                        $data["error"] = "Media file is invalid. Please select a valid audio";
                    }
                }
            } else {
                if (
                    empty($_FILES["media"]) ||
                    !file_exists($_FILES["media"]["tmp_name"]) ||
                    !in_array(
                        $_FILES["media"]["type"],
                        $music->ads_media_types
                    )
                ) {
                    $data["error"] = "Media file is invalid. Please select a valid image / video";
                }
            }
        } else {
            if (
                empty($_FILES["media"]) ||
                !file_exists($_FILES["media"]["tmp_name"]) ||
                !in_array($_FILES["media"]["type"], $music->ads_media_types)
            ) {
                $data["error"] = "Media file is invalid. Please select a valid image / video";
            }
        }
        if (empty($data["error"])) {
            $file_type = [""];
            if (!empty($_FILES["media"])) {
                $file_type = explode("/", $_FILES["media"]["type"]);
            }
            if (empty($_POST["placement"])) {
                $_POST["placement"] = "1";
            }

            $insert_data = [
                "name" => Secure($_POST["name"]),
                "audience" => Secure($_POST["audience-list"]),
                "category" => $file_type[0],
                "media" => "",
                "url" => urlencode($_POST["url"]),
                "user_id" => $user->id,
                "placement" => intval($_POST["placement"]),
                "posted" => time(),
                "headline" => Secure($_POST["title"]),
                "description" => Secure(PT_ShortText($_POST["desc"], 1000)),
                "location" => "",
                "ad_type" => $ad_type,
                "type" => intval($_POST["cost"]),
            ];

            if (
                $music->config->audio_ads == "on" &&
                !empty($_FILES["audio"])
            ) {
                $file_info = [
                    "file" => $_FILES["audio"]["tmp_name"],
                    "size" => $_FILES["audio"]["size"],
                    "name" => $_FILES["audio"]["name"],
                    "type" => $_FILES["audio"]["type"],
                ];
                $file_upload = ShareFile($file_info);
                $insert_data["audio_media"] = $file_upload["filename"];
            }

            if (
                !empty($_POST["day_limit"]) &&
                is_numeric($_POST["day_limit"]) &&
                $_POST["day_limit"] > 0
            ) {
                $insert_data["day_limit"] = Secure($_POST["day_limit"]);
                $insert_data["day"] = date("Y-m-d");
            }
            if (
                empty($_POST["type"]) ||
                (!empty($_POST["type"]) && $_POST["type"] != "audio")
            ) {
                $file_info = [
                    "file" => $_FILES["media"]["tmp_name"],
                    "size" => $_FILES["media"]["size"],
                    "name" => $_FILES["media"]["name"],
                    "type" => $_FILES["media"]["type"],
                ];
                $file_upload = ShareFile($file_info);
                $insert_data["media"] = $file_upload["filename"];
            }

            if (!empty($file_upload)) {
                $insert = $db->insert(T_USR_ADS, $insert_data);
                if (!empty($insert)) {
                    $data["status"] = 200;
                    $data["message"] = "Your ad has been published successfully";
                } else {
                    $data["error"] = "Error 500 internal server error!";
                }
            }
        }
    }
}
if ($option == "edit") {
    $data = ["status" => 400];
    $error = false;
    $type = false;
    $media = false;
    $cost = false;
    $request = [];
    $request[] =
        empty($_POST["name"]) ||
        empty($_POST["url"]) ||
        empty($_POST["title"]);
    $request[] =
        empty($_POST["desc"]) ||
        empty($_POST["id"]) ||
        !is_numeric($_POST["id"]);
    $request[] =
        empty($_POST["audience-list"]);
    $request = in_array(true, $request, true) === true;

    if ($request) {
        $data["error"] = lang("Please check the details");
    } else {
        $ad_id = Secure($_POST["id"]);
        $ad_data = $db
            ->where("id", $ad_id)
            ->where("user_id", $user->id)
            ->getOne(T_USR_ADS);
        if (empty($ad_data)) {
            $data["status"] = 404;
            $data["error"] = "Ad not found";
        } elseif (
            mb_strlen($_POST["name"]) < 5 ||
            mb_strlen($_POST["name"]) > 100
        ) {
            $data["error"] = lang("Name must be between 5/32");
        } elseif (!pt_is_url($_POST["url"])) {
            $data["error"] = lang("The URL is invalid. Please enter a valid URL");
        } elseif (
            mb_strlen($_POST["title"]) < 10 ||
            mb_strlen($_POST["title"]) > 150
        ) {
            $data["error"] = lang("Ad title must be between 5/100");
        }
    }

    if (empty($data["error"])) {
        $update_data = [
            "name" => Secure($_POST["name"]),
            "audience" => Secure($_POST["audience-list"]),
            "url" => urlencode($_POST["url"]),
            "user_id" => $user->id,
            "headline" => Secure($_POST["title"]),
            "description" => Secure(PT_ShortText($_POST["desc"], 1000)),
            "location" => "",
        ];

        $update_data["day_limit"] = 0;

        if (
            !empty($_POST["day_limit"]) &&
            is_numeric($_POST["day_limit"]) &&
            $_POST["day_limit"] > 0
        ) {
            $update_data["day_limit"] = Secure($_POST["day_limit"]);
            if (empty($ad_data->day)) {
                $update_data["day"] = date("Y-m-d");
            }
        } else {
            $update_data["day_limit"] = 0;
            $update_data["day"] = "";
            $update_data["day_spend"] = 0;
        }

        $ad_id = Secure($_POST["id"]);
        $update = $db->where("id", $ad_id)->update(T_USR_ADS, $update_data);
        if (!empty($update)) {
            $data["status"] = 200;
            $data["message"] = lang(
                "Your changes to the ad were successfully saved"
            );
        } else {
            $data["error"] = "Error 500 internal server error!";
        }
    }
}
if ($option == "delete") {
    $request = !empty($_POST["id"]) && is_numeric($_POST["id"]);
    if ($request === true) {
        $id = $_POST["id"];
        $ad = $db
            ->where("id", $id)
            ->where("user_id", $user->id)
            ->getOne(T_USR_ADS);
        $s3 = true;
        if (!empty($ad)) {
            if (file_exists($ad->media)) {
                unlink($ad->media);
            } elseif ($s3 === true) {
                PT_DeleteFromToS3($ad->media);
            }

            $db->where("id", $id)
                ->where("user_id", $user->id)
                ->delete(T_USR_ADS);
            $data["status"] = 200;
            $data["error"] = "Your ad has been deleted successfully";
        }
    }
    else{
    	$data["error"] = "id can not be empty";
    }
}
if ($option == "get") {
	$limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
    $offset             = (isset($_POST['offset'])) ? secure($_POST['offset']) : 0;
    if (!empty($offset)) {
        $db->where('id',$offset,'<');
    }
    $user_ads_array = array();
	$user_ads        = $db->where('user_id',$music->user->id)->orderBy('id','DESC')->get(T_USR_ADS,$limit);
	foreach ($user_ads as $key => $value) {
		$value->media = getMedia($value->media);
		$user_ads_array[] = $value;
	}
	$data["status"] = 200;
    $data["data"] = $user_ads_array;
}