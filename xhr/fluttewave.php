<?php
if ($option == 'pay') {
	$data['status'] = 400;
	if (!empty($_POST['amount']) && is_numeric($_POST['amount']) && !empty($_POST['email'])) {
		$email = $_POST['email'];
	    $amount = $_POST['amount'];

	    //* Prepare our rave request
	    $request = [
	        'tx_ref' => time(),
	        'amount' => $amount,
	        'currency' => 'NGN',
	        'payment_options' => 'card',
	        'redirect_url' => $music->config->site_url . "/endpoints/fluttewave/success",
	        'customer' => [
	            'email' => $email,
	            'name' => 'user_'.uniqid()
	        ],
	        'meta' => [
	            'price' => $amount
	        ],
	        'customizations' => [
	            'title' => 'Top Up Wallet',
	            'description' => 'Top Up Wallet'
	        ]
	    ];

	    //* Ca;; f;iterwave emdpoint
	    $curl = curl_init();

	    curl_setopt_array($curl, array(
	    CURLOPT_URL => 'https://api.flutterwave.com/v3/payments',
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_ENCODING => '',
	    CURLOPT_MAXREDIRS => 10,
	    CURLOPT_TIMEOUT => 0,
	    CURLOPT_FOLLOWLOCATION => true,
	    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	    CURLOPT_CUSTOMREQUEST => 'POST',
	    CURLOPT_POSTFIELDS => json_encode($request),
	    CURLOPT_HTTPHEADER => array(
	        'Authorization: Bearer '.$music->config->fluttewave_secret_key,
	        'Content-Type: application/json'
	    ),
	    ));

	    $response = curl_exec($curl);

	    curl_close($curl);
	    
	    $res = json_decode($response);
	    if($res->status == 'success')
	    {
	    	$data['status'] = 200;
	        $data['url'] = $res->data->link;
	    }
	    else
	    {
	        $data['message'] = lang('something_went_wrong_please_try_again_later_');
	    }
	}
	else{
		$data['message'] = lang("Please check your details");
	}
}
if ($option == 'success') {
	if (!empty($_GET['status']) && $_GET['status'] == 'successful' && !empty($_GET['transaction_id'])) {
		$txid = $_GET['transaction_id'];

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/{$txid}/verify",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
              "Content-Type: application/json",
              "Authorization: Bearer ".$music->config->fluttewave_secret_key
            ),
        ));
          
        $response = curl_exec($curl);
          
        curl_close($curl);
          
        $res = json_decode($response);
        if($res->status){
            $amount = $res->data->charged_amount;

            $updateUser = $db
                    ->where("id", $music->user->id)
                    ->update(T_USERS, ["wallet" => $db->inc($amount)]);
            CreatePayment([
                "user_id" => $music->user->id,
                "amount" => $amount,
                "type" => "WALLET",
                "pro_plan" => 0,
                "info" => "Replenish My Balance",
                "via" => "Flutterwave",
            ]);
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