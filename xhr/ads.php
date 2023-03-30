<?php

if (IS_LOGGED === true) {
    if ($music->config->user_ads != "on") {
        header("Location: " . $site_url);
        exit();
    }

    if ($option == "create") {
        $data = ["status" => 400];
        $error = false;
        $request = [];
        $request[] =
            empty($_POST["name"]) ||
            empty($_POST["url"]) ||
            empty($_POST["title"]);
        $request[] = empty($_POST["desc"]);
        // $request[] =
        //     empty($_POST["audience-list"]) ||
        //     !is_array($_POST["audience-list"]);
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
            $error = lang("Please check the details");
        } else {
            if (!pt_is_url($_POST["url"])) {
                $error = lang("The URL is invalid. Please enter a valid URL");
            } elseif (
                !empty($_FILES["media"]) &&
                file_exists($_FILES["media"]["tmp_name"]) &&
                $_FILES["media"]["size"] > $music->config->max_upload
            ) {
                $max = size_format($music->config->max_upload);
                $error = lang("File is too big, Max upload size is") . ": $max";
            } elseif (
                !empty($_POST["placement"]) &&
                !in_array($_POST["placement"], [1, 2])
            ) {
                $error = lang("Something went wrong Please try again later!");
            } elseif (!in_array($_POST["cost"], [1, 2])) {
                $error = lang("Something went wrong Please try again later!");
            }
        }

        if (empty($error)) {
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
                                $error = lang(
                                    "Media file is invalid. Please select a valid audio"
                                );
                            }
                        } else {
                            $error = lang(
                                "Media file is invalid. Please select a valid image / video"
                            );
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
                            $error = lang(
                                "Media file is invalid. Please select a valid audio"
                            );
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
                        $error = lang(
                            "Media file is invalid. Please select a valid image / video"
                        );
                    }
                }
            } else {
                if (
                    empty($_FILES["media"]) ||
                    !file_exists($_FILES["media"]["tmp_name"]) ||
                    !in_array($_FILES["media"]["type"], $music->ads_media_types)
                ) {
                    $error = lang(
                        "Media file is invalid. Please select a valid image / video"
                    );
                }
            }
            if (empty($error)) {
                $file_type = [""];
                if (!empty($_FILES["media"])) {
                    $file_type = explode("/", $_FILES["media"]["type"]);
                }
                if (empty($_POST["placement"])) {
                    $_POST["placement"] = "1";
                }

                $insert_data = [
                    "name" => Secure($_POST["name"]),
                    "audience" => (!empty($_POST["audience-list"]) && is_array($_POST["audience-list"]) ? implode(",", $_POST["audience-list"]) : ''),
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
                        runPlugin('AfterAdCreated', $insert_data);
                        $data["status"] = 200;
                        $data["message"] = lang(
                            "Your ad has been published successfully"
                        );
                    } else {
                        $data["message"] = lang(
                            "Error 500 internal server error!"
                        );
                    }
                }
            } else {
                $data["message"] = $error;
            }
        } else {
            $data["message"] = $error;
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
        // $request[] =
        //     empty($_POST["audience-list"]) ||
        //     !is_array($_POST["audience-list"]);
        $request = in_array(true, $request, true) === true;

        if ($request) {
            $error = lang("Please check the details");
        } else {
            $ad_id = Secure($_POST["id"]);
            $ad_data = $db
                ->where("id", $ad_id)
                ->where("user_id", $user->id)
                ->getOne(T_USR_ADS);
            if (empty($ad_data)) {
                $data["status"] = 404;
                $error = true;
            } elseif (!pt_is_url($_POST["url"])) {
                $error = lang("The URL is invalid. Please enter a valid URL");
            }
        }

        if (empty($error)) {
            $update_data = [
                "name" => Secure($_POST["name"]),
                "audience" => (!empty($_POST["audience-list"]) && is_array($_POST["audience-list"]) ? implode(",", $_POST["audience-list"]) : ''),
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
                runPlugin('AfterAdUpdated', $update_data);
                $data["status"] = 200;
                $data["message"] = $error = lang(
                    "Your changes to the ad were successfully saved"
                );
            } else {
                $data["message"] = "Error 500 internal server error!";
            }
        } else {
            $data["message"] = $error;
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
            $s3 =
                $music->config->s3_upload == "on" ||
                ($music->config->ftp_upload =
                    "on" || ($music->config->spaces = "on"))
                    ? true
                    : false;
            if (!empty($ad)) {
                if (file_exists($ad->media)) {
                    unlink($ad->media);
                } elseif ($s3 === true) {
                    PT_DeleteFromToS3($ad->media);
                }

                $db->where("id", $id)
                    ->where("user_id", $user->id)
                    ->delete(T_USR_ADS);
                runPlugin('AfterAdDeleted');
                $data["status"] = 200;
            }
        }
    }

    if ($option == "toggle-stat") {
        $request = !empty($_POST["id"]) && is_numeric($_POST["id"]);
        if ($request === true) {
            $id = $_POST["id"];
            $ad = $db
                ->where("id", $id)
                ->where("user_id", $user->id)
                ->getOne(T_USR_ADS);
            if (!empty($ad)) {
                $stat = $ad->status == 1 ? 0 : 1;
                $update = ["status" => $stat];
                $db->where("id", $id)
                    ->where("user_id", $user->id)
                    ->update(T_USR_ADS, $update);
                runPlugin('AfterAdStatusUpdated', $update);
                $data["status"] = 200;
            }
        }
    }
}

if ($option == "rad-transaction") {
    $request = !empty($_SESSION["ua_"]) && !empty($_SESSION["vo_"]);

    if ($request === true) {
        $ad_id = Secure($_SESSION["ua_"]);
        $pub_id = Secure($_SESSION["vo_"]);
        $ad = $db->where("id", $ad_id)->getOne(T_USR_ADS);
        if (!empty($ad)) {
            $ad_owner = $db->where("id", $ad->user_id)->getOne(T_USERS);
            $con_price = $music->config->ad_c_price;
            $ad_trans = false;
            $is_owner = false;
            $ad_tans_data = [
                "results" => ($ad->results += 1),
            ];

            if (IS_LOGGED) {
                $is_owner = $ad->user_id == $user->id ? true : false;
            }

            if (
                !array_key_exists($ad_id, $music->user_ad_cons["uaid_"]) &&
                !$is_owner
            ) {
                $ad_tans_data["spent"] = $ad->spent += $con_price;
                $ad_trans = true;
                $music->user_ad_cons["uaid_"][$ad->id] = $ad->id;
                setcookie(
                    "_uads",
                    htmlentities(serialize($music->user_ad_cons)),
                    time() + 10 * 365 * 24 * 60 * 60,
                    "/"
                );
                $db->insert(T_ADS_TRANS, [
                    "amount" => $con_price,
                    "type" => "spent",
                    "ad_id" => $ad_id,
                    "video_owner" => $pub_id,
                    "time" => time(),
                ]);
            }
            $db->insert(T_ADS_TRANS, [
                "type" => "click",
                "ad_id" => $ad_id,
                "video_owner" => $pub_id,
                "time" => time(),
            ]);

            $update = $db
                ->where("id", $ad_id)
                ->update(T_USR_ADS, $ad_tans_data);
            runPlugin('AfterAdViewed', $ad_tans_data);
            if ($update && $ad_trans && !$is_owner) {
                $ad_value = $ad_owner->wallet -= $con_price;
                if ($ad_value < 0) {
                    $ad_value = 0;
                }
                $db->where("id", $ad_owner->id)->update(T_USERS, [
                    "wallet" => $ad_value,
                ]);
            }

            $data["status"] = 200;
            unset($_SESSION["ua_"]);
        }
    }
}
