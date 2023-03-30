<?php
require_once "assets/includes/app.php";
use Twilio\Rest\Client;
function addhttp($url) {
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "http://" . $url;
    }
    return $url;
}
function GetThemes() {
    global $ask;
    $themes = glob('themes/*', GLOB_ONLYDIR);
    return $themes;
}
function isFuncEnabled($func) {
    return is_callable($func) && false === stripos(ini_get('disable_functions'), $func);
}
function Backup($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name, $tables = false, $backup_name = false) {
    $mysqli = new mysqli($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name);
    $mysqli->select_db($sql_db_name);
    $mysqli->query("SET NAMES 'utf8'");
    $queryTables = $mysqli->query('SHOW TABLES');
    while ($row = $queryTables->fetch_row()) {
        $target_tables[] = $row[0];
    }
    if ($tables !== false) {
        $target_tables = array_intersect($target_tables, $tables);
    }
    $content = "-- phpMyAdmin SQL Dump
-- http://www.phpmyadmin.net
--
-- Host Connection Info: " . $mysqli->host_info . "
-- Generation Time: " . date('F d, Y \a\t H:i A ( e )') . "
-- Server version: " . mysqli_get_server_info($mysqli) . "
-- PHP Version: " . PHP_VERSION . "
--\n
SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";
SET time_zone = \"+00:00\";\n
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;\n\n";
    foreach ($target_tables as $table) {
        $result        = $mysqli->query('SELECT * FROM ' . $table);
        $fields_amount = $result->field_count;
        $rows_num      = $mysqli->affected_rows;
        $res           = $mysqli->query('SHOW CREATE TABLE ' . $table);
        $TableMLine    = $res->fetch_row();
        $content       = (!isset($content) ? '' : $content) . "
-- ---------------------------------------------------------
--
-- Table structure for table : `{$table}`
--
-- ---------------------------------------------------------
\n" . $TableMLine[1] . ";\n";
        for ($i = 0, $st_counter = 0; $i < $fields_amount; $i++, $st_counter = 0) {
            while ($row = $result->fetch_row()) {
                if ($st_counter % 100 == 0 || $st_counter == 0) {
                    $content .= "\n--
-- Dumping data for table `{$table}`
--\n\nINSERT INTO " . $table . " VALUES";
                }
                $content .= "\n(";
                for ($j = 0; $j < $fields_amount; $j++) {
                    $row[$j] = str_replace("\n", "\\n", addslashes($row[$j]));
                    if (isset($row[$j])) {
                        $content .= '"' . $row[$j] . '"';
                    } else {
                        $content .= '""';
                    }
                    if ($j < ($fields_amount - 1)) {
                        $content .= ',';
                    }
                }
                $content .= ")";
                if ((($st_counter + 1) % 100 == 0 && $st_counter != 0) || $st_counter + 1 == $rows_num) {
                    $content .= ";\n";
                } else {
                    $content .= ",";
                }
                $st_counter = $st_counter + 1;
            }
        }
        $content .= "";
    }
    $content .= "
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;";
    if (!file_exists('script_backups/' . date('d-m-Y'))) {
        @mkdir('script_backups/' . date('d-m-Y'), 0777, true);
    }
    if (!file_exists('script_backups/' . date('d-m-Y') . '/' . time())) {
        mkdir('script_backups/' . date('d-m-Y') . '/' . time(), 0777, true);
    }
    if (!file_exists("script_backups/" . date('d-m-Y') . '/' . time() . "/index.html")) {
        $f = @fopen("script_backups/" . date('d-m-Y') . '/' . time() . "/index.html", "a+");
        @fwrite($f, "");
        @fclose($f);
    }
    if (!file_exists('script_backups/.htaccess')) {
        $f = @fopen("script_backups/.htaccess", "a+");
        @fwrite($f, "deny from all\nOptions -Indexes");
        @fclose($f);
    }
    if (!file_exists("script_backups/" . date('d-m-Y') . "/index.html")) {
        $f = @fopen("script_backups/" . date('d-m-Y') . "/index.html", "a+");
        @fwrite($f, "");
        @fclose($f);
    }
    if (!file_exists('script_backups/index.html')) {
        $f = @fopen("script_backups/index.html", "a+");
        @fwrite($f, "");
        @fclose($f);
    }
    $folder_name = "script_backups/" . date('d-m-Y') . '/' . time();
    $put         = @file_put_contents($folder_name . '/SQL-Backup-' . time() . '-' . date('d-m-Y') . '.sql', $content);
    if ($put) {
        $rootPath = realpath('./');
        $zip      = new ZipArchive();
        $open     = $zip->open($folder_name . '/Files-Backup-' . time() . '-' . date('d-m-Y') . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($open !== true) {
            return false;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $name => $file) {
            if (!preg_match('/\bscript_backups\b/', $file)) {
                if (!$file->isDir()) {
                    $filePath     = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($rootPath) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
        $zip->close();
        $table = T_CONFIG;
        $date  = date('d-m-Y');
        $mysqli->query("UPDATE `$table` SET `value` = '$date' WHERE `name` = 'last_backup'");
        $mysqli->close();
        return true;
    } else {
        return false;
    }
}

function isPluginEnabled($plugin) {
    global $db, $music;
    if (empty($plugin)) {
        return false;
    }
    $getPlugin = $db->where('name', secure($plugin))->getOne("plugins");
    if (empty($getPlugin)) {
        return false;
    }
    if ($getPlugin->active == 1) {
        return true;
    }
    return false;
}

function custom_design($a = false,$code = array()){
    global $music;
    $theme       = $music->config->theme;
    $data        = array();
    $custom_code = array(
        "themes/$theme/js/header.js",
        "themes/$theme/js/footer.js",
        "themes/$theme/css/custom.style.css",
    );

    if ($a == 'get') {
        foreach ($custom_code as $key => $filepath) {
            if (is_readable($filepath)) {
                $data[$key] = file_get_contents($filepath);
            }
            else{
                $data[$key] = "/* \n Error found while loading: Permission denied in $filepath \n*/";
            }
        }
    }

    else if($a == 'save' && !empty($code)){
        foreach ($code as $key => $content) {
            $filepath = $custom_code[$key];

            if (is_writable($filepath)) {
                @file_put_contents($custom_code[$key],$content);
            }

            else{
                $data[$key] = "Permission denied: $filepath is not writable";
            }
        }
    }

    return $data;
}
function GetRecommendedSongs(){
    global $music,$db;
    $interests = [];
    $data = [];
    if (IS_LOGGED === false) {
        $category_interests = $db->arrayBuilder()->get(T_USER_INTEREST,5,array('category_id'));
    }
    else{
        $category_interests = $db->arrayBuilder()->where('user_id',$music->user->id)->get(T_USER_INTEREST,null,array('category_id'));
        if (empty($category_interests)) {
            $category_interests = $db->arrayBuilder()->get(T_USER_INTEREST,5,array('category_id'));
        }
    }
    if (!empty($category_interests)) {
        foreach ($category_interests as $key => $value){
            $interests[$value['category_id']] = (int)$value['category_id'];
        }
        if (!empty($interests)) {
            $recommended = $db->arrayBuilder()->where('availability', 0)->where('category_id',array_keys($interests),'IN')->orderBy('id','DESC')->get(T_SONGS,10,array('id'));
            foreach ($recommended as $key => $value){
                $song = songData( (int)$value['id'] );
                if (!empty($song)) {
                    $data[$key] = songData( (int)$value['id'] );
                }
            }
        }
    }
    return $data;
}
function checkUserInterest(){
    global $music,$db;
    $category_interests = $db->arrayBuilder()->where('user_id',$music->user->id)->get(T_USER_INTEREST,null,array('category_id'));
    if( empty($category_interests) ){
        return false;
    }else{
        return true;
    }
}
function getUserInterest(){
    global $music,$db;
    $interests = [];
    $category_interests = $db->arrayBuilder()->where('user_id',$music->user->id)->get(T_USER_INTEREST,null,array('category_id'));
    if( empty($category_interests) ){
        return [];
    }else{
        foreach ($category_interests as $key => $value){
            $interests[$value['category_id']] = (int)$value['category_id'];
        }
        return $interests;
    }
}
function ImportImageFromFile($media, $custom_name = '_url_image') {
    if (empty($media)) {
        return false;
    }
    if (!file_exists('upload/photos/' . date('Y'))) {
        mkdir('upload/photos/' . date('Y'), 0777, true);
    }
    if (!file_exists('upload/photos/' . date('Y') . '/' . date('m'))) {
        mkdir('upload/photos/' . date('Y') . '/' . date('m'), 0777, true);
    }
    $extension = 0; //image_type_to_extension($size[2]);
    if (empty($extension)) {
        $extension = '.jpg';
    }
    $dir               = 'upload/photos/' . date('Y') . '/' . date('m');
    $file_dir          = $dir . '/' . GenerateKey() . $custom_name . $extension;
    $fileget           = file_get_contents($media);
    if (!empty($fileget)) {
        $importImage = @file_put_contents($file_dir, $fileget);
    }
    if (file_exists($file_dir)) {
        $upload_s3 = ShareFile($file_dir);
        $check_image = getimagesize($file_dir);
        if (!$check_image) {
            unlink($file_dir);
        }
        return $file_dir;
    } else {
        return false;
    }
}
function GetBanned($type = '') {
    global $sqlConnect;
    $data  = array();
    $query = mysqli_query($sqlConnect, "SELECT * FROM " . T_BANNED_IPS . " ORDER BY id DESC");
    if ($type == 'user') {
        while ($fetched_data = mysqli_fetch_assoc($query)) {
            if (filter_var($fetched_data['ip_address'], FILTER_VALIDATE_IP)) {
                $data[] = $fetched_data['ip_address'];
            }
        }
    } else {
        while ($fetched_data = mysqli_fetch_assoc($query)) {
            $data[] = $fetched_data;
        }
    }
    return $data;
}
function get_announcments() {
    global $music, $db;
    if (IS_LOGGED === false) {
        return false;
    }

    $views_table  = T_ANNOUNCEMENT_VIEWS;
    $table        = T_ANNOUNCEMENTS;
    $user         = $music->user->id;
    $subsql       = "SELECT `announcement_id` FROM `$views_table` WHERE `user_id` = '{$user}'";
    $fetched_data = $db->where(" `active` = '1' AND `id` NOT IN ({$subsql}) ")->orderBy('RAND()')->getOne(T_ANNOUNCEMENTS);
    return $fetched_data;
}
function UploadLogo($data = array()) {
    global $music,$db;
    if (isset($data['file']) && !empty($data['file'])) {
        $data['file'] = Secure($data['file']);
    }
    if (isset($data['name']) && !empty($data['name'])) {
        $data['name'] = Secure($data['name']);
    }
    if (isset($data['name']) && !empty($data['name'])) {
        $data['name'] = Secure($data['name']);
    }
    if (empty($data)) {
        return false;
    }
    $allowed           = 'png';
    $new_string        = pathinfo($data['name'], PATHINFO_FILENAME) . '.' . strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
    $extension_allowed = explode(',', $allowed);
    $file_extension    = pathinfo($new_string, PATHINFO_EXTENSION);
    if (!in_array($file_extension, $extension_allowed)) {
        return false;
    }
    $logo_name = 'logo';
    if (!empty($data['light-logo'])) {
        $logo_name = 'logo-white';
    }
    if (!empty($data['favicon'])) {
        $logo_name = 'icon';
    }
    if (!empty($data['homelogo'])) {
        $logo_name = 'home-logo';
    }
    if ($logo_name == 'logo-white' || $logo_name == 'home-logo' || $logo_name == 'logo') {
        $db->where('name', 'logo_cache')->update(T_CONFIG, array('value' => rand(100,999)));
    }
    $dir      = "themes/" . $music->config->theme . "/img/";
    $filename = $dir . "$logo_name.png";
    if (move_uploaded_file($data['file'], $filename)) {
        return true;
    }
}
function LoadAdminLinkSettings($link = '') {
    global $site_url;
    return $site_url . '/admin-cp/' . $link;
}
function LoadAdminLink($link = '') {
    global $site_url;
    return $site_url . '/admin-panel/' . $link;
}
function LoadAdminPage($page_url = '', $data = array(), $set_lang = true) {
    global $music, $lang_array, $config, $db;
    $page = './admin-panel/pages/' . $page_url . '.html';

    if (!file_exists($page)) {
        return false;
    }
    $page_content = '';
    ob_start();
    require($page);
    $page_content = ob_get_contents();
    ob_end_clean();
    if ($set_lang == true) {
        $page_content = preg_replace_callback("/{{LANG (.*?)}}/", function($m) use ($lang_array) {
            return lang($m[1]);
        }, $page_content);
    }

    if (!empty($data) && is_array($data)) {
        foreach ($data as $key => $replace) {
            if ($key == 'USER_DATA') {
                $replace = ToArray($replace);
                $page_content = preg_replace_callback("/{{USER (.*?)}}/", function($m) use ($replace) {
                    return (isset($replace[$m[1]])) ? $replace[$m[1]] : '';
                }, $page_content);
            } else {
                if( is_array($replace) || is_object($replace) ){
                    $arr = explode('_',$key);
                    $k = strtoupper($arr[0]);
                    $replace = ToArray($replace);
                    $page_content = preg_replace_callback("/{{".$k." (.*?)}}/", function($m) use ($replace) {
                        return (isset($replace[$m[1]])) ? $replace[$m[1]] : '';
                    }, $page_content);
                }else{
                    $object_to_replace = "{{" . $key . "}}";
                    $page_content      = str_replace($object_to_replace, $replace, $page_content);
                }
            }
        }
    }
    if (IS_LOGGED == true) {
        $replace = ToArray($music->user);
        $page_content = preg_replace_callback("/{{ME (.*?)}}/", function($m) use ($replace) {
            return (isset($replace[$m[1]])) ? $replace[$m[1]] : '';
        }, $page_content);
    }
    $page_content = preg_replace("/{{LINK (.*?)}}/", UrlLink("$1"), $page_content);
    $page_content = preg_replace_callback("/{{CONFIG (.*?)}}/", function($m) use ($config) {
        return (isset($config[$m[1]])) ? $config[$m[1]] : '';
    }, $page_content);
    return $page_content;
}
function UrlLink($string) {
    global $site_url;
    return rtrim($site_url ,'/') . str_replace('//','/','/' . $string);
}
function UpdateAdminDetails() {
    global $music, $db;

    $get_songs_count = $db->getValue(T_SONGS, 'count(*)');
    $update_questions_count = $db->where('name', 'total_songs')->update(T_CONFIG, array('value' => $get_songs_count));

    $get_albums_count = $db->getValue(T_ALBUMS, 'count(*)');
    $update_albums_count = $db->where('name', 'total_albums')->update(T_CONFIG, array('value' => $get_albums_count));

    $get_plays_count = $db->getValue(T_VIEWS, 'count(*)');
    $update_albums_count = $db->where('name', 'total_plays')->update(T_CONFIG, array('value' => $get_plays_count));

    $get_sales_count = number_format($db->getValue(T_PURCHAES, 'SUM(final_price)'));
    $update_sales_count = $db->where('name', 'total_sales')->update(T_CONFIG, array('value' => $get_sales_count));

    $get_users_count = $db->getValue(T_USERS, 'count(*)');
    $update_users_count = $db->where('name', 'total_users')->update(T_CONFIG, array('value' => $get_users_count));

    $get_artists_count = $db->where('artist', '1')->getValue(T_USERS, 'count(*)');
    $update_artists_count = $db->where('name', 'total_artists')->update(T_CONFIG, array('value' => $get_artists_count));

    $get_playlists_count = $db->getValue(T_PLAYLISTS, 'count(*)');
    $update_playlists_count = $db->where('name', 'total_playlists')->update(T_CONFIG, array('value' => $get_playlists_count));

    $get_unactive_users_count = $db->where('active', '0')->getValue(T_USERS, 'count(*)');
    $update_unactive_users_count = $db->where('name', 'total_unactive_users')->update(T_CONFIG, array('value' => $get_unactive_users_count));

    $user_statics = array();
    $songs_statics = array();

    $months = array('1','2','3','4','5','6','7','8','9','10','11','12');
    $date = date('Y');

    foreach ($months as $value) {
        $monthNum  = $value;
        $dateObj   = DateTime::createFromFormat('!m', $monthNum);
        $monthName = $dateObj->format('F');
        $user_statics[] = array('month' => $monthName, 'new_users' => $db->where('registered', "$date/$value")->getValue(T_USERS, 'count(*)'));
        $songs_statics[] = array('month' => $monthName, 'new_songs' => $db->where('YEAR(FROM_UNIXTIME(`time`))', "$date")->where('MONTH(FROM_UNIXTIME(`time`))', "$value")->getValue(T_SONGS, 'count(*)'));
    }
    $update_user_statics = $db->where('name', 'user_statics')->update(T_CONFIG, array('value' => json_encode($user_statics)));
    $update_songs_statics = $db->where('name', 'songs_statics')->update(T_CONFIG, array('value' => json_encode($songs_statics)));

    $update_saved_count = $db->where('name', 'last_admin_collection')->update(T_CONFIG, array('value' => time()));
}
function getConfig() {
	global $db;
    $data  = array();
    $configs = $db->get(T_CONFIG);
    foreach ($configs as $key => $config) {
        $data[$config->name] = $config->value;
    }
    return $data;
}
function lang($string = '') {
	global $lang_array, $music, $db;
    $dev = true;
    $string = trim($string);
	$stringFromArray = strtolower(preg_replace('/[^a-zA-Z0-9-_\.]/','_', $string));
	if (in_array($stringFromArray, array_keys($lang_array))) {
		return $lang_array[$stringFromArray];
	}
    if ($dev == true) {
       //$insert = $db->insert(T_LANGS, ['ref' => '', 'options' => '' , 'lang_key' => $stringFromArray, 'english' => secure($string)]);
    } else {
        return '';
    }
	$lang_array[$stringFromArray] = $string;
	return $string;
}
function userData($user_id = 0, $options = array()) {
    global $db, $music, $lang, $countries_name;
    if (!empty($options['data'])) {
        $fetched_data   = $user_id;
    }

    else {
        $fetched_data   = $db->where('id', $user_id)->getOne(T_USERS);
    }

    if (empty($fetched_data)) {
        return false;
    }

    if (empty($fetched_data->name)) {
        $fetched_data->name   = $fetched_data->username;
    }
    $fetched_data->or_avatar = $fetched_data->avatar;
    $fetched_data->or_cover = $fetched_data->cover;

    $fetched_data->avatar = getMedia($fetched_data->avatar);
    $fetched_data->cover  = getMedia($fetched_data->cover);
    $fetched_data->url    = getLink($fetched_data->username);
    $fetched_data->about_decoded = br2nl($fetched_data->about);

    if (!empty($fetched_data->name)) {
        $fetched_data->name = $fetched_data->name;
    }

    if (empty($fetched_data->about)) {
        $fetched_data->about = '';
    }
    $fetched_data->org_wallet  = $fetched_data->wallet;
    $fetched_data->wallet_format  = number_format($fetched_data->wallet);
    $fetched_data->or_balance  = $fetched_data->balance;
    $fetched_data->balance  = number_format($fetched_data->balance);
    $fetched_data->name_v   = $fetched_data->name;
    if ($fetched_data->verified == 1 && $music->config->verification_badge == 'on') {
        $fetched_data->name_v = $fetched_data->name . ' <svg xmlns="http://www.w3.org/2000/svg" width="35.271" height="34.055" viewBox="0 0 35.271 34.055"><g transform="translate(-867.5 -1775)" class="verified_ico" data-original-title="" title=""><g transform="translate(867.5 1775)" fill="var(--main-color)"><path d="M 22.03647232055664 33.35497283935547 C 21.90776252746582 33.35497283935547 21.77789306640625 33.34488296508789 21.65046310424805 33.32497406005859 L 18.17597198486328 32.78217315673828 C 17.99781227111816 32.75434112548828 17.8160514831543 32.740234375 17.6357421875 32.740234375 C 17.4554328918457 32.740234375 17.27367210388184 32.75434112548828 17.09552192687988 32.78217315673828 L 13.62102222442627 33.32497406005859 C 13.49364185333252 33.34487152099609 13.36376190185547 33.35496139526367 13.2350025177002 33.35496139526367 C 12.57409191131592 33.35498428344727 11.9277925491333 33.08604431152344 11.46182250976562 32.61711120605469 L 8.95156192779541 30.09098243713379 C 8.698522567749023 29.83634185791016 8.408892631530762 29.62286186218262 8.09072208404541 29.45648193359375 L 4.93339204788208 27.80550193786621 C 4.237522125244141 27.4416332244873 3.747802257537842 26.76151275634766 3.623402118682861 25.98616218566895 L 3.047582149505615 22.39740180969238 C 2.991642236709595 22.04881286621094 2.883692264556885 21.71174240112305 2.726702213287354 21.39554214477539 L 1.110132217407227 18.1392822265625 C 0.7645421624183655 17.44319343566895 0.7645421624183655 16.61202239990234 1.110132217407227 15.91593265533447 L 2.726712226867676 12.65968227386475 C 2.883682250976562 12.34350299835205 2.991642236709595 12.00643253326416 3.047582149505615 11.65783309936523 L 3.623412132263184 8.069052696228027 C 3.747812271118164 7.293712615966797 4.237522125244141 6.613582611083984 4.93339204788208 6.249712467193604 L 8.09072208404541 4.598742485046387 C 8.408872604370117 4.432382583618164 8.698502540588379 4.218912601470947 8.951572418212891 3.964242696762085 L 11.46183204650879 1.438102722167969 C 11.9277925491333 0.9691926836967468 12.57409191131592 0.7002527117729187 13.23501205444336 0.7002527117729187 C 13.3637523651123 0.7002527117729187 13.49363231658936 0.7103427052497864 13.62103176116943 0.7302526831626892 L 17.09552192687988 1.27305269241333 C 17.27367210388184 1.300882697105408 17.45544242858887 1.315002679824829 17.63576126098633 1.315002679824829 C 17.81607246398926 1.315002679824829 17.99784278869629 1.300882697105408 18.17599296569824 1.27305269241333 L 21.65046310424805 0.7302626967430115 C 21.77786254882812 0.7103526592254639 21.90774154663086 0.7002626657485962 22.03649139404297 0.7002626657485962 C 22.69741249084473 0.7002626657485962 23.34370231628418 0.9692026972770691 23.80966186523438 1.438112735748291 L 26.31992149353027 3.964242696762085 C 26.57298278808594 4.218912601470947 26.86262130737305 4.432392597198486 27.18077278137207 4.598752498626709 L 30.33809280395508 6.249722480773926 C 31.03396224975586 6.613592624664307 31.52367210388184 7.293722629547119 31.64809226989746 8.069072723388672 L 32.22390365600586 11.65782260894775 C 32.27983093261719 12.00640296936035 32.38779067993164 12.34348297119141 32.54477310180664 12.65969276428223 L 34.16136169433594 15.91594314575195 C 34.50694274902344 16.61203193664551 34.50694274902344 17.44320297241211 34.16136169433594 18.13930320739746 L 32.54477310180664 21.39554214477539 C 32.38779067993164 21.71173286437988 32.27984237670898 22.04881286621094 32.22390365600586 22.39741325378418 L 31.6480827331543 25.98616218566895 C 31.523681640625 26.76151275634766 31.03397178649902 27.4416332244873 30.33810234069824 27.80550193786621 L 27.18077278137207 29.45648193359375 C 26.86262130737305 29.62284278869629 26.5729923248291 29.83631324768066 26.31992149353027 30.09098243713379 L 23.80966186523438 32.61712265014648 C 23.34372138977051 33.08601379394531 22.69741249084473 33.35494232177734 22.03647232055664 33.35497283935547 Z" stroke="none"></path><path d="M 13.23503494262695 1.200252532958984 L 13.23505210876465 1.200252532958984 C 12.70630264282227 1.200271606445312 12.18925285339355 1.415424346923828 11.81649208068848 1.790531158447266 L 9.306222915649414 4.316692352294922 C 9.016992568969727 4.607732772827148 8.685991287231445 4.851701736450195 8.322402954101562 5.04182243347168 L 5.165082931518555 6.692792892456055 C 4.608392715454102 6.983892440795898 4.216611862182617 7.527992248535156 4.117092132568359 8.148262023925781 L 3.54127311706543 11.73703193664551 C 3.477352142333984 12.13538360595703 3.353971481323242 12.5206127166748 3.174552917480469 12.88201332092285 L 1.557971954345703 16.13827323913574 C 1.281513214111328 16.69514274597168 1.281501770019531 17.36007308959961 1.557960510253906 17.91695213317871 L 3.174552917480469 21.17320251464844 C 3.353961944580078 21.53457260131836 3.47734260559082 21.91980361938477 3.541261672973633 22.31819152832031 L 4.117082595825195 25.90695190429688 C 4.216602325439453 26.5272216796875 4.608381271362305 27.07132339477539 5.165082931518555 27.36242294311523 L 8.322412490844727 29.01340293884277 C 8.686031341552734 29.20354270935059 9.01704216003418 29.44752311706543 9.306222915649414 29.7385425567627 L 11.81648254394531 32.26468276977539 C 12.18926239013672 32.63981246948242 12.7062931060791 32.85496139526367 13.23501205444336 32.85496139526367 C 13.33802223205566 32.85496139526367 13.44193267822266 32.84689331054688 13.54385185241699 32.83096313476562 L 17.01831245422363 32.28817367553711 C 17.42548370361328 32.22455596923828 17.84599304199219 32.22455978393555 18.25314140319824 32.28816223144531 L 21.72764205932617 32.83097076416016 C 21.82959175109863 32.84689331054688 21.93350219726562 32.85497283935547 22.0364818572998 32.85497283935547 C 22.56519317626953 32.85497283935547 23.08222198486328 32.63982391357422 23.45500183105469 32.26469421386719 L 25.96524238586426 29.73856353759766 C 26.25445175170898 29.44752311706543 26.58546257019043 29.20353317260742 26.94908142089844 29.01340293884277 L 30.10640335083008 27.36242294311523 C 30.66310119628906 27.07132339477539 31.05487251281738 26.5272216796875 31.1544017791748 25.90695190429688 L 31.73022270202637 22.31818389892578 C 31.79414176940918 21.91979217529297 31.91752243041992 21.5345630645752 32.09693145751953 21.17321395874023 L 33.7135124206543 17.91695213317871 C 33.98997116088867 17.36008262634277 33.98997116088867 16.69515228271484 33.7135124206543 16.13827323913574 L 32.09693145751953 12.88202285766602 C 31.91752243041992 12.52064323425293 31.79414176940918 12.13541221618652 31.73022270202637 11.73703193664551 L 31.1544017791748 8.148283004760742 C 31.05487251281738 7.52800178527832 30.66310119628906 6.983901977539062 30.10640335083008 6.692802429199219 L 26.94909286499023 5.041831970214844 C 26.58548355102539 4.851713180541992 26.25447082519531 4.607732772827148 25.96526336669922 4.316682815551758 L 23.45500183105469 1.790542602539062 C 23.08222198486328 1.415412902832031 22.56519317626953 1.200263977050781 22.03647232055664 1.200263977050781 C 21.93346214294434 1.200263977050781 21.82955169677734 1.208332061767578 21.72763252258301 1.224262237548828 L 18.25318145751953 1.767051696777344 C 17.84601402282715 1.830669403076172 17.42550086975098 1.830665588378906 17.01835250854492 1.767063140869141 L 13.54385185241699 1.224250793457031 C 13.44190788269043 1.208332061767578 13.33800888061523 1.200252532958984 13.23503494262695 1.200252532958984 M 13.23500823974609 0.20025634765625 C 13.38894653320312 0.2002487182617188 13.54370498657227 0.2121047973632812 13.69821166992188 0.2362442016601562 L 17.17270278930664 0.7790412902832031 C 17.47954177856445 0.8269844055175781 17.79197311401367 0.8269844055175781 18.09881210327148 0.7790412902832031 L 21.57328224182129 0.2362442016601562 C 22.52317237854004 0.087860107421875 23.48665237426758 0.4037055969238281 24.16433334350586 1.085674285888672 L 26.67459106445312 3.61180305480957 C 26.89123153686523 3.829822540283203 27.14009094238281 4.013252258300781 27.41245269775391 4.155662536621094 L 30.56978225708008 5.806642532348633 C 31.40900230407715 6.245471954345703 31.99173164367676 7.054792404174805 32.14177322387695 7.989852905273438 L 32.71759033203125 11.57861328125 C 32.76548385620117 11.87708282470703 32.85820388793945 12.16659355163574 32.99262237548828 12.43735313415527 L 34.60920333862305 15.693603515625 C 35.02643203735352 16.53401184082031 35.02643203735352 17.5212230682373 34.60920333862305 18.36163330078125 L 32.99262237548828 21.61788177490234 C 32.85820388793945 21.88863372802734 32.76548385620117 22.17815399169922 32.71759033203125 22.47661209106445 L 32.14177322387695 26.06537246704102 C 31.99173164367676 27.00044250488281 31.40900230407715 27.80975341796875 30.56978225708008 28.24858283996582 L 27.41246223449707 29.89956283569336 C 27.14009094238281 30.04198265075684 26.89123153686523 30.22540283203125 26.67459106445312 30.44342231750488 L 24.16433334350586 32.96956253051758 C 23.48665237426758 33.65152359008789 22.52317428588867 33.96738433837891 21.57328224182129 33.8189811706543 L 18.09879302978516 33.27618408203125 C 17.79195213317871 33.22825241088867 17.47953414916992 33.22825241088867 17.17268180847168 33.27618408203125 L 13.69820213317871 33.8189811706543 C 12.74828910827637 33.96737289428711 11.78483009338379 33.65152359008789 11.10715293884277 32.96955108642578 L 8.596891403198242 30.44342231750488 C 8.380252838134766 30.22540283203125 8.131391525268555 30.04198265075684 7.859031677246094 29.89956283569336 L 4.701702117919922 28.24858283996582 C 3.862482070922852 27.80975341796875 3.279741287231445 27.00043296813965 3.129711151123047 26.06537246704102 L 2.55389404296875 22.47661209106445 C 2.506000518798828 22.17814254760742 2.413280487060547 21.88863372802734 2.278861999511719 21.61788177490234 L 0.6622734069824219 18.36162185668945 C 0.2450523376464844 17.52121353149414 0.2450523376464844 16.53400230407715 0.6622810363769531 15.6935920715332 L 2.278861999511719 12.43734359741211 C 2.413280487060547 12.16658210754395 2.506011962890625 11.87707328796387 2.553901672363281 11.5786018371582 L 3.129722595214844 7.989843368530273 C 3.279752731323242 7.054782867431641 3.862491607666016 6.245462417602539 4.701702117919922 5.806632995605469 L 7.859031677246094 4.155662536621094 C 8.131391525268555 4.013242721557617 8.380252838134766 3.829813003540039 8.596902847290039 3.61180305480957 L 11.10716247558594 1.085662841796875 C 11.67461204528809 0.5146293640136719 12.44255638122559 0.2002830505371094 13.23500823974609 0.20025634765625 Z" stroke="none" fill="var(--second-color)"></path></g><path d="M3759.753,7404.812l8.833-8.833,1.365,1.355-10.2,10.2-6.114-6.111,1.357-1.365Z" transform="translate(-2876.66 -5609.132)" fill="#fff"></path></g></svg>';
    }
    $fetched_data->country_name = '';
    if (!empty($fetched_data->country_id) && !empty($countries_name) && in_array($fetched_data->country_id, array_keys($countries_name))) {
        $fetched_data->country_name  = $countries_name[$fetched_data->country_id];
    }
    $fetched_data->is_following = false;
    if ($music->loggedin && !empty($music->user)) {
        if ($fetched_data->id != $music->user->id) {
            $fetched_data->is_following = isFollowing($fetched_data->id);
        }
    }
    if (!empty($fetched_data->email_privacy)) {
        $fetched_data->email_privacy = json_decode($fetched_data->email_privacy);
        foreach ($fetched_data->email_privacy as $key => $value) {
            $fetched_data->{$key} = $value;
        }
    }

    @$fetched_data->gender_text  = ($fetched_data->gender == 'male') ? $lang->male : $lang->female;
    return $fetched_data;
}
function isUserActive($user_id = 0) {
    global $db;
    $db->where('active', '1');
    $db->where('id', secure($user_id));
    return ($db->getValue(T_USERS, 'count(*)') > 0) ? true : false;
}
function EmailExists($email = '') {
    global $db;
    return ($db->where('email', secure($email))->getValue(T_USERS, 'count(*)') > 0) ? true : false;
}
function UsernameExits($username = '') {
    global $db;
    return ($db->where('username', secure($username))->getValue(T_USERS, 'count(*)') > 0) ? true : false;
}
function createUserSession($user_id = 0,$platform = 'web') {
    global $db,$sqlConnect, $music;
    if (empty($user_id)) {
        return false;
    }
    $session_id          = sha1(rand(11111, 99999)) . time() . md5(microtime() . $user_id);
    $insert_data         = array(
        'user_id' => $user_id,
        'session_id' => $session_id,
        'platform' => $platform,
        'time' => time()
    );

    $insert              = $db->insert(T_SESSIONS, $insert_data);

    $_SESSION['user_id'] = $session_id;
    if ($music->config->remember_device == 1 && !empty($_POST['remember_device']) && $_POST['remember_device'] == 'on') {
        setcookie("user_id", $session_id, time() + (10 * 365 * 24 * 60 * 60), "/");
    }
    $music->loggedin = true;

    $query_two = mysqli_query($sqlConnect, "DELETE FROM " . T_APP_SESSIONS . " WHERE `session_id` = '{$session_id}'");
    if ($query_two) {
        $ua = serialize(getBrowser());
        $delete_same_session = $db->where('user_id', $user_id)->where('platform_details', $ua)->delete(T_APP_SESSIONS);
        $query_three = mysqli_query($sqlConnect, "INSERT INTO " . T_APP_SESSIONS . " (`user_id`, `session_id`, `platform`, `platform_details`, `time`) VALUES('{$user_id}', '{$session_id}', 'web', '$ua'," . time() . ")");
        if ($query_three) {
            return $session_id;
        }
    }
}
function getBrowser() {
    $u_agent = (!empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '') ;
    $bname = 'Unknown';
    $platform = 'Unknown';
    $version= "";
    // First get the platform?
    if (preg_match('/macintosh|mac os x/i', $u_agent)) {
        $platform = 'mac';
    } elseif (preg_match('/windows|win32/i', $u_agent)) {
        $platform = 'windows';
    } elseif (preg_match('/iphone|IPhone/i', $u_agent)) {
        $platform = 'IPhone Web';
    } elseif (preg_match('/android|Android/i', $u_agent)) {
        $platform = 'Android Web';
    } else if (preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $u_agent)) {
        $platform = 'Mobile';
    } else if (preg_match('/linux/i', $u_agent)) {
        $platform = 'linux';
    }
    // Next get the name of the useragent yes seperately and for good reason
    $ub = 'Chrome';
    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) {
        $bname = 'Internet Explorer';
        $ub = "MSIE";
    } elseif(preg_match('/Firefox/i',$u_agent)) {
        $bname = 'Mozilla Firefox';
        $ub = "Firefox";
    } elseif(preg_match('/Chrome/i',$u_agent)) {
        $bname = 'Google Chrome';
        $ub = "Chrome";
    } elseif(preg_match('/Safari/i',$u_agent)) {
        $bname = 'Apple Safari';
        $ub = "Safari";
    } elseif(preg_match('/Opera/i',$u_agent)) {
        $bname = 'Opera';
        $ub = "Opera";
    } elseif(preg_match('/Netscape/i',$u_agent)) {
        $bname = 'Netscape';
        $ub = "Netscape";
    }
    // finally get the correct version number
    $known = array('Version', $ub, 'other');
    $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
    if (!preg_match_all($pattern, $u_agent, $matches)) {
        // we have no matching number just continue
    }
    // see how many we have
    $i = count($matches['browser']);
    if ($i != 1) {
        //we will have two since we are not using 'other' argument yet
        //see if version is before or after the name
        if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
            $version= $matches['version'][0];
        } else {
            $version= (!empty($matches['version']) && !empty($matches['version'][1]) ?  $matches['version'][1] : '');
        }
    } else {
        $version= $matches['version'][0];
    }
    // check if we have a number
    if ($version==null || $version=="") {$version="?";}
    return array(
        'userAgent' => $u_agent,
        'name'      => $bname,
        'version'   => $version,
        'platform'  => $platform,
        'pattern'    => $pattern,
        'ip_address' => get_ip_address()
    );
}
function sendMessage($data = array()) {
    global $music, $mail;
    require_once './assets/includes/mail.php';
    $email_from      = $data['from_email'] = secure($data['from_email']);
    $to_email        = $data['to_email'] = secure($data['to_email']);
    $subject         = $data['subject'];
    $data['charSet'] = secure($data['charSet']);

    try {
        if (!empty($data["return"]) && $data["return"] == 'debug') {
            $mail->SMTPDebug = 2;
        }
        
        if ($music->config->smtp_or_mail == 'mail') {
            $mail->IsMail();
        }

        else if ($music->config->smtp_or_mail == 'smtp') {
            $mail->isSMTP();
            $mail->Host        = $music->config->smtp_host;
            $mail->SMTPAuth    = true;
            $mail->Username    = $music->config->smtp_username;
            $mail->Password    = html_entity_decode($music->config->smtp_password);
            $mail->SMTPSecure  = $music->config->smtp_encryption;
            $mail->Port        = $music->config->smtp_port;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        }

        else {
            return false;
        }

        $mail->IsHTML($data['is_html']);
        $mail->setFrom($data['from_email'], $data['from_name']);
        $mail->addAddress($data['to_email'], $data['to_name']);
        $mail->Subject = $data['subject'];
        $mail->CharSet = $data['charSet'];
        if (!empty($data['reply-to'])) {
            $mail->addReplyTo($data['reply-to'], $data['from_name']);
        }
        $mail->MsgHTML($data['message_body']);
        if ($mail->send()) {
            $mail->ClearAddresses();
            return true;
        }
        else{
            if (!empty($data["return"])) {
                return $mail->ErrorInfo;
            }
        }
        
    } catch (Exception $e) {
        if (!empty($data["return"])) {
            if (!empty($e->getMessage())) {
                return $e->getMessage();
            }
            return $mail->ErrorInfo;
        }
        return false;
    } catch (phpmailerException $e) {
        if (!empty($data["return"])) {
            if (!empty($e->errorMessage())) {
                return $e->errorMessage();
            }
            return $mail->ErrorInfo;
        }
        return false;
    }

        
}
function createMainSession() {
    $hash = sha1(rand(1111, 9999));
    if (!empty($_COOKIE['hash'])) {
        return $_COOKIE['hash'];
    }
    setcookie("hash", $hash, 0, "/");
    return $hash;
}
function importImageFromLogin($media) {
    global $music;
    if (!file_exists('upload/photos/' . date('Y'))) {
        mkdir('upload/photos/' . date('Y'), 0777, true);
    }
    if (!file_exists('upload/photos/' . date('Y') . '/' . date('m'))) {
        mkdir('upload/photos/' . date('Y') . '/' . date('m'), 0777, true);
    }
    $dir               = 'upload/photos/' . date('Y') . '/' . date('m');
    $file_dir          = $dir . '/' . generateKey() . '_avatar.jpg';
    $getImage          = connect_to_url($media);
    if (!empty($getImage)) {
        $importImage = file_put_contents($file_dir, $getImage);
        if ($importImage) {
            resize_Crop_Image(400, 400, $file_dir, $file_dir, 100);
        }
    }
    if (file_exists($file_dir)) {
        if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == 'on' || $music->config->google_drive == 'on' || $music->config->spaces == 'on' || $music->config->wasabi_storage == 'on' || $music->config->backblaze_storage == 'on') {
            PT_UploadToS3($file_dir);
        }
        return $file_dir;
    } else {
        return $music->user_default_avatar;
    }
}
function shareFile($data = array(), $type = 0) {
    global $music, $mysqli;
    $allowed = '';

    runPlugin('PreFileUpload', $data);

    if (!file_exists('upload/photos/' . date('Y'))) {
        @mkdir('upload/photos/' . date('Y'), 0777, true);
    }
    if (!file_exists('upload/photos/' . date('Y') . '/' . date('m'))) {
        @mkdir('upload/photos/' . date('Y') . '/' . date('m'), 0777, true);
    }
    if (!file_exists('upload/audio/' . date('Y'))) {
        @mkdir('upload/audio/' . date('Y'), 0777, true);
    }
    if (!file_exists('upload/audio/' . date('Y') . '/' . date('m'))) {
        @mkdir('upload/audio/' . date('Y') . '/' . date('m'), 0777, true);
    }
    if (!file_exists('upload/waves/' . date('Y'))) {
        @mkdir('upload/waves/' . date('Y'), 0777, true);
    }
    if (!file_exists('upload/waves/' . date('Y') . '/' . date('m'))) {
        @mkdir('upload/waves/' . date('Y') . '/' . date('m'), 0777, true);
    }
    if (!file_exists('upload/videos/' . date('Y'))) {
        @mkdir('upload/videos/' . date('Y'), 0777, true);
    }
    if (!file_exists('upload/videos/' . date('Y') . '/' . date('m'))) {
        @mkdir('upload/videos/' . date('Y') . '/' . date('m'), 0777, true);
    }
    if (isset($data['file']) && !empty($data['file'])) {
        $data['file'] = $data['file'];
    }
    if (isset($data['name']) && !empty($data['name'])) {
        $data['name'] = secure($data['name']);
    }
    if (isset($data['name']) && !empty($data['name'])) {
        $data['name'] = secure($data['name']);
    }
    if (empty($data)) {
        return false;
    }
    if(!isset($data['name'])){
        return;
    }
    $allowed           = 'jpg,png,jpeg,gif,mp3,mp4,mov,webm,mpeg,3gp,mkv,mk3d,mks,webp';
    if (!empty($data['allowed'])) {
        $allowed  = $data['allowed'];
    }
    $new_string        = pathinfo($data['name'], PATHINFO_FILENAME) . '.' . strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
    $extension_allowed = explode(',', $allowed);
    $file_extension    = pathinfo($new_string, PATHINFO_EXTENSION);
    
    if (!empty($data['file_type']) && $data['file_type'] == 'video' && $music->config->ffmpeg_system == 'on') {
        // code...
    }
    else{
        if (!in_array($file_extension, $extension_allowed)) {
            return array(
                'error' => 'File format not supported'
            );
        }
        
        if (empty($data['file_type'])) {
            $ar = array(
                'audio/wav',
                'audio/mpeg',
                'audio/ogg',
                'audio/mp3',
                'image/png',
                'image/jpeg',
                'image/webp',
                'image/gif',
                'image/svg+xml',
                'video/x-msvideo',
                'video/msvideo',
                'video/x-ms-wmv',
                'video/x-flv',
                'video/x-matroska',
                'video/webm',
                'video/mp4',
                'video/mov',
                'video/3gp',
                'video/3gpp',
                'video/mpeg',
                'video/flv',
                'video/avi',
                'video/webm',
                'video/quicktime',
            );

            if (!in_array($data['type'], $ar)) {
                return array(
                    'error' => 'File format not supported'
                );
            }
        }
    }

    if ($file_extension == 'jpg' || $file_extension == 'jpeg' || $file_extension == 'png' || $file_extension == 'gif' || $file_extension == 'webp') {
        $folder   = 'photos';
        $fileType = 'image';
    }
    elseif ($file_extension == 'mov' || $file_extension == 'webm' || $file_extension == 'mpeg' || $file_extension == '3gp' || $file_extension == 'mkv' || $file_extension == 'mk3d' || $file_extension == 'mks' || $file_extension == 'mp4') {
        $folder   = 'videos';
        $fileType = 'video';
    }
    elseif (!empty($data['file_type']) && $data['file_type'] == 'video') {
        $folder   = 'videos';
        $fileType = 'video';
    } else {
        $folder   = 'audio';
        $fileType = 'audio';
    }
    if (empty($folder) || empty($fileType)) {
        return false;
    }

    $dir         = "upload/{$folder}/" . date('Y') . '/' . date('m');
    $filename    = $dir . '/' . generateKey() . '_' . date('d') . '_' . md5(time()) . "_{$fileType}.{$file_extension}";
    $second_file = pathinfo($filename, PATHINFO_EXTENSION);
    if (move_uploaded_file($data['file'], $filename)) {
        if ($second_file == 'jpg' || $second_file == 'jpeg' || $second_file == 'png' || $second_file == 'gif') {
            if ($type == 1) {
                @compressImage($filename, $filename, 50);
                $explode2  = @end(explode('.', $filename));
                $explode3  = @explode('.', $filename);
                $last_file = $explode3[0] . '_small.' . $explode2;
                @resize_Crop_Image(400, 400, $filename, $last_file, 60);

                if (!empty($last_file)) {
                    $upload_s3 = PT_UploadToS3($last_file);
                }
            }

            else {
                if ($second_file != 'gif') {
                    if (!empty($data['crop'])) {
                        $crop_image = resize_Crop_Image($data['crop']['width'], $data['crop']['height'], $filename, $filename, 60);
                    }
                    @compressImage($filename, $filename, 90);
                }

                if (!empty($filename)) {
                    $upload_s3 = PT_UploadToS3($filename);
                }
            }
        }

        else{
            if (!empty($filename)) {
               $upload_s3 = PT_UploadToS3($filename);
            }
        }

        $last_data             = array();
        $last_data['filename'] = $filename;
        if ($music->config->google_drive == 'on') {
          $last_data['filename'] = $upload_s3;
        }
        $last_data['name']     = $data['name'];
        runPlugin('AfterFileUpload', $data);
        return $last_data;
    }
}
function RunInBackground($data = array()) {
    ob_end_clean();
    header("Content-Encoding: none");
    header("Connection: close");
    ignore_user_abort();
    ob_start();
    if (!empty($data)) {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    $size = ob_get_length();
    header("Content-Length: $size");
    ob_end_flush();
    flush();
    session_write_close();
    if (is_callable('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}
function isAdmin() {
    global $music;
    if (IS_LOGGED == false) {
        return false;
    }
    if ($music->user->admin == 1) {
        return true;
    }
    return false;
}
function isLiked($track_id = 0, $user_id = 0) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }
    if (empty($user_id)) {
        $user_id = $music->user->id;
    }
    $track_id = secure($track_id);
    $user_id = secure($user_id);
    return ($db->where('track_id', $track_id)->where('user_id', $user_id)->where('comment_id', 0)->getValue(T_LIKES, "COUNT(*)") > 0) ? true : false;
}
function isDisLiked($track_id = 0, $user_id = 0) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }
    if (empty($user_id)) {
        $user_id = $music->user->id;
    }
    $track_id = secure($track_id);
    $user_id = secure($user_id);
    return ($db->where('track_id', $track_id)->where('user_id', $user_id)->getValue(T_DISLIKES, "COUNT(*)") > 0) ? true : false;
}
function isFavorated($track_id = 0, $user_id = 0) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }
    if (empty($user_id)) {
        $user_id = $music->user->id;
    }
    $track_id = secure($track_id);
    $user_id = secure($user_id);
    return ($db->where('track_id', $track_id)->where('user_id', $user_id)->getValue(T_FOV, "COUNT(*)") > 0) ? true : false;
}
function isFollowing($following_id = 0, $follower_id = 0) {
    global $music, $db;
    if (isLogged() == false) {
        return false;
    }
    if (empty($follower_id)) {
        $follower_id = $music->user->id;
    }
    $following_id = secure($following_id);
    $follower_id = secure($follower_id);

    // if( isset( $_POST['access_token']) && !empty($_POST['access_token']) ){
    //     return ($db->where('follower_id', $following_id)->where('following_id', $follower_id)->getValue(T_FOLLOWERS, "COUNT(*)") > 0) ? true : false;
    // }else{
        return ($db->where('follower_id', $follower_id)->where('following_id', $following_id)->getValue(T_FOLLOWERS, "COUNT(*)") > 0) ? true : false;
    // }
}
function getNotificationTextFromType($type = '') {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }
    if (empty($type)) {
        return false;
    }

    $types = [
        'follow_user' => lang("started following you."),
        'liked_track' => lang("liked your song."),
        'reviewed_track' => lang("reviewed your song."),
        'disliked_track' => lang("disliked your song."),
        'liked_comment' => lang("liked your comment."),
        'purchased' => lang("purchased your song."),
        'approved_artist' => lang("Congratulations! Your request to become an artist was approved."),
        'decline_artist' => lang("Sadly, Your request to become an artist was declined."),
        'approve_receipt' => lang('We approved your bank transfer of %d!'),
        'disapprove_receipt' => lang('We have rejected your bank transfer, please contact us for more details.'),
        'new_track' => lang('upload new track.'),
        'comment_mention' => lang('mentioned you on a comment.'),
        'new_orders' => lang('new orders has been placed'),
        'status_changed' => lang('order status has been changed'),
        'admin_status_changed' => lang('order status has been changed'),
        'refund_declined' => lang('your refund request has been declined'),
        'refund_approved' => lang('your refund request has been approved your money added to your wallet'),
        'added_tracking' => lang('added tracking info'),
        'product_approved' => lang('your product has been approved'),
        'event_joined' => lang('joined your event'),
        'bought_ticket' => lang('bought a ticket'),
        'your_song_is_ready' => lang('your song is ready to view'),
        'coinpayments_approved' => lang('coinpayments_approved'),
        'coinpayments_canceled' => lang('coinpayments_canceled'),
    ];

    if (in_array($type, array_keys($types))) {
       return $types[$type];
    }
    return "";
}
function getFavButton($id, $type) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }

    if (empty($id) || empty($type)) {
        return false;
    }

    $music->favorated = false;

    if (isFavorated($id)) {
        $music->favorated = true;
    }

    $audio_id = $db->where('id', $id)->getValue(T_SONGS, 'audio_id');

    return loadPage("buttons/$type", ['t_audio_id' => $audio_id]);
}
function getLikeButton($id, $type) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }

    if (empty($id) || empty($type)) {
        return false;
    }

    $music->liked = false;

    if (isLiked($id)) {
        $music->liked = true;
    }

    $audio_id = $db->where('id', $id)->getValue(T_SONGS, 'audio_id');

    return loadPage("buttons/$type", ['t_audio_id' => $audio_id]);
}
function getDisLikeButton($id, $type) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }

    if (empty($id) || empty($type)) {
        return false;
    }

    $music->liked = false;
    $music->disliked = false;

    if (isDisLiked($id)) {
        $music->disliked = true;
    }

    $audio_id = $db->where('id', $id)->getValue(T_SONGS, 'audio_id');

    return loadPage("buttons/$type", ['t_audio_id' => $audio_id]);
}
function countLikes($id = 0) {
    global $music, $db;

    if (empty($id)) {
        return false;
    }

    $count = $db->where('track_id', $id)->getValue(T_LIKES, 'COUNT(*)');
    return $count;
}
function countDisLikes($id = 0) {
    global $music, $db;

    if (empty($id)) {
        return false;
    }

    $count = $db->where('track_id', $id)->getValue(T_DISLIKES, 'COUNT(*)');
    return $count;
}
function countCommentLikes($id = 0) {
    global $music, $db;

    if (empty($id)) {
        return false;
    }

    $count = $db->where('comment_id', $id)->getValue(T_LIKES, 'COUNT(*)');
    return $count;
}
function BlogcountCommentLikes($id = 0) {
    global $music, $db;

    if (empty($id)) {
        return false;
    }

    $count = $db->where('comment_id', $id)->getValue(T_BLOG_LIKES, 'COUNT(*)');
    return $count;
}
function GetAd($type, $admin = true) {
    global $db;
    $type      = Secure($type);
    $query_one = "SELECT `code` FROM " . T_ADS . " WHERE `placement` = '{$type}'";
    if ($admin === false) {
        $query_one .= " AND `active` = '1'";
    }
    $fetched_data = $db->rawQuery($query_one);
    if (!empty($fetched_data)) {
        return htmlspecialchars_decode($fetched_data[0]->code);
    }
    return '';
}
function getNotifications($type = 'fetch', $seen = 'both', $limit = 20) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }

    $me = $music->user->id;

    $db->where('recipient_id', $me);
    if ($seen === true) {
        $db->where('seen', '0', '>');
    } else if ($seen === false) {
        $db->where('seen', '0');
    }
    if ($type == 'fetch') {
        return $db->orderBy('id', 'DESC')->get(T_NOTIFICATION, $limit);
    } else if ($type == 'count') {
        return $db->getValue(T_NOTIFICATION, 'COUNT(*)');
    }
}
function getActivity($activity_id = 0, $fetch = true) {
    global $music, $db;

    if ($fetch == true) {
        if (empty($activity_id) || !is_numeric($activity_id)) {
            return false;
        }
        $activity_id = secure($activity_id);
        $getActivity = $db->where('id', $activity_id)->getOne(T_ACTIVITIES);
    } else {
        $getActivity = $activity_id;
    }
    if (!empty($getActivity->track_id)) {
        $userSong = songData($getActivity->track_id);
    }
    elseif (!empty($getActivity->product_id)) {
        $userSong = $product_data = GetProduct($getActivity->product_id);
        $userSong->publisher = $userSong->user_data;
        $userSong->songArray = array('s_id' => $userSong->id,
                                     'USER_DATA' => $userSong->user_data,
                                     's_time' => time_Elapsed_String($userSong->time),
                                     's_name' => $userSong->title,
                                     's_duration' => $music->config->currency_symbol.$userSong->price,
                                     's_thumbnail' => $userSong->images[0]['image'],
                                     's_url' => $userSong->url,
                                     's_audio_id' => '',
                                     's_price' => $userSong->price,
                                     's_category' => $music->products_categories[$userSong->cat_id],
                                     'product_data' => GetProduct($getActivity->product_id));
    }
    elseif (!empty($getActivity->event_id)) {
        $userSong = GetEventById($getActivity->event_id);
        $userSong->publisher = $userSong->user_data;
        $userSong->songArray = array('s_id' => $userSong->id,
                                     'USER_DATA' => $userSong->user_data,
                                     's_time' => time_Elapsed_String($userSong->time),
                                     's_name' => $userSong->name,
                                     's_duration' => $userSong->start_date . ' - ' . date('H:i',strtotime($userSong->start_time)).'<br>'.$userSong->end_date . ' - ' . date('H:i',strtotime($userSong->end_time)),
                                     's_thumbnail' => $userSong->image,
                                     's_url' => $userSong->url,
                                     's_audio_id' => '',
                                     's_price' => '',
                                     's_category' => '',
                                     'event_data' => GetEventById($getActivity->event_id));
    }




    if (empty($userSong)) {
        return false;
    }

    $userSong->activity = $getActivity;
    $userSong->songArray['a_id'] = $getActivity->id;
    $userSong->songArray['a_type'] = $getActivity->type;
    $userSong->songArray['a_owner'] = ( $music->loggedin && $getActivity->user_id === $music->user->id ) ? true : false;
    $userSong->songArray['USER_DATA'] = userData($getActivity->user_id);
    $userSong->songArray['activity_time'] = date('c',$getActivity->time);
    $userSong->songArray['activity_time_formatted'] = time_Elapsed_String($getActivity->time);
    $userSong->songArray['activity_text'] = str_replace('|auser|', '<a href="' . getLink($userSong->publisher->username) . '" data-load="' . $userSong->publisher->username . '">' . $userSong->publisher->name . '</a>', getActivityText($getActivity->type));

    $userSong->songArray['album_text'] = '';
    if (!empty($userSong->album_id) && $getActivity->type == 'uploaded_track') {
        $getAlbum = $db->where('id', $userSong->album_id)->getOne(T_ALBUMS);
        $userSong->songArray['album_text'] = lang('in') . ' <a href="' . getLink("album/$getAlbum->album_id") . '" data-load="album/' . $getAlbum->album_id . '">' . $getAlbum->title . '</a>';
        $userSong->songArray['album'] = albumData( $getAlbum->id, true, true, false );
        unset($userSong->songArray['album']->songs);
    }else{
        $userSong->songArray['album'] = new stdClass();
    }

    $music->songData = $userSong;
    if (IS_LOGGED == true) {
        $userSong->songArray['isSongOwner'] = ($music->user->id == $getActivity->user_id) ? true : false;
        if (isset($_POST['access_token']) && !empty($_POST['access_token'])) {
            $userSong->songArray['TRACK_DATA'] = songData($getActivity->track_id);
        }
    }
    if (isset($_POST['server_key']) && !empty($_POST['server_key'])) {
        $userSong->songArray['TRACK_DATA'] = songData($getActivity->track_id);
    }

    return $userSong->songArray;

}
function getSpotlight($spotlight = 0, $fetch = true) {
    global $music, $db;
    $userSong = songData($spotlight->id);
    if (empty($userSong)) {
        return false;
    }
    $getActivity = $db->where('track_id', $userSong->id)->where('user_id',$userSong->user_id)->getOne(T_ACTIVITIES);
    if (!empty($getActivity)) {
        $userSong->activity = $getActivity;
        $userSong->songArray['a_id'] = $getActivity->id;
        $userSong->songArray['a_type'] = $getActivity->type;
        $userSong->songArray['a_owner'] = ( $spotlight->user_id === @$music->user->id ) ? true : false;
        $userSong->songArray['activity_time'] = date('c',$getActivity->time);
        $userSong->songArray['activity_time_formatted'] = time_Elapsed_String($getActivity->time);
        $userSong->songArray['activity_text'] = str_replace('|auser|', '<a href="' . getLink($userSong->publisher->username) . '" data-load="' . $userSong->publisher->username . '">' . $userSong->publisher->name . '</a>', getActivityText($getActivity->type));
    }
    // $userSong->activity = $spotlight;
    // $userSong->songArray['a_id'] = $spotlight->id;
    // $userSong->songArray['a_type'] = $spotlight->type;
    // $userSong->songArray['a_owner'] = ( $spotlight->user_id === $music->user->id ) ? true : false;
    $userSong->songArray['USER_DATA'] = userData($spotlight->user_id);
    // $userSong->songArray['activity_time'] = date('c',$spotlight->time);
    // $userSong->songArray['activity_time_formatted'] = time_Elapsed_String($spotlight->time);
    // $userSong->songArray['activity_text'] = str_replace('|auser|', '<a href="' . getLink('user/' . $userSong->publisher->username) . '" data-load="user/' . $userSong->publisher->username . '">' . $userSong->publisher->name . '</a>');
    $userSong->songArray['album_text'] = '';
    if (!empty($userSong->album_id)) {
        $getAlbum = $db->where('id', $userSong->album_id)->getOne(T_ALBUMS);
        $userSong->songArray['album_text'] = lang('in') . ' <a href="' . getLink("album/$getAlbum->album_id") . '" data-load="album/' . $getAlbum->album_id . '">' . $getAlbum->title . '</a>';
        $userSong->songArray['album'] = albumData( $getAlbum->id, true, true, false );
        unset($userSong->songArray['album']->songs);
    }else{
        $userSong->songArray['album'] = new stdClass();
    }
    $music->songData = $userSong;
    if (IS_LOGGED == true) {
        $userSong->songArray['isSongOwner'] = ($music->user->id == $spotlight->user_id) ? true : false;
        if (isset($_POST['access_token']) && !empty($_POST['access_token'])) {
            $userSong->songArray['TRACK_DATA'] = songData($spotlight->id);
        }
    }
    return $userSong->songArray;
}
function getActivityText($type) {
    global $music, $db;
    if (empty($type)) {
        return false;
    }

    $types = [
        'liked_track' => lang("liked |auser| song,"),
        'disliked_track' => lang("disliked |auser| song,"),
        'shared_track' => lang("shared |auser| song,"),
        'commented_track' => lang("commented on |auser| song,"),
        'replay_commented_track' => lang('replayed on |auser| comment,'),
        'uploaded_track' => lang("Uploaded a new song,"),
        'imported_track' => lang("Imported a new song,"),
        'created_product' => lang("created new product,"),
        'created_event' => lang("created new event,"),
        'joined_event' => lang("joined new event,"),
        'ticket_event' => lang("purchased a ticket,"),
    ];

    if (in_array($type, array_keys($types))) {
       return $types[$type];
    }
    return "";
}
function LikeExists($params){
    global $db;
    if(!isset($params['comment_user_id']) || !isset($params['track_id']) || !isset($params['user_id']) || !isset($params['comment_id'])) return false;
    if(empty($params['comment_user_id']) || empty($params['track_id']) || empty($params['user_id']) || empty($params['comment_id'])) return false;
    $islikeExists = $db->where('track_id',Secure($params['track_id']))->where('user_id',Secure($params['user_id']))->where('comment_id',Secure($params['comment_id']))->getOne(T_LIKES,['count(*) as likes']);
    if($islikeExists->likes > 0){
        return true;
    }else{
        return false;
    }
}
function BlogLikeExists($params){
    global $db;
    if(!isset($params['comment_user_id']) || !isset($params['article_id']) || !isset($params['user_id']) || !isset($params['comment_id'])) return false;
    if(empty($params['comment_user_id']) || empty($params['article_id']) || empty($params['user_id']) || empty($params['comment_id'])) return false;
    $islikeExists = $db->where('article_id',Secure($params['article_id']))->where('user_id',Secure($params['user_id']))->where('comment_id',Secure($params['comment_id']))->getOne(T_BLOG_LIKES,['count(*) as likes']);
    if($islikeExists->likes > 0){
        return true;
    }else{
        return false;
    }
}
function TrackReportExists($params){
    global $db;
    if(!isset($params['track_id']) || !isset($params['user_id'])) return false;
    if(empty($params['track_id']) || empty($params['user_id'])) return false;
    $isReportExists = $db->where('user_id',Secure($params['user_id']))->where('track_id',Secure($params['track_id']))->getOne(T_REPORTS,['count(*) as reports']);
    if($isReportExists->reports > 0){
        return true;
    }else{
        return false;
    }
}
function TrackReviewExists($params){
    global $db;
    if(!isset($params['track_id']) || !isset($params['user_id'])) return false;
    if(empty($params['track_id']) || empty($params['user_id'])) return false;
    $isReviewExists = $db->where('user_id',Secure($params['user_id']))->where('track_id',Secure($params['track_id']))->getOne(T_REVIEWS,['count(*) as reviews']);
    if($isReviewExists->reviews > 0){
        return true;
    }else{
        return false;
    }
}
function getTrackReportButton($params,$template = 'report-track') {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }

    $user_id = (isset($params['user_id'])) ? Secure($params['user_id']) : null;
    $track_id = (isset($params['track_id'])) ? Secure($params['track_id']) : null;
    if (empty($user_id) || empty($track_id)) {
        return false;
    }

    $music->track_reported = false;

    if (TrackReportExists($params)) {
        $music->track_reported = true;
    }

    $sData = songData($track_id);
    if($sData->IsOwner){
        return false;
    }
    $music->track_reported_params = $params;
    return loadPage("buttons/".$template, ['t_id' => $track_id, 'u_id' => $user_id]);
}
function getTrackReviewButton($params,$template = 'review-track') {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }

    $user_id = (isset($params['user_id'])) ? Secure($params['user_id']) : null;
    $track_id = (isset($params['track_id'])) ? Secure($params['track_id']) : null;
    if (empty($user_id) || empty($track_id)) {
        return false;
    }

    $music->track_reviewed = false;

    if (TrackReviewExists($params)) {
        $music->track_reviewed = true;
    }

    $sData = songData($track_id);
    if(!isTrackPurchased($sData->id)){
        return false;
    }
    if($sData->IsOwner){
        return false;
    }
    $music->track_reviewed_params = $params;
    return loadPage("buttons/".$template, ['t_id' => $track_id, 'u_id' => $user_id, 'oid' => $sData->audio_id]);
}
function getReviewsCount($audio_id){
    global $db;
    return $db->where('track_id', $audio_id)->getValue(T_REVIEWS, 'count(*)');
}
function CommentReportExists($params){
    global $db;
    if(!isset($params['comment_id']) || !isset($params['user_id'])) return false;
    if(empty($params['comment_id']) || empty($params['user_id'])) return false;
    $isReportExists = $db->where('user_id',Secure($params['user_id']))->where('comment_id',Secure($params['comment_id']))->getOne(T_REPORTS,['count(*) as reports']);
    if($isReportExists->reports > 0){
        return true;
    }else{
        return false;
    }
}
function BlogLikeComment($params){
    global $db;
    if(!isset($params['comment_user_id']) || !isset($params['article_id']) || !isset($params['user_id']) || !isset($params['comment_id'])) return false;
    if(empty($params['comment_user_id']) || empty($params['article_id']) || empty($params['user_id']) || empty($params['comment_id'])) return false;
    if(BlogLikeExists($params) === true) return false;

    $insert = $db->insert(T_BLOG_LIKES,array('article_id'=>Secure($params['article_id']),'user_id'=>Secure($params['user_id']),'comment_id'=>Secure($params['comment_id']),'time'=>time()));
    if ($insert) {
        return true;
    }else{
        return false;
    }
}
function BlogUnLikeComment($params){
    global $db;
    if(!isset($params['comment_user_id']) || !isset($params['article_id']) || !isset($params['user_id']) || !isset($params['comment_id'])) return false;
    if(empty($params['comment_user_id']) || empty($params['article_id']) || empty($params['user_id']) || empty($params['comment_id'])) return false;
    if(BlogLikeExists($params) === false) return false;

    $deleted = $db->where('article_id',Secure($params['article_id']))
                 ->where('user_id',Secure($params['user_id']))
                 ->where('comment_id',Secure($params['comment_id']))
                 ->delete(T_BLOG_LIKES);
    if ($deleted) {
        return true;
    }else{
        return false;
    }
}
function LikeComment($params){
    global $db;
    if(!isset($params['comment_user_id']) || !isset($params['track_id']) || !isset($params['user_id']) || !isset($params['comment_id'])) return false;
    if(empty($params['comment_user_id']) || empty($params['track_id']) || empty($params['user_id']) || empty($params['comment_id'])) return false;
    if(LikeExists($params) === true) return false;
    $insertData = array('track_id'=>Secure($params['track_id']),'user_id'=>Secure($params['user_id']),'comment_id'=>Secure($params['comment_id']),'time'=>time());
    $insert = $db->insert(T_LIKES, $insertData);
    if ($insert) {
        runPlugin('AfterCommentLiked', $insertData);
        $song = songData($params['track_id']);
        $create_notification = createNotification([
            'notifier_id' => Secure($params['user_id']),
            'recipient_id' => Secure($params['comment_user_id']),
            'type' => 'liked_comment',
            'track_id' => Secure($params['track_id']),
            'url' => Secure('track/' . $song->audio_id),
            'comment_id' => Secure($params['comment_id'])
        ]);
        return true;
    }else{
        return false;
    }
}
function UnLikeComment($params){
    global $db;
    if(!isset($params['comment_user_id']) || !isset($params['track_id']) || !isset($params['user_id']) || !isset($params['comment_id'])) return false;
    if(empty($params['comment_user_id']) || empty($params['track_id']) || empty($params['user_id']) || empty($params['comment_id'])) return false;
    if(LikeExists($params) === false) return false;

    $deleted = $db->where('track_id',Secure($params['track_id']))
                 ->where('user_id',Secure($params['user_id']))
                 ->where('comment_id',Secure($params['comment_id']))
                 ->delete(T_LIKES);
    if ($deleted) {
        runPlugin('AfterCommentDisLiked', ["id" => $params['comment_id']]);
        $db->where('notifier_id', Secure($params['user_id']));
        $db->where('recipient_id', Secure($params['comment_user_id']));
        $db->where('comment_id', Secure($params['comment_id']));
        $db->where('type', 'liked_comment');
        $db->delete(T_NOTIFICATION);
        return true;
    }else{
        return false;
    }
}
function completeQuery($table = '', $col = '') {
    return $table . '.' . $col;
}
function getSpotlights($limit = 20, $offset = 0){
    global $music, $db;

    $db->where('availability', '0')
        ->where('spotlight', '1')
        ->where(completeQuery(T_SONGS, 'user_id') . ' IN (SELECT id FROM ' . T_USERS . ' WHERE is_pro = 1)');
    if (IS_LOGGED) {
        $db->where(completeQuery(T_SONGS, 'user_id') . " NOT IN (SELECT user_id FROM blocks WHERE blocked_id = {$music->user->id})");
    }
    if (!empty($offset) && $offset > 0 && is_numeric($offset)) {
        $db->where(completeQuery(T_SONGS, 'id'), $offset, '<');
    }

    return $db->orderBy('id', 'DESC')->get(T_SONGS, $limit, [completeQuery(T_SONGS, '*')]);

}
function getActivties($limit = 20, $offset = 0, $user_id = 0, $filter_by = []) {
    global $music, $db;

    if (!empty($user_id) && $user_id > 0 && is_numeric($user_id)) {
        $db->where(completeQuery(T_ACTIVITIES, 'user_id'), $user_id);
    } else if (empty($filter_by['spotlight'])) {
        if (IS_LOGGED == false) {
            return false;
        }
        $db->where("(" . completeQuery(T_ACTIVITIES, 'user_id') . " IN (SELECT following_id FROM " . T_FOLLOWERS . " WHERE follower_id = '{$music->user->id}') OR " . completeQuery(T_ACTIVITIES, 'user_id') . " = '{$music->user->id}')");

    }
    if (!empty($filter_by['likes'])) {
        $db->where(completeQuery(T_ACTIVITIES, 'type'), 'liked_track');
    }
    if (!empty($filter_by['spotlight'])) {
        $db->where(completeQuery(T_ACTIVITIES, 'type'), 'uploaded_track');
        $db->where(completeQuery(T_ACTIVITIES, 'user_id') . ' IN (SELECT id FROM ' . T_USERS . ' WHERE is_pro = 1)');
    }
    if (!empty($filter_by['events'])) {
        $db->where(" ( " .completeQuery(T_ACTIVITIES, 'type')." = 'created_event' || ".completeQuery(T_ACTIVITIES, 'type')." = 'ticket_event' )");
        //$db->where(completeQuery(T_ACTIVITIES, 'user_id') . ' IN (SELECT id FROM ' . T_USERS . ' WHERE is_pro = 1)');
    }
    if (IS_LOGGED) {
        // $db->join("songs", completeQuery(T_ACTIVITIES, 'track_id') . " = " . completeQuery(T_SONGS, 'id'), "INNER");
        // //$db->join("products", completeQuery(T_ACTIVITIES, 'product_id') . " = " . completeQuery(T_PRODUCTS, 'id'), "INNER");
        // $db->where(completeQuery(T_SONGS, 'user_id') . " NOT IN (SELECT user_id FROM blocks WHERE blocked_id = {$music->user->id})");
        $db->where(completeQuery(T_ACTIVITIES, 'user_id') . " NOT IN (SELECT user_id FROM blocks WHERE blocked_id = {$music->user->id})");
    }

    if (!empty($offset) && $offset > 0 && is_numeric($offset)) {
        $db->where(completeQuery(T_ACTIVITIES, 'id'), $offset, '<');
    }

    $get_public_posts = false;
    if (empty($user_id)) {
        $get_public_posts = true;
    } else {
        if (!IS_LOGGED) {
           $get_public_posts = true;
        } else {
            if ($music->user->id != $user_id) {
                $get_public_posts = true;
            }
        }
    }

    if ($get_public_posts == true) {
        $db->where("(".completeQuery(T_ACTIVITIES, 'track_id') . " IN (SELECT id FROM " . T_SONGS . " WHERE availability = 0) OR ".T_ACTIVITIES.".product_id IN (SELECT id FROM " . T_PRODUCTS . " WHERE `active` = '1') OR ".T_ACTIVITIES.".event_id IN (SELECT id FROM " . T_EVENTS . "))");
    }

    return $db->orderBy(completeQuery(T_ACTIVITIES, 'id'), 'DESC')->get(T_ACTIVITIES, $limit, [completeQuery(T_ACTIVITIES, '*')]);
}
function getPlayList($id = 0, $fetch = true) {
    global $music, $db;
    if ($fetch == true) {
        if (empty($id) || !is_numeric($id)) {
            return false;
        }
        $id = secure($id);
        $getPlayList = $db->where('id', $id)->getOne(T_PLAYLISTS);
    } else {
        $getPlayList = $id;
    }

    if (empty($getPlayList)) {
        return false;
    }

    if (isBlockedFromOneSide($getPlayList->user_id)) {
        return false;
    }
    if (IS_LOGGED == true) {
        $getPlayList->IsOwner = ($music->user->id == $getPlayList->user_id) ? true : false;
    }
    $getPlayList->thumbnail_ready = getMedia($getPlayList->thumbnail);
    $getPlayList->privacy_text = ($getPlayList->privacy == 0) ? lang("Public") : lang("Private");
    $getPlayList->url = getLink("playlist/" . $getPlayList->uid);
    $getPlayList->ajax_url = "playlist/" . $getPlayList->uid;
    $getPlayList->songs = $db->where('playlist_id', $getPlayList->id)->getValue(T_PLAYLIST_SONGS, 'count(*)');
    $getPlayList->publisher = userData($getPlayList->user_id);
    return $getPlayList;
}
function getPlayListSong($id = 0) {
    global $music, $db;
    if (empty($id) || !is_numeric($id)) {
        return false;
    }
    $id = secure($id);
    $getPlayList = $db->where('id', $id)->getOne(T_PLAYLIST_SONGS);
    if (empty($getPlayList)) {
        return false;
    }
    if (IS_LOGGED == true) {
        $getPlayList->IsOwner = ($music->user->id == $getPlayList->user_id) ? true : false;
    }
    return $getPlayList;
}
function deleteActivity($data = []) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }

    if (empty($data['user_id'])) {
        $data['user_id'] = $music->user->id;
    }

    if (empty($data['user_id']) || empty($data['type'])) {
        return false;
    }

    if ((!is_numeric($data['user_id'])) && ($data['user_id'] <= 0)) {
        return false;
    }

    $data['user_id'] = secure($data['user_id']);
    $data['type'] = secure($data['type']);
    $data['time'] = secure(time());

    $delete_same_activity = $db->where('user_id', $data['user_id'])->where('type', $data['type']);
    if (!empty($data['track_id'])) {
        $data['track_id'] = secure($data['track_id']);
        $db->where('track_id', $data['track_id']);
    }
    if (!empty($data['event_id'])) {
        $data['event_id'] = secure($data['event_id']);
        $db->where('event_id', $data['event_id']);
    }
    if (!empty($data['product_id'])) {
        $data['product_id'] = secure($data['product_id']);
        $db->where('product_id', $data['product_id']);
    }
    runPlugin('AfterActivityDeleted', $data);
    return $db->delete(T_ACTIVITIES);
}
function notifyUploadTrack($data = array()){
    global $db, $music;
    if (IS_LOGGED == false) {
        return false;
    }
    $getFollowers = $db->where('following_id', $music->user->id)->where("follower_id NOT IN (SELECT blocked_id FROM blocks WHERE user_id = {$music->user->id})")->orderBy('id', 'DESC')->get(T_FOLLOWERS, null, array('*'));
    foreach ($getFollowers as $key => $value){
        $create_notification = createNotification([
            'notifier_id' => $data['user_id'],
            'recipient_id' => $value->follower_id,
            'type' => 'new_track',
            'track_id' => $data['id'],
            'url' => "track/".$data['audio_id']
        ]);
    }
}
function createActivity($data = []) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }

    if (empty($data['user_id'])) {
        $data['user_id'] = $music->user->id;
    }

    if (empty($data['user_id']) || empty($data['type'])) {
        return false;
    }

    if ((!is_numeric($data['user_id'])) && ($data['user_id'] <= 0)) {
        return false;
    }

    $data['user_id'] = secure($data['user_id']);
    $data['type'] = secure($data['type']);
    $data['time'] = secure(time());

    $delete_same_activity = $db->where('user_id', $data['user_id'])->where('type', $data['type']);
    if (!empty($data['track_id'])) {
        $data['track_id'] = secure($data['track_id']);
        $db->where('track_id', $data['track_id']);
    }
    if (!empty($data['product_id'])) {
        $data['product_id'] = secure($data['product_id']);
        $db->where('product_id', $data['product_id']);
    }
    $db->delete(T_ACTIVITIES);
    $create_activity = $db->insert(T_ACTIVITIES, $data);
    if ($create_activity) {
        runPlugin('AfterActivityCreated', $data);
        return true;
    }
}
function createNotification($data = []) {
    global $music, $db;
    if (isLogged() == false) {
        return false;
    }

    if (empty($data['notifier_id'])) {
        $data['notifier_id'] = $music->user->id;
    }

    if (empty($data['recipient_id']) || empty($data['type'])) {
        return false;
    }

    if ((!is_numeric($data['notifier_id']) || !is_numeric($data['recipient_id'])) && ($data['notifier_id'] <= 0 || $data['recipient_id'] <= 0)) {
        return false;
    }

    if ($data['recipient_id'] == $data['notifier_id'] ) {
        return false;
    }

    $isBlocked = ($db->where('blocked_id', $music->user->id)->where('user_id', $data['recipient_id'])->getValue(T_BLOCKS, 'count(*)') > 0);

    if ($isBlocked) {
        return false;
    }

    $data['notifier_id'] = secure($data['notifier_id']);
    $data['recipient_id'] = secure($data['recipient_id']);
    $data['type'] = secure($data['type']);
    $data['time'] = secure(time());

    $send_notification = false;
    $senderEmailNotification = ToArray(userData($data['notifier_id']));
    $userEmailNotification = ToArray(userData($data['recipient_id']));
    $u = $music->user;
    if ($data['type'] == 'purchased'){
        RecordUserActivities('purchase_track',array('track_id' => $data['track_id'], 'audio_id' => $data['track_id']));
    }
    if($userEmailNotification !== false) {
        if ($data['type'] == 'follow_user' && $userEmailNotification['email_on_follow_user'] == 1) {
            $send_notification = true;
        }
        if (($data['type'] == 'liked_track' || $data['type'] == 'disliked_track') && $userEmailNotification['email_on_liked_track'] == 1) {
            $send_notification = true;
        }
        if ($data['type'] == 'liked_comment' && $userEmailNotification['email_on_liked_comment'] == 1) {
            $send_notification = true;
        }
        if (($data['type'] == 'approved_artist'  || $data['type'] == 'decline_artist') ){ // && $userEmailNotification['email_on_artist_status_changed'] == 1) {
            $send_notification = true;
        }
        if (($data['type'] == 'approve_receipt' || $data['type'] == 'disapprove_receipt') ){// && $userEmailNotification['email_on_receipt_status_changed'] == 1) {
            $send_notification = true;
        }
        if ($data['type'] == 'new_track' && $userEmailNotification['email_on_new_track'] == 1) {
            $send_notification = true;
        }
        if ($data['type'] == 'reviewed_track' && $userEmailNotification['email_on_reviewed_track'] == 1) {
            $send_notification = true;
        }
        if ($data['type'] == 'comment_mention' && $userEmailNotification['email_on_comment_mention'] == 1) {
            $send_notification = true;
        }
        if ($data['type'] == 'comment_replay_mention' && $userEmailNotification['email_on_comment_replay_mention'] == 1){
            $send_notification = true;
        }
    }
    if ($data['type'] == 'new_orders' || $data['type'] == 'status_changed' || $data['type'] == 'added_tracking' || $data['type'] == 'product_approved' || $data['type'] == 'admin_status_changed' || $data['type'] == 'refund_declined' || $data['type'] == 'refund_approved' || $data['type'] == 'event_joined' || $data['type'] == 'bought_ticket') {
        $send_notification = true;
    }
    if ($send_notification == true) {

        $delete_same_notification = $db->where('notifier_id', $data['notifier_id'])->where('recipient_id', $data['recipient_id'])->where('type', $data['type']);
        if (!empty($data['track_id'])) {
            $data['track_id'] = secure($data['track_id']);
            $db->where('track_id', $data['track_id']);
        }
        if(isset($data['comment_id'])) {
            if (!empty($data['comment_id'])) {
                $data['comment_id'] = secure($data['comment_id']);
                $db->where('comment_id', $data['comment_id']);
            }
        }
        $db->delete(T_NOTIFICATION);
        $create_notification = $db->insert(T_NOTIFICATION, $data);
        if ($create_notification) {
            runPlugin('AfterNotificationCreated', $data);
            if ($music->config->android_push_native == 1 || $music->config->ios_push_native == 1 || $music->config->push == 1) {
                NotificationWebPushNotifier();
            }
            if($music->config->emailNotification == 'on') {
                sendNotificationEmail($data, $userEmailNotification,$senderEmailNotification);
            }
            return true;
        }

    }
}
function sendNotificationEmail($notification,$userEmailNotification,$senderEmailNotification){
    global $db,$music;

    $notification_text = getNotificationTextFromType($notification['type']);
    $c = '';
    if($notification['type'] == 'approved_artist' || $notification['type'] == 'decline_artist' || $notification['type'] == 'approve_receipt' || $notification['type'] == 'disapprove_receipt'){
        $notification['url'] = '';
        $senderEmailNotification['username'] = '';
        $c = $music->config->name;
    }
    $music->uname = $userEmailNotification['name'];
    $music->username = $senderEmailNotification['username'];
    $music->c = $c;
    $music->full_name = $senderEmailNotification['name'];
    $music->contents = $notification_text;
    $music->url = $notification['url'];
    $send_email_data = array(
        'from_email' => $music->config->email,
        'from_name' => $music->config->name,
        'to_email' => $userEmailNotification['email'],
        'to_name' => $userEmailNotification['name'],
        'subject' => $music->config->name . ' - ' . lang('New notification'),
        'charSet' => 'UTF-8',
        'message_body' => loadPage('emails/notification-email', array(
            'c'=> $c,
            'uname' => $userEmailNotification['name'],
            'full_name' => $senderEmailNotification['name'],
            'username' => $senderEmailNotification['username'],
            'avater' => $senderEmailNotification['avatar'],
            'contents' => $notification_text,
            'url' => $notification['url']
        )),
        'is_html' => true
    );
    //file_put_contents($notification['type'] . '_' . time() . ".txt", json_encode($send_email_data, JSON_PRETTY_PRINT));
    $send_message = sendMessage($send_email_data);

    return true;
}
function NotificationWebPushNotifier() {
    global $sqlConnect, $music;
    if (IS_LOGGED == false) {
        return false;
    }
    if ($music->config->push == 0) {
        return false;
    }
    if ($music->config->android_push_native == 0 && $music->config->ios_push_native == 0 && $music->config->web_push == 0) {
        return false;
    }
    $user_id   = Secure($music->user->id);
    $to_ids    = array();
    $send      = '';
    $query_get = mysqli_query($sqlConnect, "SELECT * FROM " . T_NOTIFICATION . " WHERE `notifier_id` = '$user_id' AND `seen` = '0' AND `sent_push` = '0' AND `type` <> 'admin_notification' ORDER BY `id` DESC");
    if (mysqli_num_rows($query_get) > 0) {
        while ($sql_get_notification_for_push = mysqli_fetch_assoc($query_get)) {
            $notification_id = $sql_get_notification_for_push['id'];
            $to_id           = $sql_get_notification_for_push['recipient_id'];
            $to_data         = userData($sql_get_notification_for_push['recipient_id']);
            $ids             = array();
            if (!empty($to_data->android_device_id) || !empty($to_data->ios_device_id) || !empty($to_data->web_device_id)) {
                if (!empty($to_data->web_device_id) && empty($to_data->ios_device_id) && empty($to_data->android_device_id)) {
                    if ($music->config->web_push == 0) {
                        return false;
                    }
                }
                $send_array                                                     = array();
                $send_array['notification']['notification_content']             = getNotificationTextFromType($sql_get_notification_for_push['type']);
                $send_array['notification']['notification_data']['url']         = $sql_get_notification_for_push['url'];
                $send_array['notification']['notification_data']['user_data']   = $to_data;
                $send_array['notification']['notification_data']['track']       = '';
                if (!empty($sql_get_notification_for_push['track_id'])) {
                    $send_array['notification']['notification_data']['track']   = $sql_get_notification_for_push['track_id'];

                }
                if ($music->config->android_push_native == 1 && !empty($to_data->android_device_id)) {
                    $send_array['send_to'] = array($to_data->android_device_id);
                    $send_array['notification']['notification_title'] = $music->user->name;
                    $send_array['notification']['notification_image'] = $music->user->avatar;
                    $send_array['notification']['notification_data']['user_id'] = $user_id;
                    $send       = SendPushNotification($send_array, 'android_native');
                }
                if ($music->config->ios_push_native == 1 && !empty($to_data->ios_device_id)) {
                    $send_array['send_to'] = array($to_data->ios_device_id);
                    $send_array['notification']['notification_title'] = $music->user->name;
                    $send_array['notification']['notification_image'] = $music->user->avatar;
                    $send_array['notification']['notification_data']['user_id'] = $user_id;
                    $send       = SendPushNotification($send_array, 'ios_native');
                }
                if ($music->config->push == 1 && !empty($to_data->web_device_id)) {
                    $send_array['send_to'] = array($to_data->web_device_id);
                    $send_array['notification']['notification_title'] = $music->user->name;
                    $send_array['notification']['notification_image'] = $music->user->avatar;
                    $send_array['notification']['notification_data']['user_id'] = $user_id;

                    $send       = SendPushNotification($send_array, 'web');
                }
                if(!empty($send)){
                    $query_get_messages_for_push = mysqli_query($sqlConnect, "UPDATE " . T_NOTIFICATION . " SET `sent_push` = '1' WHERE `notifier_id` = '$user_id' AND `sent_push` = '0'");
                }
            }
        }
    }
    return true;
}
function MessagesPushNotifier() {
    global $sqlConnect, $music;
    if (IS_LOGGED == false) {
        return false;
    }
    if ($music->config->push == 0) {
        return false;
    }
    if ($music->config->android_push_messages == 0 && $music->config->ios_push_messages == 0) {
        return false;
    }
    $user_id   = Secure($music->user->id);
    $to_ids    = array();
    $query_get = mysqli_query($sqlConnect, "SELECT * FROM " . T_MESSAGES . " WHERE `from_id` = '$user_id' AND `seen` = '0' AND `sent_push` = '0' ORDER BY `id` DESC");
    if (mysqli_num_rows($query_get) > 0) {
        while ($sql_get_messages_for_push = mysqli_fetch_assoc($query_get)) {
            if (!in_array($sql_get_messages_for_push['to_id'], $to_ids)) {
                $get_session_data = GetSessionDataFromUserID($sql_get_messages_for_push['to_id']);
                if (empty($get_session_data)) {
                    $message_id = $sql_get_messages_for_push['id'];
                    $to_id      = $sql_get_messages_for_push['to_id'];
                    $to_data    = userData($sql_get_messages_for_push['to_id']);
                    if (!empty($to_data['android_device_id']) && $music->config->android_push_messages != 0) {
                        $send_array = array(
                            'send_to' => array(
                                $to_data['android_device_id']
                            ),
                            'notification' => array(
                                'notification_content' => $sql_get_messages_for_push['text'],
                                'notification_title' => $music->user->name,
                                'notification_image' => $music->user->avatar,
                                'notification_data' => array(
                                    'user_id' => $user_id
                                )
                            )
                        );
                        $send       = SendPushNotification($send_array,'android_messenger');
                        if ($send) {
                            $query_get_messages_for_push = mysqli_query($sqlConnect, "UPDATE " . T_MESSAGES . " SET `notification_id` = '$send' WHERE `id` = '$message_id'");
                        }
                    }
                    if (!empty($to_data['ios_device_id']) && $music->config->ios_push_messages != 0) {
                        $send_array = array(
                            'send_to' => array(
                                $to_data['ios_device_id']
                            ),
                            'notification' => array(
                                'notification_content' => $sql_get_messages_for_push['text'],
                                'notification_title' => $music->user->name,
                                'notification_image' => $music->user->avatar,
                                'notification_data' => array(
                                    'user_id' => $user_id
                                )
                            )
                        );
                        $send       = SendPushNotification($send_array,'ios_messenger');
                        if ($send) {
                            $query_get_messages_for_push = mysqli_query($sqlConnect, "UPDATE " . T_MESSAGES . " SET `notification_id` = '$send' WHERE `id` = '$message_id'");
                        }
                    }
                    $query_get_messages_for_push = mysqli_query($sqlConnect, "UPDATE " . T_MESSAGES . " SET `sent_push` = '1' WHERE `from_id` = '$user_id' AND `to_id` = '$to_id' AND `sent_push` = '0'");
                }
            }
            $to_ids[] = $sql_get_messages_for_push['to_id'];
        }
    }
    return true;
}

function GetSessionDataFromUserID($user_id = 0) {
    global $sqlConnect;
    if (empty($user_id)) {
        return false;
    }
    $user_id = Secure($user_id);
    $time    = time() - 30;
    $query   = mysqli_query($sqlConnect, "SELECT * FROM " . T_APP_SESSIONS . " WHERE `user_id` = '{$user_id}' AND `platform` = 'web' AND `time` > $time LIMIT 1");
    return mysqli_fetch_assoc($query);
}

function deleteUser($user_id = 0) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }
    runPlugin('PreUserDeleted', ["user_id" => $user_id]);

    if (empty($user_id)) {
        return false;
    }

    if ($music->user->id != $user_id && !isAdmin()) {
        return false;
    }

    $userData = userData($user_id);

    // delete images
    if ($userData->or_avatar != 'upload/photos/d-avatar.jpg') {
        unlink($userData->or_avatar);
        PT_DeleteFromToS3($userData->or_avatar);
    }
    if ($userData->or_cover != 'upload/photos/d-cover.jpg') {
        unlink($userData->or_avatar);
        PT_DeleteFromToS3($userData->or_avatar);
    }

    // delete user data
    $db->where('follower_id', $user_id)->delete(T_FOLLOWERS);
    $db->where('following_id', $user_id)->delete(T_FOLLOWERS);
    $db->where('artist_id', $user_id)->delete(T_FOLLOWERS);

    $db->where('notifier_id', $user_id)->delete(T_NOTIFICATION);
    $db->where('recipient_id', $user_id)->delete(T_NOTIFICATION);

    $db->where('user_id', $user_id)->delete(T_SESSIONS);
    $db->where('user_id', $user_id)->delete(T_COMMENTS);
    $db->where('user_one', $user_id)->delete(T_CHATS);
    $db->where('user_two', $user_id)->delete(T_CHATS);
    $db->where('user_id', $user_id)->delete(T_COPYRIGHTS);

    $db->where('user_id', $user_id)->delete(T_BLOCKS);
    $db->where('blocked_id', $user_id)->delete(T_BLOCKS);

    $db->where('user_id', $user_id)->delete(T_DOWNLOADS);
    $db->where('user_id', $user_id)->delete(T_VIEWS);

    $db->where('user_id', $user_id)->delete(T_FOV);
    $db->where('user_id', $user_id)->delete(T_LIKES);

    $db->where('to_id', $user_id)->delete(T_MESSAGES);
    $db->where('from_id', $user_id)->delete(T_MESSAGES);

    $db->where('user_id', $user_id)->delete(T_REVIEWS);
    $db->where('user_id', $user_id)->delete(T_PENDING_PAYMENTS);

    $getUserAds = $db->where('user_id', $user_id)->get(T_USR_ADS);
    foreach ($getUserAds as $key => $ad) {
        $db->where('ad_id', $ad->id)->delete(T_ADS_TRANS);
        @unlink($ad->media);
        PT_DeleteFromToS3($ad->media);
    }

    //$db->where('user_id', $user_id)->delete(T_STATIONS);

    $getPlaylists = $db->where('user_id', $user_id)->get(T_PLAYLISTS);
    foreach ($getPlaylists as $key => $playlist) {
       @unlink($playlist->thumbnail);
       PT_DeleteFromToS3($playlist->thumbnail);
    }
    $db->where('user_id', $user_id)->get(T_PLAYLISTS);

    $db->where('user_id', $user_id)->delete(T_PURCHAES);
    $db->where('user_id', $user_id)->delete(T_PAYMENTS);
    $db->where('user_id', $user_id)->delete(T_APP_SESSIONS);
    $db->where('track_owner_id', $user_id)->delete(T_PURCHAES);
    $db->where('user_id', $user_id)->delete(T_REPORTS);
    $db->where('user_id', $user_id)->delete(T_USER_INTEREST);
    $db->where('user_id', $user_id)->delete(T_WITHDRAWAL_REQUESTS);

    $db->where('id', $user_id)->delete(T_USERS);

    $getUserSongs = $db->where('user_id', $user_id)->get(T_SONGS);
    foreach ($getUserSongs as $key => $song) {
        $deleteSong = deleteSong($song->id);
    }

    $getAlbums = $db->where('user_id', $user_id)->get(T_ALBUMS);
    foreach ($getAlbums as $key => $album) {
       @unlink($album->thumbnail);
       PT_DeleteFromToS3($album->thumbnail);
    }

    $db->where('user_id', $user_id)->delete(T_ALBUMS);

    //1- Delete bank_receipts
    $getBank_receipts = $db->where('user_id', $user_id)->get(T_BANK_RECEIPTS);
    foreach ($getBank_receipts as $key => $receipt) {
        @unlink($receipt->receipt_file);
        PT_DeleteFromToS3($receipt->receipt_file);
    }
    $db->where('user_id', $user_id)->delete(T_BANK_RECEIPTS);
    //2- Delete announcement_views
    $db->where('user_id', $user_id)->delete(T_ANNOUNCEMENT_VIEWS);
    //3- Delete artist_requests
    $db->where('user_id', $user_id)->delete(T_ARTIST_R);
    //4- Delete artist_requests
    $db->where('user_id', $user_id)->delete(T_DISLIKES);
    //5- Delete playlist_songs
    $db->where('user_id', $user_id)->delete(T_PLAYLISTS);
    //6- Delete activities
    $db->where('user_id', $user_id)->delete(T_ACTIVITIES);
    //7- Delete T_USERS_FIELDS
    $db->where('user_id', $user_id)->delete(T_USERS_FIELDS);
    //8- Delete T_USR_ADS
    $db->where('user_id', $user_id)->delete(T_USR_ADS);
    //9- Delete playlist_songs
    $db->where('user_id', $user_id)->delete(T_PLAYLIST_SONGS);

    runPlugin('AfterUserDeleted', ["user_id" => $user_id]);

    return true;
}
function songData($track_id = 0, $createSession = true, $fetch = true) {
    global $music, $db;
    if (empty($track_id)) {
        return false;
    }
    if ($fetch == true) {
        if (!is_numeric($track_id) || $track_id <= 0) {
            return false;
        }
        $track_id = secure($track_id);
        $getTrack = $db->where('id', $track_id)->getOne(T_SONGS);
    } else {
        $getTrack = $track_id;
    }

    if (empty($getTrack)) {
        return false;
    }

    if (isBlockedFromOneSide($getTrack->user_id)) {
        return false;
    }
    if ($getTrack->availability == 1) {
        if ($music->loggedin) {
            if ($getTrack->user_id != $music->user->id && !isAdmin()) {
                return false;
            }
        }
        else{
            return false;
        }
    }

    $getTrack->thumbnail_original = $getTrack->thumbnail;
    $getTrack->audio_location_original = $getTrack->audio_location;

    $getTrack->thumbnail = getMedia($getTrack->thumbnail);
    if( $getTrack->src === 'radio' ){
        $getTrack->audio_location = $getTrack->audio_location;
    }else{
        $getTrack->audio_location = getMedia($getTrack->audio_location);
    }
    $getTrack->publisher = userData($getTrack->user_id);
    $getTrack->org_description = EditmarkUp($getTrack->description);
    $getTrack->description = markUp($getTrack->description);

    $getTrack->time_formatted = time_Elapsed_String($getTrack->time);
    $getTrack->tags_default = $getTrack->tags;
    $getTrack->tags_array = explode(",",  $getTrack->tags);
    $getTrack->tagsFiltered = [];
    $getTrack->url = getLink("track/$getTrack->audio_id");

    //$category_data = $db->arrayBuilder()->where('id',$getTrack->category_id))->getOne(T_CATEGORIES);
    //$category_data['lang'] = $db->arrayBuilder()->where('lang_key', 'cateogry_' . $getTrack->category_id)->getOne(T_LANGS);
    if( $getTrack->category_id > 0 ){
        if( isset( $music->categories->{$getTrack->category_id} ) ){
            $getTrack->category_name = $music->categories->{$getTrack->category_id};
        }else{
            $getTrack->category_name = lang('Other');
        }
    }else{
        $getTrack->category_name = lang('Other');
    }

    //$getTrack->category_name = (!empty($music->categories->{$getTrack->category_id})) ? $music->categories->{$getTrack->category_id} : lang('Other');
    //$getTrack->category_name = (!empty($music->categories->{$getTrack->category_id})) ? $music->categories->{$getTrack->category_id} : lang('Other');

    // if ($getTrack->availability == 1) {
    //     if (IS_LOGGED) {
    //         if ($getTrack->user_id != $music->user->id) {
    //             return false;
    //         }
    //     } else {
    //         return false;
    //     }
    // }

    if ($createSession == true) {
        $new_hash = $hash = md5(time());
        setcookie("session_hash", $hash, 0, "/");
    } else {
        if (isset($_COOKIE['session_hash'])) {
            $new_hash = $_COOKIE['session_hash'];
        } else {
            $new_hash = $hash = md5(time());
            setcookie("session_hash", $hash, 0, "/");
        }
    }
    if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == "on" || $music->config->spaces == "on" || $music->config->google_drive == "on" || $music->config->wasabi_storage == 'on' || $music->config->backblaze_storage == 'on') {
        $getTrack->secure_url = $getTrack->audio_location;
        $purchase = false;
        if ($getTrack->price > 0) {
            if (!isTrackPurchased($getTrack->id)) {
                $purchase = true;
                if (IS_LOGGED == true) {
                    if ($music->user->id == $getTrack->user_id) {
                        $purchase = false;
                    }
                }
            }
        }
        if (!empty($getTrack->demo_track) && $purchase == true && $music->config->ffmpeg_system == 'on') {
            $getTrack->secure_url = getMedia($getTrack->demo_track);
        }
    } else {
        $getTrack->secure_url = getLink("get-track.php?id=$getTrack->audio_id&hash=$new_hash");
    }
    if (!empty($getTrack->tags_array)) {
        foreach ($getTrack->tags_array as $key => $tag) {
            $getTrack->tagsFiltered[] = trim($tag);
        }
    }

    $getTrack->songArray = [
        'USER_DATA' => $getTrack->publisher,
        's_time' => $getTrack->time_formatted,
        's_name' => $getTrack->title,
        's_duration' => $getTrack->duration,
        's_thumbnail' => $getTrack->thumbnail,
        's_id' => $getTrack->id,
        's_url' => $getTrack->url,
        's_audio_id' => $getTrack->audio_id,
        's_price' => $getTrack->price,
        's_category' => $getTrack->category_name
    ];

    $getTrack->count_likes = number_format_mm(countLikes($getTrack->id));
    $getTrack->count_dislikes = number_format_mm(countDisLikes($getTrack->id));
    $getTrack->count_views = number_format_mm($db->where('track_id', $getTrack->id)->getValue(T_VIEWS, 'count(*)'));
    $getTrack->count_shares = 0;
    $getTrack->count_comment = number_format_mm($db->where('track_id', $getTrack->id)->getValue(T_COMMENTS, 'count(*)'));
    $getTrack->count_favorite = number_format_mm($db->where('track_id', $getTrack->id)->getValue(T_FOV, 'count(*)'));
    if (!empty($getTrack->price) && !empty($getTrack->demo_track) && !isTrackPurchased($getTrack->id) && $music->config->ffmpeg_system == 'on') {
        $showDemo = true;
        if (IS_LOGGED == true) {
            if ($getTrack->user_id == $music->user->id) {
                $showDemo = false;
            }
        }
        if ($showDemo == true) {
            $wave = $getTrack->dark_wave;
            $getTrack->dark_wave = str_replace('_dark.png','_demo_dark.png', $wave);
            $getTrack->light_wave = str_replace('_dark.png','_demo_light.png', $wave);
            $getTrack->duration = $getTrack->demo_duration;
        }
    }
    $getTrack->isDisLiked = 0;
    if (IS_LOGGED == true) {
        $getTrack->isDisLiked = isDisLiked($getTrack->id);
        $getTrack->IsOwner = ($music->user->id == $getTrack->publisher->id) ? true : false;
        $getTrack->IsLiked = isLiked($getTrack->id, $music->user->id);
        $getTrack->is_favoriated = isFavorated($getTrack->id);

        if($getTrack->price == 0){
            $getTrack->can_listen = true;
        }else{
            $getTrack->can_listen = false;
        }

        if($getTrack->IsOwner || isTrackPurchased($getTrack->id)){
            $getTrack->can_listen = true;
        }

    }
    $album = $db->where('id',$getTrack->album_id)->getOne(T_ALBUMS,'title');
    if($album !== null) {
        $getTrack->album_name = $album->title;
    }else{
        $getTrack->album_name = '';
    }
    $getTrack->itunes_token_url = $getTrack->itunes_affiliate_url;
    if (!empty($getTrack->itunes_token)) {
        $getTrack->itunes_token_url = $getTrack->itunes_token_url.'&at='.$getTrack->itunes_token;
    }
    $getTrack->youtube_url = '';
    if (!empty($getTrack->src) && strpos($getTrack->src, 'YOUTUBE:') !== false) {
        $getTrack->youtube_url = 'https://www.youtube.com/watch?v='.explode(":", $getTrack->src)[1];
    }
    $getTrack->deezer_url = '';
    if (strpos($getTrack->src, 'EEZER:')) {
        $array = explode(':', $getTrack->src);
        if (!empty($array) && !empty($array[1])) {
            $getTrack->deezer_url = 'https://www.deezer.com/en/track/'.$array[1];
        }
    }
    $getTrack->tagged_artists = array();
    $tagged_artists = $db->where('track_id',$getTrack->id)->where('approved',1)->get(T_ARTISTS_TAGS);
    if (!empty($tagged_artists)) {
        foreach ($tagged_artists as $key => $value) {
            $getTrack->tagged_artists[] = userData($value->artist_id);
        }
    }
    $getTrack->is_reported = 0;
    if ($music->loggedin && !empty($music->user) && !empty($getTrack->track_id)) {
        if (TrackReportExists(['user_id' => $music->user->id,'track_id' => $getTrack->track_id])) {
            $getTrack->is_reported = 1;
        }
    }
    $getTrack->is_purchased = isTrackPurchased($getTrack->id);

    return $getTrack;
}
use Aws\S3\S3Client;
use SpacesAPI\Spaces;
function PT_UploadToS3($filename, $config = array()) {
    global $music;
    if(empty($filename) || is_array($filename) || is_object($filename)){
        return false;
    }
    if (!file_exists($filename)) {
        return false;
    }
    if ($music->config->s3_upload != 'on' && $music->config->ftp_upload != 'on' && $music->config->spaces != 'on' && $music->config->google_drive != 'on' && $music->config->wasabi_storage != 'on' && $music->config->backblaze_storage != 'on') {
        return false;
    }

    if ($music->config->ftp_upload == "on" && !empty($music->config->ftp_host) && !empty($music->config->ftp_username)) {
        include_once('assets/libs/ftp/vendor/autoload.php');
        $ftp = new \FtpClient\FtpClient();
        $ftp->connect($music->config->ftp_host, false, $music->config->ftp_port);
        $login = $ftp->login($music->config->ftp_username, $music->config->ftp_password);
        if ($login) {
            if (!empty($music->config->ftp_path)) {
                if ($music->config->ftp_path != "./") {
                    $ftp->chdir($music->config->ftp_path);
                }
            }
            $file_path = substr($filename, 0, strrpos( $filename, '/'));
            $file_path_info = explode('/', $file_path);
            $path = '';
            if (!$ftp->isDir($file_path)) {
                foreach ($file_path_info as $key => $value) {
                    if (!empty($path)) {
                        $path .= '/' . $value . '/' ;
                    } else {
                        $path .= $value . '/' ;
                    }
                    if (!$ftp->isDir($path)) {
                        $mkdir = $ftp->mkdir($path);
                    }
                }
            }
            $ftp->chdir($file_path);
            $ftp->pasv(true);
            if ($ftp->putFromPath($filename)) {
                if (empty($config['delete'])) {
                    if (empty($config['amazon'])) {
                        @unlink($filename);
                    }
                }
                $ftp->close();
                return true;
            }
            $ftp->close();
        }
    } else if ($music->config->spaces == 'on') {
        include_once('assets/libs/s3-lib/vendor/autoload.php');
        $key = $music->config->spaces_key;
        $secret = $music->config->spaces_secret;
        $space_name = $music->config->space_name;
        $region = $music->config->space_region;

        $s3 = new S3Client(array(
                'version' => 'latest',
                'endpoint' => 'https://' . $region . '.digitaloceanspaces.com',
                'region' => $region,
                'credentials' => array(
                    'key' => $music->config->spaces_key,
                    'secret' => $music->config->spaces_secret
                )
            ));
        $s3->putObject(array(
            'Bucket' => $music->config->space_name,
            'Key' => $filename,
            'Body' => fopen($filename, 'r+'),
            'ACL' => 'public-read',
            'CacheControl' => 'max-age=3153600'
        ));
        if (empty($config['delete'])) {
            if ($s3->doesObjectExist($music->config->space_name, $filename)) {
                if (empty($config['amazon'])) {
                    @unlink($filename);
                }
                return true;
            }
        } else {
            return true;
        }
    } else if ($music->config->google_drive == 'on') {
        require './assets/libs/google/vendor/autoload.php';
        $client = new Google_Client();
        $client->setClientId($music->config->google_ClientId);
        $client->setClientSecret($music->config->google_ClientSecret);
        $getAccessToken = $client->refreshToken($music->config->google_refreshToken);
        $client->setAccessToken($getAccessToken);
        $service = new Google_Service_Drive($client);
        $fileName = $filename;
        $folders = explode('/', $fileName);

        if ($folders[1] != 'photos' && $folders[1] != 'audio' && $folders[1] != 'videos') {
           return false;
        }

        $originalFileName = $folders[4];
        $checkIfFolderExists = check_folder_exists($client, 'deepsound-files');
        if (empty($checkIfFolderExists[0]['id'])) {
          $createFolder = create_folder($client, 'deepsound-files');
          $createFolder = create_folder($client, $folders[1] . '_' . $folders[2] . '_'. $folders[3], $createFolder);
        } else {
          $createFolder = create_folder($client, $folders[1] . '_' . $folders[2] . '_'. $folders[3], $checkIfFolderExists[0]['id']);
        }

        $file = new Google_Service_Drive_DriveFile();
        $file->setName($originalFileName);
        $file->setParents([$createFolder]);

        $createdFile = $service->files->create($file, array('data' => file_get_contents($fileName)));


        $permissionService = new Google_Service_Drive_Permission();
        $permissionService->role = "reader";
        $permissionService->type = "anyone"; // anyone with the link can view the file
        $service->permissions->create($createdFile->id, $permissionService);
        if (empty($config['amazon'])) {
            @unlink($filename);
        }
        return $createdFile->id;

    } elseif ($music->config->wasabi_storage == 'on' && !empty($music->config->wasabi_bucket_name)) {
       include_once('assets/libs/s3-lib/vendor/autoload.php');

        $s3 = new S3Client(array(
                'version' => 'latest',
                'endpoint' => 'https://s3.' . $music->config->wasabi_bucket_region . '.wasabisys.com',
                'region' => $music->config->wasabi_bucket_region,
                'credentials' => array(
                    'key' => $music->config->wasabi_access_key,
                    'secret' => $music->config->wasabi_secret_key
                )
            ));
        $s3->putObject(array(
            'Bucket' => $music->config->wasabi_bucket_name,
            'Key' => $filename,
            'Body' => fopen($filename, 'r+'),
            'ACL' => 'public-read',
            'CacheControl' => 'max-age=3153600'
        ));
        if (empty($config['delete'])) {
            if ($s3->doesObjectExist($music->config->wasabi_bucket_name, $filename)) {
                if (empty($config['wasabi'])) {
                    @unlink($filename);
                }
                return true;
            }
        } else {
            return true;
        }
    } elseif ($music->config->backblaze_storage == 'on' && !empty($music->config->backblaze_bucket_id)) {
        $info = BackblazeConnect(array('apiUrl' => 'https://api.backblazeb2.com',
                                       'uri' => '/b2api/v2/b2_authorize_account',
                                ));
        if (!empty($info)) {
            $result = json_decode($info,true);
            if (!empty($result['authorizationToken']) && !empty($result['apiUrl']) && !empty($result['accountId'])) {
                $info = BackblazeConnect(array('apiUrl' => $result['apiUrl'],
                                               'uri' => '/b2api/v2/b2_get_upload_url',
                                               'authorizationToken' => $result['authorizationToken'],
                                        ));
                if (!empty($info)) {
                    $info = json_decode($info,true);
                    if (!empty($info) && !empty($info['uploadUrl'])) {
                        $info = BackblazeConnect(array('apiUrl' => $info['uploadUrl'],
                                                       'uri' => '',
                                                       'file' => $filename,
                                                       'authorizationToken' => $info['authorizationToken'],
                                                        ));

                        if (!empty($info)) {
                            $info = json_decode($info,true);
                            if (!empty($info) && !empty($info['accountId'])) {
                                if (empty($config['delete'])) {
                                    @unlink($filename);
                                }
                                return true;
                            }
                        }
                    }
                }
            }
        }
        return false;
    } else {
        include_once('assets/libs/s3-lib/vendor/autoload.php');
        $s3Config = (
            empty($music->config->amazone_s3_key) ||
            empty($music->config->amazone_s3_s_key) ||
            empty($music->config->region) ||
            empty($music->config->s3_bucket_name)
        );

        if ($s3Config){
            return false;
        }
        $s3 = new S3Client(array(
                'version' => 'latest',
                'region' => $music->config->region,
                'credentials' => array(
                    'key' => $music->config->amazone_s3_key,
                    'secret' => $music->config->amazone_s3_s_key
                )
            ));
        $s3->putObject(array(
            'Bucket' => $music->config->s3_bucket_name,
            'Key' => $filename,
            'Body' => fopen($filename, 'r+'),
            'ACL' => 'public-read',
            'CacheControl' => 'max-age=3153600'
        ));
        if (empty($config['delete'])) {
            if ($s3->doesObjectExist($music->config->s3_bucket_name, $filename)) {
                if (empty($config['amazon'])) {
                    @unlink($filename);
                }
                return true;
            }
        } else {
            return true;
        }
    }
}
function BackblazeConnect($args=[])
{
    global $music,$db;

    $session = curl_init($args['apiUrl'] . $args['uri']);
    $content_type = '';

    if ($args['uri'] == '/b2api/v2/b2_list_buckets') {
        $data = array("accountId" => $args['accountId']);
        $post_fields = json_encode($data);
        curl_setopt($session, CURLOPT_POSTFIELDS, $post_fields); 
        curl_setopt($session, CURLOPT_POST, true); // HTTP POST
    }
    else if ($args['uri'] == '/b2api/v2/b2_get_upload_url' || $args['uri'] == '/b2api/v2/b2_list_file_names') {
        $data = array("bucketId" => $music->config->backblaze_bucket_id);
        $post_fields = json_encode($data);
        curl_setopt($session, CURLOPT_POSTFIELDS, $post_fields); 
        curl_setopt($session, CURLOPT_POST, true); // HTTP POST
    }
    else if ($args['uri'] == '/b2api/v2/b2_delete_file_version') {
        $data = array("fileId" => $args['fileId'], "fileName" => $args['fileName']);
        $post_fields = json_encode($data);
        curl_setopt($session, CURLOPT_POSTFIELDS, $post_fields); 
        curl_setopt($session, CURLOPT_POST, true); // HTTP POST
    }
    elseif (isset($args['file']) && !empty($args['file'])) {
        $handle = fopen($args['file'], 'r');
        $read_file = fread($handle,filesize($args['file']));
        curl_setopt($session, CURLOPT_POSTFIELDS, $read_file); 
    }

    // Add post fields
    
    

    // Add headers
    $headers = array();
    
    if ($args['uri'] == '/b2api/v2/b2_authorize_account') {
        $credentials = base64_encode($music->config->backblaze_access_key_id . ":" . $music->config->backblaze_access_key);
        $headers[] = "Accept: application/json";
        $headers[] = "Authorization: Basic " . $credentials;
        curl_setopt($session, CURLOPT_HTTPGET, true);
    }
    else if (isset($args['file']) && !empty($args['file'])) {
        $headers[] = "X-Bz-File-Name: " . $args['file'];
        $headers[] = "Content-Type: " . mime_content_type($args['file']);
        $headers[] = "X-Bz-Content-Sha1: " . sha1_file($args['file']);
        $headers[] = "X-Bz-Info-Author: " . "unknown";
        $headers[] = "X-Bz-Server-Side-Encryption: " . "AES256";
        $headers[] = "Authorization: " . $args['authorizationToken'];
    }
    else{
        $headers[] = "Authorization: " . $args['authorizationToken'];
    }

    curl_setopt($session, CURLOPT_HTTPHEADER, $headers); 

    curl_setopt($session, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($session, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);  // Receive server response
    $server_output = curl_exec($session); // Let's do this!
    curl_close ($session); // Clean up
    return $server_output;
}
function create_folder($client, $folder_name, $parent_folder_id=null ){
    $folder_list = check_folder_exists($client, $folder_name );
    // if folder does not exists
    if( count( $folder_list ) == 0 ){
        $service = new Google_Service_Drive( $client );
        $folder = new Google_Service_Drive_DriveFile();

        $folder->setName( $folder_name );
        $folder->setMimeType('application/vnd.google-apps.folder');
        if( !empty( $parent_folder_id ) ){
            $folder->setParents( [ $parent_folder_id ] );
        }

        $result = $service->files->create( $folder );

        $folder_id = null;

        if( isset( $result['id'] ) && !empty( $result['id'] ) ){
            $folder_id = $result['id'];
        }

        return $folder_id;
    }
    return $folder_list[0]['id'];
}

function check_folder_exists($client, $folder_name ){
    $service = new Google_Service_Drive($client);
    $parameters['q'] = "mimeType='application/vnd.google-apps.folder' and name='$folder_name' and trashed=false";
    $files = $service->files->listFiles($parameters);
    $op = [];
    foreach( $files as $k => $file ){
        $op[] = $file;
    }
    return $op;
}
function checkIfMediaIsUploaded($media) {
    global $music, $db;
    return ($db->where('file_name', secure($media))->getValue(T_UPLOADS, "count(*)") > 0) ? true : false;
}
function PT_DeleteFromToS3($filename, $config = array()) {
    global $music;

    if ($music->config->s3_upload != 'on' && $music->config->ftp_upload != 'on' && $music->config->spaces != 'on' && $music->config->google_drive != 'on' && $music->config->wasabi_storage != 'on' && $music->config->backblaze_storage != 'on') {
        return false;
    }

    if (empty($filename)) {
        return false;
    }

    if ($music->config->ftp_upload == "on" && $music->config->ftp_username !== "" && $music->config->ftp_password !== "") {
        include_once('assets/libs/ftp/vendor/autoload.php');
        $ftp = new \FtpClient\FtpClient();
        $ftp->connect($music->config->ftp_host, false, $music->config->ftp_port);
        $login = $ftp->login($music->config->ftp_username, $music->config->ftp_password);

        if ($login) {
            if (!empty($music->config->ftp_path)) {
                if ($music->config->ftp_path != "./") {
                    $ftp->chdir($music->config->ftp_path);
                }
            }
            $file_path = substr($filename, 0, strrpos( $filename, '/'));
            $file_name = substr($filename, strrpos( $filename, '/') + 1);
            $file_path_info = explode('/', $file_path);
            $path = '';
            if (!$ftp->isDir($file_path)) {
                return false;
            }
            $ftp->chdir($file_path);
            $ftp->pasv(true);
            if ($ftp->remove($file_name)) {
                return true;
            }
        }
    } else  if ($music->config->spaces == 'on') {
        include_once('assets/libs/s3-lib/vendor/autoload.php');
        $key = $music->config->spaces_key;
        $secret = $music->config->spaces_secret;
        $space_name = $music->config->space_name;
        $region = $music->config->space_region;

        $s3 = new S3Client(array(
            'version' => 'latest',
            'endpoint' => 'https://' . $region . '.digitaloceanspaces.com',
            'region' => $region,
            'credentials' => array(
                'key' => $music->config->spaces_key,
                'secret' => $music->config->spaces_secret
            )
        ));
        $s3->deleteObject(array(
            'Bucket' => $music->config->space_name,
            'Key' => $filename
        ));
        if (!$s3->doesObjectExist($music->config->space_name, $filename)) {
            return true;
        }
    } else if ($music->config->google_drive == 'on') {
        require './assets/libs/google/vendor/autoload.php';
        try {
          $client = new Google_Client();
          $client->setClientId($music->config->google_ClientId);
          $client->setClientSecret($music->config->google_ClientSecret);
          $getAccessToken = $client->refreshToken($music->config->google_refreshToken);
          $client->setAccessToken($getAccessToken);
          $service = new Google_Service_Drive($client);
          if (strpos($filename, '/') !== FALSE) {
             return false;
          }
          $delelteFile = $service->files->delete($filename);
        } catch (Exception $e) {

        }
    } elseif ($music->config->wasabi_storage == 'on' && !empty($music->config->wasabi_bucket_name)) {
        
        include_once('assets/libs/s3-lib/vendor/autoload.php');
        $s3 = new S3Client(array(
                'version' => 'latest',
                'endpoint' => 'https://s3.' . $music->config->wasabi_bucket_region . '.wasabisys.com',
                'region' => $music->config->wasabi_bucket_region,
                'credentials' => array(
                    'key' => $music->config->wasabi_access_key,
                    'secret' => $music->config->wasabi_secret_key
                )
            ));
        $s3->deleteObject(array(
            'Bucket' => $music->config->wasabi_bucket_name,
            'Key' => $filename
        ));
        if (!$s3->doesObjectExist($music->config->wasabi_bucket_name, $filename)) {
            return true;
        }

    }
    elseif ($music->config->backblaze_storage == 'on' && !empty($music->config->backblaze_bucket_id)) {
        $info = BackblazeConnect(array('apiUrl' => 'https://api.backblazeb2.com',
                                       'uri' => '/b2api/v2/b2_authorize_account',
                                ));
        if (!empty($info)) {
            $result = json_decode($info,true);
            if (!empty($result['authorizationToken']) && !empty($result['apiUrl']) && !empty($result['accountId'])) {
                $info = BackblazeConnect(array('apiUrl' => $result['apiUrl'],
                                               'uri' => '/b2api/v2/b2_list_file_names',
                                               'authorizationToken' => $result['authorizationToken'],
                                        ));
                if (!empty($info)) {
                    $info = json_decode($info,true);
                    if (!empty($info) && !empty($info['files'])) {
                        foreach ($info['files'] as $key => $value) {
                            if ($value['fileName'] == $filename) {
                                $info = BackblazeConnect(array('apiUrl' => $result['apiUrl'],
                                                               'uri' => '/b2api/v2/b2_delete_file_version',
                                                               'authorizationToken' => $result['authorizationToken'],
                                                               'fileId' => $value['fileId'],
                                                               'fileName' => $value['fileName'],
                                                        ));
                                return true;
                            }
                        }
                    }
                }
            }
        }
    } else {
        include_once('assets/libs/s3-lib/vendor/autoload.php');
        $s3Config = (
            empty($music->config->amazone_s3_key) ||
            empty($music->config->amazone_s3_s_key) ||
            empty($music->config->region) ||
            empty($music->config->s3_bucket_name)
        );

        if ($s3Config){
            return false;
        }
        $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => $music->config->region,
            'credentials' => [
                'key'    => $music->config->amazone_s3_key,
                'secret' => $music->config->amazone_s3_s_key,
            ]
        ]);

        $s3->deleteObject([
            'Bucket' => $music->config->s3_bucket_name,
            'Key'    => $filename,
        ]);

        if (!$s3->doesObjectExist($music->config->s3_bucket_name, $filename)) {
            return true;
        }
    }
}
function albumData($album_id = 0, $createSession = true, $fetch = true, $getAlbumSongs = true) {
    global $music, $db;
    if (empty($album_id)) {
        return false;
    }
    if ($fetch == true) {
        if (!is_numeric($album_id) || $album_id <= 0) {
            return false;
        }
        $track_id = secure($album_id);
        $getAlbum = $db->where('id', $album_id)->getOne(T_ALBUMS);
    } else {
        $getAlbum = $album_id;
    }

    if (empty($getAlbum)) {
        return false;
    }

    if (isBlockedFromOneSide($getAlbum->user_id)) {
        return false;
    }

    $getAlbum->thumbnail_original = $getAlbum->thumbnail;
    $getAlbum->thumbnail = getMedia($getAlbum->thumbnail);
    $getAlbum->publisher = userData($getAlbum->user_id);
    $getAlbum->description = markUp($getAlbum->description);
    $getAlbum->time_formatted = time_Elapsed_String($getAlbum->time);
    $getAlbum->url = getLink("album/$getAlbum->album_id");
    $getAlbum->category_name = (!empty($music->categories->{$getAlbum->category_id})) ? $music->categories->{$getAlbum->category_id} : lang('Other');

    if ($createSession == true) {
        $new_hash = $hash = md5(time());
        setcookie("session_hash", $hash, 0, "/");
    } else {
        if (isset($_COOKIE['session_hash'])) {
            $new_hash = $_COOKIE['session_hash'];
        } else {
            $new_hash = $hash = md5(time());
            setcookie("session_hash", $hash, 0, "/");
        }
    }
    $getAlbum->is_purchased = 0;
    if (IS_LOGGED == true) {
        $getAlbum->IsOwner = ($music->user->id == $getAlbum->user_id) ? true : false;
        if ($getAlbum->user_id !== $music->user->id && isUserBuyAlbum($getAlbum->id) && $getAlbum->price > 0) {
            $getAlbum->is_purchased = 1;
        }
    }

    $getAlbum->songs = [];
    if ($getAlbumSongs == true) {
        $songs = $db->where('album_id',$album_id)->get(T_SONGS,null,array('id'));
        foreach ($songs as $key => $song){
            $getAlbum->songs[$song->id] = songData($song->id);
        }
    }
    $getAlbum->count_songs = $db->where('album_id',$album_id)->getValue(T_SONGS,'COUNT(*)');
    return $getAlbum;
}
function deleteSong($id) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }

    if (empty($id)) {
        return false;
    }

    $id = secure($id);

    $getSong = songData($id);

    if ($getSong->album_id == 0) {
        // delete the thumbnnail if the song is not album.
        $delete_thumbnail       = @unlink($getSong->thumbnail_original);
        PT_DeleteFromToS3($getSong->thumbnail_original);
    }

    $delete_songs           = @unlink($getSong->audio_location_original);
    
    $delete_dark_wave       = @unlink($getSong->dark_wave);
    $delete_light_wave      = @unlink($getSong->light_wave);

    $delete_thumbnail       = @unlink(str_replace('_dark.png','_demo_day.png', $getSong->dark_wave));
    $delete_dark_wave       = @unlink(str_replace('_dark.png','_demo_light.png', $getSong->dark_wave));
    $delete_light_wave      = @unlink(str_replace('_dark.png','_demo_dark.png', $getSong->dark_wave));
    $delete_day_wave        = @unlink(str_replace('_dark.png','_day.png',$getSong->dark_wave));

    //Delete demo track
    $delete_demo_track      = @unlink($getSong->demo_track);
    PT_DeleteFromToS3($getSong->demo_track);
    PT_DeleteFromToS3($getSong->audio_location_original);
    PT_DeleteFromToS3($getSong->audio_location);
   
    PT_DeleteFromToS3($getSong->dark_wave);
    PT_DeleteFromToS3($getSong->light_wave);
    PT_DeleteFromToS3(str_replace('_dark.png','_day.png', $getSong->dark_wave));
    PT_DeleteFromToS3(str_replace('_dark.png','_demo_day.png', $getSong->dark_wave));
    PT_DeleteFromToS3(str_replace('_dark.png','_demo_light.png', $getSong->dark_wave));
    PT_DeleteFromToS3(str_replace('_dark.png','_demo_dark.png', $getSong->dark_wave));

    $delete = $db->where('track_id', $id)->delete(T_LIKES);
    $delete = $db->where('track_id', $id)->delete(T_DISLIKES);
    $delete = $db->where('track_id', $id)->delete(T_NOTIFICATION);
    $delete = $db->where('track_id', $id)->delete(T_VIEWS);
    $delete = $db->where('track_id', $id)->delete(T_ACTIVITIES);
    $delete = $db->where('track_id', $id)->delete(T_COMMENTS);
    $delete = $db->where('track_id', $id)->delete(T_DOWNLOADS);
    $delete = $db->where('track_id', $id)->delete(T_FOV);
    $delete = $db->where('track_id', $id)->delete(T_PLAYLIST_SONGS);
    // $delete = $db->where('track_id', $id)->delete(T_PURCHAES);
    $delete = $db->where('track_id', $id)->delete(T_REPORTS);
    $delete = $db->where('track_id', $id)->delete(T_COPYRIGHTS);
    $delete = $db->where('id', $id)->delete(T_SONGS);
    $delete = $db->where('track_id', $id)->delete(T_REVIEWS);

    if (!empty($getSong->album_id)) {
        $getAlbumPrice = $db->where('id', $getSong->album_id)->getValue(T_ALBUMS, 'price');
        $countSongs = $db->where('album_id', $getSong->album_id)->getValue(T_SONGS, 'count(*)');
        if ($getAlbumPrice > 0) {
            $getAlbumPrice = number_format($getAlbumPrice / $countSongs);
        }
        $db->where('album_id', $getSong->album_id)->update(T_SONGS, ['price' => $getAlbumPrice]);
    }
    runPlugin('AfterSongDeleted', ["id" => $id]);
    return true;
}
function number_format_mm($number = 0) {
    global $music, $db;

    if ($music->language == 'english') {
        $number = thousandsCurrencyFormat($number);
    } else {
        $number = number_format($number);
    }
    return $number;
}
function getBlogComment($id = 0, $fetch = true) {
    global $music, $db;
    if (empty($id)) {
        return false;
    }

    if ($fetch == true) {
        $id = secure($id);
        if (!is_numeric($id) || $id < 0) {
            return false;
        }
        $getComment = $db->where('id', $id)->getOne(T_BLOG_COMMENTS);
    } else {
        $getComment = $id;
    }

    if (empty($getComment)) {
        return false;
    }
    $getComment->posted = time_Elapsed_String($getComment->time);
    $getComment->value = markUp($getComment->value);
    $getComment->owner = false;
    if (IS_LOGGED) {
        if (isAdmin() || $getComment->user_id == $music->user->id) {
            $getComment->owner = true;
        }
        $comment = [
            'comment_user_id' => $getComment->user_id,
            'article_id' => $getComment->article_id,
            'user_id' => $music->user->id,
            'comment_id' => $getComment->id
        ];
        if(BlogLikeExists($comment) === true ) {
            $getComment->IsLikedComment = true;
        }else{
            $getComment->IsLikedComment = false;
        }
        $getComment->countLiked = BlogcountCommentLikes($getComment->id);
    }
    return $getComment;
}
function CheckStreamUrl($streamUrl){
    $streamExist = [];
    $file_headers_1 = @get_headers($streamUrl);
    foreach ($file_headers_1 as $key => $value) {
        if (strpos($value, '200 OK') === false) {

        } else {
            $streamExist[] = 1;
        }
    }
    if(count($streamExist) > 0){
        return true;
    }else{
        return false;
    }
}
function getCommentReplay($id = 0, $fetch = true) {
    global $music, $db;
    if (empty($id)) {
        return false;
    }

    if ($fetch == true) {
        $id = secure($id);
        if (!is_numeric($id) || $id < 0) {
            return false;
        }
        $getComment = $db->where('id', $id)->getOne(T_COMMENT_REPLIES);
    } else {
        $getComment = $id;
    }

    if (empty($getComment)) {
        return false;
    }
    $getComment->org_posted = $getComment->time;
    $getComment->posted = time_Elapsed_String($getComment->time);
    $getComment->value = markUp($getComment->value);
    $getComment->owner = false;
    $comment  = $db->where('id',$getComment->comment_id)->getOne(T_COMMENTS,array('track_id'));
    $getComment->audio_id = 0;
    if (!empty($comment->track_id)) {
        $songID = songData($comment->track_id);
        $getComment->audio_id = $songID->audio_id;
    }

    $comment_text = $getComment->value;
    $mention_regex = '/@\[([0-9]+)\]/i';
    if (preg_match_all($mention_regex, $comment_text, $matches)) {
        foreach ($matches[1] as $match) {
            $match = secure($match);
            $match_user = userData($match);
            $match_search = '@[' . $match . ']';
            if (isset($match_user->id)) {
                $match_replace = '<a href="' . $music->config->site_url . '/' . $match_user->username . '" data-load="'.$match_user->username.'">@' . $match_user->username . '</a>';
                $comment_text = str_replace($match_search, $match_replace, $comment_text);
            }
        }
    }
    $getComment->value =  $comment_text;

    $getComment->commentUser = userData($getComment->user_id);

    if (IS_LOGGED) {
        if (isAdmin() || $getComment->user_id == $music->user->id) {
            $getComment->owner = true;
        }
        $comment = [
            'comment_user_id' => $getComment->user_id,
            'track_id' => (!empty($comment->track_id) ? $comment->track_id : 0) ,
            'user_id' => $music->user->id,
            'comment_id' => $getComment->id
        ];
//        if(LikeExists($comment) === true ) {
//            $getComment->IsLikedComment = true;
//        }else{
//            $getComment->IsLikedComment = false;
//        }
//        $getComment->countLiked = countCommentLikes($getComment->id);
    }
    return $getComment;
}
function getComment($id = 0, $fetch = true) {
    global $music, $db;
    if (empty($id)) {
        return false;
    }

    if ($fetch == true) {
        $id = secure($id);
        if (!is_numeric($id) || $id < 0) {
            return false;
        }
        $getComment = $db->where('id', $id)->getOne(T_COMMENTS);
    } else {
        $getComment = $id;
    }

    if (empty($getComment)) {
        return false;
    }
    $getComment->org_posted = $getComment->time;
    $getComment->posted = time_Elapsed_String($getComment->time);
    $getComment->secondsFormated = '';
    if (!empty($getComment->songseconds)) {
        $getComment->secondsFormated = gmdate("i:s", $getComment->songseconds);
    }

    $getComment->value = markUp($getComment->value);
    $getComment->owner = false;

    $comment_text = $getComment->value;
    $mention_regex = '/@\[([0-9]+)\]/i';
    if (preg_match_all($mention_regex, $comment_text, $matches)) {
        foreach ($matches[1] as $match) {
            $match = secure($match);
            $match_user = userData($match);
            $match_search = '@[' . $match . ']';
            if (isset($match_user->id)) {
                $match_replace = '<a href="' . $music->config->site_url . '/' . $match_user->username . '" data-load="'.$match_user->username.'">@' . $match_user->username . '</a>';
                $comment_text = str_replace($match_search, $match_replace, $comment_text);
            }
        }
    }
    $getComment->value = $comment_text;

    $getComment->replies = array();
    $replies = $db->where('comment_id', $getComment->id)->get(T_COMMENT_REPLIES, null, array('*'));
    foreach($replies as $key => $replay) {
        $getComment->replies[] = getCommentReplay($replay->id);
    }
    $comment_report = [
        'user_id' => (IS_LOGGED) ? $music->user->id : 0,
        'comment_id' => $getComment->id
    ];
    $getComment->is_reported = CommentReportExists($comment_report);

    if (IS_LOGGED) {
        if (empty($getComment->track_id)) {
            $getComment->track_id = 0;
        }
        if (isAdmin() || $getComment->user_id == $music->user->id) {
            $getComment->owner = true;
        }
        $comment = [
            'comment_user_id' => $getComment->user_id,
            'track_id' => $getComment->track_id,
            'user_id' => $music->user->id,
            'comment_id' => $getComment->id
        ];
        if(LikeExists($comment) === true ) {
            $getComment->IsLikedComment = true;
        }else{
            $getComment->IsLikedComment = false;
        }
        $getComment->countLiked = countCommentLikes($getComment->id);
    }
    return $getComment;
}


//function _getCategories() {
//    global $music, $db, $lang_array;
//
//    $getCategories = $db->get(T_CATEGORIES);
//    $cateogryArray = [];
//    foreach ($getCategories as $key => $value) {
//        $cateogryArray[$value->id] = $lang_array["cateogry_$value->id"];
//    }
//    return $cateogryArray;
//}
function getCategories($justname = true) {
    global $music, $db, $lang_array;
    $getCategories = $db->orderBy('id','DESC')->get(T_CATEGORIES);//$db->where('type','category')->get(T_LANGS,null,array('*'));
    $cateogryArray = [];
    foreach ($getCategories as $key => $value) {
        if($justname === false) {
            $cateogryArray[$value->id] = $value;
            $cateogryArray[$value->id]->cateogry_name = $db->arrayBuilder()->where('lang_key', 'cateogry_' . $value->id)->getOne(T_LANGS, $music->language)[$music->language];
        }else{
            $cateogryArray[$value->id] = $db->arrayBuilder()->where('lang_key', 'cateogry_' . $value->id)->getOne(T_LANGS, $music->language)[$music->language];
        }
    }
    return $cateogryArray;
}
function getCategoryInfo($id = 0) {
    global $music, $db;

    if (empty($id)) {
        return false;
    }
    $id = secure($id);
    $category = $db->where('id', $id)->getOne(T_CATEGORIES);
    if (empty($category)) {
        return false;
    }
    $category->background_thumb = (empty($category->background_thumb)) ? $music->config->theme_url . '/img/crowd.jpg' : $category->background_thumb;
    return $category;
}


function createWalletPalLink() {
    global $music, $db;
    if (!isset($_GET['price'])) {
        return false;
    }
    $price = (int)secure($_GET['price']);
    include_once('assets/includes/paypal.php');


    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{
      "intent": "CAPTURE",
      "purchase_units": [
            {
                "items": [
                    {
                        "name": "Wallet Replenishment",
                        "description":  "Pay For ' . $music->config->name.'",
                        "quantity": "1",
                        "unit_amount": {
                            "currency_code": "'.$music->config->paypal_currency.'",
                            "value": "'.$price.'"
                        }
                    }
                ],
                "amount": {
                    "currency_code": "'.$music->config->paypal_currency.'",
                    "value": "'.$price.'",
                    "breakdown": {
                        "item_total": {
                            "currency_code": "'.$music->config->paypal_currency.'",
                            "value": "'.$price.'"
                        }
                    }
                }
            }
        ],
        "application_context":{
            "shipping_preference":"NO_SHIPPING",
            "return_url": "'.$music->config->site_url.'/wallet-purchase/true?price='.$price.'",
            "cancel_url": "'.$music->config->site_url.'/wallet-purchase/false"
        }
    }');

    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: Bearer '.$music->paypal_access_token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);
    $result = json_decode($result);
    if (!empty($result) && !empty($result->links) && !empty($result->links[1]) && !empty($result->links[1]->href)) {
        $data = array(
            'type' => 'SUCCESS',
            'url' => $result->links[1]->href
        );
        return $data;
    }
    elseif(!empty($result->message)){
        $data = array(
            'type' => 'ERROR',
            'details' => $result->message
        );
        return $data;
    }
}

function isTrackPurchased($track_id = 0, $user_id = 0) {
    global $db, $music;
    if (IS_LOGGED == false) {
        return false;
    }

    if (empty($track_id)) {
        return false;
    }

    if (empty($user_id)) {
        $user_id = $music->user->id;
    }

    $user_id = secure($user_id);
    $track_id = secure($track_id);

    return ($db->where('track_id', $track_id)->where('user_id', $user_id)->getValue(T_PURCHAES, 'count(*)') > 0) ? true : false;
}
function random_color_part() {
    return str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT);
}
function random_color() {
    return random_color_part() . random_color_part() . random_color_part();
}
function isBlockedFromOneSide($user_id = 0) {
    global $db, $music;
    if (IS_LOGGED == false) {
        return false;
    }

    if (empty($user_id)) {
        return false;
    }

    $user_id = secure($user_id);

    if ($user_id == $music->user->id) {
        return false;
    }

    return ($db->where('blocked_id', $music->user->id)->where('user_id', $user_id)->getValue(T_BLOCKS, 'count(*)') > 0) ? true : false;
}
function isBlocked($user_id = 0) {
     global $db, $music;
    if (isLogged() == false) {
        return false;
    }

    if (empty($user_id)) {
        return false;
    }

    $user_id = secure($user_id);

    if ($user_id == $music->user->id) {
        return false;
    }

    return ($db->where('user_id', $music->user->id)->where('blocked_id', $user_id)->getValue(T_BLOCKS, 'count(*)') > 0 || $db->where('blocked_id', $music->user->id)->where('user_id', $user_id)->getValue(T_BLOCKS, 'count(*)') > 0) ? true : false;
}
function isUserBuyAlbum($albumId){
    global $music,$db;
    if(empty($albumId) || !isset($music->user->id)) return false;

    $songs = $db->where('album_id',$albumId)->get(T_SONGS,null,array('id'));
    $purchase = false;
    foreach ($songs as $key => $song){
        $purchase = isTrackPurchased($song->id, $music->user->id);
    }
    return $purchase;
}
//chat
function GetMessagesUserList($data = array(),$limit = 20,$offset=0) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }

    $db->where("user_one = {$music->user->id}");


    if (isset($data['keyword'])) {
        $keyword = Secure($data['keyword']);
        $db->where("user_two IN (SELECT id FROM users WHERE username LIKE '%$keyword%' OR `name` LIKE '%$keyword%')");
    }
    if (!empty($offset)) {
        $db->where('time',secure($offset),'<');
    }

    $users = $db->orderBy('time', 'DESC')->get(T_CHATS, $limit);

    $return_methods = array('obj', 'html');

    $return_method = 'obj';
    if (!empty($data['return_method'])) {
        if (in_array($data['return_method'], $return_methods)) {
            $return_method = $data['return_method'];
        }
    }

    $users_html = '';
    $data_array = array();
    foreach ($users as $key => $user) {
        $chat_time = $user->time;
        $user = UserData($user->user_two);
        if (!empty($user)) {
            $get_last_message = $db->where("((from_id = {$music->user->id} AND to_id = $user->id AND `from_deleted` = '0') OR (from_id = $user->id AND to_id = {$music->user->id} AND `to_deleted` = '0'))")->orderBy('id', 'DESC')->getOne(T_MESSAGES);
            $get_count_seen = $db->where("to_id = {$music->user->id} AND from_id = $user->id AND `from_deleted` = '0' AND seen = 0")->orderBy('id', 'DESC')->getValue(T_MESSAGES, 'COUNT(*)');
            if ($return_method == 'html') {
                $music->isMessageActive = false;
                if (!empty($data['chat_id'])) {
                    $music->isMessageActive = ($data['chat_id'] == $user->id) ? true : false;
                }
                $users_html .= LoadPage("messages/ajax/user-list", array(
                    'ID' => $user->id,
                    'AVATAR' => $user->avatar,
                    'NAME' => $user->name,
                    'LAST_MESSAGE' => (!empty($get_last_message->text)) ? markUp( strip_tags($get_last_message->text) ) : '',
                    'COUNT' => (!empty($get_count_seen)) ? $get_count_seen : '',
                    'USERNAME' => $user->username,
                    'TIME' => time_Elapsed_String($get_last_message->time),
                    'TTIME' => $chat_time,
                ));
            } else {
                $data_array[$key]['user'] = $user;
                $data_array[$key]['get_count_seen'] = $get_count_seen;
                $data_array[$key]['get_last_message'] = $get_last_message;
                $data_array[$key]['chat_time'] = $chat_time;
            }
        }
    }
    $users_obj = (!empty($data_array)) ? ToObject($data_array) : array();
    return (!empty($users_html)) ? $users_html : $users_obj;
}
function EditMarkup($text, $link = true) {
    if ($link == true) {
        $link_search = '/\[a\](.*?)\[\/a\]/i';
        if (preg_match_all($link_search, $text, $matches)) {
            foreach ($matches[1] as $match) {
                $match_decode     = urldecode($match);
                $match_decode_url = $match_decode;
                $count_url        = mb_strlen($match_decode);
                $match_url        = $match_decode;
                if (!preg_match("/http(|s)\:\/\//", $match_decode)) {
                    $match_url = 'http://' . $match_url;
                }
                $text = str_replace('[a]' . $match . '[/a]', $match_decode_url, $text);
            }
        }
    }
    return $text;
}
//function Markup($text, $link = true) {
//    if ($link == true) {
//        $link_search = '/\[a\](.*?)\[\/a\]/i';
//        if (preg_match_all($link_search, $text, $matches)) {
//            foreach ($matches[1] as $match) {
//                $match_decode     = urldecode($match);
//                $match_decode_url = $match_decode;
//                $count_url        = mb_strlen($match_decode);
//                if ($count_url > 50) {
//                    $match_decode_url = mb_substr($match_decode_url, 0, 30) . '....' . mb_substr($match_decode_url, 30, 20);
//                }
//                $match_url = $match_decode;
//                if (!preg_match("/http(|s)\:\/\//", $match_decode)) {
//                    $match_url = 'http://' . $match_url;
//                }
//                $text = str_replace('[a]' . $match . '[/a]', '<a href="' . strip_tags($match_url) . '" target="_blank" class="hash" rel="nofollow">' . $match_decode_url . '</a>', $text);
//            }
//        }
//    }
//    return $text;
//}
function GetMessageData($id = 0) {
    global $music, $db;
    if (empty($id) || !IS_LOGGED) {
        return false;
    }
    $fetched_data = $db->where('id', Secure($id))->getOne(T_MESSAGES);
    if (!empty($fetched_data)) {
        $fetched_data->image = '';
        $fetched_data->full_image = '';
        $link_search = '/\[img\](.*?)\[\/img\]/i';
        if (preg_match_all($link_search, $fetched_data->text, $matches)) {
            foreach ($matches[1] as $match) {
                $match_decode     = urldecode($match);
                $fetched_data->image = str_replace('[img]' . $match . '[/img]', strip_tags($match_decode), $fetched_data->text);
                $fetched_data->full_image = getMedia(strip_tags($fetched_data->image));
            }
        }
        $fetched_data->API_position  = 'left';
        if ($fetched_data->from_id == $music->user->id) {
            $fetched_data->API_position  = 'right';
        }
        $fetched_data->API_type = 'text';
        if (!empty($fetched_data->full_image)) {
            $fetched_data->API_type = 'image';
        }

        $fetched_data->text = Markup($fetched_data->text);
        return $fetched_data;
    }
    return false;
}
function GetMessages($id, $data = array(),$limit = 50) {
    global $music, $db;
    if (IS_LOGGED == false) {
        return false;
    }

    $chat_id = Secure($id);

    if (!empty($data['chat_user'])) {
        $chat_user = $data['chat_user'];
    } else {
        $chat_user = UserData($chat_id);
    }


    $where = "((`from_id` = {$chat_id} AND `to_id` = {$music->user->id} AND `to_deleted` = '0') OR (`from_id` = {$music->user->id} AND `to_id` = {$chat_id} AND `from_deleted` = '0'))";

    // count messages
    $db->where($where);
    if (!empty($data['last_id'])) {
        $data['last_id'] = Secure($data['last_id']);
        $db->where('id', $data['last_id'], '>');
    }

    if (!empty($data['first_id'])) {
        $data['first_id'] = Secure($data['first_id']);
        $db->where('id', $data['first_id'], '<');
    }

    $count_user_messages = $db->getValue(T_MESSAGES, "count(*)");
    $count_user_messages = $count_user_messages - $limit;
    if ($count_user_messages < 1) {
        $count_user_messages = 0;
    }

    // get messages
    $db->where($where);
    if (!empty($data['last_id'])) {
        $db->where('id', $data['last_id'], '>');
    }

    if (!empty($data['first_id'])) {
        $db->where('id', $data['first_id'], '<');
    }

    $get_user_messages = $db->orderBy('id', 'ASC')->get(T_MESSAGES, array($count_user_messages, $limit));

    $messages_html = '';

    $return_methods = array('obj', 'html');

    $return_method = 'obj';
    if (!empty($data['return_method'])) {
        if (in_array($data['return_method'], $return_methods)) {
            $return_method = $data['return_method'];
        }
    }

    $update_seen = array();

    foreach ($get_user_messages as $key => $message) {
        if ($return_method == 'html') {
            $message_type = 'incoming';
            if ($message->from_id == $music->user->id) {
                $message_type = 'outgoing';
            }
            $messages_html .= LoadPage("messages/ajax/$message_type", array(
                'ID' => $message->id,
                'AVATAR' => $chat_user->avatar,
                'NAME' => $chat_user->name,
                'TEXT' => MarkUp($message->text)
            ));
        }
        if ($message->seen == 0 && $message->to_id == $music->user->id) {
            $update_seen[] = $message->id;
        }
    }

    if (!empty($update_seen)) {
        $update_seen = implode(',', $update_seen);
        $update_seen = $db->where("id IN ($update_seen)")->update(T_MESSAGES, array('seen' => time()));
    }

    return (!empty($messages_html)) ? $messages_html : $get_user_messages;
}
function GetMessageButton($username = '') {
    global $music, $db, $lang;
    if (empty($username)) {
        return false;
    }
    if (IS_LOGGED == false) {
        return false;
    }
//    if ($username == $music->user->username) {
//        return false;
//    }
    $button_text  = $lang->message;
    $button_icon  = 'plus-square';
    $button_class = 'subscribe';
    return LoadPage('buttons/message', array(
        'BUTTON' => $button_class,
        'ICON' => $button_icon,
        'TEXT' => $button_text,
        'USERNAME' => $username,
    ));
}
function GetJsonFriends(){
    global $music,$db;
    $users = $db->rawQuery('SELECT
                              followers.following_id,
                              users.username,
                              users.name
                            FROM
                              users
                              INNER JOIN followers ON (users.id = followers.following_id)
                            WHERE
                              following_id NOT IN (SELECT blocked_id FROM blocks WHERE user_id =  ' . $music->user->id .') AND
                              followers.follower_id = ' . $music->user->id);
    $user_data = [];
    foreach ($users as $key => $value) {
        $user_data[$key]['id'] = $value->following_id;
        $user_data[$key]['text'] = ( !empty($value->name) ) ? $value->name : $value->username;
    }

    echo json_encode($user_data);
}
function filter_string_polyfill($string)
{
    $str = preg_replace('/\x00|<[^>]*>?/', '', $string);
    return str_replace(["'", '"'], ['&#39;', '&#34;'], $str);
}
function FilterStripTags($string='')
{
    return filter_string_polyfill(strip_tags($string));
}
function echoOGTrackTags(){
    global $music;
    echo '<link rel="canonical" href="'.$music->config->site_url.'" />';
    echo '<link rel="home" href="'.$music->config->site_url.'" />';
    foreach ($music->langs as $key => $value) {
        $iso = '';
        if (!empty($music->iso[$value])) {
            $iso = $music->iso[$value];
        }
        echo '<link rel="alternate" href="'.$music->config->site_url.'?lang='.$value.'" hreflang="'.$iso.'" />';
    }
    if($music->site_pagename == 'track') {
        echo '<meta property="og:title" content="' . FilterStripTags($music->songData->title) . '">';
        echo '<meta property="og:image" content="' . $music->songData->thumbnail . '">';
        echo '<meta property="og:image:width" content="500">';
        echo '<meta property="og:image:height" content="500">';
        echo '<meta property="og:description" content="' . FilterStripTags($music->songData->org_description) . '">';
        echo '<meta property="og:url" content="'.$music->songData->url.'" />';
        echo '<script type="application/ld+json">{"@context": "https://schema.org","@type": "AudioObject","contentUrl": "'.$music->songData->audio_location.'", "description": "'.FilterStripTags($music->songData->org_description).'","duration": "'.$music->songData->duration.'","encodingFormat": "audio/mpeg","name": "'.FilterStripTags($music->songData->title).'"}</script>';
    }else if($music->site_pagename == 'playlist') {
        echo '<meta property="og:title" content="' . FilterStripTags($music->playlist->name) . '">';
        echo '<meta property="og:image" content="' . getMedia( $music->playlist->thumbnail ) .'">';
        echo '<meta property="og:image:width" content="500">';
        echo '<meta property="og:image:height" content="500">';
        echo '<meta property="og:description" content="' . FilterStripTags($music->playlist->name) . '">';
        echo '<meta property="og:url" content="'.$music->playlist->url.'" />';
    }else if($music->site_pagename == 'album') {
        echo '<meta property="og:title" content="' . FilterStripTags($music->albumData->title) . '">';
        echo '<meta property="og:image" content="' . getMedia( $music->albumData->thumbnail ) .'">';
        echo '<meta property="og:image:width" content="500">';
        echo '<meta property="og:image:height" content="500">';
        echo '<meta property="og:description" content="' . FilterStripTags($music->albumData->description) . '">';
        echo '<meta property="og:url" content="'.$music->config->site_url.'/album/'.$music->albumData->album_id.'" />';
    }else if($music->site_pagename == 'blog_article') {
        echo '<meta property="og:title" content="' . FilterStripTags($music->articleData['title']). '">';
        echo '<meta property="og:image" content="' . getMedia( $music->articleData['thumbnail'] ) .'">';
        echo '<meta property="og:image:width" content="500">';
        echo '<meta property="og:image:height" content="500">';
        echo '<meta property="og:description" content="' . FilterStripTags($music->articleData['description']) . '">';
        echo '<meta property="og:url" content="'.$music->articleData['url'].'" />';
    }elseif ($music->site_pagename == 'event' && !empty($music->event)) {
        echo '<meta property="og:title" content="' . FilterStripTags($music->event->name). '">';
        echo '<meta property="og:image" content="' . $music->event->image .'">';
        echo '<meta property="og:image:width" content="500">';
        echo '<meta property="og:image:height" content="500">';
        echo '<meta property="og:description" content="' . FilterStripTags($music->event->desc) . '">';
        echo '<meta property="og:url" content="'.$music->event->url.'" />';
    }elseif ($music->site_pagename == 'user' && !empty($music->userData)) {
        echo '<meta property="og:title" content="' . FilterStripTags($music->userData->name). '">';
        echo '<meta property="og:image" content="' . $music->userData->avatar .'">';
        echo '<meta property="og:image:width" content="500">';
        echo '<meta property="og:image:height" content="500">';
        echo '<meta property="og:description" content="' . FilterStripTags($music->userData->about) . '">';
        echo '<meta property="og:url" content="'.$music->userData->url.'" />';
    }elseif ($music->site_pagename == 'product' && !empty($music->product)) {
        echo '<meta property="og:title" content="' . FilterStripTags($music->product->title). '">';
        echo '<meta property="og:image" content="' . $music->product->images[0]['image'] .'">';
        echo '<meta property="og:image:width" content="500">';
        echo '<meta property="og:image:height" content="500">';
        echo '<meta property="og:description" content="' . FilterStripTags($music->product->desc) . '">';
        echo '<meta property="og:url" content="'.$music->product->url.'" />';
    }
    else{
        echo '<meta property="og:title" content="' . FilterStripTags($music->config->title) . '">';
        echo '<meta property="og:image" content="' . $music->config->theme_url .'/img/logo.png">';
        echo '<meta property="og:image:width" content="500">';
        echo '<meta property="og:image:height" content="500">';
        echo '<meta property="og:description" content="' . FilterStripTags($music->config->description) . '">';
        echo '<meta property="og:url" content="'.$music->config->site_url.'" />';
    }
}
function Sql_Result($res, $row = 0, $col = 0) {
    $numrows = mysqli_num_rows($res);
    if ($numrows && $row <= ($numrows - 1) && $row >= 0) {
        mysqli_data_seek($res, $row);
        $resrow = (is_numeric($col)) ? mysqli_fetch_row($res) : mysqli_fetch_assoc($res);
        if (isset($resrow[$col])) {
            return $resrow[$col];
        }
    }
    return false;
}
function UserIdFromUsername($username) {
    global $sqlConnect;
    if (empty($username)) {
        return false;
    }
    $username = Secure($username);
    $query    = mysqli_query($sqlConnect, "SELECT `id` FROM " . T_USERS . " WHERE `username` = '{$username}'");
    return Sql_Result($query, 0, 'id');
}
function GetUsersByName($name = '', $friends = false, $limit = 25) {
    global $sqlConnect, $music;
    if (isLogged() == false || !$name) {
        return false;
    }
    $user        = $music->user->id;
    $name        = Secure($name);
    $data        = array();
    $sub_sql     = "";
    $t_users     = T_USERS;
    $t_followers = T_FOLLOWERS;
    if ($friends == true) {
        $sub_sql = "
        AND ( `id` IN (SELECT `follower_id` FROM $t_followers WHERE `follower_id` <> {$user})  OR
        `id` IN (SELECT `following_id` FROM $t_followers WHERE  `following_id` <> {$user}))";
    }
    $limit_text = '';
    if (!empty($limit) && is_numeric($limit)) {
        $limit      = Secure($limit);
        $limit_text = 'LIMIT ' . $limit;
    }
    $sql   = "SELECT `id` FROM " . T_USERS . " WHERE `id` <> {$user} AND `username`  LIKE '%$name%' {$sub_sql} $limit_text";
    $query = mysqli_query($sqlConnect, $sql);
    while ($fetched_data = mysqli_fetch_assoc($query)) {
        $data[] = UserData($fetched_data['id']);
    }
    return $data;
}
function RegisterAdminNotification($registration_data = array()) {
    global $sqlConnect, $music;
    if (isLogged() == false || empty($registration_data) || empty($registration_data['text'])) {
        return false;
    }
    if (empty($registration_data['full_link']) || empty($registration_data['recipients'])) {
        return false;
    }
    if (!is_array($registration_data['recipients']) || count($registration_data['recipients']) < 1) {
        return false;
    }
    $text  = $registration_data['text'];
    $link  = $registration_data['full_link'];
    $admin = $music->user->id;
    $time  = time();
    $sql   = "INSERT INTO " . T_NOTIFICATION . " (`notifier_id`,`recipient_id`,`type`,`text`,`url`,`time`) VALUES ";
    $val   = array();

    foreach ($registration_data['recipients'] as $user_id) {
        if ($admin != $user_id) {
            $val[] = "('$admin','$user_id','admin_notification','$text','$link','$time')";
        }
    }

    $query = mysqli_query($sqlConnect, ($sql . implode(',', $val)));
    return $query;
}
function GetUserIds() {
    global $sqlConnect, $music;
    if (isLogged() == false ) {
        return false;
    }
    $data  = array();
    $admin = $music->user->id;
    $query = mysqli_query($sqlConnect, "SELECT `id` FROM " . T_USERS . " WHERE active = '1' AND `id` <> {$admin}");
    while ($fetched_data = mysqli_fetch_assoc($query)) {
        $data[] = $fetched_data['id'];
    }
    return $data;
}
function RegisterFollow($following_id = 0, $followers_id = 0) {
    global $music, $sqlConnect;


    if (!isset($following_id) or empty($following_id) or !is_numeric($following_id) or $following_id < 1) {
        return false;
    }
    if (!is_array($followers_id)) {
        $followers_id = array($followers_id);
    }
    foreach ($followers_id as $follower_id) {
        if (!isset($follower_id) or empty($follower_id) or !is_numeric($follower_id) or $follower_id < 1) {
            continue;
        }
        if (IsBlocked($following_id)) {
            continue;
        }
        $following_id = Secure($following_id);
        $follower_id  = Secure($follower_id);
        if (IsFollowing($following_id, $follower_id) === true) {
            continue;
        }
        $follower_data  = userData($follower_id);
        $following_data = userData($following_id);
        if (empty($follower_data->id) || empty($following_data->id)) {
            continue;
        }

        if ($following_id == $follower_id){
            continue;
        }

        $query = mysqli_query($sqlConnect, " INSERT INTO " . T_FOLLOWERS . " (`following_id`,`follower_id`) VALUES ({$following_id},{$follower_id})");
        if ($query) {
            $create_notification = createNotification([
                'notifier_id' => $follower_id,
                'recipient_id' => $following_id,
                'type' => 'follow_user',
            ]);
        }
    }
    return true;
}
function AutoFollow($user_id = 0) {
    global $music, $db;
    if (empty($user_id)) {
        return false;
    }
    if (!is_numeric($user_id) || $user_id == 0) {
        return false;
    }
    $get_users = explode(',', $music->config->auto_friend_users);
    if (!empty($get_users)) {
        foreach ($get_users as $key => $user) {
            $user = trim($user);
            $user = Secure($user);
            $getUserID = UserIdFromUsername($user);
            if (!empty($getUserID)) {
                $registerFollow = RegisterFollow($getUserID, $user_id);
            }
        }
        return true;
    } else {
        return false;
    }
}
function UserExists($username) {
    global $sqlConnect;
    if (empty($username)) {
        return false;
    }
    $username = Secure($username);
    $query    = mysqli_query($sqlConnect, "SELECT COUNT(`id`) FROM " . T_USERS . " WHERE `username` = '{$username}'");
    return (Sql_Result($query, 0) == 1) ? true : false;
}
function UserIdForLogin($username) {
    global $sqlConnect;
    if (empty($username)) {
        return false;
    }
    $username = Secure($username);
    $query    = mysqli_query($sqlConnect, "SELECT `id` FROM " . T_USERS . " WHERE `username` = '{$username}' OR `email` = '{$username}'");
    return Sql_Result($query, 0, 'id');
}
function EmoPhone($string = '') {
    global $emo_full;
    foreach ($emo_full as $code => $name) {
        $code   = $code;
        $string = str_replace($code, $name, $string);
    }
    return $string;
}
function blog_categories(){
    global $db;
    $lang = $_SESSION['lang'];
    $blog_categories = $db->arrayBuilder()->where('ref','blog_categories')->get(T_LANGS,null,array('lang_key',$lang));
    $data = array();
    foreach ($blog_categories as $key => $value) {
        if(isset($value[$lang])) {
            $data[$value['lang_key']] = $value[$lang];
        }
    }
    return $data;
}
function RegisterNewBlogPost($registration_data) {
    global $sqlConnect;
    if (empty($registration_data)) {
        return false;
    }
    $fields = '`' . implode('`, `', array_keys($registration_data)) . '`';
    $data   = '\'' . implode('\', \'', $registration_data) . '\'';
    $query  = mysqli_query($sqlConnect, "INSERT INTO `".T_BLOG."` ({$fields}) VALUES ({$data})");
    if ($query) {
        return true;
    }
    return false;
}
function GetBlogArticles() {
    global $sqlConnect;
    $data          = array();
    $query_one     = "SELECT * FROM `".T_BLOG."` ORDER BY `id` DESC";
    $sql_query_one = mysqli_query($sqlConnect, $query_one);
    while ($fetched_data = mysqli_fetch_assoc($sql_query_one)) {
        $data[] = GetArticle($fetched_data['id']);
    }
    return $data;
}
function GetArticle($page_name) {
    global $sqlConnect;
    if (empty($page_name)) {
        return false;
    }
    $data          = array();
    $page_name     = Secure($page_name);
    $query_one     = "SELECT * FROM `".T_BLOG."` WHERE `id` = '{$page_name}'";
    $sql_query_one = mysqli_query($sqlConnect, $query_one);
    $fetched_data  = mysqli_fetch_assoc($sql_query_one);
    $fetched_data['url'] = getLink("article/".$fetched_data['id'] . '_' . url_slug(html_entity_decode($fetched_data['title'])));
    return $fetched_data;
}
function DeleteArticle($id, $thumbnail) {
    global $sqlConnect;
    if (empty($id)) {
        return false;
    }
    $id    = Secure($id);
    $query = mysqli_query($sqlConnect, "DELETE FROM `".T_BLOG."` WHERE `id` = {$id}");
    if ($query) {
        PT_DeleteFromToS3( $thumbnail );
        @unlink($thumbnail);
        return true;
    }
    return false;
}
function redirect($url) {
    header("Loacation: $url");
    exit();
}
function url_domain($url) {
    $host = @parse_url($url, PHP_URL_HOST);
    if (!$host) {
        $host = $url;
    }
    if (substr($host, 0, 4) == "www.") {
        $host = substr($host, 4);
    }
    if (strlen($host) > 50) {
        $host = substr($host, 0, 47) . '...';
    }
    return $host;
}
function get_user_ads($placement = 1,$ad_type = 'image') {
    global $db,$music;

    $execlude_text = '';
    if(!empty($music->user_ad_cons['uaid_'])){
        $execlude = implode(',', $music->user_ad_cons['uaid_']);
        $execlude_text = "AND id NOT IN ({$execlude})";
    }
    $placement_text = '';
    if ($ad_type == 'image') {
        $placement_text = " AND placement = {$placement} ";
    }

    $ad = $db->rawQuery("select * FROM ".T_USR_ADS." where status = 1 {$placement_text} {$execlude_text} AND ( day_limit > day_spend OR day_limit = 0 ) AND ad_type = '{$ad_type}' ORDER BY RAND() LIMIT 1");
    if (!empty($ad) && !empty($ad[0])) {
        $ad = $ad[0];
    }

    return (!empty($ad)) ? $ad : false;
}
function getAdAction($adID, $action = 'view'){
    global $db;
    $ads_result = $db->where('ad_id',$adID)->where('type',$action)->get(T_ADS_TRANS);
    $res = 0;
    if (!empty($ads_result)) {
        foreach ($ads_result as $key => $ad) {
            $res += 1;
        }
    }
    return $res;
}
function getAdSpent($adID){
    global $db;
    $ads_result = $db->where('ad_id',$adID)->where('type','spent')->get(T_ADS_TRANS);
    $res = 0;
    if (!empty($ads_result)) {
        foreach ($ads_result as $key => $ad) {
            $res += $ad->amount;
        }
    }
    return $res;
}
function register_ad_views($ad_id = false, $publisher_id = false) {
    global $music, $db;
    if (empty($ad_id) || empty($publisher_id)) {
        return false;
    }
    $ad     = $db->where('id', $ad_id)->getOne(T_USR_ADS);
    $result = false;
    if (!empty($ad)) {
        $ad_owner     = $db->where('id', $ad->user_id)->getOne(T_USERS);
        $con_price    = $music->config->ad_v_price;
        //$pub_price    = $music->config->pub_price;
        $ad_trans     = false;
        $is_owner     = false;
        $ad_tans_data = array(
            'results' => ($ad->results += 1)
        );
        if (IS_LOGGED) {
            $is_owner = ($ad->user_id == $music->user->id) ? true : false;
        }
        if (!array_key_exists($ad->id, $music->user_ad_cons['uaid_'])) {
            //if ($music->config->usr_v_mon == 'on') {
                //$track_owner = $db->where('id', $publisher_id)->getOne(T_USERS);
                //if (!empty($track_owner) && ($ad->user_id != $track_owner->id)) {
                //    $db->where('id', $publisher_id)->update(T_USERS, array(
                //        'balance' => (($track_owner->balance += $pub_price))
                //    ));
                    //$db->insert(T_ADS_TRANS,array('amount' => $pub_price,'type' => 'view', 'ad_id' => $ad->id, 'track_owner' => $publisher_id, 'time' => time()));

                //}
            //}
            $ad_tans_data['spent']              = ($ad->spent += $con_price);
            $ad_trans                           = true;
            $music->user_ad_cons['uaid_'][$ad->id] = $ad->id;
            setcookie('_uads', htmlentities(serialize($music->user_ad_cons)), time() + (10 * 365 * 24 * 60 * 60), '/');
            $db->insert(T_ADS_TRANS,array('amount' => $con_price ,'type' => 'spent', 'ad_id' => $ad->id, 'track_owner' => $publisher_id, 'time' => time()));
            $db->insert(T_ADS_TRANS,array('type' => 'view', 'ad_id' => $ad->id, 'time' => time()));

        }
        $update = $db->where('id', $ad_id)->update(T_USR_ADS, $ad_tans_data);
        if ($update && $ad_trans && !$is_owner) {
            $ad_value = ($ad_owner->wallet -= $con_price);
            if ($ad_value < 0) {
                $ad_value = 0;
            }
            $db->where('id', $ad_owner->id)->update(T_USERS, array(
                'wallet' => $ad_value
            ));
            if ($ad->day_limit > 0) {
                if ($ad->day == date("Y-m-d")) {
                    $db->where('id',$ad->id)->update(T_USR_ADS,array('day_spend' => ($ad->day_spend + $con_price)));
                }
                else{
                    $db->where('id',$ad->id)->update(T_USR_ADS,array('day_spend' => $con_price ,
                        'day'       => date("Y-m-d")));
                }
            }
            $result = true;
        }
    }
    return $result;
}
function SendSMSMessage($to, $message) {
    global $music;
    if (empty($to)) {
        return false;
    }
    if (!empty($music->config->sms_twilio_username) && !empty($music->config->sms_twilio_password) && !empty($music->config->sms_t_phone_number)) {
        $account_sid = $music->config->sms_twilio_username;
        $auth_token  = $music->config->sms_twilio_password;
        $to          = secure($to);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.twilio.com/2010-04-01/Accounts/".$account_sid."/Messages");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "Body=".$message."&From=".$music->config->sms_t_phone_number."&To=".$to);
        curl_setopt($ch, CURLOPT_USERPWD, $account_sid . ':' . $auth_token);

        $headers = array();
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        if (!empty($result)) {
            $result = simplexml_load_string($result);
            if (!empty($result->Message) && !empty($result->Message->Status)) {
                return true;
            }
        }
        return false;
    }
    return false;
}
function VerifyIP($username = '') {
    global $music, $db;
    if (empty($username)) {
        return false;
    }
    if ($music->config->login_auth == 0) {
        return true;
    }
    $getuser = userData($username);
    $get_ip = '95.12.11.21';//get_ip_address();
    $getIpInfo = fetchDataFromURL("http://ip-api.com/json/$get_ip");
    $getIpInfo = json_decode($getIpInfo, true);
    if ($getIpInfo['status'] == 'success' && !empty($getIpInfo['regionName']) && !empty($getIpInfo['countryCode']) && !empty($getIpInfo['timezone']) && !empty($getIpInfo['city'])) {
        $create_new = false;
        $_SESSION['last_login_data'] = $getIpInfo;
        if (empty($getuser->last_login_data)) {
            $create_new = true;
        } else {
            $lastLoginData = unserialize($getuser->last_login_data);
            if (($getIpInfo['regionName'] != $lastLoginData['regionName']) || ($getIpInfo['countryCode'] != $lastLoginData['countryCode']) || ($getIpInfo['timezone'] != $lastLoginData['timezone']) || ($getIpInfo['city'] != $lastLoginData['city'])) {
                // send email
                $code = rand(111111, 999999);
                $hash_code = md5($code);
                $email['username'] = $getuser->name;
                $email['countryCode'] = $getIpInfo['countryCode'];
                $email['timezone'] = $getIpInfo['timezone'];
                $email['email'] = $getuser->email;
                $email['ip_address'] = $get_ip;
                $email['code'] = $code;
                $email['city'] = $getIpInfo['city'];
                $email['date'] = date("Y-m-d h:i:sa");

                $music->username = $email['username'];
                $music->code = $email['code'];
                $music->date = $email['date'];
                $music->email = $email['email'];
                $music->countryCode = $email['countryCode'];
                $music->ip_address = $email['ip_address'];
                $music->city = $email['city'];

                $update_code =  $db->where('id', $username)->update(T_USERS, array('email_code' => $hash_code));
                $email_body = LoadPage("emails/unusual-login", $email);
                $send_message_data       = array(
                    'from_email' => $music->config->email,
                    'from_name' => $music->config->name,
                    'to_email' => $getuser->email,
                    'to_name' => $getuser->name,
                    'subject' => 'Please verify that it\'s you',
                    'charSet' => 'utf-8',
                    'message_body' => $email_body,
                    'is_html' => true
                );
                $send = SendMessage($send_message_data);
                if ($send && !empty($_SESSION['last_login_data'])) {
                    return false;
                } else {
                    return true;
                }
            } else {
                return true;
            }
        }
        if ($create_new == true) {
            $lastLoginData = serialize($getIpInfo);
            $update_user = $db->where('id', $username)->update(T_USERS, array('last_login_data' => $lastLoginData));
            return true;
        }
        return false;
    } else {
        return true;
    }
}
function TwoFactor($username = '') {
    global $music, $db;
    if (empty($username)) {
        return true;
    }
    if ($music->config->two_factor == 0) {
        return true;
    }
    $getuser = userData($username);
    if ($getuser->two_factor == 0 || $getuser->two_factor_verified == 0) {
        return true;
    }
    $code = rand(111111, 999999);
    $hash_code = md5($code);
    $update_code =  $db->where('id', $username)->update(T_USERS, array('email_code' => $hash_code));
    $message = "Your confirmation code is: $code";
    if (!empty($getuser->phone_number) && ($music->config->two_factor_type == 'both' || $music->config->two_factor_type == 'phone')) {
        $send_message = SendSMSMessage($getuser->phone_number, $message);
    }
    if ($music->config->two_factor_type == 'both' || $music->config->two_factor_type == 'email') {
        $send_message_data       = array(
            'from_email' => $music->config->email,
            'from_name' => $music->config->name,
            'to_email' => $getuser->email,
            'to_name' => $getuser->name,
            'subject' => 'Please verify that it\'s you',
            'charSet' => 'utf-8',
            'message_body' => $message,
            'is_html' => true
        );
        $send = SendMessage($send_message_data);
    }
    return false;
}
function ImportFormSoundCloud($trackUrl){
    global $music,$db,$user;
    if (empty($trackUrl) || IS_LOGGED == false) return false;
    require 'assets/libs/getID3-1.9.14/getid3/getid3.php';

    runPlugin('PreSongImported', ["url" => $trackUrl]);
    $sound                          = array();
    $sound['itunes_affiliate_url'] = '';
    $sound['itunes_token'] = '';
    if (strpos($_POST['track_link'], 'soundcloud.com')) {

        $client_id                      = $music->config->sound_cloud_client_id;//'a3e059563d7fd3372b49b37f00a00bcf'
        $data                           = file_get_contents("https://api.soundcloud.com/resolve.json?url=$trackUrl&client_id=$client_id");
        if (empty($data)) {
            return false;
        }
        $data                           = json_decode($data, true);
        $sound['id'] = trim($data['id']);
        $sound['impoted_from'] = 'SOUNDCLOUD';
        $sound['title'] = trim($data['title']);
        $sound['description'] = trim($data['description']);
        $sound['original_content_size'] = trim($data['original_content_size']);
        $sound['duration'] = trim($data['duration']);
        $sound['original_format'] = trim($data['original_format']);
        $sound['artwork_url'] = trim($data['artwork_url']);
        $sound['tag_list'] = trim($data['tag_list']);
        $trackId = $data['id'];
        $data = file_get_contents("http://api.soundcloud.com/i1/tracks/$trackId/streams?client_id=$client_id");
        $data = json_decode($data, true);
        $sound['http_mp3_128_url'] = trim($data['http_mp3_128_url']);
    }
    elseif (strpos($_POST['track_link'], 'music.apple.com')) {
        $co = '';
        $array = explode('/', $_POST['track_link']);
        if (!empty($array[3])) {
            $co = $array[3];
        }
        $apple_id = substr($_POST['track_link'], strpos($_POST['track_link'], 'i=') + 2);
        $data                           = file_get_contents("https://itunes.apple.com/lookup?id=$apple_id&country=".$co);

        if (empty($data)) {
            return false;
        }
        $data                           = json_decode($data, true);
        if (empty($data['results']) || empty($data['results'][0])) {
            return false;
        }
        $data = $data['results'][0];
        $data['artworkUrl100'] = str_replace("100x100bb", "512x512bb", $data['artworkUrl100']);
        $sound['id'] = $trackId = trim($data['trackId']);
        $sound['impoted_from'] = 'ITUNES';
        $sound['title'] = trim($data['trackName']);
        $sound['description'] = trim($data['artistName']).' '.trim($data['trackName']);
        //$sound['original_content_size'] = trim($data['original_content_size']);
        $sound['duration'] = trim($data['trackTimeMillis']);
        $sound['original_format'] = pathinfo(trim($data['previewUrl']), PATHINFO_EXTENSION);
        $sound['artwork_url'] = trim($data['artworkUrl100']);
        $sound['tag_list'] = trim($data['primaryGenreName']);
        $sound['http_mp3_128_url'] = trim($data['previewUrl']);
        $aff_url = "https://geo.music.apple.com/us/album/";
        $itunes_aff = explode('/', trim($data['trackViewUrl']));
        foreach ($itunes_aff as $key => $value) {
            if ($value == 'album') {
                $aff_url .= $itunes_aff[$key + 1].'/'.$itunes_aff[$key + 2].'&app=music';
            }
        }
        $sound['itunes_affiliate_url'] = $aff_url;
        $sound['itunes_token'] = '';
        if ($music->config->itunes_affiliate == 'admin') {
            $sound['itunes_token'] = $music->config->itunes_partner_token;
        }
    }
    elseif (strpos($_POST['track_link'], 'deezer.com')) {
        $path = parse_url($_POST['track_link'])['path'];
        $array = explode('/', $path);
        $deezer_id = $array[count($array) - 1];
        $data                           = file_get_contents("https://api.deezer.com/track/$deezer_id");
        if (empty($data)) {
            return false;
        }
        $data                           = json_decode($data, true);
        $sound['id'] = trim($data['id']);
        $sound['impoted_from'] = 'DEEZER';
        $sound['title'] = trim($data['title']);
        $sound['description'] = trim($data['artist']['name']." ".$data['album']['title']." ".$data['title']);
        $sound['original_content_size'] = trim($data['rank']);
        $sound['duration'] = trim($data['duration']);
        $sound['original_format'] = trim(pathinfo($data['preview'], PATHINFO_EXTENSION));
        $sound['artwork_url'] = trim($data['album']['cover_xl']);
        $sound['tag_list'] = '';
        $trackId = $data['id'];
        $sound['http_mp3_128_url'] = trim($data['preview']);
    }
    elseif (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $_POST['track_link'], $match) && !empty($music->config->youtube_key) && $music->config->youtube_import == 'on') {
        //$response = http_request_call(0, 'https://www.yt-download.org/api/button/mp3/'.secure($match[1]), 0, 0, 0);
        $response = http_request_call(0, 'https://api.vevioz.com/api/button/mp3/'.secure($match[1]), 0, 0, 0);

        $re = '#<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1#';
        preg_match_all($re, $response, $matches, PREG_SET_ORDER, 0);
        if (!empty($matches)) {
            foreach ($matches as $key => $value) {
                if (!empty($value[2]) && strpos($value[2], 'mp3/320')) {
                    require 'assets/libs/youtube-sdk/vendor/autoload.php';
                    $thumbnail = 'upload/photos/thumbnail.jpg';
                    $sound['http_mp3_128_url'] = $value[2];
                    $sound['impoted_from'] = "YOUTUBE";
                    $sound['id'] = secure($match[1]);
                    $sound['title'] = '';
                    $sound['description'] = '';
                    $sound['tag_list'] = '';

                    try {
                        $youtube = new Madcoda\Youtube\Youtube(array('key' => $music->config->youtube_key));
                        $get_videos = $youtube->getVideoInfo(secure($match[1]));
                        if (!empty($get_videos)) {
                            $data = array(
                                'status' => 200,
                                'message' => lang("Your video will be converted to mp3 soon, you'll get notified once imported")
                            );
                            ob_end_clean();
                            header("Content-Encoding: none");
                            header("Connection: close");
                            ignore_user_abort();
                            ob_start();
                            header('Content-Type: application/json');
                            echo json_encode($data);
                            $size = ob_get_length();
                            header("Content-Length: $size");
                            ob_end_flush();
                            flush();
                            session_write_close();
                            if (is_callable('fastcgi_finish_request')) {
                                fastcgi_finish_request();
                            }
                            if (!empty($get_videos->snippet)) {

                                if (!empty($get_videos->snippet->thumbnails->maxres->url)) {
                                    $thumbnail = $get_videos->snippet->thumbnails->maxres->url;
                                } else if (!empty($get_videos->snippet->thumbnails->standard->url)) {
                                    $thumbnail = $get_videos->snippet->thumbnails->standard->url;
                                } else if (!empty($get_videos->snippet->thumbnails->high->url)) {
                                    $thumbnail = $get_videos->snippet->thumbnails->high->url;
                                } else if (!empty($get_videos->snippet->thumbnails->medium->url)) {
                                    $thumbnail = $get_videos->snippet->thumbnails->medium->url;
                                }
                                $info = $get_videos->snippet;
                                $title = $info->title;
                                if (!empty(covtime($get_videos->contentDetails->duration))) {
                                    $duration = covtime($get_videos->contentDetails->duration);
                                }
                                $description = $info->description;
                                if (!empty($get_videos->snippet->tags)) {
                                    if (is_array($get_videos->snippet->tags)) {
                                        foreach ($get_videos->snippet->tags as $key => $tag) {
                                            $tags_array[] = $tag;
                                        }
                                        $tags = implode(',', $tags_array);
                                    }
                                }
                            }
                        }
                    }
                    catch (Exception $e) {
                        $error = $e->getMessage();
                        $data['status'] = 400;
                        $data['message'] = $error;
                        header('Content-Type: application/json');
                        echo json_encode($data);
                        exit();
                    }
                    $sound['artwork_url'] = $thumbnail;
                    $sound['title'] = $title;
                    $sound['description'] = $description;
                    $sound['tag_list'] = $tags;
                }
            }
        }
        else{
            $data['status'] = 400;
            $data['message'] = lang('Error found while importing your track');
            header('Content-Type: application/json');
            echo json_encode($data);
            exit();
        }

    }
    elseif (strpos($_POST['track_link'], 'kkbox.com')) {
        $kkbox = ImportKKBOXtrack($_POST['track_link']);
        $trackId = $kkbox['id'];
        $sound = $kkbox;
    }



    //Check if this track imported before
    $imported = $db->where('src', $sound['impoted_from'].':'.$sound['id'])->getOne(T_SONGS);
    if (!empty($imported)) {
        return array('duplicated' => true);
    }else {


        if (!file_exists('upload/photos/' . date('Y'))) {
            @mkdir('upload/photos/' . date('Y'), 0777, true);
        }
        if (!file_exists('upload/photos/' . date('Y') . '/' . date('m'))) {
            @mkdir('upload/photos/' . date('Y') . '/' . date('m'), 0777, true);
        }
        if (!file_exists('upload/audio/' . date('Y'))) {
            @mkdir('upload/audio/' . date('Y'), 0777, true);
        }
        if (!file_exists('upload/audio/' . date('Y') . '/' . date('m'))) {
            @mkdir('upload/audio/' . date('Y') . '/' . date('m'), 0777, true);
        }
        if (!file_exists('upload/waves/' . date('Y'))) {
            @mkdir('upload/waves/' . date('Y'), 0777, true);
        }
        if (!file_exists('upload/waves/' . date('Y') . '/' . date('m'))) {
            @mkdir('upload/waves/' . date('Y') . '/' . date('m'), 0777, true);
        }

//        if ($music->config->ffmpeg_system == "on") {
//            $music->config->s3_upload = 'off';
//            $music->config->ftp_upload = 'off';
//        }

        $Photo = "upload/photos/" . date('Y') . '/' . date('m') . '/' . generateKey() . '_' . date('d') . '_' . md5(time()) . "_image.jpg";
        file_put_contents($Photo, file_get_contents($sound['artwork_url']));

        if (($music->config->s3_upload == 'on' || $music->config->ftp_upload == 'on' || $music->config->spaces == 'on' || $music->config->google_drive == 'on' || $music->config->wasabi_storage == 'on' || $music->config->backblaze_storage == 'on') && !empty($Photo) && is_file($Photo)) {
            $upload_s3 = PT_UploadToS3($Photo);
        }
        $sound['thumbnail'] = trim($Photo);
        if ($music->config->google_drive == 'on') {
          $sound['thumbnail'] = trim($upload_s3);
        }
        $Audio = $originalURL = "upload/audio/" . date('Y') . '/' . date('m') . '/' . generateKey() . '_' . date('d') . '_' . md5(time()) . "_audio.mp3";
        file_put_contents($Audio, file_get_contents($sound['http_mp3_128_url']));

        if(strpos($_POST['track_link'], 'soundcloud.com') && $music->config->soundcloud_go == 'off') {
            $getID3 = new getID3;
            $file = $getID3->analyze($Audio);
            $duration = '00:00';
            if (!empty($file['playtime_string'])) {
                $duration = secure($file['playtime_string']);
            }
            if ($duration == '0:30') {
                if (file_exists($Audio)) {
                    unlink($Audio);
                }
                return array('soundcloud_pro' => true);
            }
        }
        $generateWaveDark = '';
        $generateWaveLight = '';
        $generateWaveDay = '';
        $sound['audio_location'] = $originalURL;
        if ($music->config->ffmpeg_system != "off") {
            $time = time();
            $full_dir = str_replace('assets' . DIRECTORY_SEPARATOR . 'includes', '/', __DIR__);
            $ffmpeg_b = $music->config->ffmpeg_binary_file;
            $filepath = explode('.', $Audio)[0];
            $originalURL = $filepath . "_" . rand(11111, 99999) . "_converted.mp3";

            $audio_output_mp3 = $full_dir . $originalURL;


            $key = generateKey(40, 40);
            $generateWaveLight = 'upload/waves/' . date('Y') . '/' . date('m') . '/' . $key . "_light.png";
            $generateWaveDark = 'upload/waves/' . date('Y') . '/' . date('m') . '/' . $key . "_dark.png";
            $generateWaveDay = 'upload/waves/' . date('Y') . '/' . date('m') . '/' . $key . "_day.png";
            $audio_output_light_wave = $full_dir . $generateWaveLight;
            $audio_output_black_wave = $full_dir . $generateWaveDark;
            $audio_output_day_wave = $full_dir . $generateWaveDay;
            $audio_file_full_path = $full_dir . $Audio;
            $wavecolor = $music->config->waves_color;
            $shell = shell_exec("$ffmpeg_b -i $audio_file_full_path -map 0:a:0 -b:a 192k $audio_output_mp3 2>&1");
            $shell = shell_exec("$ffmpeg_b -y -i $audio_output_mp3 -filter_complex \"aformat=channel_layouts=mono,showwavespic=s=1100x150:colors=#6d6d6d\" -frames:v 1 $audio_output_black_wave 2>&1");
            $shell = shell_exec("$ffmpeg_b -y -i $audio_output_mp3 -filter_complex \"aformat=channel_layouts=mono,showwavespic=s=1100x150:colors=" . $wavecolor . "\" -frames:v 1 $audio_output_light_wave 2>&1");
            $shell = shell_exec("$ffmpeg_b -y -i $audio_output_mp3 -filter_complex \"aformat=channel_layouts=mono,showwavespic=s=1100x150:colors=#e5e5e5\" -frames:v 1 $audio_output_day_wave 2>&1");

            $sound['audio_location'] = $originalURL;
            if (file_exists($Audio)) {
                unlink($Audio);
            }
        }



        if (file_exists($generateWaveLight) && file_exists($generateWaveDark)) {
            $sound['dark_wave'] = $generateWaveDark;
            $sound['light_wave'] = $generateWaveLight;
        }

        $audio_id = generateKey(15, 15);
        $check_for_audio = $db->where('audio_id', $audio_id)->getValue(T_SONGS, 'count(*)');
        if ($check_for_audio > 0) {
            $audio_id = generateKey(15, 15);
        }

        $getID3 = new getID3;
        $file = $getID3->analyze($sound['audio_location']);
        $duration = '00:00';
        if (!empty($file['playtime_string'])) {
            $duration = secure($file['playtime_string']);
        }
        $filesize = 0;
        if (!empty($file['filesize'])) {
            $filesize = $file['filesize'];
        }
        $uploadConvertedSong = PT_UploadToS3($originalURL);
        if ($music->config->google_drive == "on") {

            $originalURL = $uploadConvertedSong;
        }

        $finalData = array(
            'audio_id' => $audio_id,
            'user_id' => $user->id,
            'title' => secure($sound['title']),
            'description' => secure($sound['description']),
            'lyrics' => '',
            'tags' => str_replace(' ', ',', $sound['tag_list']),
            'duration' => $duration,
            'audio_location' => '',
            'category_id' => 0,
            'thumbnail' => $sound['thumbnail'],
            'time' => time(),
            'registered' => date('Y') . '/' . intval(date('m')),
            'size' => $filesize,
            'availability' => 0,
            'age_restriction' => 0,
            'price' => 0,
            'spotlight' => 0,
            'ffmpeg' => ($music->config->ffmpeg_system == 'on' ) ? 1 : 0,
            'allow_downloads' => 1,
            'display_embed' => 1,
            'dark_wave' => $generateWaveDark,
            'light_wave' => $generateWaveLight,
            'audio_location' => $originalURL,
            'src' => $sound['impoted_from'].':' . $sound['id'],
            'itunes_affiliate_url' => $sound['itunes_affiliate_url'],
            'itunes_token' => $sound['itunes_token'],
            'converted' => 1
        );
        $id = $db->insert(T_SONGS, $finalData);
        if ($id) {
            runPlugin('AfterSongImported', $finalData);
            $sound['audio_id'] = $audio_id;
            $sound['id'] = $id;
        }

        PT_UploadToS3($generateWaveLight);
        PT_UploadToS3($generateWaveDark);
        PT_UploadToS3($generateWaveDay);

        $notif_data = array(
            'notifier_id' => 0,
            'recipient_id' => $user->id,
            'type' => 'your_song_is_ready',
            'url' => Secure('track/' . $audio_id),
            'time' => time()
        );
        $db->insert(T_NOTIFICATION,$notif_data);
        //file_put_contents('./'.$trackname.'.mp3' ,file_get_contents($mp3));
        return $sound;
    }
}

function isMobile() {
    $useragent=$_SERVER['HTTP_USER_AGENT'];
    if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4))) {
        return true;
    } else {
        return false;
    }
}
function LastSeen($user_id, $type = '') {
    global $music, $sqlConnect;
    if ($music->loggedin == false) {
        return false;
    }
    if (empty($user_id) || !is_numeric($user_id) || $user_id < 0) {
        return false;
    }
    if ($type == 'first') {
        $user = userData($user_id);
        if ($music->user->status == 1) {
            return false;
        }
    } else {
        if ($music->user->status == 1) {
            return false;
        }
    }
    $user_id = Secure($user_id);
    $user_session_id = $music->user_session_id;
    $sql =  " UPDATE " . T_APP_SESSIONS . " SET `time` = " . time() . " WHERE `user_id` = {$user_id} AND `session_id` = '{$user_session_id}'";
    $query   = mysqli_query($sqlConnect, $sql);
    if ($query) {
        return true;
    } else {
        return false;
    }
}

//ImportFormSoundCloud('https://soundcloud.com/uiceheidd/bandit-ft-nba-youngboy');
//exit();
/* Function By Qassim Hassan, wp-time.com */
function http_request_call($method, $url, $header, $data, $json){
    if( $method == 1 ){
        $method_type = 1; // 1 = POST
    }else{
        $method_type = 0; // 0 = GET
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_HEADER, 0);

    if( $header !== 0 ){
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    }

    curl_setopt($curl, CURLOPT_POST, $method_type);

    if( $data !== 0 ){
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($curl);

    if( $json == 0 ){
        $json = $response;
    }else{
        $json = json_decode($response, true);
    }

    curl_close($curl);

    return $json;
}

function RegisterNewField($registration_data) {
    global $music, $sqlConnect;
    if (empty($registration_data)) {
        return false;
    }
    $fields = '`' . implode('`, `', array_keys($registration_data)) . '`';
    $data   = '\'' . implode('\', \'', $registration_data) . '\'';
    $query  = mysqli_query($sqlConnect, "INSERT INTO " . T_FIELDS . " ({$fields}) VALUES ({$data})");
    if ($query) {
        $sql_id  = mysqli_insert_id($sqlConnect);
        $column  = 'fid_' . $sql_id;
        $length  = $registration_data['length'];
        $query_2 = mysqli_query($sqlConnect, "ALTER TABLE " . T_USERS_FIELDS . " ADD COLUMN `{$column}` varchar({$length}) NOT NULL DEFAULT ''");
        return true;
    }
    return false;
}
function GetWelcomeFields() {
    global $music, $sqlConnect;
    $data      = array();
    $query_one = " SELECT * FROM " . T_FIELDS . " WHERE `registration_page` = '1' ORDER BY `id` ASC";
    $sql       = mysqli_query($sqlConnect, $query_one);
    while ($fetched_data = mysqli_fetch_assoc($sql)) {
        $fetched_data['fid'] = 'fid_' . $fetched_data['id'];
        $fetched_data['name'] = preg_replace_callback("/{{LANG (.*?)}}/", function($m) use ($music) {
            return lang($m[1]);
        }, $fetched_data['name']);
        $fetched_data['description'] = preg_replace_callback("/{{LANG (.*?)}}/", function($m) use ($music) {
            return lang($m[1]);
        }, $fetched_data['description']);
        $fetched_data['type'] = preg_replace_callback("/{{LANG (.*?)}}/", function($m) use ($music) {
            return lang($m[1]);
        }, $fetched_data['type']);
        $data[]               = $fetched_data;
    }
    return $data;
}
function GetProfileFields($type = 'all') {
    global $music, $sqlConnect;
    $data       = array();
    $where      = '';
    $placements = array(
        'profile',
        'general',
        'social'
    );
    if ($type != 'all' && in_array($type, $placements)) {
        $where = "WHERE `placement` = '{$type}' AND `placement` <> 'none' AND `active` = '1'";
    } else if ($type == 'none') {
        $where = "WHERE `profile_page` = '1' AND `active` = '1'";
    } else if ($type != 'admin') {
        $where = "WHERE `active` = '1'";
    }
    $type      = Secure($type);
    $query_one = "SELECT * FROM " . T_FIELDS . " {$where} ORDER BY `id` ASC";
    $sql       = mysqli_query($sqlConnect, $query_one);
    while ($fetched_data = mysqli_fetch_assoc($sql)) {
        $fetched_data['fid'] = 'fid_' . $fetched_data['id'];
        $fetched_data['name'] = preg_replace_callback("/{{LANG (.*?)}}/", function($m) use ($music) {
            return lang($m[1]);
        }, $fetched_data['name']);
        $fetched_data['description'] = preg_replace_callback("/{{LANG (.*?)}}/", function($m) use ($music) {
            return lang($m[1]);
        }, $fetched_data['description']);
        $fetched_data['type'] = preg_replace_callback("/{{LANG (.*?)}}/", function($m) use ($music) {
            return lang($m[1]);
        }, $fetched_data['type']);
        $data[]               = $fetched_data;
    }
    return $data;
}
function GetUserCustomFields() {
    global $music, $sqlConnect;
    $data       = array();
    $where = "WHERE `active` = '1' AND `profile_page` = 1";

    $query_one = "SELECT * FROM " . T_FIELDS . " {$where} ORDER BY `id` ASC";
    $sql       = mysqli_query($sqlConnect, $query_one);
    while ($fetched_data = mysqli_fetch_assoc($sql)) {
        $fetched_data['fid'] = 'fid_' . $fetched_data['id'];
        $fetched_data['name'] = preg_replace_callback("/{{LANG (.*?)}}/", function($m) use ($music) {
            return lang($m[1]);
        }, $fetched_data['name']);
        $fetched_data['description'] = preg_replace_callback("/{{LANG (.*?)}}/", function($m) use ($music) {
            return lang($m[1]);
        }, $fetched_data['description']);
        $fetched_data['type'] = preg_replace_callback("/{{LANG (.*?)}}/", function($m) use ($music) {
            return lang($m[1]);
        }, $fetched_data['type']);
        $data[]               = $fetched_data;
    }
    return $data;
}
function UserFieldsData($user_id) {
    global $music, $sqlConnect;
    if (empty($user_id) || !is_numeric($user_id) || $user_id < 0) {
        return false;
    }
    $data         = array();
    $user_id      = Secure($user_id);
    $query_one    = "SELECT * FROM " . T_USERS_FIELDS . " WHERE `user_id` = {$user_id}";
    $sql          = mysqli_query($sqlConnect, $query_one);
    $fetched_data = mysqli_fetch_assoc($sql);
    if (empty($fetched_data)) {
        return array();
    }
    return $fetched_data;
}
function UpdateUserCustomData($user_id, $update_data, $loggedin = true) {
    global $music, $sqlConnect, $cache;
    if ($loggedin == true) {
        if ($music->loggedin == false) {
            return false;
        }
    }
    if (empty($user_id) || !is_numeric($user_id) || $user_id < 0) {
        return false;
    }
    if (empty($update_data)) {
        return false;
    }
    $user_id = Secure($user_id);
    if ($loggedin == true) {
        if (IsAdmin() === false) {
            if ($music->user->id != $user_id) {
                return false;
            }
        }
    }
    $update = array();
    foreach ($update_data as $field => $data) {
        foreach ($data as $key => $value) {
            $update[] = '`' . $key . '` = \'' . Secure($value, 0) . '\'';
        }
    }
    $impload     = implode(', ', $update);
    $query_one   = "UPDATE " . T_USERS_FIELDS . " SET {$impload} WHERE `user_id` = {$user_id}";
    $query_1     = mysqli_query($sqlConnect, "SELECT COUNT(`id`) as count FROM " . T_USERS_FIELDS . " WHERE `user_id` = {$user_id}");
    $query_1_sql = mysqli_fetch_assoc($query_1);
    $query       = false;
    if ($query_1_sql['count'] == 1) {
        $query = mysqli_query($sqlConnect, $query_one);
    } else {
        $query_2 = mysqli_query($sqlConnect, "INSERT INTO " . T_USERS_FIELDS . " (`user_id`) VALUES ({$user_id})");
        if ($query_2) {
            $query = mysqli_query($sqlConnect, $query_one);
        }
    }
    if ($query) {
        return true;
    }
    return false;
}
function GetFieldData($id = 0) {
    global $music, $sqlConnect;
    if (empty($id) || !is_numeric($id) || $id < 0) {
        return false;
    }
    $data         = array();
    $id           = Secure($id);
    $query_one    = "SELECT * FROM " . T_FIELDS . " WHERE `id` = {$id}";
    $sql          = mysqli_query($sqlConnect, $query_one);
    $fetched_data = mysqli_fetch_assoc($sql);
    if (empty($fetched_data)) {
        return array();
    }
    return $fetched_data;
}
function UpdateField($id, $update_data) {
    global $music, $sqlConnect, $cache;
    if ($music->loggedin == false) {
        return false;
    }
    if (empty($id) || !is_numeric($id) || $id < 0) {
        return false;
    }
    if (empty($update_data)) {
        return false;
    }
    $id = Secure($id);
    if (IsAdmin() === false) {
        return false;
    }
    $update = array();
    foreach ($update_data as $field => $data) {
        $update[] = '`' . $field . '` = \'' . Secure($data, 0) . '\'';
        if ($field == 'length') {
            $mysqli = mysqli_query($sqlConnect, "ALTER TABLE " . T_USERS_FIELDS . " CHANGE `fid_{$id}` `fid_{$id}` VARCHAR(" . Secure($data) . ") CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '';");
        }
    }
    $impload   = implode(', ', $update);
    $query_one = "UPDATE " . T_FIELDS . " SET {$impload} WHERE `id` = {$id} ";
    $query     = mysqli_query($sqlConnect, $query_one);
    if ($query) {
        return true;
    }
    return false;
}
function DeleteField($id) {
    global $music, $sqlConnect;
    if ($music->loggedin == false) {
        return false;
    }
    if (IsAdmin() === false) {
        return false;
    }
    $id    = Secure($id);
    $query = mysqli_query($sqlConnect, "DELETE FROM " . T_FIELDS . " WHERE `id` = {$id}");
    if ($query) {
        $query2 = mysqli_query($sqlConnect, "ALTER TABLE " . T_USERS_FIELDS . " DROP `fid_{$id}`;");
        if ($query2) {
            return true;
        }
    }
    return false;
}


function GetAdminInvitation() {
    global $sqlConnect, $music;
    if ($music->loggedin == false || !IsAdmin()) {
        return false;
    }
    $query = mysqli_query($sqlConnect, "SELECT * FROM " . T_INVITATIONS . " ORDER BY `id` DESC ");
    $data  = array();
    $site  = $music->config->site_url . '/?invite=';
    while ($fetched_data = mysqli_fetch_assoc($query)) {
        $fetched_data['url'] = $site . $fetched_data['code'];
        $data[]              = $fetched_data;
    }
    return $data;
}
function InsertAdminInvitation() {
    global $sqlConnect, $music;
    if ($music->loggedin == false || !IsAdmin()) {
        return false;
    }
    $time  = time();
    $code  = str_replace('.','',uniqid(rand(), true));
    $sql   = "INSERT INTO " . T_INVITATIONS . " (`id`,`code`,`posted`) VALUES (NULL,'$code', '$time')";
    $site  = $music->config->site_url . '/?invite=';
    $query = mysqli_query($sqlConnect, $sql);
    if ($query) {
        $last_id = mysqli_insert_id($sqlConnect);
        $data    = mysqli_query($sqlConnect, "SELECT * FROM " . T_INVITATIONS . " WHERE `id` = {$last_id}");
        if ($data && mysqli_num_rows($data) > 0) {
            $fetched_data        = mysqli_fetch_assoc($data);
            $fetched_data['url'] = $site . $fetched_data['code'];
            return $fetched_data;
        }
    }
    return false;
}
function DeleteAdminInvitation($col = '', $val = false) {
    global $sqlConnect, $music;
    if (!$val && !$col) {
        return false;
    }
    $val = Secure($val);
    $col = Secure($col);
    return mysqli_query($sqlConnect, "DELETE FROM " . T_INVITATIONS . " WHERE `$col` = '$val'");
}
function IsAdminInvitationExists($code = false) {
    global $sqlConnect, $music;
    if (!$code) {
        return false;
    }
    $code      = Secure($code);
    $data_rows = mysqli_query($sqlConnect, "SELECT `id` FROM " . T_INVITATIONS . " WHERE `code` = '$code' AND `status` = 'Pending'");
    return mysqli_num_rows($data_rows) > 0;
}
function GetRadioStations($search = "Music",$country = 'ALL',$genre = 'ALL'){
    global $music;
    $curl = curl_init();
    $_api = "https://30-000-radio-stations-and-music-charts.p.rapidapi.com/rapidapi?country=".$country."&search_keyword=".$search."&genre=".$genre;
    curl_setopt_array($curl, array(
        CURLOPT_URL => $_api,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "x-rapidapi-host: 30-000-radio-stations-and-music-charts.p.rapidapi.com",
            "x-rapidapi-key: " . $music->config->rapidapi_key
        ),
    ));
    $response = curl_exec($curl);
    $response = json_decode($response, true);
    $return = array();
    if(isset($response['results'])) {
        foreach ($response['results'] as $key => $value) {
            $return[$value['i']]['radio_id'] = $value['i'];
            $return[$value['i']]['country'] = $value['c'];
            $return[$value['i']]['genre'] = $value['g'];
            $return[$value['i']]['logo'] = 'https://www.radioair.info/app/images_radios/' . $value['l'];
            $return[$value['i']]['image'] = 'https://www.radioair.info/images_radios/' . $value['l'];
            $return[$value['i']]['name'] = $value['n'];
            $return[$value['i']]['url'] = $value['u'];
            $return[$value['i']]['genre_id'] = $value['d'];
        }
    }
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        //echo "cURL Error #:" . $err;
        return array();
    } else {
        return $return;
    }
}

//var_dump(GetRadioStations("Music"));
//exit();
//function userEmailNotification($user_id){
//    global $db, $music;
//    if (empty($user_id) || !IS_LOGGED) {
//        return false;
//    }
//    $u = userData($user_id);
//    $data = array(
//        'email_on_profile_view'             => $u->email_on_profile_view,
//        'email_on_new_message'              => $u->email_on_new_message,
//        'email_on_profile_like'             => $u->email_on_profile_like,
//        'email_on_purchase_notifications'   => $u->email_on_purchase_notifications,
//        'email_on_special_offers'           => $u->email_on_special_offers,
//        'email_on_announcements'            => $u->email_on_announcements,
//        'email_on_get_gift'                 => $u->email_on_get_gift,
//        'email_on_got_new_match'            => $u->email_on_got_new_match,
//        'email_on_chat_request'             => $u->email_on_chat_request
//    );
//    if (!in_array(1, $data)) {
//        return false;
//    } else {
//        return $data;
//    }
//}
function GetCustomPages() {
    global $sqlConnect;
    $data          = array();
    $query_one     = "SELECT * FROM " . T_CUSTOM_PAGES . " ORDER BY `id` DESC";
    $sql_query_one = mysqli_query($sqlConnect, $query_one);
    while ($fetched_data = mysqli_fetch_assoc($sql_query_one)) {
        $data[] = $fetched_data;
    }
    return $data;
}
function RegisterNewPage($registration_data) {
    global $sqlConnect;
    if (empty($registration_data)) {
        return false;
    }
    $fields = '`' . implode('`, `', array_keys($registration_data)) . '`';
    $data   = '\'' . implode('\', \'', $registration_data) . '\'';
    $query  = mysqli_query($sqlConnect, "INSERT INTO " . T_CUSTOM_PAGES . " ({$fields}) VALUES ({$data})");

    if ($query) {
        return true;
    }
    return false;
}
function DeleteCustomPage($id) {
    global $music, $sqlConnect;
    if ($music->loggedin == false || !IsAdmin()) {
        return false;
    }
    $id    = Secure($id);
    $query = mysqli_query($sqlConnect, "DELETE FROM " . T_CUSTOM_PAGES . " WHERE `id` = {$id}");
    if ($query) {
        return true;
    }
    return false;
}
function UpdateCustomPageData($id, $update_data) {
    global $music, $sqlConnect;
    if ($music->loggedin == false || !IsAdmin()) {
        return false;
    }
    if (empty($id) || !is_numeric($id) || $id < 0) {
        return false;
    }
    if (empty($update_data)) {
        return false;
    }
    $id = Secure($id);
    $update = array();
    foreach ($update_data as $field => $data) {
        $update[] = '`' . $field . '` = \'' . Secure($data, 0) . '\'';
    }
    $impload   = implode(', ', $update);
    $query_one = "UPDATE " . T_CUSTOM_PAGES . " SET {$impload} WHERE `id` = {$id} ";
    $query     = mysqli_query($sqlConnect, $query_one);
    if ($query) {
        return true;
    }
    return false;
}
function GetCustomPage($page_name) {
    global $sqlConnect;
    if (empty($page_name)) {
        return false;
    }
    $data          = array();
    $page_name     = Secure($page_name);
    $query_one     = "SELECT * FROM " . T_CUSTOM_PAGES . " WHERE `page_name` = '{$page_name}'";
    $sql_query_one = mysqli_query($sqlConnect, $query_one);
    $fetched_data  = mysqli_fetch_assoc($sql_query_one);
    return $fetched_data;
}
function PublishArticle($id) {
    global $sqlConnect;
    if (empty($id)) {
        return false;
    }
    $id    = Secure($id);
    $query = mysqli_query($sqlConnect, "UPDATE `".T_BLOG."` SET `posted` = 1 WHERE `id` = {$id}");
    if ($query) {
        return true;
    }
    return false;
}
function UnPublishArticle($id) {
    global $sqlConnect;
    if (empty($id)) {
        return false;
    }
    $id    = Secure($id);
    $query = mysqli_query($sqlConnect, "UPDATE `".T_BLOG."` SET `posted` = 0 WHERE `id` = {$id}");
    if ($query) {
        return true;
    }
    return false;
}
function GetUsersByTime($type = 'week') {
    global $sqlConnect;
    $types = array('week','month','3month','6month','9month','year');
    if (empty($type) || !in_array($type, $types)) {
        return array();
    }
    $data      = array();
    $end = time() - (60 * 60 * 24 * 7);
    $start = time() - (60 * 60 * 24 * 14);
    if ($type == 'month') {
        $end = time() - (60 * 60 * 24 * 30);
        $start = time() - (60 * 60 * 24 * 60);
    }
    if ($type == '3month') {
        $end = time() - (60 * 60 * 24 * 61);
        $start = time() - (60 * 60 * 24 * 150);
    }
    if ($type == '6month') {
        $end = time() - (60 * 60 * 24 * 151);
        $start = time() - (60 * 60 * 24 * 210);
    }
    if ($type == '9month') {
        $end = time() - (60 * 60 * 24 * 211);
        $start = time() - (60 * 60 * 24 * 300);
    }
    if ($type == 'year') {
        $end = time() - (60 * 60 * 24 * 365);
    }
    $sub1 = " WHERE `last_active` >= '{$start}' ";
    $sub2 = " AND `last_active` <= '{$end}' ";
    if ($type == 'year') {
        $sub2 = "";
    }
    $query_one = " SELECT `id` FROM " . T_USERS.$sub1.$sub2;
    $sql = mysqli_query($sqlConnect, $query_one);
    if (mysqli_num_rows($sql)) {
        while ($fetched_data = mysqli_fetch_assoc($sql)) {
            $data[] = userData($fetched_data['id']);
        }
    }

    return $data;
}
function GetAllUsersByType($type = 'all') {
    global $sqlConnect;
    $data      = array();
    $query_one = " SELECT `id` FROM " . T_USERS;
    if ($type == 'active') {
        $query_one .= " WHERE `active` = '1'";
    } else if ($type == 'inactive') {
        $query_one .= " WHERE `active` = '0' OR `active` = '2'";
    } else if ($type == 'all') {
        $query_one .= "";
    }
    $sql = mysqli_query($sqlConnect, $query_one);
    if (mysqli_num_rows($sql)) {
        while ($fetched_data = mysqli_fetch_assoc($sql)) {
            $data[] = userData($fetched_data['id']);
        }
    }

    return $data;
}
function CanLogin() {
    global $music, $sqlConnect,$db;
    if ($music->loggedin == true) {
        return false;
    }
    $ip = get_ip_address();
    if (empty($ip)) {
        return true;
    }
    if ($music->config->lock_time < 1) {
        return true;
    }
    if ($music->config->bad_login_limit < 1) {
        return true;
    }

    $time      = time() - (60 * $music->config->lock_time);
    $login = $db->where('ip',$ip)->get(T_BAD_LOGIN);
    if (count($login) >= $music->config->bad_login_limit) {
        $last = end($login);
        if ($last->time >= $time) {
            return false;
        }
    }
    $db->where('time',time()-(60 * $music->config->lock_time * 2),'<')->delete(T_BAD_LOGIN);
    return true;
}
function AddBadLoginLog() {
    global $music, $sqlConnect;
    if ($music->loggedin == true) {
        return false;
    }
    $ip = get_ip_address();
    if (empty($ip)) {
        return true;
    }
    $time      = time();
    $query     = mysqli_query($sqlConnect, "INSERT INTO " . T_BAD_LOGIN . " (`ip`, `time`) VALUES ('{$ip}', '{$time}')");
    if ($query) {
        return true;
    }
}
function CheckPaystackPayment($ref)
{
    global $music, $db;
    if (empty($ref) || $music->loggedin == false) {
        return false;
    }
    $ref = Secure($ref);
    $result = array();
    //The parameter after verify/ is the transaction reference to be verified
    $url = 'https://api.paystack.co/transaction/verify/'.$ref;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt(
      $ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$music->config->paystack_secret_key]
    );
    $request = curl_exec($ch);
    curl_close($ch);

    if ($request) {
        $result = json_decode($request, true);
        if($result){
          if($result['data']){
            if($result['data']['status'] == 'success'){
                return $result;
            }else{
              die("Transaction was not successful: Last gateway response was: ".$result['data']['gateway_response']);
            }
          }else{
            die($result['message']);
          }

        }else{
          die("Something went wrong while trying to convert the request variable to json. Uncomment the print_r command to see what is in the result variable.");
        }
      }else{
        die("Something went wrong while executing curl. Uncomment the var_dump line above this line to see what the issue is. Please check your CURL command to make sure everything is ok");
      }
}
function CheckRazorpayPayment($payment_id, $data)
{
    global $music, $db;
    if (empty($payment_id) || empty($data)) {
        return false;
    }

    $url = 'https://api.razorpay.com/v1/payments/' . $payment_id . '/capture';
    $key_id = $music->config->razorpay_key_id;
    $key_secret = $music->config->razorpay_key_secret;
    $params = http_build_query($data);
    //cURL Request
    $ch = curl_init();
    //set the url, number of POST vars, POST data
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, $key_id . ':' . $key_secret);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $request = curl_exec ($ch);
    curl_close ($ch);
    return json_decode($request);
}

function GetHtmlEmails() {
    global $music, $db,$sqlConnect;
    $data  = array();
    $query = mysqli_query($sqlConnect, "SELECT * FROM " . T_HTML_EMAILS);
    if (mysqli_num_rows($query)) {
        while ($fetched_data = mysqli_fetch_assoc($query)) {
            $data[$fetched_data['name']] = $fetched_data['value'];
        }
    }
    return $data;
}
function SaveHTMLEmails($update_name, $value) {
    global $music, $db,$sqlConnect;
    if ($music->loggedin == false) {
        return false;
    }
    $update_name = Secure($update_name);
    $value       = mysqli_real_escape_string($sqlConnect, $value);
    $query_one   = " UPDATE " . T_HTML_EMAILS . " SET `value` = '{$value}' WHERE `name` = '{$update_name}'";
    $query       = mysqli_query($sqlConnect, $query_one);
    if ($query) {
        return true;
    } else {
        return false;
    }
}
function ReplaceText($html='',$replaces=array())
{
    global $music, $db,$sqlConnect, $lang_array;
    $lang = $lang_array;
    $html = preg_replace_callback("/{{LANG (.*?)}}/", function($m) use ($lang) {
        return (isset($lang[$m[1]])) ? $lang[$m[1]] : '';
    }, $html);
    foreach ($replaces as $key => $replace) {
        $object_to_replace = "{{" . $key . "}}";
        $html      = str_replace($object_to_replace, $replace, $html);
    }
    return $html;
}
function GetAvailableLinks($user_id)
{
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->loggedin == false || empty($user_id) || !is_numeric($user_id) || $user_id < 1) {
        return false;
    }
    $user_id = Secure($user_id);
    $time = 0;
    if ($music->config->expire_user_links == 'hour') {
        $time = time() - (60 * 60);
    }
    if ($music->config->expire_user_links == 'day') {
        $time = time() - (60 * 60 * 24);
    }
    if ($music->config->expire_user_links == 'week') {
        $time = time() - (60 * 60 * 24 * 7);
    }
    if ($music->config->expire_user_links == 'month') {
        $time = time() - (60 * 60 * 24 * date("t"));
    }
    if ($music->config->expire_user_links == 'year') {
        $time = time() - (60 * 60 * 24 * 365);
    }

    $query_one = " SELECT count(*) AS count FROM " . T_INVITAION_LINKS." WHERE `user_id` = '{$user_id}' AND `time` > '{$time}' ";
    $query = mysqli_query($sqlConnect, $query_one);
    if (mysqli_num_rows($query)) {
        $fetched_data = mysqli_fetch_assoc($query);
        if ($music->config->user_links_limit > 0) {
            return $music->config->user_links_limit - $fetched_data['count'];
        }
        else{
            return lang('Unlimited');
        }
    }
    return false;
}
function GetGeneratedLinks($user_id)
{
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->loggedin == false || empty($user_id) || !is_numeric($user_id) || $user_id < 1) {
        return false;
    }
    $user_id = Secure($user_id);
    $time = 0;
    if ($music->config->expire_user_links == 'hour') {
        $time = time() - (60 * 60);
    }
    if ($music->config->expire_user_links == 'day') {
        $time = time() - (60 * 60 * 24);
    }
    if ($music->config->expire_user_links == 'week') {
        $time = time() - (60 * 60 * 24 * 7);
    }
    if ($music->config->expire_user_links == 'month') {
        $time = time() - (60 * 60 * 24 * date("t"));
    }
    if ($music->config->expire_user_links == 'year') {
        $time = time() - (60 * 60 * 24 * 365);
    }

    $query_one = " SELECT count(*) AS count FROM " . T_INVITAION_LINKS." WHERE `user_id` = '{$user_id}' AND `time` > '{$time}' ";
    $query = mysqli_query($sqlConnect, $query_one);
    if (mysqli_num_rows($query)) {

        $fetched_data = mysqli_fetch_assoc($query);
        return $fetched_data['count'];
    }
    return false;
}
function GetUsedLinks($user_id)
{
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->loggedin == false || empty($user_id) || !is_numeric($user_id) || $user_id < 1) {
        return false;
    }
    $user_id = Secure($user_id);
    $time = 0;
    if ($music->config->expire_user_links == 'hour') {
        $time = time() - (60 * 60);
    }
    if ($music->config->expire_user_links == 'day') {
        $time = time() - (60 * 60 * 24);
    }
    if ($music->config->expire_user_links == 'week') {
        $time = time() - (60 * 60 * 24 * 7);
    }
    if ($music->config->expire_user_links == 'month') {
        $time = time() - (60 * 60 * 24 * date("t"));
    }
    if ($music->config->expire_user_links == 'year') {
        $time = time() - (60 * 60 * 24 * 365);
    }

    $query_one = " SELECT count(*) AS count FROM " . T_INVITAION_LINKS." WHERE `user_id` = '{$user_id}' AND `invited_id` != 0 AND `time` > '{$time}' ";
    $query = mysqli_query($sqlConnect, $query_one);
    if (mysqli_num_rows($query)) {
        $fetched_data = mysqli_fetch_assoc($query);
        return $fetched_data['count'];
    }
    return false;
}
function GetMyInvitaionCodes($user_id)
{
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->loggedin == false || empty($user_id) || !is_numeric($user_id) || $user_id < 1) {
        return false;
    }
    $user_id = Secure($user_id);
    $time = 0;
    if ($music->config->expire_user_links == 'hour') {
        $time = time() - (60 * 60);
    }
    if ($music->config->expire_user_links == 'day') {
        $time = time() - (60 * 60 * 24);
    }
    if ($music->config->expire_user_links == 'week') {
        $time = time() - (60 * 60 * 24 * 7);
    }
    if ($music->config->expire_user_links == 'month') {
        $time = time() - (60 * 60 * 24 * date("t"));
    }
    if ($music->config->expire_user_links == 'year') {
        $time = time() - (60 * 60 * 24 * 365);
    }
    $data = array();

    $query_one = " SELECT * FROM " . T_INVITAION_LINKS." WHERE `user_id` = '{$user_id}' AND `time` > '{$time}' ";
    $query = mysqli_query($sqlConnect, $query_one);
    if (mysqli_num_rows($query)) {
        while ($fetched_data = mysqli_fetch_assoc($query)) {
            $fetched_data['user_name'] = '';
            $fetched_data['user_url'] = '';
            if (!empty($fetched_data['invited_id'])) {
                $user_data = userData($fetched_data['invited_id']);
                $fetched_data['user_name'] = $user_data->name;
                $fetched_data['user_url'] = $user_data->url;
            }
            $data[]                    = $fetched_data;
        }
    }
    return $data;
}
function IfCanGenerateLink($user_id)
{
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->loggedin == false || empty($user_id) || !is_numeric($user_id) || $user_id < 1) {
        return false;
    }
    $user_id = Secure($user_id);
    $time = 0;
    if ($music->config->expire_user_links == 'hour') {
        $time = time() - (60 * 60);
    }
    if ($music->config->expire_user_links == 'day') {
        $time = time() - (60 * 60 * 24);
    }
    if ($music->config->expire_user_links == 'week') {
        $time = time() - (60 * 60 * 24 * 7);
    }
    if ($music->config->expire_user_links == 'month') {
        $time = time() - (60 * 60 * 24 * date("t"));
    }
    if ($music->config->expire_user_links == 'year') {
        $time = time() - (60 * 60 * 24 * 365);
    }

    $query_one = " SELECT count(*) AS count FROM " . T_INVITAION_LINKS." WHERE `user_id` = '{$user_id}' AND `time` > '{$time}' ";
    $query = mysqli_query($sqlConnect, $query_one);
    if (mysqli_num_rows($query)) {
        $fetched_data = mysqli_fetch_assoc($query);
        if ($music->config->user_links_limit > 0) {
            if ($music->config->user_links_limit > $fetched_data['count']) {
                return true;
            }
            else{
                return false;
            }
        }
    }
    return true;
}
function IsUserInvitationExists($code = false) {
    global $music, $db,$sqlConnect, $lang_array;
    if (!$code) {
        return false;
    }
    $code      = Secure($code);
    $data_rows = mysqli_query($sqlConnect, "SELECT `id` FROM " . T_INVITAION_LINKS . " WHERE `code` = '$code' AND `invited_id` = 0");
    return mysqli_num_rows($data_rows) > 0;
}
function AddInvitedUser($user_id,$code)
{
    global $music, $db,$sqlConnect, $lang_array;
    if (empty($user_id) || !is_numeric($user_id) || $user_id < 1 || empty($code)) {
        return false;
    }
    $user_id = Secure($user_id);
    $code = Secure($code);
    $db->where('code',$code)->update(T_INVITAION_LINKS,array('invited_id' => $user_id));
}
function DeleteUserInvitation($col = '', $val = false) {
    global $music, $db,$sqlConnect, $lang_array;
    if (!$val && !$col) {
        return false;
    }
    $val = Secure($val);
    $col = Secure($col);
    return mysqli_query($sqlConnect, "DELETE FROM " . T_INVITAION_LINKS . " WHERE `$col` = '$val'");
}
function FFMPEGUpload($data)
{
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->loggedin == false || $music->config->ffmpeg_system != 'on' || empty($data) || empty($data['filename']) || empty($data['id']) || !is_numeric($data['id'])) {
        return false;
    }
    $ffmpeg_b                   = $music->config->ffmpeg_binary_file;
    if (!file_exists('upload/videos/' . date('Y'))) {
        @mkdir('upload/videos/' . date('Y'), 0777, true);
    }
    if (!file_exists('upload/videos/' . date('Y') . '/' . date('m'))) {
        @mkdir('upload/videos/' . date('Y') . '/' . date('m'), 0777, true);
    }
    $explode_video = explode('_video', $data['filename']);
    $video_file_full_path = dirname(dirname(__DIR__)).'/'.$data['filename'];
    $dir         = dirname(dirname(__DIR__));
    $video_path_240 = $explode_video[0] . "_video_240p_converted.mp4";
    $video_path_360 = $explode_video[0] . "_video_360p_converted.mp4";
    $video_path_480 = $explode_video[0] . "_video_480p_converted.mp4";
    $video_path_720 = $explode_video[0] . "_video_720p_converted.mp4";
    $video_path_1080 = $explode_video[0] . "_video_1080p_converted.mp4";
    $video_path_2048 = $explode_video[0] . "_video_2048p_converted.mp4";
    $video_path_4096 = $explode_video[0] . "_video_4096p_converted.mp4";
    $video_output_full_path_240 = $dir . "/".$video_path_240;
    $video_output_full_path_360 = $dir . "/".$video_path_360;
    $video_output_full_path_480 = $dir . "/".$video_path_480;
    $video_output_full_path_720 = $dir . "/".$video_path_720;
    $video_output_full_path_1080 = $dir . "/".$video_path_1080;
    $video_output_full_path_2048 = $dir . "/".$video_path_2048;
    $video_output_full_path_4096 = $dir . "/".$video_path_4096;
    $video_info     = shell_exec("$ffmpeg_b -i ".$video_file_full_path." 2>&1");
    $re = '/[0-9]{3}+x[0-9]{3}/m';
    preg_match_all($re, $video_info,$min_str);
    $resolution = 0;
    if (!empty($min_str) && !empty($min_str[0]) && !empty($min_str[0][0])) {
        $substr = substr($video_info, strpos($video_info, $min_str[0][0])-3,15);
        $re = '/[0-9]+x[0-9]+/m';
        preg_match_all($re, $substr,$resolutions);
        if (!empty($resolutions) && !empty($resolutions[0]) && !empty($resolutions[0][0])) {
            $resolution = substr($resolutions[0][0], 0,strpos($resolutions[0][0], 'x'));
        }
    }
    $ptrn     = '/Duration: ([0-9]{2}):([0-9]{2}):([^ ,])+/';
    $time     = 1;
    if (preg_match($ptrn, $video_info, $matches)) {
        $time = str_replace("Duration: ", "", $matches[0]);
        $time_breakdown = explode(":", $time);
        $time = round(($time_breakdown[0]*60*60) + ($time_breakdown[1]*60) + $time_breakdown[2]);
    }
    if ($time > 1) {
        $time = (int) ($time / 2);
    }


    $shell     = shell_exec("$ffmpeg_b -y -i $video_file_full_path -vcodec libx264 -preset ".$music->config->convert_speed." -filter:v scale=426:-2 -crf 26 $video_output_full_path_240 2>&1");

    if (file_exists($video_output_full_path_240)) {
        $db->where('id',Secure($data['id']))->update(T_EVENTS,array('240p' => 1,
                                                                    'video' => $video_path_240));
        if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == 'on' || $music->config->google_drive == 'on' || $music->config->spaces == 'on' || $music->config->wasabi_storage == 'on' || $music->config->backblaze_storage == 'on') {
            $upload_s3 = PT_UploadToS3($video_path_240);
        }
    }

    if ($resolution >= 640 || $resolution == 0) {
        $shell = shell_exec("$ffmpeg_b -y -i $video_file_full_path -vcodec libx264 -preset ".$music->config->convert_speed." -filter:v scale=640:-2 -crf 26 $video_output_full_path_360 2>&1");
        if (file_exists($video_output_full_path_360)) {
            $db->where('id',Secure($data['id']))->update(T_EVENTS,array('360p' => 1));
            if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == 'on'  || $music->config->spaces == 'on' || $music->config->wasabi_storage == 'on' || $music->config->backblaze_storage == 'on') {
                $upload_s3 = PT_UploadToS3($video_path_360);
            }
        }
    }
    if ($resolution >= 854 || $resolution == 0) {
        $shell     = shell_exec("$ffmpeg_b -y -i $video_file_full_path -vcodec libx264 -preset ".$music->config->convert_speed." -filter:v scale=854:-2 -crf 26 $video_output_full_path_480 2>&1");
        if (file_exists($video_output_full_path_480)) {
            $db->where('id',Secure($data['id']))->update(T_EVENTS,array('480p' => 1));
            if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == 'on' || $music->config->google_drive == 'on' || $music->config->spaces == 'on' || $music->config->wasabi_storage == 'on' || $music->config->backblaze_storage == 'on') {
                $upload_s3 = PT_UploadToS3($video_path_480);
            }
        }
    }
    if ($resolution >= 1280 || $resolution == 0) {
        $shell     = shell_exec("$ffmpeg_b -y -i $video_file_full_path -vcodec libx264 -preset ".$music->config->convert_speed." -filter:v scale=1280:-2 -crf 26 $video_output_full_path_720 2>&1");
        if (file_exists($video_output_full_path_720)) {
            $db->where('id',Secure($data['id']))->update(T_EVENTS,array('720p' => 1));
            if ($music->config->s3_upload == 'on' || $music->config->google_drive == 'on' || $music->config->ftp_upload == 'on' || $music->config->spaces == 'on' || $music->config->wasabi_storage == 'on' || $music->config->backblaze_storage == 'on') {
                $upload_s3 = PT_UploadToS3($video_path_720);
            }
        }
    }
    if ($resolution >= 1920 || $resolution == 0) {
        $shell     = shell_exec("$ffmpeg_b -y -i $video_file_full_path -vcodec libx264 -preset ".$music->config->convert_speed." -filter:v scale=1920:-2 -crf 26 $video_output_full_path_1080 2>&1");
        if (file_exists($video_output_full_path_1080)) {
            $db->where('id',Secure($data['id']))->update(T_EVENTS,array('1080p' => 1));
            if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == 'on' || $music->config->google_drive == 'on' || $music->config->spaces == 'on' || $music->config->wasabi_storage == 'on' || $music->config->backblaze_storage == 'on') {
                $upload_s3 = PT_UploadToS3($video_path_1080);
            }
        }
    }
    if ($resolution >= 2048 || $resolution == 0) {
        $shell     = shell_exec("$ffmpeg_b -y -i $video_file_full_path -vcodec libx264 -preset ".$music->config->convert_speed." -filter:v scale=2048:-2 -crf 26 $video_output_full_path_2048 2>&1");
        if (file_exists($video_output_full_path_2048)) {
            $db->where('id',Secure($data['id']))->update(T_EVENTS,array('2048p' => 1));
            if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == 'on' || $music->config->google_drive == 'on' || $music->config->spaces == 'on' || $music->config->wasabi_storage == 'on' || $music->config->backblaze_storage == 'on') {
                $upload_s3 = PT_UploadToS3($video_path_2048);
            }
        }
    }
    if ($resolution >= 3840 || $resolution == 0) {
        $shell     = shell_exec("$ffmpeg_b -y -i $video_file_full_path -vcodec libx264 -preset ".$music->config->convert_speed." -filter:v scale=3840:-2 -crf 26 $video_output_full_path_4096 2>&1");
        if (file_exists($video_output_full_path_4096)) {
            $db->where('id',Secure($data['id']))->update(T_EVENTS,array('4096p' => 1));
            if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == 'on' || $music->config->google_drive == 'on' || $music->config->spaces == 'on' || $music->config->wasabi_storage == 'on' || $music->config->backblaze_storage == 'on') {
                $upload_s3 = PT_UploadToS3($video_path_4096);
            }
        }
    }
    @unlink($video_file_full_path);
    return true;
}
function GetEventById($id,$hash = '')
{
    global $music, $db,$sqlConnect, $lang_array;
    if (empty($id)) {
        return false;
    }
    $id = secure($id);
    if (!empty($hash)) {
        $event = $db->where('hash_id', $id)->getOne(T_EVENTS);
    }
    else{
        $event = $db->where('id', $id)->getOne(T_EVENTS);
    }
    if (empty($event)) {
        return false;
    }
    $event->org_image = $event->image;
    $event->image = getMedia($event->image);
    if (!empty($event->video)) {
        $event->org_video = $event->video;
        $event->video = getMedia($event->video);
    }
    $event->user_data = userData($event->user_id);
    $event->start_date_js = date('m/d/y' , strtotime($event->start_date . $event->start_time));
    $event->url = getLink("event/".URLSlug($event->name,$event->hash_id));
    $event->data_load = "event/".URLSlug($event->name,$event->hash_id);
    $event->edit_url = getLink("edit_event/".URLSlug($event->name,$event->hash_id));
    $event->edit_data_load = "edit_event/".URLSlug($event->name,$event->hash_id);
    if ($music->loggedin) {
        $event->is_joined = $db->where('event_id',$event->id)->where('user_id',$music->user->id)->getValue(T_EVENTS_JOINED,'COUNT(*)');
    }
    $event->joined_count = $db->where('event_id',$event->id)->getValue(T_EVENTS_JOINED,'COUNT(*)');
    return $event;
}
function TotalGoingUsers($event_id) {
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->loggedin == false) {
        return false;
    }
    if (!$event_id || !is_numeric($event_id)) {
        return 0;
    }
    $event_id     = Secure($event_id);
    $user_id      = $music->user->id;
    $data         = array();
    $sql          = "SELECT COUNT(`id`) AS count FROM " . T_EVENTS_JOINED . " WHERE `event_id` = '$event_id'";
    $query        = mysqli_query($sqlConnect, $sql);
    if (mysqli_num_rows($query)) {
        $fetched_data = mysqli_fetch_assoc($query);
        return $fetched_data['count'];
    }
    return false;
}
function GetAllFollowStories($info = array())
{
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->loggedin == false && empty($_SESSION['fingerPrint'])) {
        return false;
    }
    $data = array();
    if ($music->loggedin) {
        $stories = $db->rawQuery("SELECT `id` FROM `".T_STORY."` WHERE `user_id` IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') GROUP BY `user_id` ORDER BY `user_id` DESC, `id` DESC");

        $myStories = $db->where("`user_id` = '".$music->user->id."'")->orderBy('id', 'DESC')->getOne(T_STORY,array('id'));
        array_unshift($stories, $myStories);
    }
    else{
        $stories = $db->rawQuery("SELECT `id` FROM `".T_STORY."` WHERE `paid` = '1' GROUP BY `user_id` ORDER BY `user_id` DESC, `id` DESC");
    }
    if (!empty($stories)) {
        foreach ($stories as $key => $value) {
            if (!empty($value) && !empty($value->id)) {
                $data[] = GetStory($value->id);
            }
        }
    }

    return $data;
}
function GetStory($id)
{
    global $music, $db,$sqlConnect, $lang_array;
    // if ($music->loggedin == false) {
    //     return false;
    // }
    if (empty($id) || !is_numeric($id)) {
        return false;
    }
    $id = Secure($id);
    $story = $db->where("id",$id)->getOne(T_STORY);
    $story->user_data = userData($story->user_id);
    $story->org_image = $story->image;
    $story->image = getMedia($story->image);
    $story->org_audio = $story->audio;
    $story->audio = getMedia($story->audio);
    $story->views_count = $db->where('story_id',$story->id)->where('user_id',$story->user_id,'!=')->getValue(T_STORY_SEEN,'COUNT(*)');
    $story->views_users = GetViewsUsers(array('story_id' => $story->id,
                                              'user_id' => $story->user_id));
    return $story;
}
function GetViewsUsers($info = array())
{
    global $music, $db,$sqlConnect, $lang_array;
    $data = array();
    if ($music->loggedin == false) {
        return $data;
    }
    if (empty($info['story_id']) || !is_numeric($info['story_id'])) {
        return false;
    }
    $limit = 10;
    $story_id = Secure($info['story_id']);
    if (!empty($info['limit']) && is_numeric($info['limit'])) {
        $limit = Secure($info['limit']);
    }
    if (!empty($info['offset'])) {
        $offset = Secure($info['offset']);
        $db->where('id',$offset,'>');
    }

    if ($music->loggedin) {
        $db->where('user_id',$music->user->id,'!=');
    }
    $seen = $db->where('story_id',$story_id)->where('story_owner_id',$music->user->id)->get(T_STORY_SEEN,$limit);
    foreach ($seen as $key => $value) {
        if (empty($value->fingerPrint)) {
            $value->user_data = userData($value->user_id);
        }
        else{
            $value->user_data = new stdClass();
            $value->user_data->avatar = getMedia('upload/photos/d-avatar.jpg');
            $value->user_data->url = 'javascript:void(0)';
            $value->user_data->username = '';
            $value->user_data->name = lang('Guest');
            $value->user_data->id = '';
        }
        $data[] = $value;
    }
    return $data;
}
function StartFollowStories($info = array())
{
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->loggedin == false && empty($_SESSION['fingerPrint'])) {
        return false;
    }
    if (empty($info['user_id']) || !is_numeric($info['user_id'])) {
        return false;
    }
    $user_id = secure($info['user_id']);
    $data = array();
    if ($music->loggedin) {
        $current = $db->where(" (`user_id` IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') OR user_id = '".$music->user->id."') AND user_id = '".$user_id."' AND `user_id` NOT IN (SELECT `user_id` FROM " . T_STORY_SEEN . " WHERE `story_id` = ".T_STORY.".id)")->orderBy('id', 'DESC')->getOne(T_STORY);
    }
    else{
        $current = $db->where(" user_id = '".$user_id."' AND `paid` = '1' AND `id` NOT IN (SELECT `story_id` FROM " . T_STORY_SEEN . " WHERE `fingerPrint` = '".secure($_SESSION['fingerPrint'])."')")->orderBy('id', 'DESC')->getOne(T_STORY);
    }


    if (!empty($current)) {
        if ($music->loggedin) {
            $db->where('user_id',$music->user->id);
        }
        else{
            $db->where('fingerPrint',secure($_SESSION['fingerPrint']));
        }
        $is_seen = $db->where('story_id',$current->id)->getValue(T_STORY_SEEN,'COUNT(*)');
        if ($is_seen < 1) {
            if ($music->loggedin) {
                $db->insert(T_STORY_SEEN,array('user_id' => $music->user->id,
                                               'story_id' => $current->id,
                                               'story_owner_id' => $current->user_id,
                                               'time' => time(),
                                               'paid' => $current->paid));
            }
            else{
                $db->insert(T_STORY_SEEN,array('fingerPrint' => secure($_SESSION['fingerPrint']),
                                               'story_id' => $current->id,
                                               'story_owner_id' => $current->user_id,
                                               'time' => time(),
                                               'paid' => $current->paid));
            }

        }
        if ($music->loggedin) {
            $stories = $db->rawQuery("SELECT `id` FROM `".T_STORY."` WHERE `user_id` IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') ORDER BY `user_id` DESC, `id` DESC");
        }
        else{
            $stories = $db->rawQuery("SELECT `id` FROM `".T_STORY."` WHERE `paid` = '1' ORDER BY `user_id` DESC, `id` DESC");
        }

        if ($music->loggedin) {
            $myStories = $db->where("`user_id` = '".$music->user->id."'")->orderBy('id', 'DESC')->get(T_STORY,null,array('id'));
            $merge = array_merge($myStories, $stories);
        }
        else{
            $merge = $stories;
        }
        if (!empty($merge)) {
            foreach ($merge as $key => $value) {
                if ($value->id == $current->id) {
                    $story = GetStory($current->id);
                    if (!empty($merge[$key + 1])) {
                        $story->next = GetStory($merge[$key + 1]->id);
                    }
                    if (!empty($merge[$key - 1])) {
                        $story->pre = GetStory($merge[$key - 1]->id);
                    }
                }
            }
        }

        return $story;
    }
    else{
        if ($music->loggedin) {
            $current = $db->where(" (`user_id` IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') OR user_id = '".$music->user->id."') AND user_id = '".$user_id."' AND `user_id` IN (SELECT `user_id` FROM " . T_STORY_SEEN . " WHERE `story_id` = ".T_STORY.".id)")->orderBy('id', 'DESC')->getOne(T_STORY);
        }
        else{
            $current = $db->where(" user_id = '".$user_id."' AND `id` IN (SELECT `story_id` FROM " . T_STORY_SEEN . " WHERE `fingerPrint` = '".secure($_SESSION['fingerPrint'])."')")->orderBy('id', 'DESC')->getOne(T_STORY);
        }

        if (!empty($current)) {
            if ($music->loggedin) {
                $stories = $db->rawQuery("SELECT `id` FROM `".T_STORY."` WHERE `user_id` IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') ORDER BY `user_id` DESC, `id` DESC");
                $myStories = $db->where("`user_id` = '".$music->user->id."'")->orderBy('id', 'DESC')->get(T_STORY,null,array('id'));
                $merge = array_merge($myStories, $stories);
            }
            else{
                $merge = $db->rawQuery("SELECT `id` FROM `".T_STORY."` WHERE `paid` = '1' ORDER BY `user_id` DESC, `id` DESC");
            }
            if (!empty($merge)) {
                foreach ($merge as $key => $value) {
                    if ($value->id == $current->id) {
                        $story = GetStory($current->id);
                        if (!empty($merge[$key + 1])) {
                            $story->next = GetStory($merge[$key + 1]->id);
                        }
                        if (!empty($merge[$key - 1])) {
                            $story->pre = GetStory($merge[$key - 1]->id);
                        }
                    }
                }
            }

            return $story;
        }
    }
    return false;
}
function NextFollowStories($info = array())
{
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->loggedin == false && empty($_SESSION['fingerPrint'])) {
        return false;
    }
    if (empty($info['next_user_id']) || !is_numeric($info['next_user_id']) || empty($info['next_story_id']) || !is_numeric($info['next_story_id'])) {
        return false;
    }
    $next_user_id = Secure($info['next_user_id']);
    $next_story_id = Secure($info['next_story_id']);
    if ($music->loggedin) {
        $stories = $db->rawQuery("SELECT `id` FROM `".T_STORY."` WHERE `user_id` IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') ORDER BY `user_id` DESC, `id` DESC");
        $myStories = $db->where("`user_id` = '".$music->user->id."'")->orderBy('id', 'DESC')->get(T_STORY,null,array('id'));
        $merge = array_merge($myStories, $stories);
    }
    else{
        $merge = $db->rawQuery("SELECT `id` FROM `".T_STORY."` WHERE `paid` = '1' ORDER BY `user_id` DESC, `id` DESC");
    }
    if (!empty($merge)) {
        foreach ($merge as $key => $value) {
            if ($value->id == $next_story_id) {
                $story = GetStory($next_story_id);
                if (!empty($merge[$key + 1])) {
                    $story->next = GetStory($merge[$key + 1]->id);
                }
                if (!empty($merge[$key - 1])) {
                    $story->pre = GetStory($merge[$key - 1]->id);
                }
            }
        }
        if ($music->loggedin) {
            $db->where('user_id',$music->user->id);
        }
        else{
            $db->where('fingerPrint',secure($_SESSION['fingerPrint']));
        }
        $is_seen = $db->where('story_id',$story->id)->getValue(T_STORY_SEEN,'COUNT(*)');
        if ($is_seen < 1) {
            if ($music->loggedin) {
                $db->insert(T_STORY_SEEN,array('user_id' => $music->user->id,
                                               'story_id' => $story->id,
                                               'story_owner_id' => $story->user_id,
                                               'time' => time(),
                                               'paid' => $story->paid));
            }
            else{
                $db->insert(T_STORY_SEEN,array('fingerPrint' => secure($_SESSION['fingerPrint']),
                                               'story_id' => $story->id,
                                               'story_owner_id' => $story->user_id,
                                               'time' => time(),
                                               'paid' => $story->paid));
            }
        }
        return $story;
    }
    return false;
}
function PreviousFollowStories($info = array())
{
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->loggedin == false && empty($_SESSION['fingerPrint'])) {
        return false;
    }
    if (empty($info['pre_user_id']) || !is_numeric($info['pre_user_id']) || empty($info['pre_story_id']) || !is_numeric($info['pre_story_id'])) {
        return false;
    }
    $pre_user_id = Secure($info['pre_user_id']);
    $pre_story_id = Secure($info['pre_story_id']);
    if ($music->loggedin) {
        $stories = $db->rawQuery("SELECT `id` FROM `".T_STORY."` WHERE `user_id` IN (SELECT `following_id` FROM " . T_FOLLOWERS . " WHERE `follower_id` = '".$music->user->id."') ORDER BY `user_id` DESC, `id` DESC");
        $myStories = $db->where("`user_id` = '".$music->user->id."'")->orderBy('id', 'DESC')->get(T_STORY,null,array('id'));
        $merge = array_merge($myStories, $stories);
    }
    else{
        $merge = $db->rawQuery("SELECT `id` FROM `".T_STORY."` WHERE `paid` = '1' ORDER BY `user_id` DESC, `id` DESC");
    }
    if (!empty($merge)) {
        foreach ($merge as $key => $value) {
            if ($value->id == $pre_story_id) {
                $story = GetStory($pre_story_id);
                if (!empty($merge[$key + 1])) {
                    $story->next = GetStory($merge[$key + 1]->id);
                }
                if (!empty($merge[$key - 1])) {
                    $story->pre = GetStory($merge[$key - 1]->id);
                }
            }
        }
        if ($music->loggedin) {
            $db->where('user_id',$music->user->id);
        }
        else{
            $db->where('fingerPrint',secure($_SESSION['fingerPrint']));
        }
        $is_seen = $db->where('story_id',$story->id)->getValue(T_STORY_SEEN,'COUNT(*)');
        if ($is_seen < 1) {
            if ($music->loggedin) {
                $db->insert(T_STORY_SEEN,array('user_id' => $music->user->id,
                                               'story_id' => $story->id,
                                               'story_owner_id' => $story->user_id,
                                               'time' => time(),
                                               'paid' => $story->paid));
            }
            else{
                $db->insert(T_STORY_SEEN,array('fingerPrint' => secure($_SESSION['fingerPrint']),
                                               'story_id' => $story->id,
                                               'story_owner_id' => $story->user_id,
                                               'time' => time(),
                                               'paid' => $story->paid));
            }
        }
        return $story;
    }
    return false;
}
function GetProduct($id,$hash = '')
{
    global $music, $db,$sqlConnect, $lang_array;
    if (empty($id)) {
        return false;
    }
    if (!empty($hash)) {
        $product = $db->where('hash_id',secure($id))->getOne(T_PRODUCTS);
    }
    else{
        $product = $db->where('id',secure($id))->getOne(T_PRODUCTS);
    }

    $images = $db->where('product_id',$product->id)->get(T_MEDIA);
    $images_data = array();
    foreach ($images as $key => $value) {
        $new = array();
        $new['org_image'] = $value->image;
        $new['image'] = getMedia($value->image);
        $product->images[] = $new;
    }
    $product->user_data = userData($product->user_id);
    $product->related_song = songData($product->related_song);
    $product->url = getLink("product/".URLSlug($product->title,$product->hash_id));
    $product->data_load = "product/".URLSlug($product->title,$product->hash_id);
    $product->edit_url = getLink("edit_product/".URLSlug($product->title,$product->hash_id));
    $product->edit_data_load = "edit_product/".URLSlug($product->title,$product->hash_id);
    $product->added_to_cart = 0;
    if (IS_LOGGED) {
        $product->added_to_cart = $db->where('product_id',$product->id)->where('user_id',$music->user->id)->getValue(T_CARD,'COUNT(*)');
    }
    $product->rating = $db->where('product_id',$product->id)->getValue(T_REVIEW,"FLOOR(sum(star)/count(id))");
    if (empty($product->rating)) {
        $product->rating = 0;
    }
    $product->reviews_count = $db->where('product_id',$product->id)->getValue(T_REVIEW,"count(id)");
    $product->formatted_price = number_format($product->price);
    return $product;
}
function GetCategoriesKeys($table)
{
    global $music, $db,$sqlConnect, $lang_array;
    if ($music->config->script_version >= '1.4') {
        $data = array();
        $categories = mysqli_query($sqlConnect, "SELECT * FROM " . $table);
        if (mysqli_num_rows($categories)) {
            while ($fetched_data = mysqli_fetch_assoc($categories)) {
                $data[$fetched_data['id']] = lang($fetched_data['lang_key']);
            }
            return $data;
        }
        return false;
    }
}
function GetLangDetails($lang_key = '') {
    global $music, $db,$sqlConnect, $lang_array;
    if (empty($lang_key)) {
        return false;
    }
    $lang_key = Secure($lang_key);
    $data     = array();
    $query    = mysqli_query($sqlConnect, "SELECT * FROM " . T_LANGS . " WHERE `lang_key` = '{$lang_key}'");
    if (mysqli_num_rows($query)) {
        while ($fetched_data = mysqli_fetch_assoc($query)) {
            //unset($fetched_data['lang_key']);
            //unset($fetched_data['id']);
            unset($fetched_data['ref']);
            unset($fetched_data['options']);
            $data[] = $fetched_data;
        }
    }

    return $data;
}
function GetReview($id)
{
    global $music, $db,$sqlConnect, $lang_array;
    if (empty($id) || !is_numeric($id)) {
        return false;
    }
    $review = $db->where('id',Secure($id))->getOne(T_REVIEW);
    $images = $db->where('review_id',$review->id)->get(T_MEDIA);
    $images_data = array();
    foreach ($images as $key => $value) {
        $new = array();
        $new['org_image'] = $value->image;
        $new['image'] = getMedia($value->image);
        $review->images[] = $new;
    }
    $review->user_data = userData($review->user_id);
    return $review;
}
function return_bytes($val) {
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= (1024 * 1024 * 1024); //1073741824
            break;
        case 'm':
            $val *= (1024 * 1024); //1048576
            break;
        case 'k':
            $val *= 1024;
            break;
    }

    return $val;
}

function formatBytes($size, $precision = 2)
{
    $base = log($size, 1024);
    $suffixes = array('', 'K', 'M', 'G', 'T');

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}
function GetNgeniusToken()
{
    global $music, $sqlConnect,$db;
    $ch = curl_init(); 
    if ($music->config->ngenius_mode == 'sandbox') {
        curl_setopt($ch, CURLOPT_URL, "https://api-gateway.sandbox.ngenius-payments.com/identity/auth/access-token"); 
    }
    else{
        curl_setopt($ch, CURLOPT_URL, "https://identity-uat.ngenius-payments.com/auth/realms/ni/protocol/openid-connect/token"); 
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "accept: application/vnd.ni-identity.v1+json",
        "authorization: Basic ".$music->config->ngenius_api_key,
        "content-type: application/vnd.ni-identity.v1+json"
      )); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
    curl_setopt($ch, CURLOPT_POST, 1); 
    curl_setopt($ch, CURLOPT_POSTFIELDS,  "{\"realmName\":\"ni\"}"); 
    $output = json_decode(curl_exec($ch)); 
    return $output;
}
function CreateNgeniusOrder($token,$postData)
{
    global $music, $sqlConnect,$db;

    $json = json_encode($postData);
    $ch = curl_init();
    if ($music->config->ngenius_mode == 'sandbox') {
        curl_setopt($ch, CURLOPT_URL, "https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/".$music->config->ngenius_outlet_id."/orders");
    }
    else{
        curl_setopt($ch, CURLOPT_URL, "https://api-gateway-uat.ngenius-payments.com/transactions/outlets/".$music->config->ngenius_outlet_id."/orders");
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer ".$token, 
    "Content-Type: application/vnd.ni-payment.v2+json",
    "Accept: application/vnd.ni-payment.v2+json"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

    $output = json_decode(curl_exec($ch));
    curl_close ($ch);
    return $output;
}
function NgeniusCheckOrder($token,$ref)
{
    global $music, $sqlConnect,$db;
    $ch = curl_init();
    if ($music->config->ngenius_mode == 'sandbox') {
        curl_setopt($ch, CURLOPT_URL, "https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/".$music->config->ngenius_outlet_id."/orders/".$ref);
    }
    else{
        curl_setopt($ch, CURLOPT_URL, "https://api-gateway-uat.ngenius-payments.com/transactions/outlets/".$music->config->ngenius_outlet_id."/orders/".$ref);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer ".$token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $output = json_decode(curl_exec($ch));
    curl_close ($ch);
    return $output;
}
function coinpayments_api_call($req = array()) {
    global $music, $sqlConnect,$db;
    $result = array('status' => 400);

    // Generate the query string
    $post_data = http_build_query($req, '', '&');
    // echo $post_data;
    // echo "<br>";
    // Calculate the HMAC signature on the POST data
    $hmac = hash_hmac('sha512', $post_data, $music->config->coinpayments_secret);
    // echo $hmac;
    // exit();

    $ch = curl_init('https://www.coinpayments.net/api.php');
    curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('HMAC: '.$hmac));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

    // Execute the call and close cURL handle
    $data = curl_exec($ch);
    // Parse and return data if successful.

    if ($data !== FALSE) {
        $info = json_decode($data, TRUE);
        if (!empty($info) && !empty($info['result'])) {
            $result = array('status' => 200,
                            'data' => $info['result']);
        }
        else{
            $result['message'] = $info['error'];
        }
    } else {
        $result['message'] = 'cURL error: '.curl_error($ch);
    }
    return $result;
}
function CheckHavePermission($page='')
{
    global $music;

    if (IS_LOGGED == false || empty($page)) {
        return false;
    }
    if (empty($music->user->permission)) {
        return false;
    }

    $permission = json_decode($music->user->permission,true);
    if (!empty($permission) && is_array($permission)) {
        if(isset($permission[$page]) && $permission[$page] == "1") {
            return true;
        }
    }
    return false;
}
function CheckHaveMultiPermission($pages=array())
{
    global $music;

    if (IS_LOGGED == false || empty($pages)) {
        return false;
    }
    if (empty($music->user->permission)) {
        return false;
    }

    $permission = json_decode($music->user->permission,true);
    if (!empty($permission) && is_array($permission)) {
        foreach ($pages as $key => $value) {
            if(isset($permission[$value]) && $permission[$value] == "1") {
                return true;
            }
        }
    }
    return false;
}
function CanUseThisFeature($user_id,$feature)
{
    global $music;
    if ($music->loggedin) {
        $user = userData(Secure($user_id));
        if (!empty($user) && in_array(Secure($feature),array_keys($music->manage_pro_features))) {
            if ($music->config->{$feature} == 'all') {
                return true;
            }
            if ($music->config->{$feature} == 'verified' && $user->verified) {
                return true;
            }
            if ($music->config->{$feature} == 'admin' && $user->admin) {
                return true;
            }
            if ($music->config->{$feature} == 'artist' && $user->artist) {
                return true;
            }
            if ($music->config->{$feature} == 'pro' && $user->is_pro && !empty($music->pro_packages[$user->pro_type]) && $music->pro_packages[$user->pro_type][$music->manage_pro_features[$feature]] == 1) {
                return true;
            }
            if ($user->admin) {
                return true;
            }
        }
    }
    return false;
}
function GetAllProInfo() {
    global $music, $sqlConnect,$db,$lang_array;
    
    $data = array();
    $packages = $db->arrayBuilder()->get(T_MANAGE_PRO);
    foreach ($packages as $fetched_key => $fetched_data) {

        if (!empty($fetched_data['features'])) {
            foreach (json_decode($fetched_data['features'],true) as $key => $value) {
                $fetched_data[$key] = $value;
            }
        }

        if (!empty($fetched_data["image"])) {
            $fetched_data["image"] = getMedia($fetched_data["image"]);
        }
        if (!empty($fetched_data["night_image"])) {
            $fetched_data["night_image"] = getMedia($fetched_data["night_image"]);
        }
        $fetched_data['name'] = $fetched_data['type'];

        $fetched_data['name'] = preg_replace_callback("/{LANG_KEY (.*?)}/", function($m) use ($lang_array) {
            return lang($m[1]);
        }, $fetched_data['name']);

        $fetched_data['ex_time'] = 60 * 60 * 24;
        if (!empty($fetched_data["time"]) && $fetched_data["time"] == 'day') {
            if (!empty($fetched_data["time_count"]) && is_numeric($fetched_data["time_count"]) && $fetched_data["time_count"] > 0) {
                $fetched_data['ex_time']  = $fetched_data['ex_time'] * $fetched_data["time_count"];
            }
        }
        else if (!empty($fetched_data["time"]) && $fetched_data["time"] == 'week') {
            $fetched_data['ex_time'] = $fetched_data['ex_time'] * 7;
            if (!empty($fetched_data["time_count"]) && is_numeric($fetched_data["time_count"]) && $fetched_data["time_count"] > 0) {
                $fetched_data['ex_time']  = $fetched_data['ex_time'] * $fetched_data["time_count"];
            }
        }
        else if (!empty($fetched_data["time"]) && $fetched_data["time"] == 'month') {
            $fetched_data['ex_time'] = $fetched_data['ex_time'] * 30;
            if (!empty($fetched_data["time_count"]) && is_numeric($fetched_data["time_count"]) && $fetched_data["time_count"] > 0) {
                $fetched_data['ex_time']  = $fetched_data['ex_time'] * $fetched_data["time_count"];
            }
        }
        else if (!empty($fetched_data["time"]) && $fetched_data["time"] == 'year') {
            $fetched_data['ex_time'] = $fetched_data['ex_time'] * 365;
            if (!empty($fetched_data["time_count"]) && is_numeric($fetched_data["time_count"]) && $fetched_data["time_count"] > 0) {
                $fetched_data['ex_time']  = $fetched_data['ex_time'] * $fetched_data["time_count"];
            }
        }
        else if (!empty($fetched_data["time"]) && $fetched_data["time"] == 'unlimited') {
            $fetched_data['ex_time'] = 0;
        }

        $fetched_data['html_icon'] = loadPage("user/pro-icon");
        if (empty($_COOKIE['mode'])) {
            if (!empty($fetched_data["image"])) {
                $fetched_data['html_icon'] = '<div><img src="'.$fetched_data["image"].'" class="pro_packages_icon"></div>';
            }
            elseif (!empty($fetched_data["night_image"])) {
                $fetched_data['html_icon'] = '<div><img src="'.$fetched_data["night_image"].'" class="pro_packages_icon"></div>';
            }
            else{
                $fetched_data['html_icon'] = loadPage("user/pro-icon");
            }
        }
        elseif (!empty($_COOKIE['mode']) && $_COOKIE['mode'] == 'day' && !empty($fetched_data["image"])) {
            $fetched_data['html_icon'] = '<div><img src="'.$fetched_data["image"].'" class="pro_packages_icon"></div>';
        }
        elseif (!empty($_COOKIE['mode']) && $_COOKIE['mode'] == 'night' && !empty($fetched_data["night_image"])) {
            $fetched_data['html_icon'] = '<div><img src="'.$fetched_data["night_image"].'" class="pro_packages_icon"></div>';
        }
        else if ($fetched_data['id'] == 1) {
            $fetched_data['html_icon'] = '<span style="color: '.(!empty($fetched_data['color']) ? $fetched_data['color'] : "#4c7737").'"><svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" viewBox="0 0 512 512" xml:space="preserve"> <g> <path style="fill:#69788D;" d="M454.929,459.436l-124-214.774c-2.209-3.825-7.101-5.138-10.929-2.929l-69.282,40 c-1.838,1.062-3.179,2.809-3.728,4.858s-0.262,4.232,0.8,6.07l124,214.774c3.489,6.046,12.851,4.8,14.655-1.93l12.571-46.914 l46.913,12.571C452.669,472.97,458.42,465.485,454.929,459.436z"/> <path style="fill:#69788D;" d="M265.01,286.592c-0.549-2.05-1.89-3.797-3.728-4.858l-69.282-40 c-3.826-2.207-8.719-0.898-10.929,2.929l-124,214.774c-3.492,6.049,2.26,13.533,8.999,11.728l46.913-12.571l12.571,46.914 c1.804,6.73,11.166,7.976,14.655,1.93l124-214.774C265.271,290.824,265.559,288.64,265.01,286.592z"/> </g> <g> <path style="fill:#56677E;" d="M369.984,312.307l-86.527,42.131l24.769,42.902h27.079c12.238,0,23.636-5.487,31.273-15.061 l20.318-25.479l6.866-3.307L369.984,312.307z"/> <path style="fill:#56677E;" d="M118.237,353.494l6.866,3.307l20.323,25.484c7.633,9.567,19.03,15.055,31.269,15.055h27.079 l23.812-41.243l-78.818-55.484L118.237,353.494z"/> </g> <path style="fill:#FFDB66;" d="M433.119,192.662l8.877-38.892c0.541-2.374-0.027-4.864-1.545-6.769l-24.872-31.188l-8.877-38.893 c-0.542-2.373-2.136-4.371-4.329-5.428l-35.941-17.308L341.56,22.996c-1.519-1.903-3.82-3.012-6.255-3.012h-39.892L259.471,2.676 c-2.193-1.057-4.748-1.057-6.941,0l-35.942,17.309h-39.892c-2.435,0-4.736,1.108-6.255,3.012l-24.872,31.189l-35.941,17.308 c-2.193,1.057-3.787,3.055-4.329,5.428l-8.877,38.893L71.55,147.003c-1.518,1.904-2.086,4.395-1.545,6.769l8.877,38.892 l-8.877,38.892c-0.541,2.374,0.027,4.864,1.545,6.769l24.872,31.188l8.877,38.893c0.542,2.373,2.136,4.371,4.329,5.428 l35.941,17.308l24.872,31.189c1.519,1.903,3.82,3.012,6.255,3.012h39.892l35.942,17.309c1.097,0.528,2.283,0.792,3.471,0.792 s2.374-0.264,3.471-0.792l35.942-17.309h39.892c2.435,0,4.736-1.108,6.255-3.012l24.872-31.189l35.941-17.308 c2.193-1.057,3.787-3.055,4.329-5.428l8.877-38.893l24.872-31.188c1.518-1.904,2.086-4.395,1.545-6.769L433.119,192.662z"/> <path style="fill:#F7C14D;" d="M256,32.662c-88.225,0-160,71.775-160,160s71.775,160,160,160s160-71.775,160-160 S344.225,32.662,256,32.662z"/> <path style="fill:#FF8C78;" d="M256,48.662c-79.402,0-144,64.598-144,144s64.598,144,144,144s144-64.598,144-144 S335.402,48.662,256,48.662z"/> <path style="fill:#DB6B5E;" d="M256,64.662c-70.58,0-128,57.42-128,128s57.42,128,128,128s128-57.42,128-128 S326.58,64.662,256,64.662z"/> <g> <path style="fill:#F7C14D;" d="M72,272.662c-11.352,0-24-19.713-24-48c0-4.418-3.582-8-8-8s-8,3.582-8,8c0,28.287-12.648,48-24,48 c-4.418,0-8,3.582-8,8s3.582,8,8,8c11.352,0,24,19.713,24,48c0,4.418,3.582,8,8,8s8-3.582,8-8c0-28.287,12.648-48,24-48 c4.418,0,8-3.582,8-8S76.418,272.662,72,272.662z"/> <path style="fill:#F7C14D;" d="M504,384.662c-8.673,0-16-10.99-16-24c0-4.418-3.582-8-8-8s-8,3.582-8,8c0,13.01-7.327,24-16,24 c-4.418,0-8,3.582-8,8s3.582,8,8,8c8.673,0,16,10.99,16,24c0,4.418,3.582,8,8,8s8-3.582,8-8c0-13.01,7.327-24,16-24 c4.418,0,8-3.582,8-8S508.418,384.662,504,384.662z"/> <path style="fill:#F7C14D;" d="M464,40.662c0-4.418-3.582-8-8-8c-8.673,0-16-10.99-16-24c0-4.418-3.582-8-8-8s-8,3.582-8,8 c0,13.01-7.327,24-16,24c-4.418,0-8,3.582-8,8s3.582,8,8,8c8.673,0,16,10.99,16,24c0,4.418,3.582,8,8,8s8-3.582,8-8 c0-13.01,7.327-24,16-24C460.418,48.662,464,45.08,464,40.662z"/> </g> <g> <path style="fill:#ECEEF1;" d="M314.402,115.664c-1.518-1.898-3.817-3.002-6.247-3.002H264h-16h-44.155 c-2.43,0-4.729,1.104-6.247,3.002L168,152.662v40l88,96l88-96v-40L314.402,115.664z"/> <path style="fill:#ECEEF1;" d="M264,112.662l32,40h48l-29.598-36.998c-1.518-1.898-3.817-3.002-6.247-3.002 C308.155,112.662,264,112.662,264,112.662z"/> </g> <rect x="296" y="152.659" style="fill:#D9DDE2;" width="48" height="40"/> <g> <polygon style="fill:#C7CCD4;" points="344,192.662 256,288.662 296,192.662 "/> <path style="fill:#C7CCD4;" d="M248,112.662l-32,40h-48l29.598-36.998c1.518-1.898,3.817-3.002,6.247-3.002 C203.845,112.662,248,112.662,248,112.662z"/> </g> <rect x="168" y="152.659" style="fill:#B4BBC6;" width="48" height="40"/> <polygon style="fill:#A1ABB8;" points="168,192.662 256,288.662 216,192.662 "/> <polygon style="fill:#D9DDE2;" points="296,152.662 264,112.662 248,112.662 216,152.662 "/> <rect x="216" y="152.659" style="fill:#C7CCD4;" width="80" height="40"/> <polygon style="fill:#B4BBC6;" points="296,192.662 256,288.662 216,192.662 "/></svg></span>';

        }



        $data[$fetched_data["id"]] = $fetched_data;

    }
    return $data;
}

function SaveStorageVideo($url='',$saveTo = '')
{
    global $pt,$music;

    if (!file_exists('upload/photos/' . date('Y'))) {
        @mkdir('upload/photos/' . date('Y'), 0777, true);
    }
    if (!file_exists('upload/photos/' . date('Y') . '/' . date('m'))) {
        @mkdir('upload/photos/' . date('Y') . '/' . date('m'), 0777, true);
    }
    if (!file_exists('upload/audio/' . date('Y'))) {
        @mkdir('upload/audio/' . date('Y'), 0777, true);
    }
    if (!file_exists('upload/audio/' . date('Y') . '/' . date('m'))) {
        @mkdir('upload/audio/' . date('Y') . '/' . date('m'), 0777, true);
    }
    if (!file_exists('upload/waves/' . date('Y'))) {
        @mkdir('upload/waves/' . date('Y'), 0777, true);
    }
    if (!file_exists('upload/waves/' . date('Y') . '/' . date('m'))) {
        @mkdir('upload/waves/' . date('Y') . '/' . date('m'), 0777, true);
    }
    if (!file_exists('upload/videos/' . date('Y'))) {
        @mkdir('upload/videos/' . date('Y'), 0777, true);
    }
    if (!file_exists('upload/videos/' . date('Y') . '/' . date('m'))) {
        @mkdir('upload/videos/' . date('Y') . '/' . date('m'), 0777, true);
    }

    $ch = curl_init();
    $headers = array(
        'Range: bytes=0-',
    );
    $options = array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLINFO_HEADER_OUT    => true,
        CURLOPT_USERAGENT => 'okhttp',
        CURLOPT_ENCODING       => "utf-8",
        CURLOPT_AUTOREFERER    => true,
        CURLOPT_COOKIEJAR      => 'cookie.txt',
        CURLOPT_COOKIEFILE     => 'cookie.txt',
        CURLOPT_REFERER        => 'http://localhost/',
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 600,
        CURLOPT_MAXREDIRS      => 10,
    );
    curl_setopt_array( $ch, $options );

    $data = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $filename = 'upload/audio/' . date('Y') . '/' . date('m').'/' .generateKey() . ".mp3";
    if (!empty($saveTo)) {
        $filename = $saveTo;
    }
    $d = fopen($filename, "w");
    fwrite($d, $data);
    fclose($d);

    return $filename;
}
function GenrateCode($user_id, $app_id) {
    global $sqlConnect, $music,$db;
    $app_id  = secure($app_id);
    $user_id = secure($user_id);
    if (empty($app_id) || empty($user_id)) {
        return false;
    }
    $token     = generateKey(40, 40);
    $have_code = $db->where('app_id',$app_id)->where('user_id',$user_id)->getValue(T_APPS_CODES,'COUNT(*)');
    if ($have_code) {
        $db->where('app_id',$app_id)->where('user_id',$user_id)->delete(T_APPS_CODES);
    }

    $db->insert(T_APPS_CODES,[
        'user_id' => $user_id,
        'app_id' => $app_id,
        'code' => $token,
        'time' => time()
    ]);
    return $token;
}

function runPlugin($hook, $dataImport = []) {
    global $music;
    if (empty($hook)) {
        return false;
    }
    $music->hooks->do_action($hook, $dataImport);
}

function getPlugins() {
    global $music, $db;
    $getPlugins = glob("plugins/*", GLOB_BRACE);
    $finalData = [];
    foreach ($getPlugins as $plugin) {
        if (is_dir($plugin)) {
            $pluginData['is_settings'] = false;
            $getPluginFromDB = $db->where("name", secure(basename($plugin)))->getOne('plugins');
            $pluginData = ['name' => basename($plugin), 'status' => '0', 'installed' => true];
            $pluginData['path'] = $plugin;
            if (isPluginEnabled(basename($plugin)) && !empty($getPluginFromDB)) {
                $pluginData['status'] = '1';
            }
            if (empty($getPluginFromDB)) {
                $pluginData['installed'] = false;
            } else {
                $pluginData['id'] = $getPluginFromDB->id;
                $pluginData['version'] = $getPluginFromDB->version;
            }
            $pluginData['config'] = [];
            $getConfigFile = file_get_contents($plugin . "/config.json");
            if (!empty($getConfigFile)) {
                $getConfigFile = json_decode($getConfigFile, true);
                $pluginData['config'] = $getConfigFile;
                $pluginData['config']['thumbnail'] = $plugin . "/assets/images/thumbnail.jpg";
            }
            $getSettingsFile = file_get_contents($plugin . "/plugin-settings.html");
            if (!empty($getSettingsFile)) {
                $pluginData['is_settings'] = true;
            }
            $finalData[] = $pluginData;
        }
        
    }
    return $finalData;
}
?>