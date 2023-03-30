<?php
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
if (IS_LOGGED == false) {
    header("Location: $site_url");
    exit();
}
$types = ["wallet"];
if ($option == "pay") {
    $data["status"] = 400;
    if (
        !empty($_POST["type"]) &&
        in_array($_POST["type"], $types) &&
        !empty($_POST["card_number"]) &&
        !empty($_POST["card_month"]) &&
        !empty($_POST["card_year"]) &&
        !empty($_POST["card_cvc"])
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
        require_once "assets/libs/authorize/vendor/autoload.php";
        $APILoginId = $music->config->authorize_login_id;
        $APIKey = $music->config->authorize_transaction_key;
        $refId = "ref" . time();
        define("AUTHORIZE_MODE", $music->config->authorize_test_mode);

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($APILoginId);
        $merchantAuthentication->setTransactionKey($APIKey);

        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber($_POST["card_number"]);
        $creditCard->setExpirationDate(
            $_POST["card_year"] . "-" . $_POST["card_month"]
        );
        $creditCard->setCardCode($_POST["card_cvc"]);

        $paymentType = new AnetAPI\PaymentType();
        $paymentType->setCreditCard($creditCard);

        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType("authCaptureTransaction");
        $transactionRequestType->setAmount($price);
        $transactionRequestType->setPayment($paymentType);

        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequestType);
        $controller = new AnetController\CreateTransactionController($request);
        if ($music->config->authorize_test_mode == "SANDBOX") {
            $Aresponse = $controller->executeWithApiResponse(
                \net\authorize\api\constants\ANetEnvironment::SANDBOX
            );
        } else {
            $Aresponse = $controller->executeWithApiResponse(
                \net\authorize\api\constants\ANetEnvironment::PRODUCTION
            );
        }

        if ($Aresponse != null) {
            if ($Aresponse->getMessages()->getResultCode() == "Ok") {
                $trans = $Aresponse->getTransactionResponse();
                if ($trans != null && $trans->getMessages() != null) {
                    $type = secure($_POST["type"]);
                    $price = 0;

                    if ($type == "wallet") {
                        if (
                            !empty($_POST["amount"]) &&
                            is_numeric($_POST["amount"]) &&
                            $_POST["amount"] > 0
                        ) {
                            $price = secure($_POST["amount"]);
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
                                    "via" => "Authorize",
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
                                $data["error"] = lang(
                                    "something_went_wrong_please_try_again_later_"
                                );
                                runPlugin('AfterFailedPayment');
                            }
                        } else {
                            $data["status"] = 400;
                            $data["error"] = lang("Please check your details");
                        }
                    }
                } else {
                    $error = lang(
                        "something_went_wrong_please_try_again_later_"
                    );
                    runPlugin('AfterFailedPayment');
                    if ($trans->getErrors() != null) {
                        $error = $trans->getErrors()[0]->getErrorText();
                    }
                    $data["status"] = 400;
                    $data["error"] = $error;
                }
            } else {
                $trans = $Aresponse->getTransactionResponse();
                $error = lang("something_went_wrong_please_try_again_later_");
                if ($trans->getErrors() != null) {
                    $error = $trans->getErrors()[0]->getErrorText();
                }
                $data["status"] = 400;
                $data["error"] = $error;
            }
        } else {
            $data["status"] = 400;
            $data["error"] = lang("Please check your details");
        }
    } else {
        $data["status"] = 400;
        $data["error"] = lang("Please check your details");
    }
}
