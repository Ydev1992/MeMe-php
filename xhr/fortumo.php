<?php
if ($option == 'get_fortumo') {
    $data['status'] = 200;
	$data['url'] = 'https://pay.fortumo.com/mobile_payments/'.$music->config->fortumo_service_id.'?cuid='.$music->user->id;
}
if ($option == 'success_fortumo') {
	if (!empty($_GET) && !empty($_GET['amount']) && !empty($_GET['status']) && $_GET['status'] == 'completed' && !empty($_GET['cuid']) && !empty($_GET['price'])) {
        $user_id = secure($_GET['cuid']);
        $amount = (int) secure($_GET['price']);
        $user = $db->objectBuilder()->where('id',$user_id)->getOne(T_USERS);
        if (!empty($user)) {
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