<?php 

if( !isset($_GET['price']) ){
    header("Location: $site_url/404");
    exit();
}
if (IS_LOGGED == false ) {
	header("Location: $site_url/404");
	exit();
}
if (empty($path['options'][1]) || empty($_GET['token'])) {
	header("Location: $site_url/payment-error");
	exit();
}

if ($path['options'][1] !== 'true') {
    header("Location: $site_url/payment-error");
    exit();
}
$price = (int)secure($_GET['price']);
$token = secure($_GET['token']);

include_once('assets/includes/paypal.php');

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url . '/v2/checkout/orders/'.$token.'/capture');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);

$headers = array();
$headers[] = 'Content-Type: application/json';
$headers[] = 'Authorization: Bearer '.$music->paypal_access_token;
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    header("Location: $site_url/payment-error?reason=invalid-payment");
    exit();
}
curl_close($ch);
if (!empty($result)) {
    $result = json_decode($result);
    if (!empty($result->status) && $result->status == 'COMPLETED') {
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
            header("Location: $site_url/payment-error?reason=cant-create-payment");
            exit();
        }
    }
}

header("Location: $site_url/payment-error?reason=invalid-payment");
exit();
