<?php
require "assets/init.php";

runPlugin('BeforePageLoad');

if (!empty($auto_redirect)) {
    $checkHTTPS = checkHTTPS();
    $isURLSSL = strpos($site_url, 'https');

    if ($isURLSSL !== false) {
        if (empty($checkHTTPS)) {
            header("Location: https://" . full_url($_SERVER));
            exit();
        }
    } else if ($checkHTTPS) {
        header("Location: http://" . full_url($_SERVER));
        exit();
    }

    if (strpos($site_url, 'www') !== false) {
        if (!preg_match('/www/', $_SERVER['HTTP_HOST'])) {
            $protocol = ($isURLSSL !== false) ? "https://" : "http://";
            header("Location: $protocol" . full_url($_SERVER));
            exit();
        }
    }

    if (preg_match('/www/', $_SERVER['HTTP_HOST'])) {
        if (strpos($site_url, 'www') === false) {
            $protocol = ($isURLSSL !== false) ? "https://" : "http://";
            header("Location: $protocol" . str_replace("www.", "", full_url($_SERVER)));
            exit();
        }
    }
}

$path = (!empty($_GET['path'])) ? getPageFromPath($_GET['path']) : null;

$page = "";
$music->path = $path;
if (!empty($path['page'])) {
	$page = $path['page'];

    if( $page == 'admin-cp' ){
        if (IS_LOGGED == false) {
            header("Location: $site_url");
            exit();
        }
        if (IsAdmin() == false && !in_array($music->user->admin, array(1,2,3))) {
            header("Location: $site_url");
            exit();
        }
        require 'admin-panel/autoload.php';
        exit();
    }

    if ($page == 'endpoint' && !empty($path['options'])) {
        if ($music->loggedin && !empty($music->user) && $music->user->is_pro && !empty($music->pro_packages[$music->user->pro_type]) && !empty($music->pro_packages[$music->user->pro_type]['max_upload']) && $music->user->admin == 0) {
            $music->config->max_upload = $music->pro_packages[$music->user->pro_type]['max_upload'];
            $music->config->user_max_upload = $music->pro_packages[$music->user->pro_type]['max_upload'];
            $music->config->pro_upload_limit = $music->pro_packages[$music->user->pro_type]['max_upload'];
        }
        if (!empty($path['options']) && !empty($path['options'][2]) && $path['options'][2] == 'get-profile' && !empty($_GET['access_token'])) {
            $request_uid = getUserFromSessionID($_GET['access_token'], 'mobile');
            $_POST = $_GET;
            $_POST['user_id'] = $request_uid;
            $_REQUEST['server_key'] = $music->config->apps_api_key;
        }
        if( !isset($_REQUEST['server_key']) ){
            header('Content-Type: application/json');
            echo json_encode(['status' => 400,"error" => 'Missing server key']);
            exit();
        }else{
            if( $_REQUEST['server_key'] !== $music->config->apps_api_key ) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 400, "error" => 'Invalid server key']);
                exit();
            }
        }

        require_once "./endpoint/functions.php";
        $data = [];
        $file_location = "./endpoint/v1/{$path['options'][1]}.php";
        $api = (!empty($path['options'][1])) ? $path['options'][1] : '';
        $option = (!empty($path['options'][2])) ? $path['options'][2] : '';
        $whitelist = [
            'login',
            'forgot-password',
            'reset-password',
            'signup',
            'contact',
            'options',
            'social-login',
            'discover',
            'get-artists',
            'get-prices',
            'search',
            'top-seller',
            'get-top-songs',
            'get-trending',
            'get-profile',
            'get-pro-user',
            'get-genres',
            'get-following',
            'get-follower',
            'get-artists',
            'get-public-playlists',
            'get-playlist-songs',
            'get-tracks-by-genres',
            'track-info',
            'get-album-songs',
            'get-comment',
            'track-info',
            'session_status',
            'confirm_user_unusal_login',
            'get',
            'get_blog',
            'get_sponsor',
            'get_user_albums',
            'get_user_latest',
            'get_user_top',
            'get_user_store',
            'get_user_radio',
            'get_user_activities'
        ];

        $is_whitelist = false;
        if( in_array($api, $whitelist) ) $is_whitelist = true;
        if( in_array($option, $whitelist) ) $is_whitelist = true;

        if( $is_whitelist === false ) {
            if( !isset($_REQUEST['access_token']) ){
                header('Content-Type: application/json');
                echo json_encode(['status' => 400,"error" => 'Invalid access token']);
                exit();
            }
            if (empty($_REQUEST['access_token'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 400,"error" => 'Invalid access token']);
                exit();
            }
            if (isLogged() === false) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 400,"error" => 'Invalid access token']);
                exit();
            }
        }

        if (file_exists($file_location)) {
            require_once $file_location;
            if (!empty($errors)) {
                $data = array(
                    'status' => 400,
                    'error' => end($errors)
                );
            }
        } else {
            $data = array(
                'status' => 400,
                'error' => "Endpoint not found"
            );
        }

        if(empty($data)){
            $data = array(
                'status' => 400,
                'error' => "Error while processing your request"
            );
        }
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
	if ($page == 'endpoints' && !empty($path['options'])) {
        if ($music->loggedin && !empty($music->user) && $music->user->is_pro && !empty($music->pro_packages[$music->user->pro_type]) && !empty($music->pro_packages[$music->user->pro_type]['max_upload']) && $music->user->admin == 0) {
            $music->config->max_upload = $music->pro_packages[$music->user->pro_type]['max_upload'];
            $music->config->user_max_upload = $music->pro_packages[$music->user->pro_type]['max_upload'];
            $music->config->pro_upload_limit = $music->pro_packages[$music->user->pro_type]['max_upload'];
        }
        if ($music->loggedin && !empty($music->user) && $music->user->admin > 0) {
            $music->config->max_upload = '10000000000';
            $music->config->user_max_upload = '10000000000';
        }
		$data = [];
		$file_location = "./xhr/{$path['options'][1]}.php";
		$option = (!empty($path['options'][2])) ? $path['options'][2] : '';
        
        if ($path['options'][1] != 'download_user_info' && $path['options'][1] != 'get-song-info' && $path['options'][1] != 'cashfree' && $path['options'][1] != 'paystack' && $path['options'][1] != 'paysera' && $path['options'][1] != 'iyzipay' && $path['options'][1] != 'fortumo' && $path['options'][1] != 'aamarpay' && $path['options'][1] != 'ngenius' && $path['options'][1] != 'coinbase' && $path['options'][1] != 'coinpayments' && $path['options'][1] != 'yoomoney' && $path['options'][1] != 'fluttewave') {
            if (empty($_REQUEST['hash_id'])) {
                header('Content-Type: application/json');
                echo json_encode(["error" => 'Invalid hash key']);
                exit();
            } else if ($_COOKIE['hash'] != $_REQUEST['hash_id']) {
                header('Content-Type: application/json');
                echo json_encode(["error" => 'Invalid hash key']);
                exit();
            }
        }
        if (!empty($_SERVER) && !empty($_SERVER['CONTENT_LENGTH']) && !empty(ini_get('post_max_size')) && ((int)ini_get('post_max_size')) > 0 && is_numeric($_SERVER['CONTENT_LENGTH'])) {
            if (return_bytes(ini_get('post_max_size')) < $_SERVER['CONTENT_LENGTH']) {
                $db->where('name', 'size_issue')->update(T_CONFIG, array('value' => "You have a server side issue, your server's max data size that can be sent to your server is ".ini_get('post_max_size').", max file size that can be uploaded to your server is ".ini_get('upload_max_filesize').", and max number of files that can be uploaded via a single request is ".ini_get('max_file_uploads').". Some users are trying to upload files more than ".formatBytes($_SERVER['CONTENT_LENGTH'])." to your server. To fix this issue please contact your server provider and increase post_max_size, upload_max_filesize and max_file_uploads."));
                header('Content-Type: application/json');
                echo json_encode(["error" => "You can't upload songs over ".ini_get('post_max_size')." due server side issue, please contact your server provider and increase post_max_size and upload_max_filesize."]);
                exit();
            }
        }
		if (file_exists($file_location)) {
			require_once $file_location;
			if (!empty($errors)) {
				$data = array(
			        'status' => 400,
			        'errors' => $errors
			    );
			}
		} else {
			$data = array(
		        'status' => 400,
		        'message' => "Endpoint not found"
		    );
		}
		header('Content-Type: application/json');
		echo json_encode($data);
		exit();
	}
}
if (!empty($_GET['ref']) && IS_LOGGED == false && !isset($_COOKIE['src'])) {
    $get_ip = get_ip_address();
    if (!isset($_SESSION['ref']) && !empty($get_ip)) {
        $_GET['ref'] = Secure($_GET['ref']);
        $ref_user_id = $db->where('username', $_GET['ref'])->getValue(T_USERS, 'id');
        $user_date = userData($ref_user_id);
        if (!empty($user_date)) {
            //if (ip_in_range($user_date->ip_address, '/24') === false && $user_date->ip_address != $get_ip) {
                $_SESSION['ref'] = $user_date->username;
            //}
        }
    }
}
if ($config['discover_land'] == 1 && IS_LOGGED == false && (empty($page) || $page == 'home')) {
    $page = 'discover';
}

$music->keyword = $music->config->keyword;
$file_location = "./sources/$page.php";
if (file_exists($file_location)) {
		require_once $file_location;
} else if (UsernameExits($page)) {
   require_once "./sources/user.php";
} else if (empty($page)) {
	require_once "./sources/home.php";
	$page = 'home';
} else if (empty($page)) {
	require_once "./sources/not-found.php";
	$page = 'not-found';
}

if (empty($music->site_content)) {
	require_once "./sources/not-found.php";
}



$seo = json_decode($music->config->seo,true);
if (in_array($page, array_keys($seo))) {
    $music->site_title       = str_replace('{SITE_TITLE}', $music->config->title, $seo[$page]['title']);
    $music->site_title = preg_replace_callback("/{LANG_KEY (.*?)}/", function($m) use ($lang_array) {
        return lang($m[1]);
    }, $music->site_title);
    $music->description = str_replace('{SITE_DESC}', $music->config->description, $seo[$page]['meta_description']);
    $music->keyword     = str_replace('{SITE_KEYWORDS}', $music->config->keyword, $seo[$page]['meta_keywords']);
}


$content_data = [
	'site_title' => $music->site_title,
    'site_desc' => htmlspecialchars(strip_tags($music->site_description)),
	'site_keyword' => $music->keyword,
    'site_content' => $music->site_content,
	'site_header' => '',
	'site_sidebar' => '',
	'site_player' => '',
	'site_loginForm' => loadPage('auth/login'),
	'site_signupForm' => loadPage('auth/signup'),
	'site_style' => loadPage('stylesheet/style'),
	'theme_url' => $config['theme_url'],
	'classes' => '',
    'FOOTER_AD' => ($music->site_pagename != 'login') ? GetAd('footer') : '',
];
if (( isset($_GET['invite']) && !empty($_GET['invite']) && !IsAdminInvitationExists( $_GET[ 'invite' ] ) && !IsUserInvitationExists( $_GET[ 'invite' ] ))) {
    $content_data['site_signupForm'] = '';
}

if ($music->site_pagename == 'forgot' || $music->site_pagename == 'reset') {
	$content_data['classes'] = "full_page";
}

if ($music->site_pagename == 'single_song') {
	$content_data['classes'] = "no-player";
}
if (!isset($_COOKIE['open_slide']) && !isMobile()) {
	$content_data['classes'] = " side_open";
}


if ($music->site_pagename != 'home') {
    $trend_search = $db->orderBy('hits', 'DESC')->get(T_SEARCHES, 10, array('id','keyword'));
	$header_data = ['site_search_bar' => loadPage('header/search-bar',$trend_search)];
	$content_data['site_header'] = (IS_LOGGED) ? loadPage('header/logged_head', $header_data) : loadPage('header/content', $header_data);
}

if ($music->site_pagename != 'forgot' && $music->site_pagename != 'reset' && $music->site_pagename != 'home') {
	$content_data['site_sidebar'] = loadPage('sidebar/content');
	$content_data['site_player'] = loadPage('player/content');
}


$maintenance_mode = false;
if ( $music->config->maintenance_mode == 'on' ) {
    if ( IS_LOGGED === false ) {
        $maintenance_mode = true;
        //http://localhost/quickdatescript.com/?access=admin
        if(isset($_GET['access']) && $_GET['access'] == 'admin'){
            $maintenance_mode = false;
            setcookie('maintenance_access','1', time() + 31556926, '/');
        }
        if (!empty($_COOKIE['maintenance_access']) && $_COOKIE['maintenance_access'] == 1) {
            $maintenance_mode = false;
        }
    } else {
        if ($music->user->admin === "0") {
            $maintenance_mode = true;
        }

    }

    if( $maintenance_mode === true ){
        $file_location = "./sources/maintenance.php";
        if (file_exists($file_location)) {
            require_once $file_location;
        }

    }
}
echo loadPage('container', $content_data);
runPlugin('AfterPageLoad');
exit();
