<?php
if (IS_LOGGED == false) {
    header("Location: $site_url");
    exit();
}
$types = ["wallet"];
if ($option == "initialize") {
    if (
        !empty($_POST["type"]) &&
        in_array($_POST["type"], $types) &&
        !empty($_POST["payment_id"]) &&
        !empty($_POST["order_id"]) &&
        !empty($_POST["amount"]) &&
        !empty($_POST["currency"])
    ) {
        $type = secure($_POST["type"]);
        $price = 0;

        if ($type == "wallet") {
            if (
                !empty($_POST["amount"]) &&
                is_numeric($_POST["amount"]) &&
                $_POST["amount"] > 0
            ) {
                $price = secure($_POST["amount"]);
            } else {
                $data["status"] = 400;
                $data["error"] = lang("Please check your details");
            }
        }
        if (!empty($price) && empty($data["error"])) {
            $payment_id = secure($_POST["payment_id"]);
            $currency_code = "INR";
            $check = [
                "amount" => $price,
                "currency" => $currency_code,
            ];
            $json = CheckRazorpayPayment($payment_id, $check);
            if (
                !empty($json) &&
                empty($json->error_code) &&
                empty($json->error)
            ) {
                $price = $price / 100;

                if ($type == "wallet") {
                    $updateUser = $db
                        ->where("id", $user->id)
                        ->update(T_USERS, ["wallet" => $db->inc($price)]);
                    if ($updateUser) {
                        CreatePayment([
                            "user_id" => $user->id,
                            "amount" => $price,
                            "type" => "WALLET",
                            "pro_plan" => 0,
                            "info" => "Replenish My Balance",
                            "via" => "razorpay",
                        ]);
                        $data["status"] = 200;
                        $re_url = $site_url . "/ads";
                        if (!empty($music) && !empty($music->user) && !empty($music->user->username)) {
                            $re_url = $site_url . "/settings/" .$music->user->username. "/wallet";
                        }
                        elseif (!empty($user) && !empty($user->username)) {
                            $re_url = $site_url . "/settings/" .$user->username. "/wallet";
                        }
                        $data["url"] = $re_url;
                    } else {
                        runPlugin('AfterFailedPayment');
                        $data["status"] = 400;
                        $data["error"] = lang("Please check your details");
                    }
                }
            } else {
                runPlugin('AfterFailedPayment');
                if (!empty($json->error_description)) {
                    $data["error"] = $json->error_description;
                } elseif (
                    !empty($json->error) &&
                    !empty($json->error->description)
                ) {
                    $data["error"] = $json->error->description;
                } else {
                    $data["error"] = lang("Something went wrong");
                }
                $data["status"] = 400;
            }
        }
    } else {
        runPlugin('AfterFailedPayment');
        $data["status"] = 400;
        $data["error"] = lang("Please check your details");
    }
}