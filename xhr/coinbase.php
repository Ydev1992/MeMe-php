<?php
if ($option == 'get_coinbase') {
	$data['status'] = 400;
	if (!empty($_POST['amount']) && is_numeric($_POST['amount']) && $_POST['amount'] > 0) {
		$amount = (int) secure($_POST['amount']);
		try {
            $redirect_url = $music->config->site_url ."/endpoints/coinbase/success_coinbase?user_id=" . $music->user->id;
            $cancel_url = $music->config->site_url ."/endpoints/coinbase/cancel_coinbase?user_id=" . $music->user->id; 
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://api.commerce.coinbase.com/charges');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            $postdata =  array('name' => 'Top Up Wallet','description' => 'Top Up Wallet','pricing_type' => 'fixed_price','local_price' => array('amount' => $amount , 'currency' => $music->config->currency), 'metadata' => array('user_id' => $music->user->id,'amount' => $amount),"redirect_url" => $redirect_url,'cancel_url' => $cancel_url);


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
                    'message' => curl_error($ch)
                );
            }
            curl_close($ch);

            $result = json_decode($result,true);
            if (!empty($result) && !empty($result['data']) && !empty($result['data']['hosted_url']) && !empty($result['data']['id']) && !empty($result['data']['code'])) {
                $db->insert(T_PENDING_PAYMENTS,array('user_id' => $music->user->id,
                                                     'payment_data' => $result['data']['code'],
                                                     'method_name' => 'coinbase',
                                                     'time' => time()));
                $data['status'] = 200;
                $data['url'] = $result['data']['hosted_url'];
            }
        }
        catch (Exception $e) {
            runPlugin('AfterFailedPayment');
            $data = array(
                'status' => 400,
                'message' => $e->getMessage()
            );
        }

	}
	else{
        runPlugin('AfterFailedPayment');
		$data = array(
            'status' => 400,
            'message' => lang("empty_amount")
        );
	}
}
if ($option == 'success_coinbase') {
	if (!empty($_GET['user_id']) && is_numeric($_GET['user_id'])) {
        $user = '';
        $coinbase_code = '';
        $user_id = secure($_GET['user_id']);
	    $payment_data           = $db->objectBuilder()->where('user_id',$user_id)->where('method_name', 'coinbase')->orderBy('id','DESC')->getOne(T_PENDING_PAYMENTS);
        if (!empty($payment_data)) {
            $user           = $db->objectBuilder()->where('id',$user_id)->getOne(T_USERS);
            $coinbase_code = $payment_data->payment_data;
        }
        

	    if (!empty($user)) {
	    	$music->user = $user;
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
            	header("Location: $site_url/payment-error?reason=".curl_error($ch));
                exit();
            }
            curl_close($ch);
            $result = json_decode($result,true);

	    	
            if (!empty($result) && !empty($result['data']) && !empty($result['data']['pricing']) && !empty($result['data']['pricing']['local']) && !empty($result['data']['pricing']['local']['amount']) && !empty($result['data']['payments']) && !empty($result['data']['payments'][0]['status']) && $result['data']['payments'][0]['status'] == 'CONFIRMED') {
            	$amount = (int)$result['data']['pricing']['local']['amount'];
                $db->where('user_id', $user->id)->where('payment_data', $coinbase_code)->delete(T_PENDING_PAYMENTS);

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
            }
	    }
	}
    $re_url = $site_url . "/ads";
    if (!empty($music) && !empty($music->user) && !empty($music->user->username)) {
        $re_url = $site_url . "/settings/" .$music->user->username. "/wallet";
    }
    elseif (!empty($user) && !empty($user->username)) {
        $re_url = $site_url . "/settings/" .$user->username. "/wallet";
    }
    header("Location: " . $re_url);
    exit();
}
if ($option == 'cancel_coinbase') {
    if (!empty($_GET['user_id']) && is_numeric($_GET['user_id'])) {
        $user_id = secure($_GET['user_id']);
        $user = $db->where('id',$user_id)->getOne(T_USERS);
        if (!empty($user)) {
            $db->where('user_id', $user->id)->where('method_name', 'coinbase')->delete(T_PENDING_PAYMENTS);
        }
    }
    $re_url = $site_url . "/ads";
    if (!empty($music) && !empty($music->user) && !empty($music->user->username)) {
        $re_url = $site_url . "/settings/" .$music->user->username. "/wallet";
    }
    elseif (!empty($user) && !empty($user->username)) {
        $re_url = $site_url . "/settings/" .$user->username. "/wallet";
    }
    runPlugin('AfterFailedPayment');
    header("Location: " . $re_url);
    exit();
}