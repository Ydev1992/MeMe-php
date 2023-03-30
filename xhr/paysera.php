<?php
if (IS_LOGGED == false) {
    header("Location: $site_url");
    exit();
}
$types = ["wallet"];
if ($option == "initialize") {
    if (!empty($_POST["type"]) && in_array($_POST["type"], $types)) {
        $type = secure($_POST["type"]);
        $price = 0;

        if ($type == "wallet") {
            if (
                !empty($_POST["amount"]) &&
                is_numeric($_POST["amount"]) &&
                $_POST["amount"] > 0
            ) {
                $price = secure($_POST["amount"]);
                $callback_url =
                    $music->config->site_url .
                    "/endpoints/paysera/pay?type=wallet&amount=" .
                    $price;
            } else {
                $data["status"] = 400;
                $data["error"] = lang("Please check your details");
            }
        }
        if (!empty($price) && empty($data["error"])) {
            $price = intval($price);
            require_once "assets/libs/Paysera.php";

            $request = WebToPay::redirectToPayment([
                "projectid" => $music->config->paysera_project_id,
                "sign_password" => $music->config->paysera_sign_password,
                "orderid" => rand(111111, 999999),
                "amount" => $price,
                "currency" => $music->config->currency,
                "country" => "LT",
                "accepturl" => $callback_url,
                "cancelurl" => $site_url . "/payment-error?reason=not-found",
                "callbackurl" => $site_url . "/payment-error?reason=not-found",
                "test" => $music->config->paysera_mode,
            ]);
            $data = ["status" => 200, "url" => $request];
        }
    }
}
if ($option == "pay") {
    if (!empty($_GET["type"]) && in_array($_GET["type"], $types)) {
        try {
            require_once "assets/libs/Paysera.php";
            $response = WebToPay::checkResponse($_GET, [
                "projectid" => $music->config->paysera_project_id,
                "sign_password" => $music->config->paysera_sign_password,
            ]);

            // if ($response['test'] !== '0') {
            //     throw new Exception('Testing, real payment was not made');
            // }
            if ($response["type"] !== "macro") {
                header("Location: $site_url/payment-error?reason=not-found");
                exit();
                //throw new Exception('Only macro payment callbacks are accepted');
            }
            $orderId = $response["orderid"];
            $amount = $response["amount"];
            $currency = $response["currency"];

            if ($currency != $music->config->currency) {
                header("Location: $site_url/payment-error?reason=no-price");
                exit();
            }
            $type = secure($_GET["type"]);

            if ($type == "wallet") {
                $price = $amount;
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
                        "via" => "paysera",
                    ]);
                    $re_url = $site_url . "/ads";
                    if (!empty($music) && !empty($music->user) && !empty($music->user->username)) {
                        $re_url = $site_url . "/settings/" .$music->user->username. "/wallet";
                    }
                    elseif (!empty($user) && !empty($user->username)) {
                        $re_url = $site_url . "/settings/" .$user->username. "/wallet";
                    }
                    header("Location: " . $re_url);
                    exit();
                } else {
                    runPlugin('AfterFailedPayment');
                    header(
                        "Location: $site_url/payment-error?reason=cant-create-payment"
                    );
                    exit();
                }
            }
        } catch (Exception $e) {
            runPlugin('AfterFailedPayment');
            header("Location: $site_url/payment-error?reason=something-wrong");
            exit();
        }
    }
}
