<?php
// +------------------------------------------------------------------------+
// | @author Deen Doughouz (DoughouzForest)
// | @author_url 1: http://www.playtubescript.com
// | @author_url 2: http://codecanyon.net/user/doughouzforest
// | @author_email: wowondersocial@gmail.com
// +------------------------------------------------------------------------+
// | PlayTube - The Ultimate Video Sharing Platform
// | Copyright (c) 2017 PlayTube. All rights reserved.
// +------------------------------------------------------------------------+
function loadPage($page_url = '', $data = array(), $set_lang = true) {
    global $music, $lang_array, $config, $countries_name, $db;
    if (!empty($data['pluginSettings'])) {
        $page = './plugins/' . $page_url . '/plugin-settings.html';
        if (!file_exists($page)) {
            return false;
        }
    } else {
        $page = './themes/' . $config['theme'] . '/layout/' . $page_url . '.html';
        if (!file_exists($page)) {
            die("File not Exists : $page");
        }
    }
    $page_content = '';
    ob_start();
    require($page);
    $page_content = ob_get_contents();
    ob_end_clean();
    if (preg_match_all("/{{HOOK (.*?)}}/", $page_content, $matchPlugins)) {
        $hooksList = [];
        foreach ($matchPlugins[1] as $key => $hook) {
            ob_start();
            $music->hooks->do_action($hook);
            $hookAction = ob_get_contents();
            ob_end_clean();
            $page_content = str_replace("{{HOOK " . $hook. "}}", $hookAction, $page_content);
        }
    }
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
    if ($music->loggedin == true) {
        $replace = ToArray($music->user);
        $page_content = preg_replace_callback("/{{ME (.*?)}}/", function($m) use ($replace) {
            return (isset($replace[$m[1]])) ? $replace[$m[1]] : '';
        }, $page_content);
    }
    $page_content = preg_replace("/{{LINK (.*?)}}/", getLink("$1"), $page_content);
    $page_content = preg_replace_callback("/{{CONFIG (.*?)}}/", function($m) use ($config) {
        return (isset($config[$m[1]])) ? $config[$m[1]] : '';
    }, $page_content);

    
    return $page_content;
}

function loadPluginSettings($page_url = '', $data = array(), $set_lang = true) {
    global $music, $lang_array, $config, $countries_name, $db;
    $page = './plugins/' . $page_url . '/admin-settings.html';
    if (!file_exists($page)) {
        die("File not Exists : $page");
    }
    $page_content = '';
    ob_start();
    require($page);
    $page_content = ob_get_contents();
    ob_end_clean();
    if (preg_match_all("/{{HOOK (.*?)}}/", $page_content, $matchPlugins)) {
        $hooksList = [];
        foreach ($matchPlugins[1] as $key => $hook) {
            ob_start();
            $music->hooks->do_action($hook);
            $hookAction = ob_get_contents();
            ob_end_clean();
            $page_content = str_replace("{{HOOK " . $hook. "}}", $hookAction, $page_content);
        }
    }
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
    if ($music->loggedin == true) {
        $replace = ToArray($music->user);
        $page_content = preg_replace_callback("/{{ME (.*?)}}/", function($m) use ($replace) {
            return (isset($replace[$m[1]])) ? $replace[$m[1]] : '';
        }, $page_content);
    }
    $page_content = preg_replace("/{{LINK (.*?)}}/", getLink("$1"), $page_content);
    $page_content = preg_replace_callback("/{{CONFIG (.*?)}}/", function($m) use ($config) {
        return (isset($config[$m[1]])) ? $config[$m[1]] : '';
    }, $page_content);

    
    return $page_content;
}

function fetchDataFromURL($url = '') {
    if (empty($url)) {
        return false;
    }
    $ch = curl_init($url);
    curl_setopt( $ch, CURLOPT_POST, false );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.7.12) Gecko/20050915 Firefox/1.0.7");
    curl_setopt( $ch, CURLOPT_HEADER, false );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt( $ch, CURLOPT_TIMEOUT, 5);
    return curl_exec( $ch );
}
function PT_LoadAdminPage($page_url = '', $data = array(), $set_lang = true) {
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
            return (isset($lang_array[$m[1]])) ? $lang_array[$m[1]] : '';
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
                $object_to_replace = "{{" . $key . "}}";
                $page_content      = str_replace($object_to_replace, $replace, $page_content);
            }
        }
    }
    if (IS_LOGGED == true) {
        $replace = ToArray($music->user);
        $page_content = preg_replace_callback("/{{ME (.*?)}}/", function($m) use ($replace) {
            return (isset($replace[$m[1]])) ? $replace[$m[1]] : '';
        }, $page_content);
    }
    $page_content = preg_replace("/{{LINK (.*?)}}/", getLink("$1"), $page_content);
    $page_content = preg_replace_callback("/{{CONFIG (.*?)}}/", function($m) use ($config) {
        return (isset($config[$m[1]])) ? $config[$m[1]] : '';
    }, $page_content);
    return $page_content;
}
function getLink($string) {
    global $site_url;
    return $site_url . '/' . $string;
}
function getMedia($media = '', $is_upload = false){
    global $music;
    if (empty($media)) {
        return '';
    }

    $media_url     = $music->config->site_url . '/' . $media;
    if ($music->config->s3_upload == 'on' && $is_upload == false) {
        $media_url = "https://" . $music->config->s3_bucket_name . ".s3.amazonaws.com/" . $media;
        if (!empty($music->config->amazon_endpoint) && filter_var($music->config->amazon_endpoint, FILTER_VALIDATE_URL)) {
            $media_url = $music->config->amazon_endpoint . "/" . $media;
        }
    } else if ($music->config->ftp_upload == "on") {
        return addhttp($music->config->ftp_endpoint) . '/' . $media;
    } else if ($music->config->spaces == 'on') {
        if (empty($music->config->spaces_key) || empty($music->config->spaces_secret) || empty($music->config->space_region) || empty($music->config->space_name)) {
            return $music->config->site_url . '/' . $media;
        }
        if (!empty($music->config->spaces_endpoint) && filter_var($music->config->spaces_endpoint, FILTER_VALIDATE_URL)) {
            return $music->config->spaces_endpoint . "/" . $media;
        }
        return  'https://' . $music->config->space_name . '.' . $music->config->space_region . '.digitaloceanspaces.com/' . $media;
    } else if ($music->config->google_drive == 'on') {
      if (strpos($media, '/') === FALSE) {
          return 'https://docs.google.com/uc?export=download&id=' . $media;
      }
    } elseif (!empty($music->config->wasabi_access_key) && $music->config->wasabi_storage == 'on') {
        $music->config->wasabi_site_url        = 'https://s3.'.$music->config->wasabi_bucket_region.'.wasabisys.com';
        if (!empty($music->config->wasabi_endpoint) && filter_var($music->config->wasabi_endpoint, FILTER_VALIDATE_URL)) {
            return $music->config->wasabi_endpoint . "/" . $media;
        }
        if (!empty($music->config->wasabi_bucket_name)) {
            $music->config->wasabi_site_url = 'https://s3.'.$music->config->wasabi_bucket_region.'.wasabisys.com/'.$music->config->wasabi_bucket_name;
            return $music->config->wasabi_site_url . '/' . $media;
        }
    } elseif ($music->config->backblaze_storage == 'on' && !empty($music->config->backblaze_bucket_id)) {
        if (!empty($music->config->backblaze_endpoint) && filter_var($music->config->backblaze_endpoint, FILTER_VALIDATE_URL)) {
            return $music->config->backblaze_endpoint . "/" . $media;
        }
        return 'https://' . $music->config->backblaze_bucket_name . '.s3.' . $music->config->backblaze_region . '.backblazeb2.com/' . $media;
    }

    return $media_url;
}
function PT_Slug($string, $video_id) {
    global $music;
    if ($music->config->seo_link != 'on') {
        return $video_id;
    }
    $slug = url_slug($string, array(
        'delimiter' => '-',
        'limit' => 100,
        'lowercase' => true,
        'replacements' => array(
            '/\b(an)\b/i' => 'a',
            '/\b(example)\b/i' => 'Test'
        )
    ));
    return $slug . '_' . $video_id . '.html';
}
function URLSlug($string, $id) {
    global $music;
    $slug = url_slug($string, array(
        'delimiter' => '-',
        'limit' => 100,
        'lowercase' => true,
        'replacements' => array(
            '/\b(an)\b/i' => 'a',
            '/\b(example)\b/i' => 'Test'
        )
    ));
    return $slug . '_' . $id . '.html';
}
function GetPostIdFromUrl($string) {
    $slug_string = '';
    $string      = secure($string);
    $string      = str_replace('.html', '', $string);
    if (preg_match('/[^a-z\s-]/i', $string)) {
        $string_exp  = @explode('_', $string);
        $slug_string = $string_exp[1];
    } else {
        $slug_string = $string;
    }
    return secure($slug_string);
}
function PT_LoadAdminLinkSettings($link = '') {
    global $site_url;
    return $site_url . '/admin-cp/' . $link;
}
function PT_LoadAdminLink($link = '') {
    global $site_url;
    return $site_url . '/admin-panel/' . $link;
}
function url_slug($str, $options = array()) {
    // Make sure string is in UTF-8 and strip invalid UTF-8 characters
    $str      = mb_convert_encoding((string) $str, 'UTF-8', mb_list_encodings());
    $defaults = array(
        'delimiter' => '-',
        'limit' => null,
        'lowercase' => true,
        'replacements' => array(),
        'transliterate' => false
    );
    // Merge options
    $options  = array_merge($defaults, $options);
    $char_map = array(
        // Latin
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'Æ' => 'AE',
        'Ç' => 'C',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ð' => 'D',
        'Ñ' => 'N',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        'Ő' => 'O',
        'Ø' => 'O',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ű' => 'U',
        'Ý' => 'Y',
        'Þ' => 'TH',
        'ß' => 'ss',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'å' => 'a',
        'æ' => 'ae',
        'ç' => 'c',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ð' => 'd',
        'ñ' => 'n',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'ő' => 'o',
        'ø' => 'o',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ű' => 'u',
        'ý' => 'y',
        'þ' => 'th',
        'ÿ' => 'y',
        // Latin symbols
        '©' => '(c)',
        // Greek
        'Α' => 'A',
        'Β' => 'B',
        'Γ' => 'G',
        'Δ' => 'D',
        'Ε' => 'E',
        'Ζ' => 'Z',
        'Η' => 'H',
        'Θ' => '8',
        'Ι' => 'I',
        'Κ' => 'K',
        'Λ' => 'L',
        'Μ' => 'M',
        'Ν' => 'N',
        'Ξ' => '3',
        'Ο' => 'O',
        'Π' => 'P',
        'Ρ' => 'R',
        'Σ' => 'S',
        'Τ' => 'T',
        'Υ' => 'Y',
        'Φ' => 'F',
        'Χ' => 'X',
        'Ψ' => 'PS',
        'Ω' => 'W',
        'Ά' => 'A',
        'Έ' => 'E',
        'Ί' => 'I',
        'Ό' => 'O',
        'Ύ' => 'Y',
        'Ή' => 'H',
        'Ώ' => 'W',
        'Ϊ' => 'I',
        'Ϋ' => 'Y',
        'α' => 'a',
        'β' => 'b',
        'γ' => 'g',
        'δ' => 'd',
        'ε' => 'e',
        'ζ' => 'z',
        'η' => 'h',
        'θ' => '8',
        'ι' => 'i',
        'κ' => 'k',
        'λ' => 'l',
        'μ' => 'm',
        'ν' => 'n',
        'ξ' => '3',
        'ο' => 'o',
        'π' => 'p',
        'ρ' => 'r',
        'σ' => 's',
        'τ' => 't',
        'υ' => 'y',
        'φ' => 'f',
        'χ' => 'x',
        'ψ' => 'ps',
        'ω' => 'w',
        'ά' => 'a',
        'έ' => 'e',
        'ί' => 'i',
        'ό' => 'o',
        'ύ' => 'y',
        'ή' => 'h',
        'ώ' => 'w',
        'ς' => 's',
        'ϊ' => 'i',
        'ΰ' => 'y',
        'ϋ' => 'y',
        'ΐ' => 'i',
        // Turkish
        'Ş' => 'S',
        'İ' => 'I',
        'Ç' => 'C',
        'Ü' => 'U',
        'Ö' => 'O',
        'Ğ' => 'G',
        'ş' => 's',
        'ı' => 'i',
        'ç' => 'c',
        'ü' => 'u',
        'ö' => 'o',
        'ğ' => 'g',
        // Russian
        'А' => 'A',
        'Б' => 'B',
        'В' => 'V',
        'Г' => 'G',
        'Д' => 'D',
        'Е' => 'E',
        'Ё' => 'Yo',
        'Ж' => 'Zh',
        'З' => 'Z',
        'И' => 'I',
        'Й' => 'J',
        'К' => 'K',
        'Л' => 'L',
        'М' => 'M',
        'Н' => 'N',
        'О' => 'O',
        'П' => 'P',
        'Р' => 'R',
        'С' => 'S',
        'Т' => 'T',
        'У' => 'U',
        'Ф' => 'F',
        'Х' => 'H',
        'Ц' => 'C',
        'Ч' => 'Ch',
        'Ш' => 'Sh',
        'Щ' => 'Sh',
        'Ъ' => '',
        'Ы' => 'Y',
        'Ь' => '',
        'Э' => 'E',
        'Ю' => 'Yu',
        'Я' => 'Ya',
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'yo',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'j',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'c',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'sh',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya',
        // Ukrainian
        'Є' => 'Ye',
        'І' => 'I',
        'Ї' => 'Yi',
        'Ґ' => 'G',
        'є' => 'ye',
        'і' => 'i',
        'ї' => 'yi',
        'ґ' => 'g',
        // Czech
        'Č' => 'C',
        'Ď' => 'D',
        'Ě' => 'E',
        'Ň' => 'N',
        'Ř' => 'R',
        'Š' => 'S',
        'Ť' => 'T',
        'Ů' => 'U',
        'Ž' => 'Z',
        'č' => 'c',
        'ď' => 'd',
        'ě' => 'e',
        'ň' => 'n',
        'ř' => 'r',
        'š' => 's',
        'ť' => 't',
        'ů' => 'u',
        'ž' => 'z',
        // Polish
        'Ą' => 'A',
        'Ć' => 'C',
        'Ę' => 'e',
        'Ł' => 'L',
        'Ń' => 'N',
        'Ó' => 'o',
        'Ś' => 'S',
        'Ź' => 'Z',
        'Ż' => 'Z',
        'ą' => 'a',
        'ć' => 'c',
        'ę' => 'e',
        'ł' => 'l',
        'ń' => 'n',
        'ó' => 'o',
        'ś' => 's',
        'ź' => 'z',
        'ż' => 'z',
        // Latvian
        'Ā' => 'A',
        'Č' => 'C',
        'Ē' => 'E',
        'Ģ' => 'G',
        'Ī' => 'i',
        'Ķ' => 'k',
        'Ļ' => 'L',
        'Ņ' => 'N',
        'Š' => 'S',
        'Ū' => 'u',
        'Ž' => 'Z',
        'ā' => 'a',
        'č' => 'c',
        'ē' => 'e',
        'ģ' => 'g',
        'ī' => 'i',
        'ķ' => 'k',
        'ļ' => 'l',
        'ņ' => 'n',
        'š' => 's',
        'ū' => 'u',
        'ž' => 'z'
    );
    // Make custom replacements
    $str      = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);
    // Transliterate characters to ASCII
    if ($options['transliterate']) {
        $str = str_replace(array_keys($char_map), $char_map, $str);
    }
    // Replace non-alphanumeric characters with our delimiter
    $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);
    // Remove duplicate delimiters
    $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);
    // Truncate slug to max. characters
    $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');
    // Remove delimiter from ends
    $str = trim($str, $options['delimiter']);
    return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
}
function br2nl($st) {
    if (empty($st)) {
        return $st;
    }
    $breaks = array(
        "<br />",
        "<br>",
        "<br/>"
    );
    return str_ireplace($breaks, "\r\n", $st);
}
function ToObject($array) {
    $object = new stdClass();
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $value = ToObject($value);
        }
        if (isset($value)) {
            $object->$key = $value;
        }
    }
    return $object;
}
function ToArray($obj) {
    if (is_object($obj))
        $obj = (array) $obj;
    if (is_array($obj)) {
        $new = array();
        foreach ($obj as $key => $val) {
            $new[$key] = ToArray($val);
        }
    } else {
        $new = $obj;
    }
    return $new;
}
function GetTerms() {
    global $mysqli;
    $data  = array();
    $query = mysqli_query($mysqli, "SELECT * FROM " . T_TERMS);
    while ($fetched_data = mysqli_fetch_assoc($query)) {
        $data[$fetched_data['type']] = $fetched_data['content'];
    }
    return $data;
}
function UpdateSeenReports() {
    global $mysqli;
    $query_one = " UPDATE " . T_REPORTS . " SET `seen` = 1 WHERE `seen` = 0";
    $sql       = mysqli_query($mysqli, $query_one);
    if ($sql) {
        return true;
    }
}
function secure($string, $censored_words = 1, $br = true) {
    global $mysqli;
    $string = trim($string);
    $string = mysqli_real_escape_string($mysqli, $string);
    $string = htmlspecialchars($string, ENT_QUOTES);
    if ($br == true) {
        $string = str_replace('\r\n', " <br>", $string);
        $string = str_replace('\n\r', " <br>", $string);
        $string = str_replace('\r', " <br>", $string);
        $string = str_replace('\n', " <br>", $string);
    } else {
        $string = str_replace('\r\n', "", $string);
        $string = str_replace('\n\r', "", $string);
        $string = str_replace('\r', "", $string);
        $string = str_replace('\n', "", $string);
    }
    $string = stripslashes($string);
    $string = str_replace('&amp;#', '&#', $string);
    $string = preg_replace("/{{(.*?)}}/", '', $string);
    if ($censored_words == 1) {
        global $config;
        $censored_words = @explode(",", $config['censored_words']);
        foreach ($censored_words as $censored_word) {
            $censored_word = trim($censored_word);
            $string        = str_replace($censored_word, '****', $string);
        }
    }
    return $string;
}
function isLogged() {
    if (isset($_POST['access_token'])) {
        $id = getUserFromSessionID($_POST['access_token'], 'mobile');
        if (is_numeric($id) && !empty($id)) {
            return true;
        }else{
            return false;
        }
    }

    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $id = getUserFromSessionID($_SESSION['user_id']);
        if (is_numeric($id) && !empty($id)) {
            return true;
        }
    }

    else if (isset($_COOKIE['user_id']) && !empty($_COOKIE['user_id'])) {
        $id = getUserFromSessionID($_COOKIE['user_id']);
        if (is_numeric($id) && !empty($id)) {
            return true;
        }
    }

    else {
        return false;
    }
}
function getUserFromSessionID($session_id, $platform = 'web') {
    global $db;
    if (empty($session_id)) {
        return false;
    }
    $platform   = secure($platform);
    $session_id = secure($session_id);
    $return     = $db->where('session_id', $session_id);
    $return     = $db->where('platform', $platform);
    return $db->getValue(T_SESSIONS, 'user_id');
}
function generateKey($minlength = 20, $maxlength = 20, $uselower = true, $useupper = true, $usenumbers = true, $usespecial = false) {
    $charset = '';
    if ($uselower) {
        $charset .= "abcdefghijklmnopqrstuvwxyz";
    }
    if ($useupper) {
        $charset .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    }
    if ($usenumbers) {
        $charset .= "123456789";
    }
    if ($usespecial) {
        $charset .= "~@#$%^*()_+-={}|][";
    }
    if ($minlength > $maxlength) {
        $length = mt_rand($maxlength, $minlength);
    } else {
        $length = mt_rand($minlength, $maxlength);
    }
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        $key .= $charset[(mt_rand(0, strlen($charset) - 1))];
    }
    return $key;
}
function resize_Crop_Image($max_width, $max_height, $source_file, $dst_dir, $quality = 80) {
    $imgsize = @getimagesize($source_file);
    $width   = $imgsize[0];
    $height  = $imgsize[1];
    $mime    = $imgsize['mime'];
    switch ($mime) {
        case 'image/gif':
            $image_create = "imagecreatefromgif";
            $image        = "imagegif";
            break;
        case 'image/png':
            $image_create = "imagecreatefrompng";
            $image        = "imagepng";
            break;
        case 'image/jpeg':
            $image_create = "imagecreatefromjpeg";
            $image        = "imagejpeg";
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $image_create = "imagecreatefromwebp";
                $image        = "imagewebp";
            }
            else{
                return false;
            }
            break;
        default:
            return false;
            break;
    }
    $dst_img    = @imagecreatetruecolor($max_width, $max_height);
    $src_img    = $image_create($source_file);
    $width_new  = $height * $max_width / $max_height;
    $height_new = $width * $max_height / $max_width;
    if ($width_new > $width) {
        $h_point = (($height - $height_new) / 2);
        @imagecopyresampled($dst_img, $src_img, 0, 0, 0, $h_point, $max_width, $max_height, $width, $height_new);
    } else {
        $w_point = (($width - $width_new) / 2);
        @imagecopyresampled($dst_img, $src_img, 0, 0, $w_point, 0, $max_width, $max_height, $width_new, $height);
    }
    @imagejpeg($dst_img, $dst_dir, $quality);
    if ($dst_img)
        @imagedestroy($dst_img);
    if ($src_img)
        @imagedestroy($src_img);
}
function compressImage($source_url, $destination_url, $quality) {
    $info = getimagesize($source_url);
    if ($info['mime'] == 'image/jpeg') {
        $image = @imagecreatefromjpeg($source_url);
        @imagejpeg($image, $destination_url, $quality);
    } elseif ($info['mime'] == 'image/gif') {
        $image = @imagecreatefromgif($source_url);
        @imagegif($image, $destination_url, $quality);
    } elseif ($info['mime'] == 'image/png') {
        $image = @imagecreatefrompng($source_url);
        @imagepng($image, $destination_url);
    } elseif ($info['mime'] == 'image/webp') {
        if (function_exists('imagecreatefromwebp')) {
            $image = @imagecreatefromwebp($source_url);
            @imagewebp($image, $destination_url);
        }
    }
}
function time_Elapsed_String($musicime) {
    global $music;
    $etime = time() - $musicime;
    if ($etime < 45) {
        return lang('Just now');
    }
    if ($etime >= 45 && $etime < 90) {
        return lang('about a minute ago');
    }
    $day = 24 * 60 * 60;
    if ($etime > $day * 30 && $etime < $day * 45) {
        return lang('about a month ago');
    }
    $a        = array(
        365 * 24 * 60 * 60 => "year",
        30 * 24 * 60 * 60 => "month",
        24 * 60 * 60 => "day",
        60 * 60 => "hour",
        60 => "minute",
        1 => "second"
    );
    $a_plural = array(
        'year' => lang("years"),
        'month' => lang("months"),
        'day' => lang("days"),
        'hour' => lang("hours"),
        'minute' => lang("minutes"),
        'second' => lang("seconds")
    );
    foreach ($a as $secs => $str) {
        $d = $etime / $secs;
        if ($d >= 1) {
            $r        = round($d);

            if ($music->language_type == 'rtl') {
                $time_ago = lang("ago") . ' ' . $r . ' ' . ($r > 1 ? $a_plural[$str] : $str);
            } else {
                $time_ago = $r . ' ' . ($r > 1 ? $a_plural[$str] : $str) . ' ' . lang("ago");
            }

            return $time_ago;
        }
    }
}
function check_($check) {
    $siteurl = urlencode($_SERVER['SERVER_NAME']);
    $file    = file_get_contents('http://www.playtubescript.com/purchase.php?code=' . $check . '&url=' . $siteurl);
    $check   = json_decode($file, true);
    return $check;
}
function check_success($check) {
    $siteurl = urlencode($_SERVER['SERVER_NAME']);
    $file    = file_get_contents('http://www.playtubescript.com/purchase.php?code=' . $check . '&success=true&url=' . $siteurl);
    $check   = json_decode($file, true);
    return $check;
}
function PT_EditMarkup($text, $link = true) {
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
function markUp($text, $link = true) {
    if ($link == true) {
        $link_search = '/\[a\](.*?)\[\/a\]/i';
        if (preg_match_all($link_search, $text, $matches)) {
            foreach ($matches[1] as $match) {
                $match_decode     = urldecode($match);
                $match_decode_url = $match_decode;
                $count_url        = mb_strlen($match_decode);
                if ($count_url > 50) {
                    $match_decode_url = mb_substr($match_decode_url, 0, 30) . '....' . mb_substr($match_decode_url, 30, 20);
                }
                $match_url = $match_decode;
                if (!preg_match("/http(|s)\:\/\//", $match_decode)) {
                    $match_url = 'http://' . $match_url;
                }
                $text = str_replace('[a]' . $match . '[/a]', '<a href="' . strip_tags($match_url) . '" target="_blank" class="hash" rel="nofollow">' . $match_decode_url . '</a>', $text);
            }
        }
    }

    $link_search = '/\[img\](.*?)\[\/img\]/i';
    if (preg_match_all($link_search, $text, $matches)) {
        foreach ($matches[1] as $match) {
            $match_decode     = urldecode($match);
            $text = str_replace('[img]' . $match . '[/img]', '<a href="' . getMedia(strip_tags($match_decode)) . '" target="_blank"><img style="width:300px;border-radius: 20px;" src="' . getMedia(strip_tags($match_decode)) . '"></a>', $text);
        }
    }
    return $text;
}
function covtime($youtube_time) {
    $start = new DateTime('@0'); // Unix epoch
    $start->add(new DateInterval($youtube_time));
    return $start->format('H:i:s');
}
function PT_CreateSession() {
    $hash = sha1(rand(1111, 9999));
    if (!empty($_SESSION['hash_id'])) {
        $_SESSION['hash_id'] = $_SESSION['hash_id'];
        return $_SESSION['hash_id'];
    }
    $_SESSION['hash_id'] = $hash;
    return $hash;
}
function PT_ShortText($text = "", $len = 100) {
    if (empty($text) || !is_string($text) || !is_numeric($len) || $len < 1) {
        return "****";
    }
    if (strlen($text) > $len) {
        $text = mb_substr($text, 0, $len, "UTF-8") . "..";
    }
    return $text;
}
function PT_GetIdFromURL($url = false) {
    if (!$url) {
        return false;
    }
    $slug = @end(explode('_', $url));
    $id   = 0;
    $slug = explode('.', $slug);
    $id   = (is_array($slug) && !empty($slug[0]) && is_numeric($slug[0])) ? $slug[0] : 0;
    return $id;
}
function PT_Decode($text = '') {
    return htmlspecialchars_decode($text);
}
function PT_Backup($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name, $tables = false, $backup_name = false) {
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
function size_format($bytes) {
    $size = array('2000000' => '2MB',
                  '6000000' => '6MB',
                  '12000000' => '12MB',
                  '24000000' => '24MB',
                  '48000000' => '48MB',
                  '96000000' => '96MB',
                  '256000000' => '256MB',
                  '512000000' => '512MB',
                  '1000000000' => '1GB',
                  '10000000000' => '10GB');
    return $size[$bytes];
}
// function pt_size_format($bytes) {
//     $kb = 1024;
//     $mb = $kb * 1024;
//     $gb = $mb * 1024;
//     $tb = $gb * 1024;
//     if (($bytes >= 0) && ($bytes < $kb)) {
//         return $bytes . ' B';
//     } elseif (($bytes >= $kb) && ($bytes < $mb)) {
//         return ceil($bytes / $kb) . ' KB';
//     } elseif (($bytes >= $mb) && ($bytes < $gb)) {
//         return ceil($bytes / $mb) . ' MB';
//     } elseif (($bytes >= $gb) && ($bytes < $tb)) {
//         return ceil($bytes / $gb) . ' GB';
//     } elseif ($bytes >= $tb) {
//         return ceil($bytes / $tb) . ' TB';
//     } else {
//         return $bytes . ' B';
//     }
// }
function pt_delete_field($id = false) {
    global $music, $sqlConnect;
    if (IS_LOGGED == false || !PT_IsAdmin()) {
        return false;
    }
    $id    = PT_Secure($id);
    $table = T_FIELDS;
    $query = mysqli_query($sqlConnect, "DELETE FROM `$table` WHERE `id` = {$id}");
    if ($query) {
        $table  = T_USR_PROF_FIELDS;
        $query2 = mysqli_query($sqlConnect, "ALTER TABLE `$table` DROP `fid_{$id}`;");
        if ($query2) {
            return true;
        }
    }
    return false;
}
function pt_is_url($url = false) {
    if (empty($url)) {
        return false;
    }
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return true;
    }
    return false;
}
function clear_cookies() {
    foreach ($_COOKIE as $key => $value) {
        setcookie($key, $value, time() - 10000, "/");
    }
}
function pt_url_domain($url) {
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
function pt_redirect($url) {
    header("Loacation: $url");
    exit();
}
function connect_to_url($url = '', $config = array()) {
    if (empty($url)) {
        return false;
    }
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.7.12) Gecko/20050915 Firefox/1.0.7");
    if (!empty($config['POST'])) {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $config['POST']);
    }
    if (!empty($config['bearer'])) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $config['bearer']
        ));
    }
    //execute the session
    $curl_response = curl_exec($curl);
    //finish off the session
    curl_close($curl);
    return $curl_response;
}
function verify_api_auth($user_id,$session_id, $platform = 'phone') {
    global $db;
    if (empty($session_id) || empty($user_id)) {
        return false;
    }
    $platform   = PT_Secure($platform);
    $session_id = PT_Secure($session_id);
    $user_id    = PT_Secure($user_id);

    $db->where('session_id', $session_id);
    $db->where('user_id', $user_id);
    $db->where('platform', $platform);
    return ($db->getValue(T_SESSIONS, 'COUNT(*)') == 1);
}
function pt_vrequest_exists(){
    global $db,$music;
    if (!IS_LOGGED) {
        return false;
    }

    $user    = $music->user->id;
    return ($db->where("user_id",$user)->getValue(T_VERIF_REQUESTS,"count(*)") > 0);
}
function pt_get_announcments() {
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
function pt_is_banned($ip_address = false){
    global $music, $db;
    $table = T_BANNED_IPS;
    try {
        $ip    = $db->where('ip_address',$ip_address,'=')->getValue($table,"count(*)");
        return ($ip > 0);
    } catch (Exception $e) {
        return false;
    }
}
function pt_custom_design($a = false,$code = array()){
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
function pt_notify($data = array()){
    global $music, $db;
    if (empty($data) || !is_array($data)) {
        return false;
    }

    $t_notif = T_NOTIFICATIONS;
    $query   = $db->insert($t_notif,$data);
    return $query;
}
function pt_get_notification($args = array()){
    global $music, $db;
    $options  = array(
        "recipient_id" => 0,
        "type" => null,
    );

    $args         = array_merge($options, $args);
    $recipient_id = $args['recipient_id'];
    $type         = $args['type'];
    $data         = array();
    $t_notif      = T_NOTIFICATIONS;

    $db->where('recipient_id',$recipient_id);
    if ($type == 'new') {
        $data = $db->where('seen',0)->getValue($t_notif,'count(*)');
    }

    else{
        $query      = $db->orderBy('id','DESC')->get($t_notif,20);
        foreach ($query as $notif_data_row) {
            $data[] = ToArray($notif_data_row);
        }
    }

    $db->where('recipient_id',$recipient_id);
    $db->where('time',(time() - 432000));
    $db->where('seen',0,'>');
    $db->delete($t_notif);

    return $data;
}
function ffmpeg_duration($filename = false){
    global $music;

    $ffmpeg_b = $music->config->ffmpeg_binary_file;
    $output   = shell_exec("$ffmpeg_b -i {$filename} 2>&1");
    $musicrn     = '/Duration: ([0-9]{2}):([0-9]{2}):([^ ,])+/';
    $time     = 30;
    if (preg_match($musicrn, $output, $matches)) {
        $time = str_replace("Duration: ", "", $matches[0]);
        $time_breakdown = explode(":", $time);
        $time = round(($time_breakdown[0]*60*60) + ($time_breakdown[1]*60) + $time_breakdown[2]);
    }

    return $time;
}
function http_respond($data = array()) {
    if (is_callable('fastcgi_finish_request')) {
        session_write_close();
        fastcgi_finish_request();
        return;
    }

    ignore_user_abort(true);
    ob_start();
    $serverProtocol = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL', FILTER_SANITIZE_STRING);
    header($serverProtocol . ' 200 OK');
    header('Content-Encoding: none');
    header('Content-Length: ' . ob_get_length());
    header('Connection: close');
    ob_end_flush();
    ob_flush();
    flush();
}
function get_ip_address() {
    if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false) {
            $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($iplist as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP))
                    return $ip;
            }
        } else {
            if (filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED']) && filter_var($_SERVER['HTTP_X_FORWARDED'], FILTER_VALIDATE_IP))
        return $_SERVER['HTTP_X_FORWARDED'];
    if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && filter_var($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'], FILTER_VALIDATE_IP))
        return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_FORWARDED_FOR'], FILTER_VALIDATE_IP))
        return $_SERVER['HTTP_FORWARDED_FOR'];
    if (!empty($_SERVER['HTTP_FORWARDED']) && filter_var($_SERVER['HTTP_FORWARDED'], FILTER_VALIDATE_IP))
        return $_SERVER['HTTP_FORWARDED'];
    return $_SERVER['REMOTE_ADDR'];
}
function thousandsCurrencyFormat($num) {

  if($num>1000) {

        $x = round($num);
        $x_number_format = number_format($x);
        $x_array = explode(',', $x_number_format);
        $x_parts = array('K', 'M', 'B', 'T');
        $x_count_parts = count($x_array) - 1;
        $x_display = $x;
        $x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
        $x_display .= $x_parts[$x_count_parts - 1];

        return $x_display;

  }

  return $num;
}
function db_langs() {
    global $music, $db;
    $data   = array();
    $t_lang = T_LANGS;
    $query  = $db->rawQuery("DESCRIBE `$t_lang`");
    foreach ($query as $column) {
        $data[] = $column->Field;
    }

    unset($data[0]);
    unset($data[1]);
    unset($data[2]);
    unset($data[3]);
    asort($data);
    return $data;
}
function get_langs($lang = 'english') {
    global $music, $db;
    $data   = array();
    $t_lang = T_LANGS;
    try {
        $query  = $db->rawQuery("SELECT `lang_key`, `$lang` FROM `$t_lang`");
    } catch (Exception $e) {

    }

    foreach ($query as $item) {
        $data[$item->lang_key] = $item->$lang;
    }

    return $data;
}
function PT_Duration($text) {
    $duration_search = '/\[d\](.*?)\[\/d\]/i';

    if (preg_match_all($duration_search, $text, $matches)) {
        foreach ($matches[1] as $match) {
            $time = explode(":", $match);
            $current_time = ($time[0]*60)+$time[1];
            $text = str_replace('[d]' . $match . '[/d]', '<a  class="hash" href="javascript:void(0)" onclick="go_to_duration('.$current_time.')">' . $match . '</a>', $text);
        }
    }
    return $text;
}
function getPageFromPath($path = '') {
    if (empty($path)) {
        return false;
    }
    $path = explode("/", $path);
    $data = array();
    $data['options'] = array();
    if (!empty($path[0])) {
        $data['page'] = $path[0];
    }
    if (!empty($path[1])) {
        unset($path[0]);
        $data['options'] = $path;
    }
    return $data;
}
function getPageFromPathAdmin($path = '') {
    if (empty($path)) {
        return false;
    }
    $path = explode("&", $path);
    $data = array();
    $data['options'] = array();
    if (!empty($path[0])) {
        $data['page'] = $path[0];
    }
    if (!empty($path[1])) {
        unset($path[0]);
        $data['options'] = $path;
        foreach ($path as $key => $value) {
            preg_match_all('/(.*)=(.*)/m', $value, $matches);
            if (!empty($matches) && !empty($matches[1]) && !empty($matches[1][0]) && !empty($matches[2]) && !empty($matches[2][0])) {
                $_GET[$matches[1][0]] = $matches[2][0];
            }

        }
    }
    return $data;
}
function formatSeconds($str_time) {
    sscanf($str_time, "%d:%d:%d", $hours, $minutes, $seconds);
    return isset($seconds) ? $hours * 3600 + $minutes * 60 + $seconds : $hours * 60 + $minutes;
}


function CreatePayment($data){
    global $db;
    if(empty($data)){
        return false;
    }
    runPlugin('AfterSuccessPayment', $data);
    return $db->insert(T_PAYMENTS, $data);
}
function TrackPurchaseData() {
    global $sqlConnect;
    $type_table   = T_PAYMENTS;
    $query_one    = mysqli_query($sqlConnect, "SELECT SUM(`amount`) as count FROM {$type_table} WHERE `type` = 'TRACK' AND `amount` <> 0 AND YEAR(`date`) = '".date("Y")."' AND MONTH(`date`) = '".date('n')."'");
    $fetched_data = mysqli_fetch_assoc($query_one);
    return $fetched_data['count'];
}
function CountAllPaymentData($type) {
    global $sqlConnect;
    $type_table   = T_PAYMENTS;
    $type         = Secure($type);
    $query_one    = mysqli_query($sqlConnect, "SELECT COUNT(`id`) as count FROM {$type_table} WHERE `pro_plan` = '{$type}'");
    $fetched_data = mysqli_fetch_assoc($query_one);
    return $fetched_data['count'];
}
function AmountAllPaymentData($type) {
    global $sqlConnect;
    $type_table   = T_PAYMENTS;
    $type         = Secure($type);
    $query_one    = mysqli_query($sqlConnect, "SELECT SUM(`amount`) as count FROM {$type_table} WHERE `pro_plan` = '{$type}'");
    $fetched_data = mysqli_fetch_assoc($query_one);
    return $fetched_data['count'];
}
function CountAllPayment() {
    global $sqlConnect;
    $type_table = T_PAYMENTS;
    $query_one  = mysqli_query($sqlConnect, "SELECT `amount` FROM {$type_table}");
    $final_data = 0;
    while ($fetched_data = mysqli_fetch_assoc($query_one)) {
        $final_data += $fetched_data['amount'];
    }
    return $final_data;
}
function CountThisMonthPayment() {
    global $sqlConnect;
    $type_table = T_PAYMENTS;
    $date       = date('n') . '/' . date("Y");
    $query_one  = mysqli_query($sqlConnect, "SELECT `amount` FROM {$type_table} WHERE `amount` <> 0 AND YEAR(`date`) = '".date("Y")."' AND MONTH(`date`) = '".date('n')."'");
    $final_data = 0;
    while ($fetched_data = mysqli_fetch_assoc($query_one)) {
        $final_data += $fetched_data['amount'];
    }
    return $final_data;
}
function GetRegisteredPaymentsStatics($month, $type = '') {
    global $sqlConnect;
    $year         = date("Y");
    $type_table   = T_PAYMENTS;
    $query_one    = mysqli_query($sqlConnect, "SELECT SUM(`amount`) as count FROM {$type_table} WHERE YEAR(`date`) = '".$year."' AND MONTH(`date`) = '".$month."' AND `pro_plan` = '{$type}'");
    $fetched_data = mysqli_fetch_assoc($query_one);
    return (float)$fetched_data['count'];
}
function GetTrackPaymentsStatics($month) {
    global $sqlConnect;
    $year         = date("Y");
    $type_table   = T_PAYMENTS;
    $query_one    = mysqli_query($sqlConnect, "SELECT SUM(`amount`) as count FROM {$type_table} WHERE YEAR(`date`) = '".$year."' AND MONTH(`date`) = '".$month."' AND `type` = 'TRACK'");
    $fetched_data = mysqli_fetch_assoc($query_one);
    return (float)$fetched_data['count'];
}
function ip_in_range($ip, $range) {
    if (strpos($range, '/') == false) {
        $range .= '/32';
    }
    // $range is in IP/CIDR format eg 127.0.0.1/24
    list($range, $netmask) = explode('/', $range, 2);
    $range_decimal    = ip2long($range);
    $ip_decimal       = ip2long($ip);
    $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
    $netmask_decimal  = ~$wildcard_decimal;
    return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
}


function RecordUserActivities($activity, $obj = array()){
    global $music,$config,$db;
    $update = false;
    $activities = $music->activities;
    if(isLogged() == false) return false;
    if(empty($obj)) return false;
    if($config['point_system'] === 'off') return false;
    if(empty($activity)) return false;
    if(!in_array($activity, $activities) || !isset($config['point_system_' . $activity . '_cost'])) return false;
    if (empty($config['point_system_' . $activity . '_cost'])) {
      return false;
    }
    $_cost = intval($config['point_system_' . $activity . '_cost']);
    $_add_wallet = true;
    $audio_id = '';
    if (!empty($obj['audio_id'])) {
        $audio_id = $obj['audio_id'];
    }
    if($activity === 'update_profile_picture' || $activity === 'update_profile_cover'){
        $is_exist = $db->where('action' , $activity)->where('user_id',$music->user->id)->getOne(T_POINT_SYSTEM);
        if (!empty($is_exist)) return false;
    }

    if(!empty($obj['audio_id'])){
        $is_exist = $db->where('action' , $activity)->where('user_id', $music->user->id)->where('audio_id', $audio_id)->getOne(T_POINT_SYSTEM);
        if (!empty($is_exist)) return false;
    }

    if($activity == 'comment' || $activity == 'repost' || $activity == 'review_track' || $activity == 'report_track' || $activity == 'listen_to_song' ){
        if($obj['track_user_id'] == $music->user->id) return false;
    }
    if($activity == 'replay_comment' || $activity == 'like_comment' || $activity == 'report_comment'){
        if($obj['track_user_id'] == $music->user->id) return false;
        if($obj['comment_user_id'] == $music->user->id) return false;
    }
    if($activity == 'like_track' || $activity == 'dislike_track'){
        if($obj['track_user_id'] == $music->user->id) return false;
    }
    if($activity == 'unlike_comment' || $activity == 'unlike_blog_comment'){
        if($obj['track_user_id'] == $music->user->id && $activity == 'unlike_comment') return false;
        $_add_wallet = false;
    }
    $wallet_cost = $_cost * $music->config->point_system_points_to_dollar;
    if($_add_wallet) {
      if ($music->config->points_to == 'on') {
        $update = $db->where('id', $music->user->id)->update(T_USERS, array('balance' => $db->inc($wallet_cost)));
      } else {
        $update = $db->where('id', $music->user->id)->update(T_USERS, array('wallet' => $db->inc($wallet_cost)));
      }

    }else{
      if ($music->config->points_to == 'on') {
        $update = $db->where('id', $music->user->id)->update(T_USERS, array('balance' => $db->dec($wallet_cost)));
      } else {
        $update = $db->where('id', $music->user->id)->update(T_USERS, array('wallet' => $db->dec($wallet_cost)));
      }

    }

    if ($update) {
        $db->insert(T_POINT_SYSTEM,array(
            'user_id' => $music->user->id,
            'action' => $activity,
            'reword' => $_cost,
            'is_add' => ($_add_wallet) ? 1 : 0,
            'obj' => serialize($obj),
            'audio_id' => $audio_id,
            'time' => time()
        ));
        return true;
    }else{
        return false;
    }
}
function GetPointEarned(){
    global $db,$music;
    $earned = 0;
    if (!IS_LOGGED) {
        return 0;
    }
    $points = $db->where('user_id', $music->user->id)->get(T_POINT_SYSTEM,null,array('*'));
    foreach($points as $key => $value){
        if($value->is_add == 1){
            $earned = $earned + $value->reword;
        }else{
            $earned = $earned - $value->reword;
        }
    }
    return ($earned > 0) ? $earned : '0.00';
}
function GetWalletReworded(){
    global $music;
    $points = GetPointEarned() * $music->config->point_system_points_to_dollar;
    return ($points > 0) ? $points : '0.00';
}

function getFilesShouldUpload() {
  global $db;
  $addedFiles = [];
  $getDataFromAlbums = $db->get(T_ALBUMS);
  foreach ($getDataFromAlbums as $key => $album) {
      if (strpos($album->thumbnail, '/') !== FALSE) {
          if (file_exists($album->thumbnail)) {
              $addedFiles[] = ['table' => T_ALBUMS, 'id' => $album->id, 'col' => 'thumbnail', 'file' => $album->thumbnail];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_ARTIST_R);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->passport, '/') !== FALSE) {
          if (file_exists($data->passport)) {
              $addedFiles[] = ['table' => T_ARTIST_R, 'id' => $data->id, 'col' => 'passport', 'file' => $data->passport];
          }
      }
      if (strpos($data->photo, '/') !== FALSE) {
          if (file_exists($data->photo)) {
              $addedFiles[] = ['table' => T_ARTIST_R, 'id' => $data->id, 'col' => 'photo', 'file' => $data->photo];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_BANK_RECEIPTS);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->receipt_file, '/') !== FALSE) {
          if (file_exists($data->receipt_file)) {
              $addedFiles[] = ['table' => T_BANK_RECEIPTS, 'id' => $data->id, 'col' => 'receipt_file', 'file' => $data->receipt_file];
          }
      }
  }

  $data = [];
  $getDataFrom = $db->get(T_USR_ADS);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->media, '/') !== FALSE) {
          if (file_exists($data->media)) {
              $addedFiles[] = ['table' => T_USR_ADS, 'id' => $data->id, 'col' => 'media', 'file' => $data->media];
          }
      }
      if (strpos($data->media, '/') !== FALSE) {
          if (file_exists($data->audio_media)) {
              $addedFiles[] = ['table' => T_USR_ADS, 'id' => $data->id, 'col' => 'audio_media', 'file' => $data->audio_media];
          }
      }
  }

  $data = [];
  $getDataFrom = $db->get(T_BLOG);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->thumbnail, '/') !== FALSE) {
          if (file_exists($data->thumbnail)) {
              $addedFiles[] = ['table' => T_BLOG, 'id' => $data->id, 'col' => 'thumbnail', 'file' => $data->thumbnail];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_EVENTS);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->image, '/') !== FALSE) {
          if (file_exists($data->image)) {
              $addedFiles[] = ['table' => T_EVENTS, 'id' => $data->id, 'col' => 'image', 'file' => $data->image];
          }
      }
      if (strpos($data->video, '/') !== FALSE) {
          if (file_exists($data->video)) {
              $addedFiles[] = ['table' => T_EVENTS, 'id' => $data->id, 'col' => 'video', 'file' => $data->video];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_MEDIA);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->image, '/') !== FALSE) {
          if (file_exists($data->image)) {
              $addedFiles[] = ['table' => T_MEDIA, 'id' => $data->id, 'col' => 'image', 'file' => $data->image];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_SONGS);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->thumbnail, '/') !== FALSE) {
          if (file_exists($data->thumbnail)) {
              $addedFiles[] = ['table' => T_SONGS, 'id' => $data->id, 'col' => 'thumbnail', 'file' => $data->thumbnail];
          }
      }
      if (strpos($data->audio_location, '/') !== FALSE) {
          if (file_exists($data->audio_location)) {
              $addedFiles[] = ['table' => T_SONGS, 'id' => $data->id, 'col' => 'audio_location', 'file' => $data->audio_location];
          }
      }
      if (strpos($data->demo_track, '/') !== FALSE) {
          if (file_exists($data->demo_track)) {
              $addedFiles[] = ['table' => T_SONGS, 'id' => $data->id, 'col' => 'demo_track', 'file' => $data->demo_track];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_USERS);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->avatar, '/') !== FALSE && $data->avatar != 'upload/photos/d-avatar.jpg') {
          if (file_exists($data->avatar)) {
              $addedFiles[] = ['table' => T_USERS, 'id' => $data->id, 'col' => 'avatar', 'file' => $data->avatar];
          }
      }
      if (strpos($data->cover, '/') !== FALSE && $data->cover != 'upload/photos/d-cover.jpg') {
          if (file_exists($data->cover)) {
              $addedFiles[] = ['table' => T_USERS, 'id' => $data->id, 'col' => 'cover', 'file' => $data->cover];
          }
      }
  }
  return $addedFiles;
}

function getFilesShouldDownload() {
  global $db;
  $addedFiles = [];
  $getDataFromAlbums = $db->get(T_ALBUMS);
  foreach ($getDataFromAlbums as $key => $album) {
      if (strpos($album->thumbnail, '/') === FALSE) {
          if (!empty($album->thumbnail)) {
              $addedFiles[] = ['table' => T_ALBUMS, 'id' => $album->id, 'col' => 'thumbnail', 'file' => $album->thumbnail];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_ARTIST_R);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->passport, '/') === FALSE) {
          if (!empty($data->passport)) {
              $addedFiles[] = ['table' => T_ARTIST_R, 'id' => $data->id, 'col' => 'passport', 'file' => $data->passport];
          }
      }
      if (strpos($data->photo, '/') === FALSE) {
          if (!empty($data->photo)) {
              $addedFiles[] = ['table' => T_ARTIST_R, 'id' => $data->id, 'col' => 'photo', 'file' => $data->photo];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_BANK_RECEIPTS);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->receipt_file, '/') === FALSE) {
          if (!empty($data->receipt_file)) {
              $addedFiles[] = ['table' => T_BANK_RECEIPTS, 'id' => $data->id, 'col' => 'receipt_file', 'file' => $data->receipt_file];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_BLOG);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->thumbnail, '/') === FALSE) {
          if (!empty($data->thumbnail)) {
              $addedFiles[] = ['table' => T_BLOG, 'id' => $data->id, 'col' => 'thumbnail', 'file' => $data->thumbnail];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_EVENTS);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->image, '/') === FALSE) {
          if (!empty($data->image)) {
              $addedFiles[] = ['table' => T_EVENTS, 'id' => $data->id, 'col' => 'image', 'file' => $data->image];
          }
      }
      if (strpos($data->video, '/') === FALSE) {
          if (!empty($data->video)) {
              $addedFiles[] = ['table' => T_EVENTS, 'id' => $data->id, 'col' => 'video', 'file' => $data->video];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_MEDIA);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->image, '/') === FALSE) {
          if (!empty($data->image)) {
              $addedFiles[] = ['table' => T_MEDIA, 'id' => $data->id, 'col' => 'image', 'file' => $data->image];
          }
      }
  }

  $data = [];
  $getDataFrom = $db->get(T_USR_ADS);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->media, '/') === FALSE) {
          if (!empty($data->media)) {
              $addedFiles[] = ['table' => T_USR_ADS, 'id' => $data->id, 'col' => 'media', 'file' => $data->media];
          }
      }
      if (strpos($data->media, '/') === FALSE) {
          if (!empty($data->audio_media)) {
              $addedFiles[] = ['table' => T_USR_ADS, 'id' => $data->id, 'col' => 'audio_media', 'file' => $data->audio_media];
          }
      }
  }

  $data = [];
  $getDataFrom = $db->get(T_SONGS);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->thumbnail, '/') === FALSE) {
          if (!empty($data->thumbnail)) {
              $addedFiles[] = ['table' => T_SONGS, 'id' => $data->id, 'col' => 'thumbnail', 'file' => $data->thumbnail];
          }
      }
      if (strpos($data->audio_location, '/') === FALSE) {
          if (!empty($data->audio_location)) {
              $addedFiles[] = ['table' => T_SONGS, 'id' => $data->id, 'col' => 'audio_location', 'file' => $data->audio_location];
          }
      }
      if (strpos($data->demo_track, '/') === FALSE) {
          if (!empty($data->demo_track)) {
              $addedFiles[] = ['table' => T_SONGS, 'id' => $data->id, 'col' => 'demo_track', 'file' => $data->demo_track];
          }
      }
  }
  $data = [];
  $getDataFrom = $db->get(T_USERS);
  foreach ($getDataFrom as $key => $data) {
      if (strpos($data->avatar, '/') === FALSE) {
          if (!empty($data->avatar)) {
              $addedFiles[] = ['table' => T_USERS, 'id' => $data->id, 'col' => 'avatar', 'file' => $data->avatar];
          }
      }
      if (strpos($data->cover, '/') === FALSE) {
          if (!empty($data->cover)) {
              $addedFiles[] = ['table' => T_USERS, 'id' => $data->id, 'col' => 'cover', 'file' => $data->cover];
          }
      }
  }
  return $addedFiles;
}
function getDirContents($dir, &$results = array()) {
    global $db;
    $files = scandir($dir);
    $forbiddenArray = ['.htaccess', 'index.html', 'step2.png', 'thumbnail.jpg', 'speed.jpg', 'parts.jpg', 'f-avatar.png', 'd-cover.jpg', 'd-avatar.jpg', 'step1.png'];
    foreach ($files as $key => $value) {
        $path = $dir . "/" . $value;
        if (!is_dir($path) && !in_array($value, $forbiddenArray)) {
            $results[] = $path;
        } else if ($value != "." && $value != "..") {
            getDirContents($path, $results);
            if (!is_dir($path)) {
                $results[] = $path;
            }
        }
    }
    return $results;
}

function filterFiles($results, $storage) {
    global $db;
    $fianlToAdd = [];
    foreach ($results as $key => $fileName) {
        $checkIfFileExistsInUpload = $db->where('filename', secure($fileName))->where('storage', $storage)->getOne(T_UPLOADED_MEDIA);
        
        if (empty($checkIfFileExistsInUpload)) {
            $fianlToAdd[] = $fileName;
        }
    }
    return $fianlToAdd;
}
function createPath($path) {
    if (is_dir($path))
        return true;
    $prev_path = substr($path, 0, strrpos($path, '/', -2) + 1 );
    $return = createPath($prev_path);
    return ($return && is_writable($prev_path)) ? mkdir($path) : false;
}
function GetIso()
{
    global $music,$db;
    $iso = array();
    foreach ($music->langs as $key => $value) {
        try {
          $info = $db->where('lang_name',$value)->getOne(T_LANG_ISO);
          if (!empty($info) && !empty($info->iso)) {
              $iso[$value] = $info->iso;
          }
        } catch (\Exception $e) {

        }
    }
    return $iso;
}

function checkHTTPS() {
    if(!empty($_SERVER['HTTPS'])) {
        if($_SERVER['HTTPS'] !== 'off') {
          return true;
        }
    } else {
      if($_SERVER['SERVER_PORT'] == 443) {
        return true;
      }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
      if ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
         return true;
      }
    }
    return false;
}

function url_origin( $s, $use_forwarded_host = false )
{
    $ssl      = ( ! empty( $s['HTTPS'] ) && $s['HTTPS'] == 'on' );
    $sp       = strtolower( $s['SERVER_PROTOCOL'] );
    $protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
    $port     = $s['SERVER_PORT'];
    $port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
    $host     = ( $use_forwarded_host && isset( $s['HTTP_X_FORWARDED_HOST'] ) ) ? $s['HTTP_X_FORWARDED_HOST'] : ( isset( $s['HTTP_HOST'] ) ? $s['HTTP_HOST'] : null );
    $host     = isset( $host ) ? $host : $s['SERVER_NAME'] . $port;
    return $host;
}

function full_url( $s, $use_forwarded_host = false )
{
    return url_origin( $s, $use_forwarded_host ) . $s['REQUEST_URI'];
}

function parse_size($size) {
  $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
  $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
  if ($unit) {
    // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
    return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
  }
  else {
    return round($size);
  }
}

function file_upload_max_size() {
  static $max_size = -1;

  if ($max_size < 0) {
    // Start with post_max_size.
    $post_max_size = parse_size(ini_get('post_max_size'));
    if ($post_max_size > 0) {
      $max_size = $post_max_size;
    }

    // If upload_max_size is less, then reduce. Except if upload_max_size is
    // zero, which indicates no limit.
    $upload_max = parse_size(ini_get('upload_max_filesize'));
    if ($upload_max > 0 && $upload_max < $max_size) {
      $max_size = $upload_max;
    }
    
  }
  return $max_size;
}

function getStatus($config = array()) {
    global $music,$db;

    $errors = [];

    
    if(!ini_get('allow_url_fopen') ) {
        $errors[] = ["type" => "error", "message" => "PHP function <strong>allow_url_fopen</strong> is disabled on your server, it is required to be enabled."];
    }
    if(!function_exists('mime_content_type')) {
        $errors[] = ["type" => "error", "message" => "PHP <strong>FileInfo</strong> extension is disabled on your server, it is required to be enabled."];
    }
    if (!class_exists('DOMDocument')) {
        $errors[] = ["type" => "error", "message" => "PHP <strong>dom & xml</strong> extensions are disabled on your server, they are required to be enabled."];
    }
    if (!is_writable('./upload')) {
        $errors[] = ["type" => "error", "message" => "The folder: <strong>/upload</strong> is not writable, upload folder and all subfolder(s) permission should be set to <strong>777</strong>."];
    }
    if (!is_writable('./sitemaps')) {
        $errors[] = ["type" => "error", "message" => "The folder: <strong>/sitemaps</strong> is not writable, sitemaps folder permission should be set to <strong>777</strong>."];
    }


    if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == 'on' || $music->config->spaces == 'on' || $music->config->wasabi_storage == 'on' || $music->config->backblaze_storage == 'on' || $music->config->google_drive == 'on') {
        if (!is_writable('./upload/photos/d-avatar.jpg')) {
            $errors[] = ["type" => "error", "message" => "The file: <strong>./upload/photos/d-avatar.jpg</strong> is not writable, the file permission should be set to <strong>777</strong>.<br> Also make sure the file exists."];
        }

        if (!is_writable('./upload/photos/d-cover.jpg')) {
            $errors[] = ["type" => "error", "message" => "The file: <strong>./upload/photos/d-cover.jpg</strong> is not writable, the file permission should be set to <strong>777</strong>.<br> Also make sure the file exists."];
        }
        
        if (!is_writable('./upload/photos/thumbnail.jpg')) {
            $errors[] = ["type" => "error", "message" => "The file: <strong>./upload/photos/thumbnail.jpg</strong> is not writable, the file permission should be set to <strong>777</strong>.<br> Also make sure the file exists."];
        }
        
        if (!is_writable('./upload/photos/app-default-icon.png')) {
            $errors[] = ["type" => "error", "message" => "The file: <strong>./upload/photos/app-default-icon.png</strong> is not writable, the file permission should be set to <strong>777</strong>.<br> Also make sure the file exists."];
        }
        
    }


    if ($music->config->ffmpeg_system == 'on') {
        if (!isfuncEnabled("shell_exec")) {
            $errors[] = ["type" => "error", "message" => "The function: <strong>shell_exec</strong> is not enabled, please contact your hosting provider to enable it, it's required for <strong>FFMPEG</strong>."];
        }
        if ($music->config->ffmpeg_binary_file == "./ffmpeg/ffmpeg" || $music->config->ffmpeg_binary_file == "ffmpeg/ffmpeg") {
            if (!is_writable($music->config->ffmpeg_binary_file)) {
                $errors[] = ["type" => "error", "message" => "The file: <strong>ffmpeg/ffmpeg</strong> is not writable, file permission should be <strong>777</strong>."];
            }
        }
        
    }
    
    if (!is_writable('./sitemap-main.xml')) {
        $errors[] = ["type" => "error", "message" => "The file: <strong>./sitemap-main.xml</strong> is not writable, the file permission should be set to <strong>777</strong>."];
    }


    if (session_status() == PHP_SESSION_NONE) {
        $errors[] = ["type" => "error", "message" => "PHP Session can't start, please check the session settings on your server, the session path should be writable, contact your server for more Information."];
    }


    if (!empty($config['curl'])) {
        $ch = curl_init ();
        $timeout = 10; 
        $myHITurl = "https://www.google.com";
        curl_setopt ( $ch, CURLOPT_URL, $myHITurl );
        curl_setopt ( $ch, CURLOPT_HEADER, 0 );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $file_contents = curl_exec ( $ch );
        if (curl_errno ( $ch )) {
            $errors[] = ["type" => "error", "message" => "<strong>cURL</strong> is not functioning, can't connect to the outside world, error found: <strong>" . curl_error ( $ch ) . "</strong>, please contact your hosting provider to fix it."];
        }
        curl_close ( $ch );
    }


    if (!empty($config['htaccess'])) {
        if (!file_exists('./.htaccess')) {
            $errors[] = ["type" => "error", "message" => "The file: <strong>.htaccess</strong> is not uploaded to your server, make sure the file <strong>.htaccess</strong> is uploaded to your server."];
        } else {
            $file_gethtaccess = file_get_contents("./.htaccess");
            if (strpos($file_gethtaccess, "index.php?path") === false) {
                $errors[] = ["type" => "error", "message" => "The file: <strong>.htaccess</strong> is not updated, please re-upload the original .htaccess file."];
            }
        }
    }


    // if (!empty($config['nodejsport']) && $pt->config->server == "nodejs") {
    //     $parse = parse_url($pt->config->site_url);
    //     $host = $parse['host'];
    //     $ports = array($pt->config->server_port);
    //     foreach ($ports as $port)
    //     {
    //         $connection = @fsockopen($host, $port);

    //         if (!is_resource($connection))
    //         {
    //             $errors[] = ["type" => "error", "message" => "<strong>NodeJS</strong>is enabled, but the system can't connect to NodeJS server, <strong> " . $host . ':' . $port . " </strong>is down or port <strong>$port</strong> is blocked."];
    //         } 
    //     }
    // }


    $dirs = array_filter(glob('upload/*'), 'is_dir');
    foreach ($dirs as $key => $value) {
        if (!is_writable($value)) {
            $errors[] = ["type" => "error", "message" => "The folder: <strong>{$value}</strong> is not writable, folder permission should be set to <strong>777</strong>."];
        }
    }

    if (empty($music->config->smtp_host) && empty($music->config->smtp_username)) {
        $errors[] = ["type" => "error", "message" => "<strong>SMTP</strong> is not configured, it's recommended to setup <strong>SMTP</strong>, so the system can send e-mails from the server. <br> <a href=" . LoadAdminLinkSettings('email-settings') . ">Click Here To Setup SMTP</a>"];
    }




    if (!is_writable('./themes/' . $music->config->theme . '/img')) {
        $errors[] = ["type" => "error", "message" => "The folder: <strong>/themes/{$music->config->theme}/img</strong> is not writable, the path and all subfolder(s) permission should be set to <strong>777</strong>, including <strong>logo.png</strong>"];
    }
    

    if (file_exists('./install')) {
        $errors[] = ["type" => "error", "message" => "The folder: <strong>./install</strong> is not deleted or renamed, make sure the folder <strong>./install</strong> is deleted."];
    }

    

    if (!empty($music->config->filesVersion)) {
        if ($music->config->filesVersion > $music->config->version) {
            $errors[] = ["type" => "error", "message" => "There is a conflict in database version and files version, your database version is: <strong>v{$music->config->version}</strong>, but script version is: <strong>v{$music->config->filesVersion}</strong>. <br> Please run <strong><a href='{$music->config->site_url}/update.php'>{$music->config->site_url}/update.php</a></strong> of <strong>v{$music->config->filesVersion}</strong>. <br><br><a href='https://docs.deepsoundscript.com/#updates'>Click Here For More Information.</a>"];
        } else if ($music->config->filesVersion < $music->config->version) {
            $errors[] = ["type" => "error", "message" => "There is a conflict in database version and files version, your database version is: <strong>v{$music->config->version}</strong>, but script version is: <strong>v{$music->config->filesVersion}</strong>. <br>Please upload the files of <strong>v{$music->config->filesVersion}</strong> using FTP or SFTP, file managers are not recommended."];
        }
    } else {
        $errors[] = ["type" => "error", "message" => "There is a conflict in database version and files version, your database version is: <strong>v{$music->config->version}</strong>, but script version is: <strong>v{$music->config->filesVersion}</strong>, <br>Please upload the files of <strong>v{$music->config->filesVersion}</strong> using FTP or SFTP, file managers are not recommended."];
    }

    //if (!empty($music->config->cronjob_last_run)) {
        $now = strtotime("-15 minutes");
        if ($music->config->cronjob_last_run < $now) {
            $errors[] = ["type" => "error", "message" => "File <strong>cron-job.php</strong> last run exceeded 15 minutes, make sure it's added to cronjob list. <br> <a href=" . LoadAdminLinkSettings('cronjob_settings') . ">CronJob Settings</a>"];
        }
    //}

    

    $getSqlModes = $db->rawQuery("SELECT @@sql_mode as modes;");
      if (!empty($getSqlModes[0]->modes)) {
         $results = @explode(',', strtolower($getSqlModes[0]->modes));
         if (in_array('strict_trans_tables', $results)) {
           $errors[] = ["type" => "error", "message" => "The sql-mode <b>strict_trans_tables</b> is enabled in your mysql server, please contact your host provider to disable it."];
         }
         if (in_array('only_full_group_by', $results)) {
           $errors[] = ["type" => "error", "message" => "The sql-mode <b>only_full_group_by</b> is enabled in your mysql server, this can cause some issues on your website, please contact your host provider to disable it."];
         }
      }

    $getUploadSize = file_upload_max_size();


    if ($getUploadSize < 1000000000) {
        $errors[] = ["type" => "warning", "message" => "Your server max upload size is less than 100MB, Current: <strong>" . formatBytes($getUploadSize). "</strong> Recommended is <strong>1024MB</strong>. You should update both: upload_max_filesize, post_max_size."];
    }


    if (ini_get('max_execution_time') < 100 && ini_get('max_execution_time') > 0) {
        $errors[] = ["type" => "warning", "message" => "Your server max_execution_time is less than 100 seconds, Current: <strong>" . ini_get('max_execution_time'). "</strong> Recommended is <strong>3000</strong>."];
    }


    if ($music->config->developer_mode == "on") {
        $errors[] = ["type" => "warning", "message" => "<strong>Developer Mode</strong> is enabled in <strong>Settings -> General Configuration</strong>, it's not recommended to enable <strong>Developer Mode</strong> if your website is live, some errors may show."];
    }


    if(!function_exists('exif_read_data')) {
        $errors[] = ["type" => "warning", "message" => "PHP <strong>exif</strong> extension is disabled on your server, it is recommended to be enabled."];
    }


    try {
        $getSqlWait = $db->rawQuery("show variables where Variable_name='wait_timeout';");
        if (!empty($getSqlWait[0]->Value)) {
            if ($getSqlWait[0]->Value < 1000) {
              $errors[] = ["type" => "warning", "message" => "The MySQL variable <b>wait_timeout</b> is {$getSqlWait[0]->Value}, minumum required is <strong>1000</strong>, please contact your host provider to update it."];
            }
        }
    } catch (Exception $e) {
        
    }

    return $errors;
}
function ImportKKBOXtrack($url='')
{
    global $db,$lang;

    $ch = curl_init();
    $options = array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 5.0; SM-G900P Build/LRX21T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Mobile Safari/537.36',
        CURLOPT_ENCODING       => "utf-8",
        CURLOPT_AUTOREFERER    => false,
        CURLOPT_COOKIEJAR      => 'cookie.txt',
        CURLOPT_COOKIEFILE     => 'cookie.txt',
        CURLOPT_REFERER        => 'https://www.kkbox.com/',
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_MAXREDIRS      => 10,
    );
    curl_setopt_array( $ch, $options );
    $data = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $dom = new domdocument();
    $dom->loadhtml($data);
    $metas = $dom->getelementsbytagname('meta');
    preg_match('/(http|https):\/\/www.kkbox\.com\/(.*)\/(.*)\/song\/(.*)/', $url, $match);
    $id = '';
    if (!empty($match) && !empty($match[4])) {
        $id = $match[4];
    }

    $data = array(
        'impoted_from' => 'KKBOX',
        'duration' => 30,
        'original_content_size' => '',
        'original_format' => '',
        'tag_list' => '',
        'itunes_affiliate_url' => '',
        'itunes_token' => '',
        'id' => $id,
    );
    foreach($metas as $meta) {
        if ($meta->getattribute('property') == 'og:title') {
            $data['title'] = $meta->getattribute('content');
        }
        if ($meta->getattribute('property') == 'og:image') {
            $data['artwork_url'] = $meta->getattribute('content');
        }
        if ($meta->getattribute('property') == 'og:description') {
            $data['description'] = $meta->getattribute('content');
        }
        if ($meta->getattribute('property') == 'og:audio') {
            $data['http_mp3_128_url'] = $meta->getattribute('content');
        }
        if ($meta->getattribute('property') == 'music:preview_url:secure_url') {
            $data['http_mp3_128_url'] = $meta->getattribute('content');
        }
        if ($meta->getattribute('property') == 'music:preview_url:url') {
            $data['http_mp3_128_url'] = $meta->getattribute('content');
        }
    }
    return $data;
}