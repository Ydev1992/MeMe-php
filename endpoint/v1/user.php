<?php
if ($option == 'general' || $option == 'profile' || $option == 'password' || $option == 'delete' || $option == 'get-profile' || $option == 'update_notification_setting') {
    if (empty($_POST['user_id']) || !is_numeric($_POST['user_id']) || $_POST['user_id'] == 0) {
        $errors[] = "Invalid user ID";
    } else {
        $userData = userData($_POST['user_id']);
    }
}
if ($option == 'top_wallet_paypal') {
    if (!empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0) {
        $price = secure($_POST['price']);
        $updateUser = $db->where('id', $user->id)->update(T_USERS, ['wallet' => $db->inc($price)]);
        if ($updateUser) {
            CreatePayment(array(
                'user_id'   => $user->id,
                'amount'    => $price,
                'type'      => 'WALLET',
                'pro_plan'  => 0,
                'info'      => 'Replenish My Balance',
                'via'       => 'PayPal'
            ));
            $data = [
                        'status' => 200,
                        'message' => 'paid successfully'
                    ];
        } else {
            $errors[] = "something went wrong";
        }
    }
    else{
        $errors[] = "price can not be empty";
    }
    if (!empty($errors)) {
        $data = array('status' => 400, 'error' => $errors);
        echo json_encode($data);
        exit();
    }
}
if ($option == 'top_wallet_stripe') {
    if (!empty($_POST['token']) && !empty($_POST['price']) && is_numeric($_POST['price'])) {
        require_once('assets/import/stripe_v8/vendor/autoload.php');
        $stripe = array(
            'secret_key' => $music->config->stripe_secret,
            'publishable_key' => $music->config->stripe_id
        );
        \Stripe\Stripe::setApiKey($stripe[ 'secret_key' ]);

        $customer = \Stripe\Customer::create(array(
            'source' => $_POST['token']
        ));

        $charge   = \Stripe\Charge::create(array(
            'customer' => $customer->id,
            'amount' => $price,
            'currency' => $music->config->stripe_currency
        ));


        // $intent = \Stripe\PaymentIntent::retrieve($_POST['token']);
        // $charges = $intent->charges->data;
        if (!empty($charges)) {
            // $price = ($charges[0]->amount / 100);

            $updateUser = $db->where('id', $user->id)->update(T_USERS, ['wallet' => $db->inc($price)]);
            if ($updateUser) {
                CreatePayment(array(
                    'user_id'   => $user->id,
                    'amount'    => $price,
                    'type'      => 'WALLET',
                    'pro_plan'  => 0,
                    'info'      => 'Replenish My Balance',
                    'via'       => 'Stripe'
                ));
                $data = [
                        'status' => 200,
                        'message' => 'paid successfully'
                    ];
            } else {
                $errors[] = "something went wrong";
            }
        }
        else{
            $errors[] = "Wrong token";
        }
    }
    else{
        $errors[] = "token can not be empty";
    }
    if (!empty($errors)) {
        $data = array('status' => 400, 'error' => $errors);
        echo json_encode($data);
        exit();
    }
}
if ($option == 'top_wallet_cashfree') {
    if (!empty($_POST['type']) && in_array($_POST['type'], array('initialize','pay'))) {
        if ($_POST['type'] == 'initialize') {
            if (!empty($_POST['phone']) && !empty($_POST['name']) && !empty($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                if (!empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0) {
                    $price = secure($_POST['price']);
                    $callback_url = $music->config->site_url . "/endpoints/cashfree/pay?type=wallet&amount=".$price;
                    $result = array();
                    $order_id = uniqid();
                    $name = secure($_POST['name']);
                    $email = secure($_POST['email']);
                    $phone = secure($_POST['phone']);


                    $secretKey = $music->config->cashfree_secret_key;
                    $postData = array( 
                      "appId" => $music->config->cashfree_client_key, 
                      "orderId" => "order".$order_id, 
                      "orderAmount" => $price, 
                      "orderCurrency" => "INR", 
                      "orderNote" => "", 
                      "customerName" => $name, 
                      "customerPhone" => $phone, 
                      "customerEmail" => $email,
                      "returnUrl" => $callback_url, 
                      "notifyUrl" => $callback_url,
                    );
                     // get secret key from your config
                     ksort($postData);
                     $signatureData = "";
                     foreach ($postData as $key => $value){
                          $signatureData .= $key.$value;
                     }
                     $signature = hash_hmac('sha256', $signatureData, $secretKey,true);
                     $signature = base64_encode($signature);
                     $cashfree_link = 'https://test.cashfree.com/billpay/checkout/post/submit';
                     if ($music->config->cashfree_mode == 'live') {
                        $cashfree_link = 'https://www.cashfree.com/checkout/post/submit';
                     }

                    $form = '<form id="redirectForm" method="post" action="'.$cashfree_link.'"><input type="hidden" name="appId" value="'.$music->config->cashfree_client_key.'"/><input type="hidden" name="orderId" value="order'.$order_id.'"/><input type="hidden" name="orderAmount" value="'.$price.'"/><input type="hidden" name="orderCurrency" value="INR"/><input type="hidden" name="orderNote" value=""/><input type="hidden" name="customerName" value="'.$name.'"/><input type="hidden" name="customerEmail" value="'.$email.'"/><input type="hidden" name="customerPhone" value="'.$phone.'"/><input type="hidden" name="returnUrl" value="'.$callback_url.'"/><input type="hidden" name="notifyUrl" value="'.$callback_url.'"/><input type="hidden" name="signature" value="'.$signature.'"/></form>';
                    $data['status'] = 200;
                    $data['html'] = $form;
                    $data['cashfree_link'] = $cashfree_link;
                    $data['appId'] = $music->config->cashfree_client_key;
                    $data['orderId'] = $order_id;
                    $data['orderAmount'] = $price;
                    $data['orderCurrency'] = 'INR';
                    $data['orderNote'] = '';
                    $data['customerName'] = $name;
                    $data['customerEmail'] = $email;
                    $data['customerPhone'] = $phone;
                    $data['returnUrl'] = $callback_url;
                    $data['notifyUrl'] = $callback_url;
                    $data['signature'] = $signature;
                }
                else{
                    $errors[] = "price can not be empty";
                }
            }
            else{
                $errors[] = "phone , name , email can not be empty";
            }
        }
        else{
            if (!empty($_POST['txStatus']) && $_POST['txStatus'] == 'SUCCESS') {
                if (!empty($_POST["orderId"]) && !empty($_POST["orderAmount"]) && !empty($_POST["referenceId"]) && !empty($_POST["paymentMode"]) && !empty($_POST["txMsg"]) && !empty($_POST["txTime"]) && !empty($_POST["signature"]) && !empty($_POST["price"]) && is_numeric($_POST["price"]) && $_POST["price"] > 0) {
                    $orderId = $_POST["orderId"];
                    $orderAmount = $_POST["orderAmount"];
                    $referenceId = $_POST["referenceId"];
                    $txStatus = $_POST["txStatus"];
                    $paymentMode = $_POST["paymentMode"];
                    $txMsg = $_POST["txMsg"];
                    $txTime = $_POST["txTime"];
                    $signature = $_POST["signature"];
                    $data = $orderId.$orderAmount.$referenceId.$txStatus.$paymentMode.$txMsg.$txTime;
                    $hash_hmac = hash_hmac('sha256', $data, $music->config->cashfree_secret_key, true) ;
                    $computedSignature = base64_encode($hash_hmac);
                    if ($signature == $computedSignature) {
                        $price = secure($_POST['price']);
                        $updateUser = $db->where('id', $user->id)->update(T_USERS, ['wallet' => $db->inc($price)]);
                        if ($updateUser) {
                            CreatePayment(array(
                                'user_id'   => $user->id,
                                'amount'    => $price,
                                'type'      => 'WALLET',
                                'pro_plan'  => 0,
                                'info'      => 'Replenish My Balance',
                                'via'       => 'Cashfree'
                            ));
                            $data = [
                                'status' => 200,
                                'message' => 'paid successfully'
                            ];
                        } else {
                            $errors[] = "something went wrong";
                        }
                    }
                    else{
                        $errors[] = "wrong data";
                    }
                }
                else{
                    $errors[] = "orderId , orderAmount , referenceId , paymentMode , txMsg , txTime , signature , price can not be empty";
                }
            }
            else{
                $errors[] = "txStatus empty or not SUCCESS";
            }
        }
    }
    else{
        $errors[] = "type can not be empty";
    }
    if (!empty($errors)) {
        $data = array('status' => 400, 'error' => $errors);
        echo json_encode($data);
        exit();
    }
}
if ($option == 'top_wallet_razorpay') {
    if (!empty($_POST['payment_id']) && !empty($_POST['merchant_amount']) && !empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0) {
        $payment_id = secure($_POST['payment_id']);
        $price = secure($_POST['price']);
        $currency_code = "INR";
        $check = array(
            'amount' => $price,
            'currency' => $currency_code,
        );
        $json = CheckRazorpayPayment($payment_id,$check);
        if (!empty($json) && empty($json->error_code) && empty($json->error)) {
            $price = ($price / 100);
            $updateUser = $db->where('id', $user->id)->update(T_USERS, ['wallet' => $db->inc($price)]);
            if ($updateUser) {
                CreatePayment(array(
                    'user_id'   => $user->id,
                    'amount'    => $price,
                    'type'      => 'WALLET',
                    'pro_plan'  => 0,
                    'info'      => 'Replenish My Balance',
                    'via'       => 'razorpay'
                ));
                $data = [
                        'status' => 200,
                        'message' => 'paid successfully'
                    ];
            } else {
                $errors[] = "something went wrong";
            }
        }
        else{
            if (!empty($json->error_description)) {
                $errors[] = $json->error_description;
            }
            elseif (!empty($json->error) && !empty($json->error->description)) {
                $errors[] = $json->error->description;
            }
            else{
                $errors[] = "wrong data";
            }
        }
    }
    else{
        $errors[] = "payment_id , merchant_amount , price can not be empty";
    }
    if (!empty($errors)) {
        $data = array('status' => 400, 'error' => $errors);
        echo json_encode($data);
        exit();
    }
}
if ($option == 'top_wallet_paystack') {
    if (!empty($_POST['type']) && in_array($_POST['type'], array('initialize','pay'))) {
        if ($_POST['type'] == 'initialize') {
            if (!empty($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) && !empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0) {
                $price = secure($_POST['price']);
                $callback_url = $music->config->site_url . "/endpoints/paystack/pay?type=wallet&amount=".$price;
                $result = array();
                $reference = uniqid();
                $price = (int) $price * 100;

                //Set other parameters as keys in the $postdata array
                $postdata =  array('email' => $_POST['email'], 'amount' => $price,"reference" => $reference,'callback_url' => $callback_url);
                $url = "https://api.paystack.co/transaction/initialize";

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL,$url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($postdata));  //Post Fields
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $headers = [
                  'Authorization: Bearer '.$music->config->paystack_secret_key,
                  'Content-Type: application/json',

                ];
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                $request = curl_exec ($ch);

                curl_close ($ch);

                if (!empty($request)) {
                    $result = json_decode($request, true);
                    if (!empty($result)) {
                         if (!empty($result['status']) && $result['status'] == 1 && !empty($result['data']) && !empty($result['data']['authorization_url']) && !empty($result['data']['access_code'])) {
                            $data['status'] = 200;
                            $data['url'] = $result['data']['authorization_url'];
                            $data['reference'] = $reference;
                        }
                        else{
                            $errors[] = $result['message'];
                        }
                    }
                    else{
                        $errors[] = "empty response from paystack server";
                    }
                }
                else{
                    $errors[] = "empty response from paystack server";
                }
            }
            else{
                $errors[] = "email , price can not be empty";
            }
        }
        else{
            if (!empty($_POST['reference']) && !empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0) {
                $payment  = CheckPaystackPayment($_POST['reference']);
                if ($payment) {
                    $price = secure($_POST['price']);
                    $updateUser = $db->where('id', $user->id)->update(T_USERS, ['wallet' => $db->inc($price)]);
                    if ($updateUser) {
                        CreatePayment(array(
                            'user_id'   => $user->id,
                            'amount'    => $price,
                            'type'      => 'WALLET',
                            'pro_plan'  => 0,
                            'info'      => 'Replenish My Balance',
                            'via'       => 'paystack'
                        ));
                        $data = [
                            'status' => 200,
                            'message' => 'paid successfully'
                        ];
                    } else {
                        $errors[] = "something went wrong";
                    }
                }
                else{
                    $errors[] = "Wrong data";
                }
            }
            else{
                $errors[] = "reference , price can not be empty";
            }
        }
    }
    else{
        $errors[] = "type can not be empty";
    }
    if (!empty($errors)) {
        $data = array('status' => 400, 'error' => $errors);
        echo json_encode($data);
        exit();
    }
}
if ($option == 'top_wallet_paysera') {
    if (!empty($_POST['type']) && in_array($_POST['type'], array('initialize','pay'))) {
        if ($_POST['type'] == 'initialize') {
            if (!empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0) {
                $price = secure($_POST['price']);
                $callback_url = $music->config->site_url . "/endpoints/paysera/pay?type=wallet&amount=".$price;
                $price = intval($price);
                require_once 'assets/import/Paysera.php';

                $request = WebToPay::redirectToPayment(array(
                    'projectid'     => $music->config->paysera_project_id,
                    'sign_password' => $music->config->paysera_sign_password,
                    'orderid'       => rand(111111,999999),
                    'amount'        => $price,
                    'currency'      => $music->config->currency,
                    'country'       => 'LT',
                    'accepturl'     => $callback_url,
                    'cancelurl'     => $site_url.'/payment-error?reason=not-found',
                    'callbackurl'   => $site_url.'/payment-error?reason=not-found',
                    'test'          => $music->config->paysera_mode,
                ));
                $data = array('status' => 200,
                              'url' => $request);
            }
            else{
                $errors[] = "price can not be empty";
            }
        }
        else{
            try {
                require_once 'assets/import/Paysera.php';
                $response = WebToPay::checkResponse($_GET, array(
                    'projectid'     => $music->config->paysera_project_id,
                    'sign_password' => $music->config->paysera_sign_password,
                ));
         
                // if ($response['test'] !== '0') {
                //     throw new Exception('Testing, real payment was not made');
                // }
                if ($response['type'] !== 'macro') {
                    $errors[] = "Only macro payment callbacks are accepted";
                    //throw new Exception('Only macro payment callbacks are accepted');
                }
                $orderId = $response['orderid'];
                $amount = $response['amount'];
                $currency = $response['currency'];

                if ($currency != $music->config->currency) {
                    $errors[] = "Wrong currency";
                }
                $price = $amount;
                $updateUser = $db->where('id', $user->id)->update(T_USERS, ['wallet' => $db->inc($price)]);
                if ($updateUser) {
                    CreatePayment(array(
                        'user_id'   => $user->id,
                        'amount'    => $price,
                        'type'      => 'WALLET',
                        'pro_plan'  => 0,
                        'info'      => 'Replenish My Balance',
                        'via'       => 'paysera'
                    ));
                    $data = [
                        'status' => 200,
                        'message' => 'paid successfully'
                    ];
                } else {
                    $errors[] = "something went wrong";
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
    else{
        $errors[] = "type can not be empty";
    }
    if (!empty($errors)) {
        $data = array('status' => 400, 'error' => $errors);
        echo json_encode($data);
        exit();
    }
} 
if ($option == 'top_wallet_iyzipay') {
    if (!empty($_POST['type']) && in_array($_POST['type'], array('initialize','pay'))) {
        if ($_POST['type'] == 'initialize') {
            if (!empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0) {
                $type = 'wallet';
                $price = secure($_POST['price']);
                $callback_url = $music->config->site_url . "/endpoints/iyzipay/pay?type=wallet&amount=".$price;
                require_once 'assets/import/iyzipay/samples/config.php';
                $request->setPrice($price);
                $request->setPaidPrice($price);
                $request->setCallbackUrl($callback_url);
                

                $basketItems = array();
                $firstBasketItem = new \Iyzipay\Model\BasketItem();
                $firstBasketItem->setId("BI".rand(11111111,99999999));
                $firstBasketItem->setName($type);
                $firstBasketItem->setCategory1($type);
                $firstBasketItem->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
                $firstBasketItem->setPrice($price);
                $basketItems[0] = $firstBasketItem;
                $request->setBasketItems($basketItems);
                $checkoutFormInitialize = \Iyzipay\Model\CheckoutFormInitialize::create($request, IyzipayConfig::options());
                $content = $checkoutFormInitialize->getCheckoutFormContent();
                if (!empty($content)) {
                    $data['html'] = $content;
                    $data['status'] = 200;
                    $data['ConversationId'] = $ConversationId;
                }
                else{
                    $errors[] = "can not create payment";
                }
            }
            else{
                $errors[] = "price can not be empty";
            }
        }
        else{
            if (!empty($_POST['token']) && !empty($_POST['ConversationId']) && !empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0) {
                require_once 'assets/import/iyzipay/samples/config.php';

                # create request class
                $request = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
                $request->setLocale(\Iyzipay\Model\Locale::TR);
                $request->setConversationId($_POST['ConversationId']);
                $request->setToken($_POST['token']);

                # make request
                $checkoutForm = \Iyzipay\Model\CheckoutForm::retrieve($request, Config::options());

                # print result
                if ($checkoutForm->getPaymentStatus() == 'SUCCESS') {
                    $price = secure($_POST['price']);
                    $updateUser = $db->where('id', $user->id)->update(T_USERS, ['wallet' => $db->inc($price)]);
                    if ($updateUser) {
                        CreatePayment(array(
                            'user_id'   => $user->id,
                            'amount'    => $price,
                            'type'      => 'WALLET',
                            'pro_plan'  => 0,
                            'info'      => 'Replenish My Balance',
                            'via'       => 'Iyzipay'
                        ));
                        $data = [
                            'status' => 200,
                            'message' => 'paid successfully'
                        ];
                    } else {
                        $errors[] = "something went wrong";
                    }
                }
            }
            else{
                $errors[] = "token or ConversationId or price not set";
            }
        }
    }
    else{
        $errors[] = "type can not be empty";
    }
    if (!empty($errors)) {
        $data = array('status' => 400, 'error' => $errors);
        echo json_encode($data);
        exit();
    }
}
if ($option == 'top_wallet_2Checkout') {
    if (empty($_POST['card_number']) || empty($_POST['card_cvc']) || empty($_POST['card_month']) || empty($_POST['card_year']) || empty($_POST['token']) || empty($_POST['card_name']) || empty($_POST['card_address']) || empty($_POST['card_city']) || empty($_POST['card_state']) || empty($_POST['card_zip']) || empty($_POST['card_country']) || empty($_POST['card_email']) || empty($_POST['card_phone'])) {
        $errors = 'card_number,card_cvc,card_month,card_year,token,card_name,card_address,card_city,card_state,card_zip,card_country,card_email,card_phone can not be empty';
    }
    else{
        if (!empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0) {
            $price = secure($_POST['price']);
            $callback_url = $music->config->site_url . "/endpoints/paystack/pay?type=wallet&amount=".$price;
            require_once 'assets/import/2checkout_0_4_0/lib/Twocheckout.php';
            Twocheckout::privateKey($music->config->checkout_private_key);
            Twocheckout::sellerId($music->config->checkout_seller_id);
            if ($music->config->checkout_mode == 'sandbox') {
                Twocheckout::sandbox(true);
            } else {
                Twocheckout::sandbox(false);
            }
            try {
                $amount1 = $price;
                $charge  = Twocheckout_Charge::auth(array(
                    "merchantOrderId" => "123",
                    "token" => $_POST['token'],
                    "currency" => $music->config->checkout_currency,
                    "total" => $amount1,
                    "billingAddr" => array(
                        "name" => $_POST['card_name'],
                        "addrLine1" => $_POST['card_address'],
                        "city" => $_POST['card_city'],
                        "state" => $_POST['card_state'],
                        "zipCode" => $_POST['card_zip'],
                        "country" => $countries_name[$_POST['card_country']],
                        "email" => $_POST['card_email'],
                        "phoneNumber" => $_POST['card_phone']
                    )
                ));
                if ($charge['response']['responseCode'] == 'APPROVED') {
                    $updateUser = $db->where('id', $user->id)->update(T_USERS, ['wallet' => $db->inc($price)]);
                    if ($updateUser) {
                        CreatePayment(array(
                            'user_id'   => $user->id,
                            'amount'    => $price,
                            'type'      => 'WALLET',
                            'pro_plan'  => 0,
                            'info'      => 'Replenish My Balance',
                            'via'       => '2checkout'
                        ));
                        $data = [
                            'status' => 200,
                            'message' => 'paid successfully'
                        ];
                    } else {
                        $errors[] = "something went wrong";
                    }
                }
                 else {
                    $errors[] = "Your payment was declined, please contact your bank or card issuer and make sure you have the required funds.";
                }
            }
            catch (Twocheckout_Error $e) {
                $errors[] = $e->getMessage();
            }
        }
        else{
            $errors[] = "price can not be empty";
        }
    }
    if (!empty($errors)) {
        $data = array('status' => 400, 'error' => $errors);
        echo json_encode($data);
        exit();
    }
}
if ($option == 'create_yoomoney') {
    $data['status'] = 400;
    if (!empty($_POST['amount']) && is_numeric($_POST['amount']) && $_POST['amount'] > 0) {
        $amount = secure($_POST['amount']);
        $order_id = uniqid();
        $yoomoney_hash = rand(11111,99999).rand(11111,99999);
        $receiver = $music->config->yoomoney_wallet_id;
        $successURL = $music->config->site_url ."/endpoints/yoomoney/success_yoomoney";
        $form = '<form id="yoomoney_form" method="POST" action="https://yoomoney.ru/quickpay/confirm.xml">    
                    <input type="hidden" name="receiver" value="'.$receiver.'"> 
                    <input type="hidden" name="quickpay-form" value="donate"> 
                    <input type="hidden" name="targets" value="transaction '.$order_id.'">   
                    <input type="hidden" name="paymentType" value="PC"> 
                    <input type="hidden" name="sum" value="'.$amount.'" data-type="number"> 
                    <input type="hidden" name="successURL" value="'.$successURL.'">
                    <input type="hidden" name="label" value="'.$yoomoney_hash.'">
                </form>';
        $data     = array(
            'status'   => '200',
            'form'    => $form,
            'url'    => "https://yoomoney.ru/quickpay/confirm.xml",
            'receiver'    => $receiver,
            'quickpay-form'    => "donate",
            'targets'    => 'transaction '.$order_id,
            'paymentType'    => "PC",
            'sum'    => $amount,
            'successURL'    => $successURL,
            'label'    => $yoomoney_hash,
        );
    }
    else{
        $data = array(
            'status' => 400,
            'error' => 'amount can not be empty'
        );
    }
}
if ($option == 'success_yoomoney') {
    if (!empty($_POST['notification_type']) && !empty($_POST['operation_id']) && !empty($_POST['amount']) && !empty($_POST['currency']) && !empty($_POST['datetime']) && !empty($_POST['sender']) && !empty($_POST['codepro']) && !empty($_POST['label']) && !empty($_POST['sha1_hash'])) {
        $hash = sha1($_POST['notification_type'].'&'.
        $_POST['operation_id'].'&'.
        $_POST['amount'].'&'.
        $_POST['currency'].'&'.
        $_POST['datetime'].'&'.
        $_POST['sender'].'&'.
        $_POST['codepro'].'&'.
        $music->config->yoomoney_notifications_secret.'&'.
        $_POST['label']);

        if ($_POST['sha1_hash'] != $hash || $_POST['codepro'] == true || $_POST['unaccepted'] == true) {
            $data = array(
                'status' => 400,
                'error' => 'something went wrong'
            );
        }
        else{
            if (!empty($_POST['label'])) {
                $amount = secure($_POST['amount']);
                $updateUser = $db
                        ->where("id", $user->id)
                        ->update(T_USERS, ["wallet" => $db->inc($amount)]);
                CreatePayment([
                    "user_id" => $user->id,
                    "amount" => $amount,
                    "type" => "WALLET",
                    "pro_plan" => 0,
                    "info" => "Replenish My Balance",
                    "via" => "Yoomoney",
                ]);
                $data     = array(
                    'status'   => '200',
                    'message' => 'paid successfully'
                );
            }
        }
    }
    else{
        $data = array(
            'status' => 400,
            'error' => 'notification_type , operation_id , amount , currency , datetime , sender , codepro , label , sha1_hash can not be empty'
        );
    }
}
if ($option == 'get_fortumo') {
    $hash = rand(11111,55555).rand(11111,55555);
    $data['status'] = 200;
    $data['url'] = 'https://pay.fortumo.com/mobile_payments/'.$music->config->fortumo_service_id.'?cuid='.$hash;
}
if ($option == 'success_fortumo') {
    if (!empty($_POST) && !empty($_POST['amount']) && !empty($_POST['status']) && $_POST['status'] == 'completed' && !empty($_POST['cuid']) && !empty($_POST['price'])) {
        $amount = (int) secure($_POST['price']);
        $music->user = $user;
        $updateUser = $db
                ->where("id", $music->user->id)
                ->update(T_USERS, ["wallet" => $db->inc($amount)]);
        CreatePayment([
            "user_id" => $music->user->id,
            "amount" => $amount,
            "type" => "WALLET",
            "pro_plan" => 0,
            "info" => "Replenish My Balance",
            "via" => "fortumo",
        ]);
        $data     = array(
            'status'   => 200,
            'message' => 'paid successfully'
        );
    }
    else{
        $data = array(
            'status' => 400,
            'error' => 'amount , status , cuid , price can not be empty'
        );
    }
}
if ($option == 'get_aamarpay') {
    if (!empty($_POST['amount']) && is_numeric($_POST['amount']) && $_POST['amount'] > 0 && !empty($_POST['name']) && !empty($_POST['email']) && !empty($_POST['phone'])) {
        $amount   = (int)secure($_POST[ 'amount' ]);
        $name   = secure($_POST[ 'name' ]);
        $email   = secure($_POST[ 'email' ]);
        $phone   = secure($_POST[ 'phone' ]);
        if ($music->config->aamarpay_mode == 'sandbox') {
            $url = 'https://sandbox.aamarpay.com/request.php'; // live url https://secure.aamarpay.com/request.php
        }
        else {
            $url = 'https://secure.aamarpay.com/request.php';
        }
        $tran_id = rand(1111111,9999999);
        $fields = array(
            'store_id' => $music->config->aamarpay_store_id, //store id will be aamarpay,  contact integration@aamarpay.com for test/live id
            'amount' => $amount, //transaction amount
            'payment_type' => 'VISA', //no need to change
            'currency' => 'BDT',  //currenct will be USD/BDT
            'tran_id' => $tran_id, //transaction id must be unique from your end
            'cus_name' => $name,  //customer name
            'cus_email' => $email, //customer email address
            'cus_add1' => '',  //customer address
            'cus_add2' => '', //customer address
            'cus_city' => '',  //customer city
            'cus_state' => '',  //state
            'cus_postcode' => '', //postcode or zipcode
            'cus_country' => 'Bangladesh',  //country
            'cus_phone' => $phone, //customer phone number
            'cus_fax' => 'Not¬Applicable',  //fax
            'ship_name' => '', //ship name
            'ship_add1' => '',  //ship address
            'ship_add2' => '',
            'ship_city' => '',
            'ship_state' => '',
            'ship_postcode' => '',
            'ship_country' => 'Bangladesh',
            'desc' => 'top up wallet',
            'success_url' => $music->config->site_url ."/endpoints/aamarpay/success_aamarpay", //your success route
            'fail_url' => $music->config->site_url ."/endpoints/aamarpay/cancel_aamarpay", //your fail route
            'cancel_url' => $music->config->site_url ."/endpoints/aamarpay/cancel_aamarpay", //your cancel url
            'opt_a' => '',  //optional paramter
            'opt_b' => '',
            'opt_c' => '',
            'opt_d' => '',
            'signature_key' => $music->config->aamarpay_signature_key //signature key will provided aamarpay, contact integration@aamarpay.com for test/live signature key
        );
        $fields_string = http_build_query($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $url_forward = str_replace('"', '', stripslashes($result));
        curl_close($ch);
        if ($music->config->aamarpay_mode == 'sandbox') {
            $base_url = 'https://sandbox.aamarpay.com/'.$url_forward;
        }
        else {
            $base_url = 'https://secure.aamarpay.com/'.$url_forward;
        }
        $data['status'] = 200;
        $data['url'] = $base_url;
    }
    else{
        $data = array(
            'status' => 400,
            'error' => 'amount , name , email , phone can not be empty'
        );
    }
}
if ($option == 'success_aamarpay') {
    if (!empty($_POST['amount']) && !empty($_POST['mer_txnid']) && !empty($_POST['pay_status']) && $_POST['pay_status'] == 'Successful') {
        $amount   = (int)secure($_POST['amount']);
        $updateUser = $db
                ->where("id", $user->id)
                ->update(T_USERS, ["wallet" => $db->inc($amount)]);
        CreatePayment([
            "user_id" => $user->id,
            "amount" => $amount,
            "type" => "WALLET",
            "pro_plan" => 0,
            "info" => "Replenish My Balance",
            "via" => "Aamarpay",
        ]);
        $data     = array(
            'status'   => 200,
            'message' => 'paid successfully'
        );
    }
    else{
        $data = array(
            'status' => 400,
            'error' => 'amount , mer_txnid , pay_status can not be empty'
        );
    }
}
if ($option == 'get_ngenius') {
    if (!empty($_POST['amount']) && is_numeric($_POST['amount']) && $_POST['amount'] > 0) {
        $token = GetNgeniusToken();
        if (!empty($token->message)) {
            $data = array(
                'status' => 400,
                'error' => $token->message
            );
        }
        elseif (!empty($token->errors) && !empty($token->errors[0]) && !empty($token->errors[0]->message)) {
            $data = array(
                'status' => 400,
                'error' => $token->errors[0]->message
            );
        }
        else{
            $amount = (int) secure($_POST['amount']);
            $postData = new StdClass();
            $postData->action = "SALE";
            $postData->amount = new StdClass();
            $postData->amount->currencyCode = "AED";
            $postData->amount->value = $amount;
            $postData->merchantAttributes = new \stdClass();
            $postData->merchantAttributes->redirectUrl = $music->config->site_url ."/endpoints/ngenius/success_ngenius";
            //$postData->merchantAttributes->redirectUrl = "http://192.168.1.108/deep/endpoints/ngenius/success_ngenius";
            $order = CreateNgeniusOrder($token->access_token,$postData);
            if (!empty($order->message)) {
                $data = array(
                    'status' => 400,
                    'error' => $order->message
                );
            }
            elseif (!empty($order->errors) && !empty($order->errors[0]) && !empty($order->errors[0]->message)) {
                $data = array(
                    'status' => 400,
                    'error' => $order->errors[0]->message
                );
            }
            else{
                $data['status'] = 200;
                $data['url'] = $order->_links->payment->href;
                $data['ngenius_ref'] = $order->reference;
            }
        }
    }
    else{
        $data = array(
            'status' => 400,
            'error' => 'amount can not be empty'
        );
    }
}
if ($option == 'success_ngenius') {
    if (!empty($_POST['ref'])) {
        $token = GetNgeniusToken();
        if (!empty($token->message)) {
            $data = array(
                'status' => 400,
                'error' => $token->message
            );
        }
        elseif (!empty($token->errors) && !empty($token->errors[0]) && !empty($token->errors[0]->message)) {
            $data = array(
                'status' => 400,
                'error' => $token->errors[0]->message
            );
        }
        else{
            $order = NgeniusCheckOrder($token->access_token,$_POST['ref']);
            if (!empty($order->message)) {
                $data = array(
                    'status' => 400,
                    'error' => $order->message
                );
            }
            elseif (!empty($order->errors) && !empty($order->errors[0]) && !empty($order->errors[0]->message)) {
                $data = array(
                    'status' => 400,
                    'error' => $order->errors[0]->message
                );
            }
            else{
                if ($order->_embedded->payment[0]->state == "CAPTURED") {
                    $amount = secure($order->amount->value);
                    $updateUser = $db
                            ->where("id", $user->id)
                            ->update(T_USERS, ["wallet" => $db->inc($amount)]);
                    CreatePayment([
                        "user_id" => $user->id,
                        "amount" => $amount,
                        "type" => "WALLET",
                        "pro_plan" => 0,
                        "info" => "Replenish My Balance",
                        "via" => "Ngenius",
                    ]);
                    $data     = array(
                        'status'   => 200,
                        'message' => 'paid successfully'
                    );
                }
                else{
                    $data = array(
                        'status' => 400,
                        'error' => 'something went wrong'
                    );
                }
            }
        }
    }
    else{
        $data = array(
            'status' => 400,
            'error' => 'ref can not be empty'
        );
    }
}
if ($option == 'get_coinbase') {
    if (!empty($_POST['amount']) && is_numeric($_POST['amount']) && $_POST['amount'] > 0) {
        $amount = (int) secure($_POST['amount']);
        try {
            $coinbase_hash = rand(1111,9999).rand(11111,99999);
            $redirect_url = $music->config->site_url ."/endpoints/coinbase/success_coinbase?coinbase_hash=".$coinbase_hash;
            $cancel_url = $music->config->site_url ."/endpoints/coinbase/cancel_coinbase?coinbase_hash=".$coinbase_hash; 
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://api.commerce.coinbase.com/charges');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            $postdata =  array('name' => 'Top Up Wallet','description' => 'Top Up Wallet','pricing_type' => 'fixed_price','local_price' => array('amount' => $amount , 'currency' => $music->config->currency), 'metadata' => array('coinbase_hash' => $coinbase_hash,'amount' => $amount),"redirect_url" => $redirect_url,'cancel_url' => $cancel_url);


            curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($postdata));

            $headers = array();
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'X-Cc-Api-Key: '.$music->config->coinbase_key;
            $headers[] = 'X-Cc-Version: 2018-03-22';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                $data = array(
                    'status' => 400,
                    'error' => curl_error($ch)
                );
            }
            curl_close($ch);

            $result = json_decode($result,true);
            if (!empty($result) && !empty($result['data']) && !empty($result['data']['hosted_url']) && !empty($result['data']['id']) && !empty($result['data']['code'])) {
                $data['status'] = 200;
                $data['url'] = $result['data']['hosted_url'];
                $data['coinbase_hash'] = $coinbase_hash;
                $data['coinbase_code'] = $result['data']['code'];
            }
        }
        catch (Exception $e) {
            $data = array(
                'status' => 400,
                'error' => $e->getMessage()
            );
        }

    }
    else{
        $data = array(
            'status' => 400,
            'error' => 'amount can not be empty'
        );
    }
}
if ($option == 'success_coinbase') {
    if (!empty($_POST['coinbase_hash']) && is_numeric($_POST['coinbase_hash']) && !empty($_POST['coinbase_code'])) {
        $coinbase_hash = secure($_POST['coinbase_hash']);
        $coinbase_code = secure($_POST['coinbase_code']);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.commerce.coinbase.com/charges/'.$coinbase_code);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'X-Cc-Api-Key: '.$music->config->coinbase_key;
        $headers[] = 'X-Cc-Version: 2018-03-22';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $data = array(
                'status' => 400,
                'error' => curl_error($ch)
            );
        }
        curl_close($ch);
        $result = json_decode($result,true);

        
        if (!empty($result) && !empty($result['data']) && !empty($result['data']['pricing']) && !empty($result['data']['pricing']['local']) && !empty($result['data']['pricing']['local']['amount']) && !empty($result['data']['payments']) && !empty($result['data']['payments'][0]['status']) && $result['data']['payments'][0]['status'] == 'CONFIRMED') {
            $amount = (int)$result['data']['pricing']['local']['amount'];
            $updateUser = $db
                    ->where("id", $user->id)
                    ->update(T_USERS, ["wallet" => $db->inc($amount)]);
            CreatePayment([
                "user_id" => $user->id,
                "amount" => $amount,
                "type" => "WALLET",
                "pro_plan" => 0,
                "info" => "Replenish My Balance",
                "via" => "Coinbase",
            ]);
            $data     = array(
                'status'   => 200,
                'message' => 'paid successfully'
            );
        }
        else{
            $data = array(
                'status' => 400,
                'error' => 'something went wrong'
            );
        }
    }
    else{
        $data = array(
            'status' => 400,
            'error' => 'coinbase_hash can not be empty'
        );
    }
}
if ($option == 'get_coinpayments') {
    if (!empty($_POST['amount']) && is_numeric($_POST['amount']) && $_POST['amount'] > 0) {
        $amount   = (int)secure($_POST[ 'amount' ]);
        if (empty($music->config->coinpayments_coin)) {
            $music->config->coinpayments_coin = 'BTC';
        }
        $result = coinpayments_api_call(array('key' => $music->config->coinpayments_public_key,
                                              'version' => '1',
                                              'format' => 'json',
                                              'cmd' => 'create_transaction',
                                              'amount' => $amount,
                                              'currency1' => $music->config->currency,
                                              'currency2' => $music->config->coinpayments_coin,
                                              'custom' => $amount,
                                              'cancel_url' => $music->config->site_url ."/endpoints/coinpayments/cancel_coinpayments",
                                              'buyer_email' => $music->user->email));

        
        if (!empty($result) && $result['status'] == 200) {
            $data = array(
                'status' => 200,
                'url' => $result['data']['checkout_url']
            );
        }
        else{
            $data = array(
                'status' => 400,
                'error' => $result['message']
            );
        }
    }
    else{
        $data = array(
            'status' => 400,
            'error' => 'amount can not be empty'
        );
    }
}
if ($option == 'check_coinpayments') {
    if (!empty($_POST['coinpayments_txn_id'])) {
        $coinpayments_txn_id = secure($_POST['coinpayments_txn_id']);
        $result = coinpayments_api_call(array('key' => $music->config->coinpayments_public_key,
                                              'version' => '1',
                                              'format' => 'json',
                                              'cmd' => 'get_tx_info',
                                              'full' => '1',
                                              'txid' => $coinpayments_txn_id));
        if (!empty($result) && $result['status'] == 200) {
            if ($result['data']['status'] == -1) {
                $notif_data = array(
                    'recipient_id' => $music->user->id,
                    'type' => 'coinpayments_canceled',
                    'admin' => 1,
                    'url' => 'ads',
                    'time' => time()
                );
                $db->insert(T_NOTIFICATION,$notif_data);
                $data = array(
                    'status' => 400,
                    'error' => 'payment not accepted'
                );
            }
            elseif ($result['data']['status'] == 100) {
                $amount   = $result['data']['checkout']['amountf'];
                $updateUser = $db
                        ->where("id", $music->user->id)
                        ->update(T_USERS, ["wallet" => $db->inc($amount)]);
                CreatePayment([
                    "user_id" => $user->id,
                    "amount" => $amount,
                    "type" => "WALLET",
                    "pro_plan" => 0,
                    "info" => "Replenish My Balance",
                    "via" => "Coinpayments",
                ]);
                $notif_data = array(
                    'recipient_id' => $music->user->id,
                    'type' => 'coinpayments_approved',
                    'admin' => 1,
                    'url' => 'ads',
                    'time' => time()
                );
                $db->insert(T_NOTIFICATION,$notif_data);
                $data = array(
                    'status' => 200,
                    'message' => 'payment accepted'
                );
            }
        }
        else{
            $data = array(
                    'status' => 200,
                    'message' => 'something went wrong'
                );
        }
    }
    else{
        $data = array(
            'status' => 400,
            'error' => 'coinpayments_txn_id can not be empty'
        );
    }
}
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
if ($option == 'top_wallet_authorize_net') {
    if (!empty($_POST['card_number']) && !empty($_POST['card_month']) && !empty($_POST['card_year']) && !empty($_POST['card_cvc'])) {
        if (!empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0) {
            $price = secure($_POST['price']);
            require_once 'assets/import/authorize/vendor/autoload.php';
            $APILoginId = $music->config->authorize_login_id;
            $APIKey = $music->config->authorize_transaction_key;
            $refId = 'ref' . time();
            define("AUTHORIZE_MODE", $music->config->authorize_test_mode);
            
            $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
            $merchantAuthentication->setName($APILoginId);
            $merchantAuthentication->setTransactionKey($APIKey);

            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber($_POST['card_number']);
            $creditCard->setExpirationDate($_POST['card_year'] . "-" . $_POST['card_month']);
            $creditCard->setCardCode($_POST['card_cvc']);

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
            if ($music->config->authorize_test_mode == 'SANDBOX') {
                $Aresponse = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
            }
            else{
                $Aresponse = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
            }
            
            if ($Aresponse != null) {
                if ($Aresponse->getMessages()->getResultCode() == 'Ok') {
                    $trans = $Aresponse->getTransactionResponse();
                    if ($trans != null && $trans->getMessages() != null) {
                        $updateUser = $db->where('id', $user->id)->update(T_USERS, ['wallet' => $db->inc($price)]);
                        if ($updateUser) {
                            CreatePayment(array(
                                'user_id'   => $user->id,
                                'amount'    => $price,
                                'type'      => 'WALLET',
                                'pro_plan'  => 0,
                                'info'      => 'Replenish My Balance',
                                'via'       => 'Authorize'
                            ));
                            $data = [
                                'status' => 200,
                                'message' => 'paid successfully'
                            ];
                        } else {
                            $errors[] = "something went wrong";
                        }
                    }
                    else{
                        $errors[] = "transaction not found";
                    }
                }
                else{
                    $errors[] = "response status != Ok";
                }
            }
            else{
                $errors[] = "empty response";
            }
        }
        else{
            $errors[] = "price can not be empty";
        }
    }
    else{
        $errors[] = "card_number , card_month , card_year , card_cvc can not be empty";
    }
    if (!empty($errors)) {
        $data = array('status' => 400, 'error' => $errors);
        echo json_encode($data);
        exit();
    }
}
use SecurionPay\SecurionPayGateway;
use SecurionPay\Exception\SecurionPayException;
use SecurionPay\Request\CheckoutRequestCharge;
use SecurionPay\Request\CheckoutRequest;
if ($option == 'top_wallet_securionpay') {
    if (!empty($_POST['type']) && in_array($_POST['type'], array('initialize','pay'))) {
        if ($_POST['type'] == 'initialize') {
            if (!empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0) {
                $price = secure($_POST['price']);
                require_once 'assets/import/securionpay_2_4_0/vendor/autoload.php';
                $securionPay = new SecurionPayGateway($music->config->securionpay_secret_key);
                $user_key = rand(1111,9999).rand(11111,99999);

                $checkoutCharge = new CheckoutRequestCharge();
                $checkoutCharge->amount(($price * 100))->currency('USD')->metadata(array('user_key' => $user_key,
                                                                                         'type' => 'wallet'));

                $checkoutRequest = new CheckoutRequest();
                $checkoutRequest->charge($checkoutCharge);

                $signedCheckoutRequest = $securionPay->signCheckoutRequest($checkoutRequest);
                if (!empty($signedCheckoutRequest)) {
                    $data['status'] = 200;
                    $data['token'] = $signedCheckoutRequest;
                    $data['securionpay_key'] = $user_key;
                }
                else{
                    $errors[] = "can not create payment";
                }
            }
            else{
                $errors[] = "price can not be empty";
            }
        }
        else{
            if (!empty($_POST) && !empty($_POST['charge']) && !empty($_POST['charge']['id']) && !empty($_POST['securionpay_key'])) {
                $url = "https://api.securionpay.com/charges?limit=10";

                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_USERPWD, $music->config->securionpay_secret_key.":password");
                $resp = curl_exec($curl);
                curl_close($curl);
                $resp = json_decode($resp,true);
                if (!empty($resp) && !empty($resp['list'])) {
                    foreach ($resp['list'] as $key => $value) {
                        if ($value['id'] == $_POST['charge']['id']) {
                            if (!empty($value['metadata']) && !empty($value['metadata']['type']) && !empty($value['metadata']['user_key']) && !empty($value['amount'])) {
                                if ($_POST['securionpay_key'] == $value['metadata']['user_key']) {
                                    $price = intval(Secure($value['amount'])) / 100;
                                    $type = $value['metadata']['type'];
                                    $updateUser = $db->where('id', $user->id)->update(T_USERS, ['wallet' => $db->inc($price)]);
                                    if ($updateUser) {
                                        CreatePayment(array(
                                            'user_id'   => $user->id,
                                            'amount'    => $price,
                                            'type'      => 'WALLET',
                                            'pro_plan'  => 0,
                                            'info'      => 'Replenish My Balance',
                                            'via'       => 'securionpay'
                                        ));
                                        $data = [
                                            'status' => 200,
                                            'message' => 'paid successfully'
                                        ];
                                    } else {
                                        $errors[] = "something went wrong";
                                    }
                                }
                                else{
                                    $errors[] = "securionpay_key can not be empty";
                                }
                            }
                        }
                    }
                }
                else{
                    $errors[] = "transaction not found";
                }
            }
            else{
                $errors[] = "charge can not be empty";
            }
        }
    }
    else{
        $errors[] = "type can not be empty";
    }
    if (!empty($errors)) {
        $data = array('status' => 400, 'error' => $errors);
        echo json_encode($data);
        exit();
    }
}
if ($option == 'purchase') {
    $types = array(
        'buy_album',
        'buy_song',
        'go_pro',
    );
    if (!empty($_POST['type']) && in_array($_POST['type'], $types)) {
        $price = 0;
        if ($_POST['type'] == 'buy_song') {
            if (empty($_POST['id'])) {
                $data = array('status' => 400, 'error' => 'id can not be empty');
                echo json_encode($data);
                exit();
            }
            $trackID = secure($_POST['id']);
            $getIDAudio = $db->where('audio_id', $trackID)->getValue(T_SONGS, 'id');
            if (empty($getIDAudio)) {
                $data = array('status' => 400, 'error' => 'invalid track');
                echo json_encode($data);
                exit();
            }
            if (isTrackPurchased($getIDAudio)) {
                $data['status'] = 400;
                $data['error'] = 'You already purchase this track.';
                echo json_encode($data);
                exit();
            }
            $songData = songData($getIDAudio);

            if (empty($songData->price)) {
                $data = array(
                    'status' => 400,
                    'error' => 'no price.'
                );
                echo json_encode($data);
                exit();
            }
            if ($songData->price > $music->user->org_wallet) {
                $data = array(
                    'status' => 400,
                    'error' => "You don't have enough money please top up your wallet"
                );
                echo json_encode($data);
                exit();
            }
            if (empty($data['error'])) {
                
                $getAdminCommission = $music->config->commission;
                $final_price = round((($getAdminCommission * $songData->price) / 100), 2);
                $addPurchase = [
                    'track_id' => $songData->id,
                    'user_id' => $user->id,
                    'price' => $songData->price,
                    'title' => $songData->title,
                    'track_owner_id' => $songData->user_id,
                    'final_price' => $final_price,
                    'commission' => $getAdminCommission,
                    'time' => time()
                ];
                $createPayment = $db->insert(T_PURCHAES, $addPurchase);
                if ($createPayment) {
                    $db->where('id', $music->user->id)->update(T_USERS, ['wallet' => $db->dec($songData->price)]);
                    CreatePayment(array(
                        'user_id'   => $user->id,
                        'amount'    => $final_price,
                        'type'      => 'TRACK',
                        'pro_plan'  => 0,
                        'info'      => $songData->audio_id,
                        'via'       => 'wallet'
                    ));
                    $addUserWallet = $db->where('id', $songData->user_id)->update(T_USERS, ['balance' => $db->inc($final_price)]);
                    $create_notification = createNotification([
                        'notifier_id' => $user->id,
                        'recipient_id' => $songData->user_id,
                        'type' => 'purchased',
                        'track_id' => $songData->id,
                        'url' => "track/$songData->audio_id"
                    ]);
                    $data = [
                        'status' => 200,
                        'message' => 'paid successfully'
                    ];
                } else {
                    $data['status'] = 400;
                    $data['error'] = "something went wrong";
                    echo json_encode($data);
                    exit();
                }
            }
        }
        elseif ($_POST['type'] == 'buy_album') {
            if (empty($_POST['id'])) {
                $data = array('status' => 400, 'error' => 'id can not be empty');
                echo json_encode($data);
                exit();
            }
            $album = $db->where('album_id',secure($_POST['id']))->getOne(T_ALBUMS);
            if (!empty($album)) {
                if ($album->price <= $music->user->org_wallet) {
                    if (!empty($album) && !empty($album->price) && is_numeric($album->price) && $album->price > 0) {
                        $price    = $album->price;
                        $albumData = albumData($album->id, true, true, true);
                        if (!empty($albumData) && !empty($albumData->price) && is_numeric($albumData->price) && $albumData->price > 0) {
                            $album_id = $albumData->album_id;

                            $getAdminCommission = $music->config->commission;
                            $final_price = 0;

                            $createPayment = false;
                            foreach ($albumData->songs as $key => $song){
                                $final_price += round((($getAdminCommission * $song->price) / 100), 2);
                                $addPurchase = [
                                    'track_id' => $song->id,
                                    'user_id' => $user->id,
                                    'price' => $song->price,
                                    'title' => $song->title,
                                    'track_owner_id' => $song->user_id,
                                    'final_price' => round((($getAdminCommission * $song->price) / 100), 2),
                                    'commission' => $getAdminCommission,
                                    'time' => time()
                                ];

                                $createPayment = $db->insert(T_PURCHAES, $addPurchase);
                                if ($createPayment) {
                                    CreatePayment(array(
                                        'user_id'   => $user->id,
                                        'amount'    => $final_price,
                                        'type'      => 'TRACK',
                                        'pro_plan'  => 0,
                                        'info'      => $song->audio_id,
                                        'via'       => 'wallet'
                                    ));
                                    $create_notification = createNotification([
                                        'notifier_id' => $user->id,
                                        'recipient_id' => $song->user_id,
                                        'type' => 'purchased',
                                        'track_id' => $song->id,
                                        'url' => "track/$song->audio_id"
                                    ]);
                                }
                            }

                            if ($createPayment) {
                                $db->where('id', $music->user->id)->update(T_USERS, ['wallet' => $db->dec($album->price)]);
                                $updatealbumpurchases = $db->where('album_id', $album_id)->update(T_ALBUMS, array('purchases' => $db->inc(1) ));
                                $addUserWallet = $db->where('id', $albumData->user_id)->update(T_USERS, ['balance' => $db->inc($final_price)]);
                                $data = [
                                    'status' => 200,
                                    'message' => 'paid successfully'
                                ];
                            } else {
                                $data['status'] = 400;
                                $data['error'] = lang("something_went_wrong_please_try_again_later_");
                                echo json_encode($data);
                                exit();
                            }
                        }
                        else{
                            $data['status'] = 400;
                            $data['error'] = lang("album not found");
                            echo json_encode($data);
                            exit();
                        }
                    }
                    else{
                        $data['status'] = 400;
                        $data['error'] = lang("album not found");
                        echo json_encode($data);
                        exit();
                    }
                }
                else{
                    $data = array(
                        'status' => 400,
                        'error' => "You don't have enough money please top up your wallet"
                    );
                    echo json_encode($data);
                    exit();
                }
            }
            else{
                $data = array(
                    'status' => 400,
                    'error' => "album not found"
                );
                echo json_encode($data);
                exit();
            }
        }
        elseif ($_POST['type'] == 'go_pro') {
            if ($music->config->pro_price <= $music->user->org_wallet) {
                $price = $music->config->pro_price;
                $updateUser = $db->where('id', $user->id)->update(T_USERS, ['is_pro' => 1, 'pro_time' => time()]);
                if ($updateUser) {
                    CreatePayment(array(
                        'user_id'   => $user->id,
                        'amount'    => $music->config->pro_price,
                        'type'      => 'PRO',
                        'pro_plan'  => 1,
                        'info'      => '',
                        'via'       => 'wallet'
                    ));
                    if ((!empty($_SESSION['ref']) || !empty($user->ref_user_id)) && $music->config->affiliate_type == 1 && $user->referrer == 0) {
                        if ($music->config->amount_percent_ref > 0) {
                            if (!empty($_SESSION['ref'])) {
                                $ref_user_id = $db->where('username', secure($_SESSION['ref']))->getValue(T_USERS, 'id');
                            }
                            elseif (!empty($user->ref_user_id)) {
                                $ref_user_id = $db->where('id', $user->ref_user_id)->getValue(T_USERS, 'id');
                            }
                            if (!empty($ref_user_id) && is_numeric($ref_user_id)) {
                                $db->where('id', $user->user_id)->update(T_USERS,array(
                                    'referrer' => $ref_user_id,
                                    'src' => 'Referrer'
                                ));
                                $ref_amount     = ($music->config->amount_percent_ref * $music->config->pro_price) / 100;
                                $db->where('id', $ref_user_id)->update(T_USERS,array('balance' => $db->inc($ref_amount)));
                                unset($_SESSION['ref']);
                            }
                        } else if ($music->config->amount_ref > 0) {
                            if (!empty($_SESSION['ref'])) {
                                $ref_user_id = $db->where('username', secure($_SESSION['ref']))->getValue(T_USERS, 'id');
                            }
                            elseif (!empty($user->ref_user_id)) {
                                $ref_user_id = $db->where('id', $user->ref_user_id)->getValue(T_USERS, 'id');
                            }
                            if (!empty($ref_user_id) && is_numeric($ref_user_id)) {
                                $db->where('id', $user->user_id)->update(T_USERS,array(
                                    'referrer' => $ref_user_id,
                                    'src' => 'Referrer'
                                ));
                                $db->where('id', $ref_user_id)->update(T_USERS,array('balance' => $db->inc($music->config->amount_ref)));
                                unset($_SESSION['ref']);
                            }
                        }
                    }
                    RecordUserActivities('go_pro',array('mode' => 'upgraded'));
                    $data = [
                        'status' => 200,
                        'message' => 'paid successfully'
                    ];
                } else {
                    $data['status'] = 400;
                    $data['error'] = lang("something_went_wrong_please_try_again_later_");
                    echo json_encode($data);
                    exit();
                }
            }
            else{
                $data = array(
                    'status' => 400,
                    'error' => "You don't have enough money please top up your wallet"
                );
                echo json_encode($data);
                exit();
            }
        }
    }
}
if ($option == 'trend_search') {
    $trend_search = $db->orderBy('hits', 'DESC')->get(T_SEARCHES, 10, array('id','keyword'));
    $data = [
        'status' => 200,
        'data' => $trend_search
    ];
}








if ($option == 'purchase_album') {
    if (empty($_POST['user_id']) || !is_numeric($_POST['user_id']) || $_POST['user_id'] == 0) {
        $errors[] = "Invalid user ID";
    }
    else if (empty($_POST['album_id']) || !is_numeric($_POST['album_id']) || $_POST['album_id'] == 0) {
        $errors[] = "Invalid album ID";
    } else {
        if (empty($errors)) {
            $album = $db->where('id',secure($_POST['album_id']))->getOne(T_ALBUMS);
            if (!empty($album)) {
                $payment_via = (isset($_POST['via'])) ? secure($_POST['via']) : 'PayPal';
                $price    = $album->price;
                $albumData = albumData($album->id, true, true, true);
                if (!empty($albumData) && !empty($albumData->price) && is_numeric($albumData->price) && $albumData->price > 0) {
                    $album_id = $albumData->album_id;

                    $getAdminCommission = $music->config->commission;
                    $final_price = 0;

                    $createPayment = false;
                    foreach ($albumData->songs as $key => $song){
                        $final_price += round((($getAdminCommission * $song->price) / 100), 2);
                        $addPurchase = [
                            'track_id' => $song->id,
                            'user_id' => $user->id,
                            'price' => $song->price,
                            'title' => $song->title,
                            'track_owner_id' => $song->user_id,
                            'final_price' => round((($getAdminCommission * $song->price) / 100), 2),
                            'commission' => $getAdminCommission,
                            'time' => time()
                        ];

                        $createPayment = $db->insert(T_PURCHAES, $addPurchase);
                        if ($createPayment) {
                            CreatePayment(array(
                                'user_id'   => $user->id,
                                'amount'    => $final_price,
                                'type'      => 'TRACK',
                                'pro_plan'  => 0,
                                'info'      => $song->audio_id,
                                'via'       => $payment_via
                            ));
                            $create_notification = createNotification([
                                'notifier_id' => $user->id,
                                'recipient_id' => $song->user_id,
                                'type' => 'purchased',
                                'track_id' => $song->id,
                                'url' => "track/$song->audio_id"
                            ]);
                        }
                    }

                    if ($createPayment) {
                        $updatealbumpurchases = $db->where('album_id', $album_id)->update(T_ALBUMS, array('purchases' => $db->inc(1) ));
                        $data['status'] = 200;
                        $data['url'] = $site_url."/album/{$album_id}";
                        $data = [
                            'status' => 200,
                            'message' => 'Album purchased successfully'
                        ];
                    } else {
                        $errors[] = "something went wrong";
                    }
                }
                else{
                    $errors[] = "album is free";
                }
            }
            else{
                $errors[] = "album not found";
            }
            if (!empty($errors)) {
                $data = array('status' => 400, 'error' => $errors);
                echo json_encode($data);
                exit();
            }
        }else{
            $data = array('status' => 400, 'error' => $errors);
            echo json_encode($data);
            exit();
        }
    }
}
if ($option == 'purchase_track') {
    if (empty($_POST['user_id']) || !is_numeric($_POST['user_id']) || $_POST['user_id'] == 0) {
        $errors[] = "Invalid user ID";
    }
    else if (empty($_POST['track_id'])) {
        $errors[] = "Please check your details";
    } else {

        if (!filter_var($_POST['track_id'], FILTER_SANITIZE_NUMBER_INT)) {
            $errors[] = "Invalid id";
        }
        if ($_POST['track_id'] == 0) {
            $errors[] = "Invalid id";
        }
        if (empty($errors)) {
            $payment_via = (isset($_POST['via'])) ? secure($_POST['via']) : 'PayPal';
            $track_id                 = secure($_POST['track_id']);
            $songData = songData($track_id);
            if (empty($songData->price)) {
                $data = array('status' => 400, 'error' => "Please check your details");
                echo json_encode($data);
                exit();
            }

            $getAdminCommission = $music->config->commission;
            $final_price = round((($getAdminCommission * $songData->price) / 100), 2);
            $addPurchase = [
                'track_id' => $songData->id,
                'user_id' => $user->id,
                'price' => $songData->price,
                'track_owner_id' => $songData->user_id,
                'final_price' => $final_price,
                'commission' => $getAdminCommission,
                'time' => time()
            ];

            $createPayment = $db->insert(T_PURCHAES, $addPurchase);
            if ($createPayment) {
                CreatePayment(array(
                    'user_id'   => $user->id,
                    'amount'    => $final_price,
                    'type'      => 'TRACK',
                    'pro_plan'  => 0,
                    'info'      => $songData->audio_id,
                    'via'       => $payment_via
                ));
                $addUserWallet = $db->where('id', $songData->user_id)->update(T_USERS, ['balance' => $db->inc($final_price)]);
                $create_notification = createNotification([
                    'notifier_id' => $user->id,
                    'recipient_id' => $songData->user_id,
                    'type' => 'purchased',
                    'track_id' => $songData->id,
                    'url' => "track/$songData->audio_id"
                ]);
                $data = [
                    'status' => 200,
                    'message' => 'Track purchased successfully'
                ];
            } else {
                $data = array('status' => 400, 'error' => "Please check your details");
                echo json_encode($data);
                exit();
            }
        }else{
            $data = array('status' => 400, 'error' => $errors);
            echo json_encode($data);
            exit();
        }
    }
}
if ($option == 'my-purchases') {
    if (empty($_POST['id'])) {
        $errors[] = "Please check your details";
    } else {

        if (!filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT)) {
            $errors[] = "Invalid id";
        }
        if ($_POST['id'] == 0) {
            $errors[] = "Invalid id";
        }
        if (empty($errors)) {
            $id                 = secure($_POST['id']);
            $limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
            $offset             = (isset($_POST['offset'])) ? secure($_POST['offset']) : 0;
            if (!empty($offset)) {
                $db->where('id',$offset,'<');
            }
            $getPurchased = $db->where('user_id', $music->user->id)->orderBy('id', 'DESC')->get(T_PURCHAES, $limit);
            $purchased_data = array();
            if (!empty($getPurchased)) {
                foreach ($getPurchased as $key => $song) {
                    if (!empty($song->track_id)) {
                        $song->songData = songData($song->track_id);
                    }
                    elseif (!empty($song->event_id)) {
                        $song->event = GetEventById($song->event_id);
                        unset($song->event->user_data->email_code);
                        unset($song->event->user_data->password);
                    }
                    elseif (!empty($song->order_hash_id)) {
                        $song->orders = $db->where('hash_id',$song->order_hash_id)->get(T_ORDERS);
                    }
                    $purchased_data[] = $song;
                }
            }



            $data = [
                'status' => 200,
                'data' => $purchased_data
            ];
        }
    }
}
if ($option == 'get-profile') {
    if (empty($_POST['user_id']) || empty($_POST['fetch'])) {
        $errors[] = "Please check your details";
    } else {

        if (!filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT)) {
            $errors[] = "Invalid id";
        }
        if ($_POST['user_id'] == 0) {
            $errors[] = "Invalid id";
        }
        if (empty($errors)) {
            $count                      = [];
            
            if( isset( $_POST['access_token'] ) ) {
                $request_uid = getUserFromSessionID($_POST['access_token'], 'mobile');
            }else{
                $request_uid = secure($_POST['user_id']);
            }

            $id          = secure($_POST['user_id']);
            $music->user = userData($id);
            if (!empty($music->user)) {

                unset($music->user->password);
                unset($music->user->email_code);
                $count['followers']         = $db->where('following_id', $id)->where("follower_id NOT IN (SELECT blocked_id FROM blocks WHERE user_id = $id)")->getValue(T_FOLLOWERS, 'COUNT(*)');
                $count['following']         = $db->where('follower_id', $id)->where("following_id NOT IN (SELECT blocked_id FROM blocks WHERE user_id = $id)")->getValue(T_FOLLOWERS, 'COUNT(*)');
                $count['albums']            = $db->where('user_id', $id)->getValue(T_ALBUMS, 'COUNT(*)');
                $count['playlists']         = $db->where('user_id', $id)->getValue(T_PLAYLISTS, 'COUNT(*)');
                $count['blocks']            = $db->where('user_id', $id)->getValue(T_BLOCKS, 'COUNT(*)');
                $count['favourites']        = $db->where('user_id', $id)->getValue(T_FOV, 'COUNT(*)');
                $count['recently_played']   = $db->where('user_id', $id)->getValue(T_VIEWS, 'COUNT(*)');;
                $count['liked']             = $db->where('user_id', $id)->where('track_id', 0,"<>")->getValue(T_LIKES, 'COUNT(*)');
                $count['activities']        = $db->where('user_id', $id)->getValue(T_ACTIVITIES, 'COUNT(*)');
                $count['latest_songs']      = $db->where('user_id', $id)->getValue(T_SONGS, 'COUNT(*)');
                $count['top_songs']         = count($db->rawQuery("
                                                SELECT " . T_SONGS . ".*, COUNT(" . T_VIEWS . ".id) AS " . T_VIEWS . "
                                                FROM " . T_SONGS . " LEFT JOIN " . T_VIEWS . " ON " . T_SONGS . ".id = " . T_VIEWS . ".track_id
                                                WHERE " . T_SONGS . ".user_id = ".$id."
                                                GROUP BY " . T_SONGS . ".id
                                                ORDER BY " . T_VIEWS . " DESC"));
                $count['store']             = $db->where('user_id', $id)->where('price', '0', '<>')->getValue(T_SONGS, 'COUNT(*)');
                $count['stations']             = $db->where('user_id', $id)->where('src', 'radio')->getValue(T_SONGS, 'COUNT(*)');

                $user_data = $music->user;
                $user_data->is_following = isFollowing($request_uid, $id);
                $user_data->IsBloked = isBlocked($id);

                $fetch = explode(',',secure($_POST['fetch']));
                if(in_array('followers',$fetch) || secure($_POST['fetch']) == "all"){
                    $newData = array();
                    $followers = GetFollowers($id);
                    foreach ($followers as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->followers = $newData;
                    //$count['followers'] = $followers['count'];
                }
                if(in_array('following',$fetch) || secure($_POST['fetch']) == "all"){
                    $newData = array();
                    $following = GetFollowing($id);
                    foreach ($following as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->following = $newData;
                    //$count['following'] = $following['count'];
                }
                if(in_array('albums',$fetch) || secure($_POST['fetch']) == "all"){
                    $newData = array();
                    $albums = GetAlbums($id);
                    foreach ($albums as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->albums = $newData;
                    //$count['albums'] = $albums['count'];
                }
                if(in_array('playlists',$fetch) || secure($_POST['fetch']) == "all"){
                    $newData = array();
                    $playlists = GetPlaylists($id);
                    foreach ($playlists as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->playlists = $newData;
                    //$count['playlists'] = $playlists['count'];
                }
                if(in_array('blocks',$fetch) || secure($_POST['fetch']) == "all"){
                    $newData = array();
                    $blocks = GetBlocks($id);
                    foreach ($blocks as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->blocks = $newData;
                    //$count['blocks'] = $blocks['count'];
                }
                if(in_array('favourites',$fetch) || secure($_POST['fetch']) == "all"){
                    $newData = array();
                    $favourites = GetFavourites($id);
                    foreach ($favourites as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->favourites = $newData;
                    //$count['favourites'] = $favourites['count'];
                }
                if(in_array('recently_played',$fetch) || secure($_POST['fetch']) == "all"){
                    $newData = array();
                    $recently_played = GetRecentlyPlayed($id);
                    foreach ($recently_played as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->recently_played = $newData;
                    //$count['recently_played'] = $recently_played['count'];
                }
                if(in_array('liked',$fetch) || secure($_POST['fetch']) == "all"){
                    $newData = array();
                    $liked = GetLiked($id);
                    foreach ($liked as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->liked = $newData;
                    //$count['liked'] = $liked['count'];
                }
                if(in_array('activities',$fetch) || secure($_POST['fetch']) == "all"){
                    $newData = array();
                    $activities = GetActivities($id);
                    foreach ($activities as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->activities = $newData;
                    //$count['activities'] = $activities['count'];
                }
                if(in_array('latest_songs',$fetch) || secure($_POST['fetch']) == "all"){
                    $newData = array();
                    $latestsongs = GetLatestSongs($id);
                    foreach ($latestsongs as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->latestsongs = $newData;
                    //$count['latest_songs'] = $latestsongs['count'];
                }
                if(in_array('top_songs',$fetch) || secure($_POST['fetch']) == "all"){
                    $newData = array();
                    $top_songs = GetTopSongs($id);
                    foreach ($top_songs as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->top_songs = $newData;
                    //$count['top_songs'] = $top_songs['count'];
                }
                if((in_array('store',$fetch) || secure($_POST['fetch']) == "all") && $music->config->artist_sell == 'on'){
                    $newData = array();
                    $store = GetStore($id);
                    foreach ($store as $key => $value) {
                        if (!empty($value) && (is_array($value) || is_object($value))) {
                            $newData[] = $value;
                        }
                    }
                    $user_data->store = $newData;
                    //$count['store'] = $store['count'];
                }
                if((in_array('events',$fetch) || secure($_POST['fetch']) == "all") && $music->config->event_system == 1){
                    $all_events = array();
                    $events = $db->where('user_id',$music->user->id)->where(" `end_date` >= CURDATE()")->orderBy('id', 'DESC')->get(T_EVENTS,10,array('id'));
                    foreach ($events as $key => $value) {
                        $event = GetEventById($value->id);
                        unset($event->user_data->password);
                        unset($event->user_data->email_code);
                        $all_events[] = $event;
                    }
                    $user_data->events = $all_events;
                }
                if(in_array('stations',$fetch) || secure($_POST['fetch']) == "all"){
                    $sql = 'SELECT * FROM `'.T_SONGS.'` WHERE `src` = "radio" AND `user_id` = "'.$id.'" limit 20';
                    $getUserStations            = $db->objectBuilder()->rawQuery($sql);
                    $songs_data = array();
                    if (!empty($getUserStations)) {

                        foreach ($getUserStations as $key => $song) {
                            $userSong = songData($song->id);
                            unset($userSong->publisher->password);
                            foreach ($userSong->songArray as $key => $value) {
                                unset($userSong->songArray->{$key}->USER_DATA->password);
                            }
                            $songs_data[] = $userSong;
                        }
                    }
                    $user_data->stations = $songs_data;
                }
            }

            $data = [
                'status' => 200,
                'data' => $user_data,
                'details' => $count
            ];
        }
    }
}

if ($option == 'get-pro-user') {
    $users = [];
    $pro_users = $db->where('is_pro','1')->objectbuilder()->orderBy('id', 'DESC')->get(T_USERS, 20);
    foreach ($pro_users as $key => $value){
        $users[] = userData($value->id);
    }
    $data = [
        'status' => 200,
        'data' => $users
    ];
}
if ($option == 'get-genres') {
    $genres = [];
    $categories = getCategories(false);
    foreach ($categories as $key => $value) {
        $value->background_thumb = (empty($value->background_thumb)) ? $music->config->theme_url . '/img/crowd.jpg' : getMedia($value->background_thumb);
        $genres[] = $value;
    }
    $data = [
        'status' => 200,
        'data' => $genres
    ];
}
if ($option == 'get-following') {
    if (empty($_POST['id'])) {
        $errors[] = "Please check your details";
    } else {

        if (!filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT)) {
            $errors[] = "Invalid id";
        }
        if ($_POST['id'] == 0) {
            $errors[] = "Invalid id";
        }
        if (empty($errors)) {
            $id                 = secure($_POST['id']);
            $limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
            $offset             = (isset($_POST['offset'])) ? secure($_POST['offset']) : 0;
            $data = [
                'status' => 200,
                'data' => GetFollowing($id,$limit,$offset)
            ];
        }
    }
}
if ($option == 'get-follower') {
    if (empty($_POST['id'])) {
        $errors[] = "Please check your details";
    } else {

        if (!filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT)) {
            $errors[] = "Invalid id";
        }
        if ($_POST['id'] == 0) {
            $errors[] = "Invalid id";
        }
        if (empty($errors)) {
            $id                 = secure($_POST['id']);
            $limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
            $offset             = (isset($_POST['offset'])) ? secure($_POST['offset']) : 0;
            $data = [
                'status' => 200,
                'data' => GetFollowers($id,$limit,$offset)
            ];
        }
    }
}
if ($option == 'get-artists') {
    $limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
    $offset             = (isset($_POST['offset'])) ? secure($_POST['offset']) : 0;
    $data = [
        'status' => 200,
        'data' => GetArtists($limit,$offset)
    ];
}

if(!in_array($option, $whitelist)) {
    if (IS_LOGGED == false) {
        $errors[] = "You ain't logged in!";
    }
}

if (empty($errors)) {

    if ($option == 'profile') {
        if (empty($_POST['name'])) {
            $errors[] = "Please check your details";
        } else {
            $name                 = secure($_POST['name']);
            $about_me             = secure($_POST['about_me']);
            $facebook             = secure($_POST['facebook']);
            $website              = secure($_POST['website']);
            if (!filter_var($_POST['website'], FILTER_VALIDATE_URL)) {
                $errors[] = "Invalid website url, format allowed: http(s)://*.*/*";
            }
            if (filter_var($_POST['facebook'], FILTER_VALIDATE_URL)) {
                $errors[] = "Invalid facebook username, urls are not allowed";
            }
            if (empty($errors)) {
                $update_data = [
                    'name' => $name,
                    'about' => $about_me,
                    'facebook' => $facebook,
                    'website' => $website,
                ];

                if (isAdmin() || $userData->id == $user->id) {
                    $update = $db->where('id', $userData->id)->update(T_USERS, $update_data);
                    if ($update) {
                        $data = [
                            'status' => 200,
                            'message' => "Profile successfully updated!"
                        ];
                    }
                }
            }
        }
    }

    if ($option == 'delete') {
        if (empty($_POST['c_pass'])) {
            $errors[] = "Please check your details";
        } else {
            $c_pass      = secure($_POST['c_pass']);

            if (!password_verify($c_pass, $db->where('id', $userData->id)->getValue(T_USERS, 'password'))) {
                $errors[] = "Your current password is invalid";
            }
            if (empty($errors)) {
                if (isAdmin() || $userData->id == $user->id) {
                    $delete = deleteUser($userData->id);
                    if ($delete) {
                        $data = [
                            'status' => 200,
                            'message' => "Your account was successfully deleted, please wait.."
                        ];
                    }
                }
            }
        }
    }

    if ($option == 'password') {
        if (empty($_POST['c_pass']) || empty($_POST['n_pass']) || empty($_POST['rn_pass'])) {
            $errors[] = "Please check your details";
        } else {
            $c_pass      = secure($_POST['c_pass']);
            $n_pass      = secure($_POST['n_pass']);
            $rn_pass     = secure($_POST['rn_pass']);
            if (!password_verify($c_pass, $db->where('id', $userData->id)->getValue(T_USERS, 'password'))) {
                $errors[] = "Your current password is invalid";
            } else if ($n_pass != $rn_pass) {
                $errors[] = "Passwords don't match";
            } else if (strlen($n_pass) < 4 || strlen($n_pass) > 32) {
                $errors[] = "New password is too short";
            }
            if (empty($errors)) {
                $update_data = [
                    'password' => password_hash($n_pass, PASSWORD_DEFAULT),
                ];

                if (isAdmin() || $userData->id == $user->id) {
                    $update = $db->where('id', $userData->id)->update(T_USERS, $update_data);
                    if ($update) {
                        $data = [
                            'status' => 200,
                            'message' => "Your password was successfully updated!"
                        ];
                    }
                }
            }
        }
    }



    if ($option == 'get-recently-played') {
        if (empty($_POST['id'])) {
            $errors[] = "Please check your details";
        } else {

            if (!filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT)) {
                $errors[] = "Invalid id";
            }
            if ($_POST['id'] == 0) {
                $errors[] = "Invalid id";
            }
            if (empty($errors)) {
                $id                 = secure($_POST['id']);
                $limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
                $offset             = (isset($_POST['offset'])) ? secure($_POST['offset']) : 0;
                $data = [
                    'status' => 200,
                    'data' => GetRecentlyPlayed($id,$limit,$offset)
                ];
            }
        }
    }

    if ($option == 'get-favourites') {
        if (empty($_POST['id'])) {
            $errors[] = "Please check your details";
        } else {

            if (!filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT)) {
                $errors[] = "Invalid id";
            }
            if ($_POST['id'] == 0) {
                $errors[] = "Invalid id";
            }
            if (empty($errors)) {
                $id                 = secure($_POST['id']);
                $limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
                $offset             = (isset($_POST['offset'])) ? secure($_POST['offset']) : 0;
                $data = [
                    'status' => 200,
                    'data' => GetFavourites($id,$limit,$offset)
                ];
            }
        }
    }

    if ($option == 'get-blocks') {
        if (empty($_POST['id'])) {
            $errors[] = "Please check your details";
        } else {

            if (!filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT)) {
                $errors[] = "Invalid id";
            }
            if ($_POST['id'] == 0) {
                $errors[] = "Invalid id";
            }
            if (empty($errors)) {
                $id                 = secure($_POST['id']);
                $limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
                $offset             = (isset($_POST['offset'])) ? secure($_POST['offset']) : 0;
                $data = [
                    'status' => 200,
                    'data' => GetBlocks($id,$limit,$offset)
                ];
            }
        }
    }

    if ($option == 'get-liked') {
        if (empty($_POST['id'])) {
            $errors[] = "Please check your details";
        } else {

            if (!filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT)) {
                $errors[] = "Invalid id";
            }
            if ($_POST['id'] == 0) {
                $errors[] = "Invalid id";
            }
            if (empty($errors)) {
                $id                 = secure($_POST['id']);
                $limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
                $offset             = (isset($_POST['offset'])) ? secure($_POST['offset']) : 0;
                $data = [
                    'status' => 200,
                    'data' => GetLiked($id,$limit,$offset)
                ];
            }
        }
    }

    if ($option == 'get-recommended') {
        if (empty($_POST['id'])) {
            $errors[] = "Please check your details";
        } else {
            if (!filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT)) {
                $errors[] = "Invalid id";
            }
            if ($_POST['id'] == 0) {
                $errors[] = "Invalid id";
            }
            if (empty($errors)) {
                $id                 = secure($_POST['id']);
                $data = [
                    'status' => 200,
                    'data' => GetRecommendedSongs()
                ];
            }
        }
    }

    if ($option == 'general') {
        if (empty($_POST['username']) || empty($_POST['email'])) {
            $errors[] = "Please check your details";
        } else {
            $username          = secure($_POST['username']);
            $email             = secure($_POST['email']);
            if (UsernameExits($_POST['username']) && $_POST['username'] != $userData->username) {
                $errors[] = "This username is already taken";
            }
            if (strlen($_POST['username']) < 4 || strlen($_POST['username']) > 32) {
                $errors[] = "Username length must be between 5 / 32";
            }
            if (!preg_match('/^[\w]+$/', $_POST['username'])) {
                $errors[] = "Invalid username characters";
            }
            if (EmailExists($_POST['email']) && $_POST['email'] != $userData->email) {
                $errors[] = "This e-mail is already taken";
            }
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "This e-mail is invalid";
            }
            $country = $userData->country_id;
            if (in_array($_POST['country'], array_keys($countries_name))) {
                $country = secure($_POST['country']);
            }

            $gender = $userData->gender;
            if (in_array($_POST['gender'], ['male', 'female'])) {
                $gender = secure($_POST['gender']);
            }

            $age = $userData->age;
            if (is_numeric($_POST['age']) && ($_POST['age'] <= 100 || $_POST['age'] >= 0)) {
                $age = secure($_POST['age']);
            }

            $ispro = $userData->is_pro;
            if (!empty($_POST['ispro']) && IsAdmin()) {
                if ($_POST['ispro'] == 'yes') {
                    $ispro = 1;
                } else if ($_POST['ispro'] == 'no') {
                    $ispro = 0;
                }
                if ($ispro == $userData->is_pro) {
                    $ispro = $userData->is_pro;
                }
            }

            $verified = $userData->verified;
            if (!empty($_POST['verified']) && IsAdmin()) {
                if ($_POST['verified'] == 'yes') {
                    $verified = 1;
                } else if ($_POST['verified'] == 'no') {
                    $verified = 0;
                }
                if ($verified == $userData->verified) {
                    $verified = $userData->verified;
                }
            }

            if (empty($errors)) {
                $update_data = [
                    'username' => $username,
                    'email' => $email,
                    'gender' => $gender,
                    'age' => $age,
                    'country_id' => $country,
                    'is_pro' => $ispro,
                    'verified' => $verified
                ];
                if (!empty($_POST['paypal_email']) && filter_var($_POST['paypal_email'], FILTER_VALIDATE_EMAIL)) {
                    $update_data['paypal_email'] = secure($_POST['paypal_email']);
                }

                if (isAdmin() || $userData->id == $user->id) {
                    $update = $db->where('id', $userData->id)->update(T_USERS, $update_data);
                    if ($update) {
                        $data = [
                            'status' => 200,
                            'message' => "Settings successfully updated!"
                        ];
                    }
                }
            }
        }
    }

    if ($option == 'update-profile-cover') {
        if (empty($_FILES)) {
            $errors[] = "Please check your details";
        }
        if (empty($errors)) {
            if (!empty($_FILES['cover']['tmp_name'])) {
                $type = (!empty($_REQUEST['type'])) ? secure($_REQUEST['type']) : "";
                $file_info = array(
                    'file' => $_FILES['cover']['tmp_name'],
                    'size' => $_FILES['cover']['size'],
                    'name' => $_FILES['cover']['name'],
                    'type' => $_FILES['cover']['type'],
                    'crop' => array('width' => 1600, 'height' => 400),
                    'allowed' => 'jpg,png,jpeg,gif,webp'
                );
                if ($type == 'artist') {
                    $file_info['crop'] = array('width' => 1400, 'height' => 800);
                }
                $file_upload = shareFile($file_info);
                if (!empty($file_upload['filename'])) {
                    $update_data['cover'] = $file_upload['filename'];
                    $db->where('id', $user->id)->update(T_USERS, $update_data);
                    RecordUserActivities('update_profile_cover',array('uid' => $user->id));
                    $data['status'] = 200;
                    $data['img'] = getMedia($file_upload['filename']);
                }
            }
            if (!empty($_FILES['video']['tmp_name']) && $music->config->can_use_channel_trailer == 'on' && $music->config->can_use_channel_trailer) {
                $file_info = array(
                    'file' => $_FILES['video']['tmp_name'],
                    'size' => $_FILES['video']['size'],
                    'name' => $_FILES['video']['name'],
                    'type' => $_FILES['video']['type'],
                );
                $file_upload = shareFile($file_info);
                if (!empty($file_upload['filename'])) {
                    require 'assets/import/getID3-1.9.14/getid3/getid3.php';
                    $getID3    = new getID3;
                    $file     = $getID3->analyze($file_upload['filename']);
                    if (!empty($file['playtime_seconds']) && $file['playtime_seconds'] <= 10) {
                        $update_data['cover'] = $file_upload['filename'];
                        $db->where('id', $user->id)->update(T_USERS, $update_data);
                        $data['status'] = 200;
                        RecordUserActivities('update_profile_cover',array('uid' => $user->id));
                        $data['video'] = getMedia($file_upload['filename']);
                    }
                    else{
                        $data = [
                            'status' => 400,
                            'error' => "Video duration must be less than or equal 10 seconds"
                        ];
                    }
                }
            }
        }
    }

    if ($option == 'update-profile-picture') {
        if (empty($_FILES)) {
            $errors[] = "Please check your details";
        }
        if (empty($errors)) {
            if (!empty($_FILES['avatar']['tmp_name'])) {
                $file_info = array(
                    'file' => $_FILES['avatar']['tmp_name'],
                    'size' => $_FILES['avatar']['size'],
                    'name' => $_FILES['avatar']['name'],
                    'type' => $_FILES['avatar']['type'],
                    'crop' => array('width' => 400, 'height' => 400),
                    'allowed' => 'jpg,png,jpeg,gif,webp'
                );
                $file_upload = shareFile($file_info);
                if (!empty($file_upload['filename'])) {
                    $update_data['avatar'] = $file_upload['filename'];
                    $db->where('id', $user->id)->update(T_USERS, $update_data);
                    RecordUserActivities('update_profile_picture',array('uid' => $user->id));
                    $data['status'] = 200;
                    $data['img'] = getMedia($file_upload['filename']);
                }
            }
        }
    }

    if ($option == 'upgrade-membership') {
        if (empty($_POST['id'])) {
            $errors[] = "Please check your details";
        } else {

            if (!filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT)) {
                $errors[] = "Invalid id";
            }
            if ($_POST['id'] == 0) {
                $errors[] = "Invalid id";
            }
            if (empty($errors)) {
                $id                 = secure($_POST['id']);
                $updated = $db->where('id',$id)->update(T_USERS,array('is_pro'=> 1, 'pro_time'=> time()));
                if($updated) {
                    CreatePayment(array(
                        'user_id'   => $id,
                        'amount'    => $music->config->pro_price,
                        'type'      => 'PRO',
                        'pro_plan'  => 1,
                        'info'      => '',
                        'via'       => '-'
                    ));

                    $data = [
                        'status' => 200,
                        'data' => 'Upgraded successfully'
                    ];
                }else{
                    $data = [
                        'status' => 400,
                        'error' => 'Error While Upgrading Account'
                    ];
                }
            }
        }
    }
    if ($option == 'update_two_factor') {
        $error = '';
        if ($_POST['two_factor'] == 'enable') {
            $is_phone = false;
            if (!empty($_POST['phone_number']) && ($music->config->two_factor_type == 'both' || $music->config->two_factor_type == 'phone')) {
                preg_match_all('/\+(9[976]\d|8[987530]\d|6[987]\d|5[90]\d|42\d|3[875]\d|
                                2[98654321]\d|9[8543210]|8[6421]|6[6543210]|5[87654321]|
                                4[987654310]|3[9643210]|2[70]|7|1)\d{1,14}$/', $_POST['phone_number'], $matches);
                if (!empty($matches[1][0]) && !empty($matches[0][0])) {
                    $is_phone = true;
                }
            }
            if ((empty($_POST['phone_number']) && $music->config->two_factor_type == 'phone') || empty($_POST['two_factor']) || $_POST['two_factor'] != 'enable') {
                $error = 'Please check your details.';
            }
            elseif (!empty($_POST['phone_number']) && ($music->config->two_factor_type == 'both' || $music->config->two_factor_type == 'phone') && $is_phone == false) {
                $error = 'Phone number should be as this format: +90..';
            }

            if (empty($error)) {
                $code = rand(111111, 999999);
                $hash_code = md5($code);
                $message = "Your confirmation code is: $code";
                $phone_sent = false;
                $email_sent = false;
                if (!empty($_POST['phone_number']) && ($music->config->two_factor_type == 'both' || $music->config->two_factor_type == 'phone')) {
                    $send = SendSMSMessage($_POST['phone_number'], $message);
                    if ($send) {
                        $phone_sent = true;
                        $Update_data = array(
                            'phone_number' => secure($_POST['phone_number'])
                        );
                        $update = $db->where('id', $user->id)->update(T_USERS, $Update_data);
                    }
                }
                if ($music->config->two_factor_type == 'both' || $music->config->two_factor_type == 'email') {
                    $send_message_data       = array(
                        'from_email' => $music->config->email,
                        'from_name' => $music->config->name,
                        'to_email' => $music->user->email,
                        'to_name' => $music->user->name,
                        'subject' => 'Please verify that it’s you',
                        'charSet' => 'utf-8',
                        'message_body' => $message,
                        'is_html' => true
                    );
                    $send = SendMessage($send_message_data);
                    if ($send) {
                        $email_sent = true;
                    }
                }
                if ($email_sent == true || $phone_sent == true) {
                    $Update_data = array(
                        'two_factor' => 0,
                        'two_factor_verified' => 0,
                        'email_code' => $hash_code
                    );
                    $update = $db->where('id', $user->id)->update(T_USERS, $Update_data);
                    $data = [
                        'status' => 200,
                        'data' => 'We have sent you an email with the confirmation code.'
                    ];
                }
                else{
                    $data = [
                        'status' => 400,
                        'error' => 'Something went wrong, please try again later.'
                    ];
                }
            }
            else{
                $data = [
                        'status' => 400,
                        'error' => $error
                    ];
            }
        }
        elseif($_POST['two_factor'] == 'disable'){
            $Update_data = array(
                'two_factor' => 0,
                'two_factor_verified' => 0
            );
            $update = $db->where('id', $user->id)->update(T_USERS, $Update_data);
            $data = [
                        'status' => 200,
                        'data' => 'Settings successfully updated!'
                    ];
        }

    }
    if ($option == 'verify_two_factor') {
        if (empty($_POST['code'])) {
            $data = [
                    'status' => 400,
                    'error' => 'code can not be empty'
                ];
        }
        else{
            $confirm_code = $db->where('id', $user->id)->where('email_code', md5($_POST['code']))->getValue(T_USERS, 'count(*)');
            $Update_data = array();
            if (empty($confirm_code)) {
                $data = [
                    'status' => 400,
                    'error' => 'Wrong confirmation code.'
                ];
            }
            if (empty($error)) {
                $message = '';
                if ($music->config->two_factor_type == 'phone') {
                    $message = 'Your phone number has been successfully verified.';
                    if (!empty($user->new_phone)) {
                        $Update_data['phone_number'] = $user->new_phone;
                        $Update_data['new_phone'] = '';
                    }
                }
                if ($music->config->two_factor_type == 'email') {
                    $message = 'Your E-mail has been successfully verified.';
                    if (!empty($user->new_email)) {
                        $Update_data['email'] = $user->new_email;
                        $Update_data['new_email'] = '';
                    }
                }
                if ($music->config->two_factor_type == 'both') {
                    $message = 'Your phone number and E-mail have been successfully verified.';
                    if (!empty($_GET['setting'])) {
                        if (!empty($user->new_email)) {
                            $Update_data['email'] = $user->new_email;
                            $Update_data['new_email'] = '';
                        }
                        if (!empty($user->new_phone)) {
                            $Update_data['phone_number'] = $user->new_phone;
                            $Update_data['new_phone'] = '';
                        }
                    }
                }
                $Update_data['two_factor_verified'] = 1;
                $Update_data['two_factor'] = 1;
                $Update_data['email_code'] = '';
                $update = $db->where('id', $user->id)->update(T_USERS, $Update_data);
                $data = [
                        'status' => 200,
                        'data' => $message
                    ];
            }
        }

    }
    if ($option == 'update_notification_setting') {
        $array = ToArray($userData->email_privacy);
        if (!empty($_POST)) {
            foreach ($_POST as $key => $value) {
                if (in_array($key, array_keys($array)) && is_numeric($value)) {
                    if ($value == 1) {
                        $array[$key] = '1';
                    }
                    else{
                        $array[$key] = '0';
                    }
                }
            }
            if (isAdmin() || $userData->id == $user->id) {
                $update = $db->where('id', $userData->id)->update(T_USERS, array('email_privacy' => json_encode($array)));
                if ($update) {
                    $data = [
                        'status' => 200,
                        'message' => "Settings successfully updated!"
                    ];
                }
                else{
                    $data = [
                        'status' => 400,
                        'error' => 'something went wrong'
                    ];
                }
            }
            else{
                $data = [
                    'status' => 400,
                    'error' => 'you are not the profile owner'
                ];
            }
        }
        else{
            $data = [
                    'status' => 400,
                    'error' => 'check your details'
                ];
        }
    }
}
?>