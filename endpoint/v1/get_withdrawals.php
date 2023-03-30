<?php

$limit              = (isset($_POST['limit'])) ? secure($_POST['limit']) : 20;
$offset             = (isset($_POST['offset'])) ? secure($_POST['offset']) : 0;
if (!empty($offset)) {
    $db->where('id',$offset,'<');
}
$user_withdrawals  = $db->where('user_id',$music->user->id)->orderBy('id', 'DESC')->get(T_WITHDRAWAL_REQUESTS,$limit);
$data = [
    'status' => 200,
    'data' => $user_withdrawals
];