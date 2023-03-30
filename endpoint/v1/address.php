<?php
if (IS_LOGGED == false) {
    $data = array('status' => 400, 'error' => 'You ain\'t logged in!');
    echo json_encode($data);
    exit();
}
if ($option == 'add') {
	$data["status"] = 400;
	if (
        !empty($_POST["name"]) &&
        !empty($_POST["phone"]) &&
        !empty($_POST["country"]) &&
        !empty($_POST["city"]) &&
        !empty($_POST["zip"]) &&
        !empty($_POST["address"])
    ) {
        $id = $db->insert(T_ADDRESS, [
            "name" => Secure($_POST["name"]),
            "phone" => Secure($_POST["phone"]),
            "city" => Secure($_POST["city"]),
            "zip" => Secure($_POST["zip"]),
            "address" => Secure($_POST["address"]),
            "user_id" => $music->user->id,
            "time" => time(),
            "country" => Secure($_POST["country"]),
        ]);
        if (!empty($id)) {
            $data["status"] = 200;
            $data["url"] = getLink(
                "settings/" . $music->user->username . "/addresses"
            );
            $data["message"] = "Your address has been added successfully";
        } else {
            $data["error"] = "Error 500 internal server error!";
        }
    } else {
        $data["error"] = "Please check the details";
    }
}
if ($option == "edit") {
	$data["status"] = 400;
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
            $db->where("id", $address->id)->update(T_ADDRESS, [
                "name" => Secure($_POST["name"]),
                "phone" => Secure($_POST["phone"]),
                "city" => Secure($_POST["city"]),
                "zip" => Secure($_POST["zip"]),
                "address" => Secure($_POST["address"]),
                "country" => Secure($_POST["country"]),
            ]);
            $data["status"] = 200;
            $data["url"] = getLink(
                "settings/" . $music->user->username . "/addresses"
            );
            $data["message"] = "Your address has been edited successfully";
        } else {
            $data["error"] = "Please check the details";
        }
    } else {
        $data["error"] = "Please check the details";
    }
}
if ($option == "delete") {
	$data["status"] = 400;
    if (!empty($_POST["id"]) && is_numeric($_POST["id"]) && $_POST["id"] > 0) {
        $address = $db->where("id", Secure($_POST["id"]))->getOne(T_ADDRESS);
        if (
            !empty($address) &&
            ($address->user_id == $music->user->id || IsAdmin())
        ) {
            $db->where("id", $address->id)->delete(T_ADDRESS);
            $data["status"] = 200;
            $data["message"] = "Your address has been deleted successfully";
        } else {
            $data["error"] = "Please check the details";
        }
    } else {
        $data["error"] = "Please check the details";
    }
}
if ($option == "get") {
	$limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
    $offset             = (isset($_POST['offset'])) ? secure($_POST['offset']) : 0;
    if (!empty($offset)) {
        $db->where('id',$offset,'<');
    }
    $address = $db->where('user_id', $music->user->id)->orderBy('id', 'DESC')->get(T_ADDRESS, $limit);
    $data["status"] = 200;
    $data["data"] = $address;
}
if ($option == "get_by_id") {
	$data["status"] = 400;
	if (!empty($_POST["id"]) && is_numeric($_POST["id"]) && $_POST["id"] > 0) {
		$id = secure($_POST["id"]);
		$address = $db->where('user_id', $music->user->id)->where('id', $id)->getOne(T_ADDRESS);
	    $data["status"] = 200;
	    $data["data"] = $address;
	}
	else{
		$data["error"] = "id can not be empty";
	}	
}