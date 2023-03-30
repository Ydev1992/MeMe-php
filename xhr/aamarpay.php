<?php
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
            'opt_a' => $music->user->id,  //optional paramter
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
            'message' => lang("Please check your details")
        );
    }
}
if ($option == 'success_aamarpay') {
	if (!empty($_POST['amount']) && !empty($_POST['mer_txnid']) && !empty($_POST['opt_a']) && !empty($_POST['pay_status']) && $_POST['pay_status'] == 'Successful') {
		$user = $db->objectBuilder()->where('id',secure($_POST['opt_a']))->getOne(T_USERS);
		if (!empty($user)) {
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
if ($option == 'cancel_aamarpay') {
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