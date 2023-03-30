<?php
error_reporting(0);
ini_set('display_startup_errors', 0);
ini_set('display_errors', 0);

@ini_set('max_execution_time', 0);
@ini_set("memory_limit", "-1");
@set_time_limit(0);

require 'config.php';
require 'assets/libs/DB/vendor/autoload.php';
require 'assets/libs/plugins/hooks.php';

$music     = ToObject(array());

// Connect to MySQL Server
$mysqli     = new mysqli($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name);

$ServerErrors = array();
if (mysqli_connect_errno()) {
    $ServerErrors[] = "Failed to connect to MySQL: " . mysqli_connect_error();
}
if (isset($ServerErrors) && !empty($ServerErrors)) {
    foreach ($ServerErrors as $Error) {
        echo "<h3>" . $Error . "</h3>";
    }
    die();
}
$sqlConnect = $mysqli;

$music->hooks = $hooks = Hooks::getInstance();

$query = $mysqli->query("SET NAMES utf8");

//for emoji icons encoding
$mysqli->set_charset('utf8mb4');
$mysqli->query("SET collation_connection = utf8mb4_unicode_ci");

// Connecting to DB after verfication
$db = new MysqliDb($mysqli);

$baned_ips = GetBanned('user');

if (in_array($_SERVER["REMOTE_ADDR"], $baned_ips)) {
    exit();
}

$http_header = 'http://';
if (!empty($_SERVER['HTTPS'])) {
    $http_header = 'https://';
}

$music->site_pages           = array('home');
$music->actual_link          = $http_header . $_SERVER['HTTP_HOST'] . urlencode($_SERVER['REQUEST_URI']);

$config                   = getConfig();
if ($config['developer_mode'] == 'on') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
$music->loggedin          = false;

if (!empty($_GET['theme'])) {
    if ($_GET['theme'] == 'default' || $_GET['theme'] == 'volcano' || $_GET['theme'] == 'soundify' ) {
        $_SESSION['theme'] = $_GET['theme'];
    }
}
$config["withdrawal_payment_method"] = json_decode($config['withdrawal_payment_method'],true);
$config['theme'] = (!empty($_SESSION['theme'])) ? $_SESSION['theme'] : $config['theme'];

$config['theme_url']      = $site_url . '/themes/' . $config['theme'];
$config['site_url']       = $site_url;
$config['ajax_url']       = $site_url . '/endpoints';
//$config['script_version'] = $music->script_version;//// edited old
$config['script_version'] = $config['version'];// edited
$site = parse_url($site_url);
if (empty($site['host'])) {
    $config['hostname'] = $site['scheme'] . '://' .  $site['host'];
}

$music->config               = ToObject($config);
$langs                       = db_langs();
$music->langs                = $langs;
$music->iso = GetIso();

$music->config->currency_array = unserialize($music->config->currency_array);
$music->config->currency_symbol_array = unserialize($music->config->currency_symbol_array);

$music->paypal_currency = array('USD','EUR','AUD','BRL','CAD','CZK','DKK','HKD','HUF','INR','ILS','JPY','MYR','MXN','TWD','NZD','NOK','PHP','PLN','GBP','RUB','SGD','SEK','CHF','THB');
$music->checkout_currency = array('USD','EUR','AED','AFN','ALL','ARS','AUD','AZN','BBD','BDT','BGN','BMD','BND','BOB','BRL','BSD','BWP','BYN','BZD','CAD','CHF','CLP','CNY','COP','CRC','CZK','DKK','DOP','DZD','EGP','FJD','GBP','GTQ','HKD','HNL','HRK','HUF','IDR','ILS','INR','JMD','JOD','JPY','KES','KRW','KWD','KZT','LAK','LBP','LKR','LRD','MAD','MDL','MMK','MOP','MRO','MUR','MVR','MXN','MYR','NAD','NGN','NIO','NOK','NPR','NZD','OMR','PEN','PGK','PHP','PKR','PLN','PYG','QAR','RON','RSD','RUB','SAR','SBD','SCR','SEK','SGD','SYP','THB','TND','TOP','TRY','TTD','TWD','UAH','UYU','VND','VUV','WST','XCD','XOF','YER','ZAR');
$music->stripe_currency = array('USD','EUR','AUD','BRL','CAD','CZK','DKK','HKD','HUF','ILS','JPY','MYR','MXN','TWD','NZD','NOK','PHP','PLN','RUB','SGD','SEK','CHF','THB','GBP');

$payment_currency = $music->config->currency;
$music->config->currency_symbol = $config['currency_symbol']  = !empty($music->config->currency_symbol_array[$music->config->currency]) ? $music->config->currency_symbol_array[$music->config->currency] : '$';

$music->script_version = $music->config->version;

if (isLogged() == true) {
    $music->loggedin   = true;
    $music->user_session_id = (!empty($_SESSION['user_id'])) ? $_SESSION['user_id'] : $_COOKIE['user_id'];
    if (isset($_POST['access_token']) && !empty($_POST['access_token'])) {
        $music->user_session  = getUserFromSessionID($_POST['access_token'], 'mobile');
        $music->user_session_id = secure($_POST['access_token']);
    }else{
        $session_id        = (!empty($_SESSION['user_id'])) ? $_SESSION['user_id'] : $_COOKIE['user_id'];
        $music->user_session  = getUserFromSessionID($session_id);
    }

    if (empty($music->user_session) && !empty($_POST['access_token'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 400,"error" => 'Invalid access token']);
        exit();
    }

    $user = $music->user  = userData($music->user_session);
    $user->wallet      = number_format($user->wallet);

    if (!empty($user->language) && in_array($user->language, $langs)) {
        $_SESSION['lang'] = $user->language;
    }

    if ($user->id < 0 || empty($user->id) || !is_numeric($user->id) || isUserActive($user->id) === false) {
        header("Location: " . getLink('logout'));
    }
}

if (isset($_GET['lang']) AND !empty($_GET['lang'])) {
    $lang_name = secure(strtolower($_GET['lang']));

    if (in_array($lang_name, $langs)) {
        $_SESSION['lang'] = $lang_name;
        if ($music->loggedin == true) {
            $db->where('id', $user->id)->update(T_USERS, array('language' => $lang_name));
        }
    }
}

define('IS_LOGGED', $music->loggedin);


if (empty($_SESSION['lang'])) {
  $_SESSION['lang'] = $music->config->language;
}

if (isset($_SESSION['user_id'])) {
    if (empty($_COOKIE['user_id'])) {
        setcookie("user_id", $_SESSION['user_id'], time() + (10 * 365 * 24 * 60 * 60), "/");
    }
}

$music->min_song_price = 0;//(float)$db->rawQuery('SELECT MIN('.T_SONG_PRICE.'.price) AS MinPrice FROM '.T_SONG_PRICE.' WHERE '.T_SONG_PRICE.'.price > 0')[0]->MinPrice;
$music->max_song_price = (float)$db->rawQuery('SELECT MAX('.T_SONG_PRICE.'.price) AS MaxPrice FROM '.T_SONG_PRICE)[0]->MaxPrice;
$music->song_prices = [];
$prices = $db->rawQuery('SELECT '.T_SONG_PRICE.'.price FROM '.T_SONG_PRICE);
$music->song_prices[] = '0.00';
foreach ($prices as $key => $value){
    $music->song_prices[] = $value->price;
}
$music->song_prices = array_unique($music->song_prices);
$music->language      = $_SESSION['lang'];
$music->language_type = 'ltr';

// Add rtl languages here.
$rtl_langs           = array(
    'arabic',
    'urdu',
    'hebrew'
);

// checking if corrent language is rtl.
foreach ($rtl_langs as $lang) {
    if ($music->language == strtolower($lang)) {
        $music->language_type = 'rtl';
    }
}


// Include Language File
$lang_file = 'assets/langs/' . $music->language . '.php';
if (file_exists($lang_file)) {
    require($lang_file);
}



$lang_array = get_langs($music->language);
if (empty($lang_array)) {
    $lang_array = get_langs();
}

$lang       = ToObject($lang_array);
$music->lang_array = $lang_array;
$music->user_default_avatar = 'upload/photos/d-avatar.jpg';
$music->categories  = ToObject(getCategories());

$music->update_cache                 = '';
if (!empty($music->config->last_update)) {
    $update_cache = time() - 21600;
    if ($update_cache < $music->config->last_update) {
        $music->update_cache = '?' . sha1(time());
    }
}

$music->mode_link = 'night';
$music->mode_text = lang('Night mode');

$music->ads_media_types = array(
    'video/mp4',
    'video/mov',
    'video/mpeg',
    'video/flv',
    'video/avi',
    'video/webm',
    'video/quicktime',
    'image/png',
    'image/jpeg',
    'image/gif'
);
$music->activities = array(
		'comment',                  //done
		'upload',
		'listen_to_song',            //done
		'replay_comment',           //done
		'like_track',               //done
		'dislike_track',            //done
		'like_comment',             //done
		'like_blog_comment',        //done
		'unlike_comment',           //done
		'unlike_blog_comment',      //done
		'repost',                   //done
		'track_download',           //done
		'import',                   //done
		'purchase_track',           //done
		'go_pro',                   //done
		'review_track',             //done
		'report_track',             //done
		'report_comment',           //done
		'add_to_playlist',          //done
		'create_new_playlist',      //done
		'update_profile_picture',   //done
		'update_profile_cover',     //done
        'admob',     //done
);
$music->ads_audio_types =   array(
                                'audio/wav',
                                'audio/mpeg',
                                'audio/ogg',
                                'audio/mp3'
                            );
if ($music->config->user_ads == 'on') {

    if (!isset($_COOKIE['_uads'])) {
        setcookie('_uads', htmlentities(serialize(array(
            'date' => strtotime('+1 day'),
            'uaid_' => array()
        ))), time() + (10 * 365 * 24 * 60 * 60),'/');
    }

    $music->user_ad_cons = array(
        'date' => strtotime('+1 day'),
        'uaid_' => array()
    );

    if (!empty($_COOKIE['_uads'])) {
        $music->user_ad_cons = unserialize(html_entity_decode($_COOKIE['_uads']));
    }

    if (!is_array($music->user_ad_cons) || !isset($music->user_ad_cons['date']) || !isset($music->user_ad_cons['uaid_'])) {
        setcookie('_uads', htmlentities(serialize(array(
            'date' => strtotime('+1 day'),
            'uaid_' => array()
        ))), time() + (10 * 365 * 24 * 60 * 60),'/');
    }

    if (is_array($music->user_ad_cons) && isset($music->user_ad_cons['date']) && $music->user_ad_cons['date'] < time()) {
        setcookie('_uads', htmlentities(serialize(array(
            'date' => strtotime('+1 day'),
            'uaid_' => array()
        ))),time() + (10 * 365 * 24 * 60 * 60),'/');
    }
}

// night mode
if (empty($_COOKIE['mode'])) {
    setcookie("mode", $music->config->night_mode, time() + (10 * 365 * 24 * 60 * 60), '/');
    $_COOKIE['mode'] = $music->config->night_mode;
    $music->mode_link = 'day';
    $music->mode_text = lang('Night mode');
} else {
    if ($_COOKIE['mode'] == 'day') {
        $music->mode_link = 'night';
        $music->mode_text = lang('Night mode');
    }
    if ($_COOKIE['mode'] == 'night') {
        $music->mode_link = 'day';
        $music->mode_text = lang('Day mode');
    }
}

if (!empty($_GET['mode'])) {
    if ($_GET['mode'] == 'day') {
        setcookie("mode", 'day', time() + (10 * 365 * 24 * 60 * 60), '/');
        $_COOKIE['mode'] = 'day';
        $music->mode_link = 'night';
        $music->mode_text = lang('Night mode');
    } else if ($_GET['mode'] == 'night') {
        setcookie("mode", 'night', time() + (10 * 365 * 24 * 60 * 60), '/');
        $_COOKIE['mode'] = 'night';
        $music->mode_link = 'day';
        $music->mode_text = lang('Day mode');
    }
}

if (empty($_SESSION['uploads'])) {

    $_SESSION['uploads'] = array();

    if (empty($_SESSION['uploads']['music'])) {
        $_SESSION['uploads']['music'] = array();
    }

    if (empty($_SESSION['uploads']['images'])) {
        $_SESSION['uploads']['images'] = array();
    }
}
if ($music->config->push == 1 || $music->config->android_push_native == 1 || $music->config->ios_push_native == 1) {
    include_once('assets/includes/onesignal.php');
}
$music->timezones = array(
    'Pacific/Midway'       => "(GMT-11:00) Midway Island",
    'US/Samoa'             => "(GMT-11:00) Samoa",
    'US/Hawaii'            => "(GMT-10:00) Hawaii",
    'US/Alaska'            => "(GMT-09:00) Alaska",
    'US/Pacific'           => "(GMT-08:00) Pacific Time (US &amp; Canada)",
    'America/Tijuana'      => "(GMT-08:00) Tijuana",
    'US/Arizona'           => "(GMT-07:00) Arizona",
    'US/Mountain'          => "(GMT-07:00) Mountain Time (US &amp; Canada)",
    'America/Chihuahua'    => "(GMT-07:00) Chihuahua",
    'America/Mazatlan'     => "(GMT-07:00) Mazatlan",
    'America/Mexico_City'  => "(GMT-06:00) Mexico City",
    'America/Monterrey'    => "(GMT-06:00) Monterrey",
    'Canada/Saskatchewan'  => "(GMT-06:00) Saskatchewan",
    'US/Central'           => "(GMT-06:00) Central Time (US &amp; Canada)",
    'US/Eastern'           => "(GMT-05:00) Eastern Time (US &amp; Canada)",
    'US/East-Indiana'      => "(GMT-05:00) Indiana (East)",
    'America/Bogota'       => "(GMT-05:00) Bogota",
    'America/Lima'         => "(GMT-05:00) Lima",
    'America/Caracas'      => "(GMT-04:30) Caracas",
    'Canada/Atlantic'      => "(GMT-04:00) Atlantic Time (Canada)",
    'America/La_Paz'       => "(GMT-04:00) La Paz",
    'America/Santiago'     => "(GMT-04:00) Santiago",
    'Canada/Newfoundland'  => "(GMT-03:30) Newfoundland",
    'America/Buenos_Aires' => "(GMT-03:00) Buenos Aires",
    'Greenland'            => "(GMT-03:00) Greenland",
    'Atlantic/Stanley'     => "(GMT-02:00) Stanley",
    'Atlantic/Azores'      => "(GMT-01:00) Azores",
    'Atlantic/Cape_Verde'  => "(GMT-01:00) Cape Verde Is.",
    'Africa/Casablanca'    => "(GMT) Casablanca",
    'Europe/Dublin'        => "(GMT) Dublin",
    'Europe/Lisbon'        => "(GMT) Lisbon",
    'Europe/London'        => "(GMT) London",
    'Africa/Monrovia'      => "(GMT) Monrovia",
    'Europe/Amsterdam'     => "(GMT+01:00) Amsterdam",
    'Europe/Belgrade'      => "(GMT+01:00) Belgrade",
    'Europe/Berlin'        => "(GMT+01:00) Berlin",
    'Europe/Bratislava'    => "(GMT+01:00) Bratislava",
    'Europe/Brussels'      => "(GMT+01:00) Brussels",
    'Europe/Budapest'      => "(GMT+01:00) Budapest",
    'Europe/Copenhagen'    => "(GMT+01:00) Copenhagen",
    'Europe/Ljubljana'     => "(GMT+01:00) Ljubljana",
    'Europe/Madrid'        => "(GMT+01:00) Madrid",
    'Europe/Paris'         => "(GMT+01:00) Paris",
    'Europe/Prague'        => "(GMT+01:00) Prague",
    'Europe/Rome'          => "(GMT+01:00) Rome",
    'Europe/Sarajevo'      => "(GMT+01:00) Sarajevo",
    'Europe/Skopje'        => "(GMT+01:00) Skopje",
    'Europe/Stockholm'     => "(GMT+01:00) Stockholm",
    'Europe/Vienna'        => "(GMT+01:00) Vienna",
    'Europe/Warsaw'        => "(GMT+01:00) Warsaw",
    'Europe/Zagreb'        => "(GMT+01:00) Zagreb",
    'Europe/Athens'        => "(GMT+02:00) Athens",
    'Europe/Bucharest'     => "(GMT+02:00) Bucharest",
    'Africa/Cairo'         => "(GMT+02:00) Cairo",
    'Africa/Harare'        => "(GMT+02:00) Harare",
    'Europe/Helsinki'      => "(GMT+02:00) Helsinki",
    'Europe/Istanbul'      => "(GMT+02:00) Istanbul",
    'Asia/Jerusalem'       => "(GMT+02:00) Jerusalem",
    'Europe/Kiev'          => "(GMT+02:00) Kyiv",
    'Europe/Minsk'         => "(GMT+02:00) Minsk",
    'Europe/Riga'          => "(GMT+02:00) Riga",
    'Europe/Sofia'         => "(GMT+02:00) Sofia",
    'Europe/Tallinn'       => "(GMT+02:00) Tallinn",
    'Europe/Vilnius'       => "(GMT+02:00) Vilnius",
    'Asia/Baghdad'         => "(GMT+03:00) Baghdad",
    'Asia/Kuwait'          => "(GMT+03:00) Kuwait",
    'Africa/Nairobi'       => "(GMT+03:00) Nairobi",
    'Asia/Riyadh'          => "(GMT+03:00) Riyadh",
    'Europe/Moscow'        => "(GMT+03:00) Moscow",
    'Asia/Tehran'          => "(GMT+03:30) Tehran",
    'Asia/Baku'            => "(GMT+04:00) Baku",
    'Europe/Volgograd'     => "(GMT+04:00) Volgograd",
    'Asia/Muscat'          => "(GMT+04:00) Muscat",
    'Asia/Tbilisi'         => "(GMT+04:00) Tbilisi",
    'Asia/Yerevan'         => "(GMT+04:00) Yerevan",
    'Asia/Kabul'           => "(GMT+04:30) Kabul",
    'Asia/Karachi'         => "(GMT+05:00) Karachi",
    'Asia/Tashkent'        => "(GMT+05:00) Tashkent",
    'Asia/Kolkata'         => "(GMT+05:30) Kolkata",
    'Asia/Kathmandu'       => "(GMT+05:45) Kathmandu",
    'Asia/Yekaterinburg'   => "(GMT+06:00) Ekaterinburg",
    'Asia/Almaty'          => "(GMT+06:00) Almaty",
    'Asia/Dhaka'           => "(GMT+06:00) Dhaka",
    'Asia/Novosibirsk'     => "(GMT+07:00) Novosibirsk",
    'Asia/Bangkok'         => "(GMT+07:00) Bangkok",
    'Asia/Jakarta'         => "(GMT+07:00) Jakarta",
    'Asia/Krasnoyarsk'     => "(GMT+08:00) Krasnoyarsk",
    'Asia/Chongqing'       => "(GMT+08:00) Chongqing",
    'Asia/Hong_Kong'       => "(GMT+08:00) Hong Kong",
    'Asia/Kuala_Lumpur'    => "(GMT+08:00) Kuala Lumpur",
    'Australia/Perth'      => "(GMT+08:00) Perth",
    'Asia/Singapore'       => "(GMT+08:00) Singapore",
    'Asia/Taipei'          => "(GMT+08:00) Taipei",
    'Asia/Ulaanbaatar'     => "(GMT+08:00) Ulaan Bataar",
    'Asia/Urumqi'          => "(GMT+08:00) Urumqi",
    'Asia/Irkutsk'         => "(GMT+09:00) Irkutsk",
    'Asia/Seoul'           => "(GMT+09:00) Seoul",
    'Asia/Tokyo'           => "(GMT+09:00) Tokyo",
    'Australia/Adelaide'   => "(GMT+09:30) Adelaide",
    'Australia/Darwin'     => "(GMT+09:30) Darwin",
    'Asia/Yakutsk'         => "(GMT+10:00) Yakutsk",
    'Australia/Brisbane'   => "(GMT+10:00) Brisbane",
    'Australia/Canberra'   => "(GMT+10:00) Canberra",
    'Pacific/Guam'         => "(GMT+10:00) Guam",
    'Australia/Hobart'     => "(GMT+10:00) Hobart",
    'Australia/Melbourne'  => "(GMT+10:00) Melbourne",
    'Pacific/Port_Moresby' => "(GMT+10:00) Port Moresby",
    'Australia/Sydney'     => "(GMT+10:00) Sydney",
    'Asia/Vladivostok'     => "(GMT+11:00) Vladivostok",
    'Asia/Magadan'         => "(GMT+12:00) Magadan",
    'Pacific/Auckland'     => "(GMT+12:00) Auckland",
    'Pacific/Fiji'         => "(GMT+12:00) Fiji",
);

$music->products_categories = GetCategoriesKeys(T_PRODUCTS_CATEGORY);
if (!isset($_COOKIE['_us'])) {
    setcookie('_us', time() + (60 * 60 * 24) , time() + (10 * 365 * 24 * 60 * 60));
}


try {
  $music->pro_packages       = GetAllProInfo();
} catch (\Exception $e) {} 

$music->manage_pro_features = array('who_can_download' => 'can_use_download',
                                    'who_can_sell' => 'can_use_sell',
                                    'who_can_multi_upload' => 'can_use_multi_upload',
                                    'channel_trailer_upload' => 'can_use_channel_trailer',
                                    'who_can_blog' => 'can_use_blog',
                                    'who_can_station_import' => 'can_use_station_import',
                                    'who_can_upload' => 'can_use_upload',
                                    'who_can_soundcloud_import' => 'can_use_soundcloud_import',
                                    'who_can_deezer_import' => 'can_use_deezer_import',
                                    'who_can_itunes_import' => 'can_use_itunes_import',
                                    'itunes_affiliate' => 'can_use_itunes_affiliate',
                                    'who_can_youtube_import' => 'can_use_youtube_import',
                                    'who_can_user_ads' => 'can_use_user_ads',
                                    'who_audio_ads' => 'can_use_audio_ads',
                                    'who_can_event_system' => 'can_use_event_system',
                                    'who_can_story_system' => 'can_use_story_system',
                                    'who_can_store_system' => 'can_use_store_system',
                                    'who_can_point_system' => 'can_use_point_system',
                                    'who_can_donate_system' => 'can_use_donate_system',
                                    );
$music->available_pro_features = array();
$music->available_verified_features = array();
$music->available_artist_features = array();

foreach ($music->manage_pro_features as $key => $value) {
    $music->config->{$value} = true;
    if ($music->loggedin && !empty($music->user)) {
        if ($music->config->{$key} == 'verified' && !$music->user->verified) {
            $music->config->{$value} = false;
        }
        if ($music->config->{$key} == 'admin' && !$music->user->admin) {
            $music->config->{$value} = false;
        }
        if ($music->config->{$key} == 'pro' && !$music->user->is_pro) {
            $music->config->{$value} = false;
        }
        if ($music->config->{$key} == 'artist' && !$music->user->artist) {
            $music->config->{$value} = false;
        }
        if ($music->config->{$key} == 'pro' && $music->user->is_pro && !empty($music->pro_packages[$music->user->pro_type]) && $music->pro_packages[$music->user->pro_type][$value] != 1) {
            $music->config->{$value} = false;
        }
        if ($music->user->admin) {
            $music->config->{$value} = true;
        }
    }
    if ($music->config->{$key} == 'pro') {
        $music->available_pro_features[$key] = $value;
    }
    if ($music->config->{$key} == 'verified') {
        $music->available_verified_features[$key] = $value;
    }
    if ($music->config->{$key} == 'artist') {
        $music->available_artist_features[$key] = $value;
    }
}
if (!$music->config->can_use_blog) {
    $music->config->allow_user_create_blog = 'off';
}
if (!$music->config->can_use_soundcloud_import) {
    $music->config->soundcloud_import = 'off';
}
if (!$music->config->can_use_deezer_import) {
    $music->config->deezer_import = 'off';
}
if (!$music->config->can_use_itunes_import) {
    $music->config->itunes_import = 'off';
}
if (!$music->config->can_use_youtube_import) {
    $music->config->youtube_import = 'off';
}
if (!$music->config->can_use_point_system) {
    $music->config->point_system = 'off';
    $config['point_system'] = 'off';
}

$terms_langs = $db->where("lang_key = 'terms_of_use_page' OR lang_key = 'privacy_policy_page' OR lang_key = 'about_page' OR lang_key = 'dmca_terms_page'")->get(T_LANGS);
$music->terms_of_use_page = 1;
$music->privacy_policy_page = 1;
$music->about_page = 1;
$music->dmca_terms_page = 1;
foreach ($terms_langs as $key => $value) {
    if (in_array($value->options, array('on','off'))) {
        if ($value->options == 'on') {
            $music->{$value->lang_key} = 1;
        }
        else{
            $music->{$value->lang_key} = 0;
        }
    }
    else{
        $db->where('lang_key',$value->lang_key)->update(T_LANGS,array('options' => 'on'));
    }
}

$music->config->filesVersion = '1.5.2';

if ($music->config->filesVersion != $music->config->version) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
$music->reserved_usernames = array();
if (!empty($music->config->reserved_usernames)) {
    $music->reserved_usernames = explode(',', $music->config->reserved_usernames);
}

$getPlugins = glob("plugins/*", GLOB_BRACE);

$jsFiles = [];
$cssFiles = [];

foreach ($getPlugins as $plugin) {
    if (is_dir($plugin)) {
        if (isPluginEnabled(basename($plugin))) {
            $loadPlugins = glob($plugin . "/*");
            foreach ($loadPlugins as $key => $loadedPlugin) {
                $ext = substr(strrchr($loadedPlugin, '.'), 1);
                if ($ext == 'php') {
                    require "$loadedPlugin";
                }
                if ($ext == 'js') {
                    $jsFiles[] = '<script type="text/javascript" src="' . $site_url . "/" . $loadedPlugin . '"></script>';
                }
                if ($ext == 'css') {
                    $cssFiles[] = '<link href="' . $site_url . "/" . $loadedPlugin . '" rel="stylesheet">';
                }
            }
        }
    }
}
$music->jsPluginFiles = implode("\n", $jsFiles);
$music->cssPluginFiles = implode("\n", $cssFiles);