<?php
if (IS_LOGGED == false && $option != "pay") {
    header("Location: $site_url");
    exit();
}
$types = ["wallet"];
if ($option == "initialize") {
    if (!empty($_POST["type"]) && in_array($_POST["type"], $types)) {
        $type = secure($_POST["type"]);
        $price = 0;
        require_once "assets/libs/iyzipay/samples/config.php";

        if ($type == "wallet") {
            if (
                !empty($_POST["amount"]) &&
                is_numeric($_POST["amount"]) &&
                $_POST["amount"] > 0
            ) {
                $price = secure($_POST["amount"]);
                $callback_url = $music->config->site_url . "/endpoints/iyzipay/pay?type=wallet&user_id=" . $music->user->id . "&ConversationId=" . $ConversationId;
            } else {
                $data["status"] = 400;
                $data["error"] = lang("Please check your details");
            }
        }
        if (!empty($price) && empty($data["error"])) {
            
            $request->setPrice($price);
            $request->setPaidPrice($price);
            $request->setCallbackUrl($callback_url);

            $basketItems = [];
            $firstBasketItem = new \Iyzipay\Model\BasketItem();
            $firstBasketItem->setId("BI" . rand(11111111, 99999999));
            $firstBasketItem->setName($type);
            $firstBasketItem->setCategory1($type);
            $firstBasketItem->setItemType(
                \Iyzipay\Model\BasketItemType::PHYSICAL
            );
            $firstBasketItem->setPrice($price);
            $basketItems[0] = $firstBasketItem;
            $request->setBasketItems($basketItems);
            $checkoutFormInitialize = \Iyzipay\Model\CheckoutFormInitialize::create(
                $request,
                IyzipayConfig::options()
            );
            $content = $checkoutFormInitialize->getCheckoutFormContent();
            if (!empty($content)) {
                $data["html"] = $content;
                $data["status"] = 200;
            } else {
                $data["error"] = lang("Please check your details");
                $data["status"] = 400;
            }
        }
    }
}
if ($option == "pay") {
    if (!empty($_GET["type"]) && in_array($_GET["type"], $types) && !empty($_GET["ConversationId"]) && !empty($_GET["user_id"]) && is_numeric($_GET["user_id"])) {
        $ConversationId = secure($_GET["ConversationId"]);
        $user = $db->where("id", secure($_GET["user_id"]))->getOne(T_USERS);
        if (!empty($_POST["token"]) && !empty($user) && !empty($ConversationId)) {
            require_once "assets/libs/iyzipay/samples/config.php";

            # create request class
            $request = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
            $request->setLocale(\Iyzipay\Model\Locale::TR);
            $request->setConversationId($ConversationId);
            $request->setToken($_POST["token"]);

            # make request
            $checkoutForm = \Iyzipay\Model\CheckoutForm::retrieve(
                $request,
                IyzipayConfig::options()
            );

            # print result
            if ($checkoutForm->getPaymentStatus() == "SUCCESS") {
                $type = secure($_GET["type"]);

                if ($type == "wallet") {
                    $price = $checkoutForm->getPrice();
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
                            "via" => "Iyzipay",
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
                header("Location: $site_url/payment-error?reason=not-found");
                exit();
            }
        } else {
            runPlugin('AfterFailedPayment');
            header("Location: $site_url/payment-error?reason=not-found");
            exit();
        }
    }
    header("Location: $site_url/payment-error?reason=not-found");
    exit();
}