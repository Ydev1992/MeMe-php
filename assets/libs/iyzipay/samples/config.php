<?php
require_once(dirname(__DIR__).'/IyzipayBootstrap.php');

IyzipayBootstrap::init();


class IyzipayConfig
{
    public static function options()
    {
    	global $music;
        $site_url = 'https://sandbox-api.iyzipay.com';
        if ($music->config->iyzipay_mode == '0') {
            $site_url = 'https://api.iyzipay.com';
        }

        $options = new \Iyzipay\Options();
        $options->setApiKey($music->config->iyzipay_key);
        $options->setSecretKey($music->config->iyzipay_secret_key);
        $options->setBaseUrl($site_url);

        return $options;
    }
}
$ConversationId = rand(11111111,99999999);
$request = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
$request->setLocale(\Iyzipay\Model\Locale::TR);
$request->setConversationId($ConversationId);
$request->setCurrency(\Iyzipay\Model\Currency::TL);
$request->setBasketId("B".rand(11111111,99999999));
$request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);
$request->setEnabledInstallments(array(2, 3, 6, 9));


$buyer = new \Iyzipay\Model\Buyer();
$buyer->setId($music->config->iyzipay_buyer_id);
$buyer->setName($music->config->iyzipay_buyer_name);
$buyer->setSurname($music->config->iyzipay_buyer_surname);
$buyer->setGsmNumber($music->config->iyzipay_buyer_gsm_number);
$buyer->setEmail($music->config->iyzipay_buyer_email);
$buyer->setIdentityNumber($music->config->iyzipay_identity_number);
$buyer->setRegistrationAddress($music->config->iyzipay_address);
$buyer->setCity($music->config->iyzipay_city);
$buyer->setCountry($music->config->iyzipay_country);
$buyer->setZipCode($music->config->iyzipay_zip);
$request->setBuyer($buyer);


$shippingAddress = new \Iyzipay\Model\Address();
$shippingAddress->setContactName($music->config->iyzipay_buyer_name.' '.$music->config->iyzipay_buyer_surname);
$shippingAddress->setCity($music->config->iyzipay_city);
$shippingAddress->setCountry($music->config->iyzipay_country);
$shippingAddress->setAddress($music->config->iyzipay_address);
$shippingAddress->setZipCode($music->config->iyzipay_zip);
$request->setShippingAddress($shippingAddress);

$billingAddress = new \Iyzipay\Model\Address();
$billingAddress->setContactName($music->config->iyzipay_buyer_name.' '.$music->config->iyzipay_buyer_surname);
$billingAddress->setCity($music->config->iyzipay_city);
$billingAddress->setCountry($music->config->iyzipay_country);
$billingAddress->setAddress($music->config->iyzipay_address);
$billingAddress->setZipCode($music->config->iyzipay_zip);
$request->setBillingAddress($billingAddress);