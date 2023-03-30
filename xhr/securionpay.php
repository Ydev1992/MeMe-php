<?php
use SecurionPay\SecurionPayGateway;
use SecurionPay\Exception\SecurionPayException;
use SecurionPay\Request\CheckoutRequestCharge;
use SecurionPay\Request\CheckoutRequest;
if (IS_LOGGED == false) {
    header("Location: $site_url");
    exit();
}
$types = ["wallet"];
if ($option == "token") {
    $data["status"] = 400;
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
            } else {
                $data["status"] = 400;
                $data["error"] = lang("Please check your details");
            }
        }
        if (!empty($price) && empty($data["error"])) {
            require_once "assets/libs/securionpay/vendor/autoload.php";
            $securionPay = new SecurionPayGateway(
                $music->config->securionpay_secret_key
            );
            $user_key = rand(1111, 9999) . rand(11111, 99999);

            $checkoutCharge = new CheckoutRequestCharge();
            $checkoutCharge
                ->amount($price * 100)
                ->currency("USD")
                ->metadata(["user_key" => $user->id, "type" => $_POST["type"]]);

            $checkoutRequest = new CheckoutRequest();
            $checkoutRequest->charge($checkoutCharge);

            $signedCheckoutRequest = $securionPay->signCheckoutRequest(
                $checkoutRequest
            );
            if (!empty($signedCheckoutRequest)) {
                $data["status"] = 200;
                $data["token"] = $signedCheckoutRequest;
            } else {
                $data["status"] = 400;
                $data["error"] = lang("Please check your details");
            }
        }
    }
}
if ($option == "handle") {
    if (
        !empty($_POST) &&
        !empty($_POST["charge"]) &&
        !empty($_POST["charge"]["id"])
    ) {
        $url = "https://api.securionpay.com/charges?limit=10";

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $curl,
            CURLOPT_USERPWD,
            $music->config->securionpay_secret_key . ":password"
        );
        $resp = curl_exec($curl);
        curl_close($curl);
        $resp = json_decode($resp, true);
        if (!empty($resp) && !empty($resp["list"])) {
            foreach ($resp["list"] as $key => $value) {
                if ($value["id"] == $_POST["charge"]["id"]) {
                    if (
                        !empty($value["metadata"]) &&
                        !empty($value["metadata"]["type"]) &&
                        in_array($value["metadata"]["type"], $types) &&
                        !empty($value["metadata"]["user_key"]) &&
                        !empty($value["amount"])
                    ) {
                        if (!empty($value["metadata"]["user_key"])) {
                            $price = intval(Secure($value["amount"])) / 100;
                            $type = $value["metadata"]["type"];

                            if ($type == "wallet") {
                                $updateUser = $db
                                    ->where("id", $user->id)
                                    ->update(T_USERS, [
                                        "wallet" => $db->inc($price),
                                    ]);
                                if ($updateUser) {
                                    CreatePayment([
                                        "user_id" => $user->id,
                                        "amount" => $price,
                                        "type" => "WALLET",
                                        "pro_plan" => 0,
                                        "info" => "Replenish My Balance",
                                        "via" => "securionpay",
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
                                    $data["error"] = lang(
                                        "something_went_wrong_please_try_again_later_"
                                    );
                                }
                            }
                        } else {
                            runPlugin('AfterFailedPayment');
                            $data["status"] = 400;
                            $data["error"] = lang(
                                "something_went_wrong_please_try_again_later_"
                            );
                        }
                    } else {
                        runPlugin('AfterFailedPayment');
                        $data["status"] = 400;
                        $data["error"] = lang(
                            "something_went_wrong_please_try_again_later_"
                        );
                    }
                }
            }
        } else {
            runPlugin('AfterFailedPayment');
            $data["status"] = 400;
            $data["error"] = lang(
                "something_went_wrong_please_try_again_later_"
            );
        }
    } else {
        $data["status"] = 400;
        $data["error"] = lang("Please check your details");
    }
}
