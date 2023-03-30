<?php
if ($option == "login") {
    runPlugin('PreUserLogin', $_POST);
    if (!empty($_POST)) {
        if (empty($_POST["username"]) || empty($_POST["password"])) {
            if ($music->config->prevent_system == 1) {
                AddBadLoginLog();
            }
            $errors[] = lang("Please check your details");
        } else {
            if ($music->config->prevent_system == 1) {
                if (!CanLogin()) {
                    $errors[] = lang(
                        "Too many login attempts please try again later"
                    );
                    header("Content-type: application/json");
                    echo json_encode([
                        "status" => 400,
                        "errors" => $errors,
                    ]);
                    exit();
                }
            }
            $username = secure($_POST["username"]);
            $password = secure($_POST["password"]);
            $phone = 0;

            $getUser = $db
                ->where("(username = ? or email = ?)", [$username, $username])
                ->getOne(T_USERS, ["password", "id", "active", "admin"]);

            if (empty($getUser)) {
                if ($music->config->prevent_system == 1) {
                    AddBadLoginLog();
                }
                $errors[] = lang("Incorrect username or password");
            } elseif (!password_verify($password, $getUser->password)) {
                if ($music->config->prevent_system == 1) {
                    AddBadLoginLog();
                }
                $errors[] = lang("Incorrect username or password");
            } elseif ($getUser->active == 0) {
                $errors[] = lang(
                    "Your account is not activated yet, please check your inbox for the activation link"
                );
            }

            if ($music->config->maintenance_mode == "on") {
                if ($getUser->admin === 0) {
                    $errors[] = lang(
                        "Website maintenance mode is active, Login for user is forbidden"
                    );
                }
            }
            if (empty($errors)) {
                if (VerifyIP($getUser->id) === false) {
                    $_SESSION["code_id"] = $getUser->id;
                    $data = [
                        "status" => 600,
                        "location" => getLink("unusual-login"),
                    ];
                    $phone = 1;
                }
                if (TwoFactor($getUser->id) === false) {
                    $_SESSION["code_id"] = $getUser->id;
                    $two_factor_hash = bin2hex(random_bytes(18));
                    $db->where('id',$_SESSION['code_id'])->update(T_USERS,array('two_factor_hash' => $two_factor_hash));
                    $_SESSION['two_factor_hash'] = $two_factor_hash;
                    setcookie("two_factor_hash", $two_factor_hash, time() + (60 * 60));
                    $data = [
                        "status" => 600,
                        "location" => getLink("unusual-login?type=two-factor"),
                    ];
                    $phone = 1;
                }
            }

            if (empty($errors) && $phone == 0) {
                createUserSession($getUser->id);
                $music->loggedin = true;
                $music->user = userData($getUser->id);
                $data = [
                    "status" => 200,
                    "header" => loadPage("header/logged_head", [
                        "site_search_bar" => loadPage("header/search-bar"),
                    ]),
                ];
                if (!empty($_POST['last_url'])) {
                    $data['last_url'] = secure($_POST['last_url']);
                }
                runPlugin('AfterUserLogin', $_POST);
            }
        }
    }
}

if ($option == "forgot-password") {
    runPlugin('PreForgotPassword', $_REQUEST);
    if (!empty($_POST)) {
        if (empty($_POST["email"])) {
            $errors[] = lang("Please check your details");
        } else {
            $email = secure($_POST["email"]);

            $getUser = $db
                ->where("email = ?", [$email])
                ->getOne(T_USERS, ["password", "id", "active", "email_code"]);

            if (empty($getUser)) {
                $errors[] = lang("This e-mail is not found");
            }

            if ($music->config->maintenance_mode == "on") {
                $errors[] = lang("Website maintenance mode is active");
            }

            if (empty($errors)) {
                $user_id = $getUser->id;
                $email_code = sha1(
                    rand(11111, 99999) .
                        rand(1111, 9999) .
                        uniqid(rand(1111, 9999))
                );
                $rest_user = userData($user_id);
                $time = time() + 60 * 60 * 24;
                $update = $db
                    ->where("id", $getUser->id)
                    ->update(T_USERS, [
                        "email_code" => $email_code,
                        "time_code_sent" => $time,
                    ]);

                $update_data["USER_DATA"] = $rest_user;
                $update_data["email_code"] = $email_code;
                $music->email_code = $email_code;
                $music->username = $rest_user->name;

                $send_email_data = [
                    "from_email" => $music->config->email,
                    "from_name" => $music->config->name,
                    "to_email" => $email,
                    "to_name" => $rest_user->name,
                    "subject" => lang("Reset Password"),
                    "charSet" => "UTF-8",
                    "message_body" => loadPage(
                        "emails/reset-password",
                        $update_data
                    ),
                    "is_html" => true,
                ];

                $send_message = sendMessage($send_email_data);
                if ($send_message) {
                    runPlugin('AfterForgotPassword', $send_email_data);
                    $data = [
                        "status" => 200,
                        "message" => lang(
                            "Please check your inbox / spam folder for the reset email."
                        ),
                    ];
                } else {
                    $errors[] = lang(
                        "Error found while sending the reset link, please try again later."
                    );
                }
            }
        }
    }
}

if ($option == "reset-password") {
    runPlugin('PreResetPassword', $_REQUEST);
    if (!empty($_POST)) {
        if (
            empty($_POST["password"]) ||
            empty($_POST["c_password"]) ||
            empty($_POST["email_code"])
        ) {
            $errors[] = lang("Please check your details");
        } else {
            $password = secure($_POST["password"]);
            $c_password = secure($_POST["c_password"]);
            $old_email_code = secure($_POST["email_code"]);

            $password_hashed = password_hash($password, PASSWORD_DEFAULT);
            if ($password != $c_password) {
                $errors[] = lang("Passwords don't match");
            } elseif (strlen($password) < 4 || strlen($password) > 32) {
                $errors[] = lang("Password is too short");
            }

            if ($music->config->maintenance_mode == "on") {
                $errors[] = lang("Website maintenance mode is active");
            }
            if (empty($errors)) {
                $user_id = $db
                    ->where("email_code", $old_email_code)
                    ->where("time_code_sent", time(), ">")
                    ->getValue(T_USERS, "id");
                if (!empty($user_id)) {
                    $email_code = sha1(time() + rand(1111, 9999));
                    $update = $db
                        ->where("id", $user_id)
                        ->update(T_USERS, [
                            "password" => $password_hashed,
                            "email_code" => "",
                        ]);
                    if ($update) {
                        runPlugin('AfterResetPassword', ["id" => $user_id]);
                        createUserSession($user_id);
                        $data = ["status" => 200];
                    }
                } else {
                    $errors[] = lang("Please check your details");
                }
            }
        }
    }
}

if ($option == "signup") {
    runPlugin('PreUserSignUp', $_REQUEST);
    if (
        isset($_GET["invite"]) &&
        !empty($_GET["invite"]) &&
        !IsAdminInvitationExists($_GET["invite"]) &&
        !IsUserInvitationExists($_GET["invite"])
    ) {
        $data = [
            "status" => 200,
            "link" => $site_url,
        ];
        header("Content-type: application/json");
        echo json_encode($data);
        exit();
    }
    $fields = GetWelcomeFields();
    if (!empty($_POST)) {
        if ($music->config->auto_username == 1) {
            $_POST['username'] = time() . rand(111111, 999999);
            if (empty($_POST['first_name']) || empty($_POST['last_name'])) {
                $errors[] = lang("first_name_last_name_empty");
                header("Content-type: application/json");
                echo json_encode(array(
                    'errors' => $errors,
                    'status' => 400
                ));
                exit();
            }
            else{
                $_POST["name"] = $_POST['first_name'] . ' ' . $_POST['last_name'];
            }
        }
        if (
            empty($_POST["username"]) ||
            empty($_POST["password"]) ||
            empty($_POST["email"]) ||
            empty($_POST["c_password"]) ||
            empty($_POST["name"])
        ) {
            $errors[] = lang("Please check your details");
        } else {
            if (
                $music->config->user_registration == "on" &&
                isset($_GET["invite"]) &&
                !IsAdminInvitationExists($_GET["invite"]) &&
                !IsUserInvitationExists($_GET["invite"])
            ) {
                $data = [
                    "status" => 200,
                    "link" => $site_url,
                ];
                header("Content-type: application/json");
                echo json_encode($data);
                exit();
            }

            $username = secure($_POST["username"]);
            $name = secure($_POST["name"]);
            $password = secure($_POST["password"]);
            $c_password = secure($_POST["c_password"]);
            $password_hashed = password_hash($password, PASSWORD_DEFAULT);
            $email = secure($_POST["email"]);
            if (UsernameExits($_POST["username"])) {
                $errors[] = lang("This username is already taken");
            }
            if (
                strlen($_POST["username"]) < 4 ||
                strlen($_POST["username"]) > 32
            ) {
                $errors[] = lang("Username length must be between 5 / 32");
            }
            if (!preg_match('/^[\w]+$/', $_POST["username"])) {
                $errors[] = lang("Invalid username characters");
            }
            if ($music->config->reserved_usernames_system == 1 && in_array($_POST["username"], $music->reserved_usernames)) {
                $errors[] = lang("This username is disallowed");
            }
            if (EmailExists($_POST["email"])) {
                $errors[] = lang("This e-mail is already taken");
            }
            if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
                $errors[] = lang("This e-mail is invalid");
            }
            if ($password != $c_password) {
                $errors[] = lang("Passwords don't match");
            }
            if (strlen($password) < 4) {
                $errors[] = lang("Password is too short");
            }
            if ($music->config->recaptcha == "on") {
                if (
                    !isset($_POST["g-recaptcha-response"]) ||
                    empty($_POST["g-recaptcha-response"])
                ) {
                    $errors[] = lang("Please check the re-captcha");
                }
            }

            if ($music->config->maintenance_mode == "on") {
                $errors[] = lang("Website maintenance mode is active");
            }
            if (!empty($fields) && count($fields) > 0) {
                foreach ($fields as $key => $field) {
                    if (empty($_POST[$field["fid"]])) {
                        $errors[] = $field["name"] . " " . lang("is required");
                    }
                    if (mb_strlen($_POST[$field["fid"]]) > $field["length"]) {
                        $errors[] =
                            $field["name"] .
                            " " .
                            lang("field max characters is") .
                            " " .
                            $field["length"];
                    }
                }
            }
            $field_data = [];
            $active = $music->config->validation == "on" ? 0 : 1;
            if (empty($errors)) {
                if (!empty($fields) && count($fields) > 0) {
                    foreach ($fields as $key => $field) {
                        if (!empty($_POST[$field["fid"]])) {
                            $_name = $field["fid"];
                            if (!empty($_POST[$_name])) {
                                $field_data[] = [
                                    $_name => $_POST[$_name],
                                ];
                            }
                        }
                    }
                }

                $email_code = sha1(time() + rand(111, 999));
                $insert_data = [
                    "username" => $username,
                    "password" => $password_hashed,
                    "email" => $email,
                    "name" => $name,
                    "ip_address" => get_ip_address(),
                    "active" => $active,
                    "email_code" => $email_code,
                    "last_active" => time(),
                    "registered" => date("Y") . "/" . intval(date("m")),
                    "time" => time(),
                ];
                $insert_data["language"] = $music->config->language;
                if (!empty($_SESSION["lang"])) {
                    if (in_array($_SESSION["lang"], $langs)) {
                        $insert_data["language"] = $_SESSION["lang"];
                    }
                }

                if (
                    !empty($_SESSION["ref"]) &&
                    $music->config->affiliate_type == 0
                ) {
                    $ref_user_id = $db
                        ->where("username", Secure($_SESSION["ref"]))
                        ->getValue(T_USERS, "id");
                    if (!empty($ref_user_id) && is_numeric($ref_user_id)) {
                        $insert_data["referrer"] = Secure($ref_user_id);
                        $insert_data["src"] = Secure("Referrer");
                        $db->where(
                            "username",
                            Secure($_SESSION["ref"])
                        )->update(T_USERS, [
                            "balance" => $db->inc($music->config->amount_ref),
                        ]);
                        unset($_SESSION["ref"]);
                    }
                } elseif (
                    !empty($_SESSION["ref"]) &&
                    $music->config->affiliate_type == 1
                ) {
                    $ref_user_id = $db
                        ->where("username", Secure($_SESSION["ref"]))
                        ->getValue(T_USERS, "id");
                    if (!empty($ref_user_id) && is_numeric($ref_user_id)) {
                        $insert_data["ref_user_id"] = Secure($ref_user_id);
                    }
                }

                $user_id = $db->insert(T_USERS, $insert_data);
                if (!empty($user_id)) {
                    runPlugin('AfterUserSignUp', $insert_data);
                    if ($music->config->invite_links_system == "1") {
                        AddInvitedUser($user_id, Secure($_GET["invite"]));
                    }
                    if (!empty($field_data)) {
                        $insert = UpdateUserCustomData(
                            $user_id,
                            $field_data,
                            false
                        );
                    }
                    if ($music->config->validation == "on") {
                        $link = $email_code . "/" . $username;
                        $data["EMAIL_CODE"] = $link;
                        $data["USERNAME"] = $username;
                        $music->email_code = $link;
                        $music->username = $username;
                        $send_email_data = [
                            "from_email" => $music->config->email,
                            "from_name" => $music->config->name,
                            "to_email" => $email,
                            "to_name" => $username,
                            "subject" => lang("Confirm your account"),
                            "charSet" => "UTF-8",
                            "message_body" => loadPage(
                                "emails/confirm-account",
                                $data
                            ),
                            "is_html" => true,
                        ];
                        $send_message = sendMessage($send_email_data);
                        $data = [
                            "status" => 403,
                            "message" => lang(
                                "Registration successful! We have sent you an email, Please check your inbox/spam to verify your account."
                            ),
                        ];
                    } else {
                        createUserSession($user_id);
                        $music->loggedin = true;
                        $music->user = userData($user_id);

                        $autoFollow = false;
                        if (!empty($music->config->auto_friend_users)) {
                            $autoFollow = AutoFollow($user_id);
                        }

                        if (
                            isset($_GET["invite"]) &&
                            IsAdminInvitationExists($_GET["invite"])
                        ) {
                            $db->where("code", secure($_GET["invite"]))->update(
                                T_INVITATIONS,
                                ["status" => "Active"]
                            );
                        }

                        $data = [
                            "status" => 200,
                            "autoFollow" => $autoFollow,
                            "header" => loadPage("header/logged_head", [
                                "site_search_bar" => loadPage(
                                    "header/search-bar"
                                ),
                            ]),
                        ];
                    }
                }
            }
        }
    }
}
if ($option == "resend_two_factor") {
    $hash = '';
    if (!empty($_SESSION) && !empty($_SESSION['two_factor_hash'])) {
        $hash = filter_var($_SESSION['two_factor_hash'], FILTER_SANITIZE_STRING);
        $hash = secure($hash);
    }
    if (!empty($_COOKIE) && !empty($_COOKIE['two_factor_hash'])) {
        $hash = filter_var($_COOKIE['two_factor_hash'], FILTER_SANITIZE_STRING);
        $hash = secure($hash);
    }
    if (empty($hash)) {
        $data['status'] = 400;
        $data['message'] = lang('code_two_expired');
    }
    else{
        $user = $db->where('two_factor_hash',$hash)->where('email_code','','!=')->getOne(T_USERS);
        if (!empty($user)) {
            if ($user->time_code_sent == 0 || $user->time_code_sent < (time() - (60 * 1))) {
                if (TwoFactor($user->id) === false) {
                    $db->where('id',$_SESSION['code_id'])->update(T_USERS,array('time_code_sent' => time()));
                    $data = array(
                        'status' => 200,
                        'message' => lang('code_successfully_sent')
                    );
                }
                else{
                    $data['status'] = 400;
                    $data['message'] = lang('something_went_wrong_please_try_again_later_');
                }
            }
            else{
                $data['status'] = 400;
                $data['message'] = lang('you_cant_send_now');
            }
        }
        else{
            $data['status'] = 400;
            $data['message'] = lang('something_went_wrong_please_try_again_later_');
        }
    }
}
if ($option == 'google_login') {
    if ($music->loggedin == false && $music->config->plus_login == 'on' && !empty($music->config->google_app_ID) && !empty($music->config->google_app_key) && !empty($_POST['id_token'])) {
        $data['status']   = 400;
        $access_token     = $_POST['id_token'];
        $get_user_details = fetchDataFromURL("https://oauth2.googleapis.com/tokeninfo?id_token={$access_token}");
        $json_data        = json_decode($get_user_details);
        $social_id    = '';
        $user_email    = '';
        $user_name    = '';
        $name    = '';
        if (!empty($json_data->error)) {
            $data['message'] = $error_icon . $json_data->error;
        } else if (!empty($json_data->kid)) {
            $social_id    = $json_data->kid;
            $user_email = $json_data->email;
            $user_name  = $json_data->sub;
            $name  = $json_data->name;
            if (empty($user_email)) {
                $user_email = 'go_' . $social_id . '@google.com';
            }
            if(!empty($json_data->email) && empty($json_data->email_verified)) {
                $data['message'] = lang('google_email_verify');
            }
        }
        if (!empty($social_id) && empty($data['message'])) {
            if (EmailExists($user_email) === true) {
                $db->where('email', $user_email);
                $login = $db->getOne(T_USERS);
                createUserSession($login->id);
                runPlugin('AfterUserLogin', ["id" => $login->id]);
                $data['status'] = 200;
                $data['location'] = $site_url;
            } else {

                $str          = md5(microtime());
                $id           = substr($str, 0, 9);
                $password     = substr(md5(time()), 0, 9);
                $user_uniq_id = (empty($db->where('username', $id)->getValue(T_USERS, 'id'))) ? $id : 'u_' . $id;
                $re_data      = array(
                    'username' => secure($user_uniq_id, 0),
                    'email' => secure($user_email, 0),
                    'password' => secure(sha1($password), 0),
                    'email_code' => secure(sha1($user_uniq_id), 0),
                    'name' => secure($name),
                    'avatar' => secure(importImageFromLogin($json_data->picture)),
                    'src' => 'Google',
                    'active' => '1',
                    'time' => time()
                );
                $re_data['language'] = $music->config->language;
                if (!empty($_SESSION['lang'])) {
                    if (in_array($_SESSION['lang'], $langs)) {
                        $re_data['language'] = $_SESSION['lang'];
                    }
                }
                $insert_id = $db->insert(T_USERS, $re_data);
                if ($insert_id) {
                    createUserSession($insert_id);
                    runPlugin('AfterUserSignUp', $re_data);
                    $data['status'] = 200;
                    $data['location'] = $site_url;
                } 
            }
        }
    }
}
?>