<?php

$url = "https://api-m.sandbox.paypal.com";
if ($music->config->paypal_mode == 'live') {
    $url = "https://api-m.paypal.com";
}

$music->paypal_access_token = null;
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url . '/v1/oauth2/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
curl_setopt($ch, CURLOPT_USERPWD, $music->config->paypal_id . ':' . $music->config->paypal_secret);

$headers = array();
$headers[] = 'Content-Type: application/x-www-form-urlencoded';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
curl_close($ch);
$result = json_decode($result);
if (!empty($result->access_token)) {
  $music->paypal_access_token = $result->access_token;
}