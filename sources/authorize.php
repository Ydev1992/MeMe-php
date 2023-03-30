<?php
if (empty($_GET['app_id']) || empty($_GET['app_secret']) || empty($_GET['code'])) {
    $errors = array(
        'status' => 400,
        'errors' => array(
            'error_code' => 1,
            'message' => 'app_id , app_secret , code can not be empty'
        )
    );
    header("Content-type: application/json");
    echo json_encode($errors, JSON_PRETTY_PRINT);
    exit();
}

$app = $db->where('app_id',secure($_GET['app_id']))->where('app_secret',secure($_GET['app_secret']))->getOne(T_APPS);
if (empty($app)) {
    $errors = array(
        'status' => 400,
        'errors' => array(
            'error_code' => 2,
            'message' => 'app not found'
        )
    );
    header("Content-type: application/json");
    echo json_encode($errors, JSON_PRETTY_PRINT);
    exit();
}

$app_code = $db->where('app_id',$app->id)->where('code',secure($_GET['code']))->getOne(T_APPS_CODES);
if (empty($app_code)) {
    $errors = array(
        'status' => 400,
        'errors' => array(
            'error_code' => 3,
            'message' => 'wrong code'
        )
    );
    header("Content-type: application/json");
    echo json_encode($errors, JSON_PRETTY_PRINT);
    exit();
}

$have_permission = $db->where('app_id',$app->id)->where('user_id',$app_code->user_id)->getValue(T_APPS_PERMISSION,'COUNT(*)');
if ($have_permission == 0) {
    $errors = array(
        'status' => 400,
        'errors' => array(
            'error_code' => 4,
            'message' => 'missing permission'
        )
    );
    header("Content-type: application/json");
    echo json_encode($errors, JSON_PRETTY_PRINT);
    exit();
}

createUserSession($app_code->user_id,'mobile');

$db->where('app_id',$app->id)->where('code',secure($_GET['code']))->delete(T_APPS_CODES);
$data = array(
            'status' => 200,
            'access_token' => $_SESSION['user_id']
        );

header("Content-type: application/json");
echo json_encode($data, JSON_PRETTY_PRINT);
exit();