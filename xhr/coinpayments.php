<?php
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
            $db->insert(T_PENDING_PAYMENTS,array('user_id' => $music->user->id,
                                                 'payment_data' => $result['data']['txn_id'],
                                                 'method_name' => 'coinpayments',
                                                 'time' => time()));
            $data = array(
                'status' => 200,
                'url' => $result['data']['checkout_url']
            );
        }
        else{
            runPlugin('AfterFailedPayment');
            $data = array(
                'status' => 400,
                'message' => $result['message']
            );
        }
	}
	else{
		$data = array(
            'status' => 400,
            'message' => lang("empty_amount")
        );
	}
}
if ($option == 'cancel_coinpayments') {
    $db->where('user_id', $music->user->id)->where('method_name', 'coinpayments')->delete(T_PENDING_PAYMENTS);
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