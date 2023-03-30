<?php
require_once('assets/libs/stripe/vendor/autoload.php');
if (IS_LOGGED == false) {
    header("Location: $site_url/404");
    exit();
}

if (empty($path['options'][1]) || !isset($_SESSION['stripe_session_payment_intent'])) {
    header("Location: $site_url/payment-error");
    exit();
}
if($path['options'][1] === 'false'){
    header("Location: $site_url/payment-error");
    exit();
}

$stripe = array(
    'secret_key' => $music->config->stripe_secret,
    'publishable_key' => $music->config->stripe_id
);
\Stripe\Stripe::setApiKey($stripe[ 'secret_key' ]);


$intent = \Stripe\PaymentIntent::retrieve($_SESSION['stripe_session_payment_intent']);
$charges = $intent->charges->data;

if($charges[0]->captured === 'false'){
    header("Location: $site_url/payment-error?reason=not-found");
    exit();
}

try{
    $price = ($charges[0]->amount / 100);

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

} catch (Exception $e) {
    header("Location: $site_url/payment-error?reason=invalid-payment");
    exit();
}