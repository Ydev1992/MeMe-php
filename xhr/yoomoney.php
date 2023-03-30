<?php
if ($option == "create_yoomoney") {
	$data['status'] = 400;
	if (!empty($_POST['amount']) && is_numeric($_POST['amount']) && $_POST['amount'] > 0) {
		$amount = secure($_POST['amount']);
		$order_id = uniqid();
		$receiver = $music->config->yoomoney_wallet_id;
		$successURL = $music->config->site_url ."/endpoints/yoomoney/success_yoomoney";
		$form = '<form id="yoomoney_form" method="POST" action="https://yoomoney.ru/quickpay/confirm.xml">    
					<input type="hidden" name="receiver" value="'.$receiver.'"> 
					<input type="hidden" name="quickpay-form" value="donate"> 
					<input type="hidden" name="targets" value="transaction '.$order_id.'">   
					<input type="hidden" name="paymentType" value="PC"> 
					<input type="hidden" name="sum" value="'.$amount.'" data-type="number"> 
					<input type="hidden" name="successURL" value="'.$successURL.'">
					<input type="hidden" name="label" value="'.$music->user->id.'">
				</form>';
		$data['status'] = 200;
		$data['html'] = $form;
	}
	else{
		$data = array(
            'status' => 400,
            'message' => lang("empty_amount")
        );
	}
}
if ($option == "success_yoomoney") {
	$hash = sha1($_POST['notification_type'].'&'.
	$_POST['operation_id'].'&'.
	$_POST['amount'].'&'.
	$_POST['currency'].'&'.
	$_POST['datetime'].'&'.
	$_POST['sender'].'&'.
	$_POST['codepro'].'&'.
	$music->config->yoomoney_notifications_secret.'&'.
	$_POST['label']);

	$_POST['codepro'] = (is_string($_POST['codepro']) && strtolower($_POST['codepro']) == 'true' ? true : false);

	if ($_POST['sha1_hash'] != $hash || $_POST['codepro'] == true) {
		runPlugin('AfterFailedPayment');
		header("Location: $site_url/payment-error?reason=cant-create-payment");
        exit();
	}
	else{
		if (!empty($_POST['label'])) {
			$user = $db->objectBuilder()->where('id',secure($_POST['label']))->getOne(T_USERS);
			if (!empty($user)) {
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