<?php
if (IS_LOGGED == false && $option != "pay") {
    header("Location: $site_url");
    exit();
}
$types = ["wallet"];
if ($option == "initialize") {
    if (
        !empty($_POST["type"]) &&
        in_array($_POST["type"], $types) &&
        !empty($_POST["phone"]) &&
        !empty($_POST["name"]) &&
        !empty($_POST["email"]) &&
        filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)
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
                $callback_url =
                    $music->config->site_url .
                    "/endpoints/cashfree/pay?type=wallet&user_id=" . $user->id;
            } else {
                $data["status"] = 400;
                $data["error"] = lang("Please check your details");
            }
        }

        if (!empty($price) && empty($data["error"])) {
            $result = [];
            $order_id = uniqid();
            $name = secure($_POST["name"]);
            $email = secure($_POST["email"]);
            $phone = secure($_POST["phone"]);

            $secretKey = $music->config->cashfree_secret_key;
            $postData = [
                "appId" => $music->config->cashfree_client_key,
                "orderId" => "order" . $order_id,
                "orderAmount" => $price,
                "orderCurrency" => "INR",
                "orderNote" => "",
                "customerName" => $name,
                "customerPhone" => $phone,
                "customerEmail" => $email,
                "returnUrl" => $callback_url,
                "notifyUrl" => $callback_url,
            ];
            // get secret key from your config
            ksort($postData);
            $signatureData = "";
            foreach ($postData as $key => $value) {
                $signatureData .= $key . $value;
            }
            $signature = hash_hmac("sha256", $signatureData, $secretKey, true);
            $signature = base64_encode($signature);
            $cashfree_link =
                "https://test.cashfree.com/billpay/checkout/post/submit";
            if ($music->config->cashfree_mode == "live") {
                $cashfree_link =
                    "https://www.cashfree.com/checkout/post/submit";
            }

            $form =
                '<form id="redirectForm" method="post" action="' .
                $cashfree_link .
                '"><input type="hidden" name="appId" value="' .
                $music->config->cashfree_client_key .
                '"/><input type="hidden" name="orderId" value="order' .
                $order_id .
                '"/><input type="hidden" name="orderAmount" value="' .
                $price .
                '"/><input type="hidden" name="orderCurrency" value="INR"/><input type="hidden" name="orderNote" value=""/><input type="hidden" name="customerName" value="' .
                $name .
                '"/><input type="hidden" name="customerEmail" value="' .
                $email .
                '"/><input type="hidden" name="customerPhone" value="' .
                $phone .
                '"/><input type="hidden" name="returnUrl" value="' .
                $callback_url .
                '"/><input type="hidden" name="notifyUrl" value="' .
                $callback_url .
                '"/><input type="hidden" name="signature" value="' .
                $signature .
                '"/></form>';
            $data["status"] = 200;
            $data["html"] = $form;
        }
    }
}
if ($option == "pay") {
    if (empty($_POST["txStatus"]) || $_POST["txStatus"] != "SUCCESS") {
        runPlugin('AfterFailedPayment');
        header("Location: $site_url");
        exit();
    }

    if (
        !empty($_GET["type"]) &&
        in_array($_GET["type"], $types) &&
        !empty($_GET["user_id"])
    ) {
        $user = $db
            ->where("id", secure($_GET["user_id"]))
            ->getOne(T_USERS);
        if (!empty($user)) {
            $orderId = $_POST["orderId"];
            $orderAmount = $_POST["orderAmount"];
            $referenceId = $_POST["referenceId"];
            $txStatus = $_POST["txStatus"];
            $paymentMode = $_POST["paymentMode"];
            $txMsg = $_POST["txMsg"];
            $txTime = $_POST["txTime"];
            $signature = $_POST["signature"];
            $data =
                $orderId .
                $orderAmount .
                $referenceId .
                $txStatus .
                $paymentMode .
                $txMsg .
                $txTime;
            $hash_hmac = hash_hmac(
                "sha256",
                $data,
                $music->config->cashfree_secret_key,
                true
            );
            $computedSignature = base64_encode($hash_hmac);
            if ($signature == $computedSignature) {
                $type = secure($_GET["type"]);

                if ($type == "wallet") {
                    $price = intval(secure($_POST["orderAmount"]));
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
                            "via" => "Cashfree",
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
            } else {
                runPlugin('AfterFailedPayment');
                header("Location: $site_url");
                exit();
            }
        }
    }
    header("Location: $site_url");
    exit();
}
