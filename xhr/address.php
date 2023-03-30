<?php
if ($music->config->store_system != "on") {
    header("Location: " . $site_url);
    exit();
}
$data["status"] = 400;
if ($option == "add" && IS_LOGGED) {
    if (
        !empty($_POST["name"]) &&
        !empty($_POST["phone"]) &&
        !empty($_POST["country"]) &&
        !empty($_POST["city"]) &&
        !empty($_POST["zip"]) &&
        !empty($_POST["address"])
    ) {
        $insertArray = [
            "name" => Secure($_POST["name"]),
            "phone" => Secure($_POST["phone"]),
            "city" => Secure($_POST["city"]),
            "zip" => Secure($_POST["zip"]),
            "address" => Secure($_POST["address"]),
            "user_id" => $music->user->id,
            "time" => time(),
            "country" => Secure($_POST["country"]),
        ];
        $id = $db->insert(T_ADDRESS, $insertArray);
        runPlugin('AfterAddressAdded', $insertArray);
        if (!empty($id)) {
            $data["status"] = 200;
            $data["url"] = getLink(
                "settings/" . $music->user->username . "/addresses"
            );
            $data["message"] = lang("Your address has been added successfully");
        } else {
            $data["message"] = lang("Error 500 internal server error!");
        }
    } else {
        $data["message"] = lang("Please check the details");
    }
}
if ($option == "edit" && IS_LOGGED) {
    if (
        !empty($_POST["name"]) &&
        !empty($_POST["phone"]) &&
        !empty($_POST["country"]) &&
        !empty($_POST["city"]) &&
        !empty($_POST["zip"]) &&
        !empty($_POST["address"]) &&
        !empty($_POST["id"]) &&
        is_numeric($_POST["id"]) &&
        $_POST["id"] > 0
    ) {
        $address = $db->where("id", Secure($_POST["id"]))->getOne(T_ADDRESS);
        if (
            !empty($address) &&
            ($address->user_id == $music->user->id || IsAdmin())
        ) {
            $insertArray = [
                "name" => Secure($_POST["name"]),
                "phone" => Secure($_POST["phone"]),
                "city" => Secure($_POST["city"]),
                "zip" => Secure($_POST["zip"]),
                "address" => Secure($_POST["address"]),
                "country" => Secure($_POST["country"]),
            ];
            $db->where("id", $address->id)->update(T_ADDRESS, $insertArray);
            runPlugin('AfterAddressUpdated', $insertArray);
            $data["status"] = 200;
            $data["url"] = getLink(
                "settings/" . $music->user->username . "/addresses"
            );
            $data["message"] = lang(
                "Your address has been edited successfully"
            );
        } else {
            $data["message"] = lang("Please check the details");
        }
    } else {
        $data["message"] = lang("Please check the details");
    }
}
if ($option == "delete" && IS_LOGGED) {
    if (!empty($_POST["id"]) && is_numeric($_POST["id"]) && $_POST["id"] > 0) {
        $address = $db->where("id", Secure($_POST["id"]))->getOne(T_ADDRESS);
        if (
            !empty($address) &&
            ($address->user_id == $music->user->id || IsAdmin())
        ) {
            $db->where("id", $address->id)->delete(T_ADDRESS);
            runPlugin('AfterAddressDeleted', ['address_id' => $address->id]);
            $data["status"] = 200;
        } else {
            $data["message"] = lang("Please check the details");
        }
    } else {
        $data["message"] = lang("Please check the details");
    }
}
