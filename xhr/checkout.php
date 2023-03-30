<?php
if (IS_LOGGED == false) {
    header("Location: $site_url");
    exit();
}
$types = ["wallet"];
if (!empty($_POST["type"]) && in_array($_POST["type"], $types)) {
    if (
        empty($_POST["card_number"]) ||
        empty($_POST["card_cvc"]) ||
        empty($_POST["card_month"]) ||
        empty($_POST["card_year"]) ||
        empty($_POST["token"]) ||
        empty($_POST["card_name"]) ||
        empty($_POST["card_address"]) ||
        empty($_POST["card_city"]) ||
        empty($_POST["card_state"]) ||
        empty($_POST["card_zip"]) ||
        empty($_POST["card_country"]) ||
        empty($_POST["card_email"]) ||
        empty($_POST["card_phone"]) ||
        empty($_POST["token"])
    ) {
        $data = [
            "status" => 400,
            "error" => lang("Please check your details"),
        ];
    } else {
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
                    "/endpoints/paystack/pay?type=wallet&amount=" .
                    $price;
            } else {
                $data["status"] = 400;
                $data["error"] = lang("Please check your details");
            }
        }
        if (!empty($price) && empty($data["error"])) {
            require_once 'assets/libs/2checkout/lib/Twocheckout.php';
            Twocheckout::privateKey($music->config->checkout_private_key);
            Twocheckout::sellerId($music->config->checkout_seller_id);
            if ($music->config->checkout_mode == "sandbox") {
                Twocheckout::sandbox(true);
            } else {
                Twocheckout::sandbox(false);
            }
            try {
                $amount1 = $price;
                $charge = Twocheckout_Charge::auth([
                    "merchantOrderId" => "123",
                    "token" => $_POST["token"],
                    "currency" => $music->config->checkout_currency,
                    "total" => $amount1,
                    "billingAddr" => [
                        "name" => $_POST["card_name"],
                        "addrLine1" => $_POST["card_address"],
                        "city" => $_POST["card_city"],
                        "state" => $_POST["card_state"],
                        "zipCode" => $_POST["card_zip"],
                        "country" => $countries_name[$_POST["card_country"]],
                        "email" => $_POST["card_email"],
                        "phoneNumber" => $_POST["card_phone"],
                    ],
                ]);
                if ($charge["response"]["responseCode"] == "APPROVED") {
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
                                "via" => "2checkout",
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
                            $data["status"] = 400;
                            $data["error"] = lang("Please check your details");
                        }
                    }
                } else {
                    runPlugin('AfterFailedPayment');
                    $data = [
                        "status" => 400,
                        "error" => lang(
                            "Your payment was declined, please contact your bank or card issuer and make sure you have the required funds."
                        ),
                    ];
                }
            } catch (Twocheckout_Error $e) {
                runPlugin('AfterFailedPayment');
                $data = [
                    "status" => 400,
                    "error" => $e->getMessage(),
                ];
            }
        }
    }
}
