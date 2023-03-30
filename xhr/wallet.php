<?php
if ($option == 'get_modal') {
    $types = array('pro','wallet','pay','subscribe');
    $data['status'] = 400;
    if (!empty($_POST['type']) && in_array($_POST['type'], $types)) {
        $user = $db->where('id',$music->user->id)->getOne(T_USERS);

        $price = 0;
        $video_id = 0;
        $user_id = 0;
        if (!empty($_POST['price'])) {
            $price = Secure($_POST['price']);
        }
        if (!empty($_POST['user_id'])) {
            $user_id = Secure($_POST['user_id']);
        }

        $music->show_wallet = 1;

        $html = LoadPage('modals/wallet-payment-modal',array('TYPE' => Secure($_POST['type']),'PRICE' => $price,'USER_ID' => $user_id));
        if (!empty($html)) {
            $data['status'] = 200;
            $data['html'] = $html;
        }
    }
}
if ($option == 'pay') {
    $types = array(
        'buy_album',
        'buy_song',
        'go_pro',
    );
    if (!empty($_POST['type']) && in_array($_POST['type'], $types)) {
        $price = 0;
        if ($_POST['type'] == 'buy_song' && !empty($_POST['id'])) {
            $trackID = secure($_POST['id']);
            $getIDAudio = $db->where('audio_id', $trackID)->getValue(T_SONGS, 'id');
            if (empty($getIDAudio)) {
                $data = array(
                    'status' => 400,
                    'error' => 'invalid track'
                );
            }
            if (isTrackPurchased($getIDAudio)) {
                $data['status'] = 400;
                $data['error'] = 'You already purchase this track.';
            }
            $songData = songData($getIDAudio);

            if (empty($songData->price)) {
                $data = array(
                    'status' => 400,
                    'error' => 'no price.'
                );
            }
            if ($songData->price > $music->user->org_wallet) {
                $data = array(
                    'status' => 400,
                    'error' => "<a href='".getLink("settings/".$music->user->username."/wallet")."'>".lang("You don't have enough money please top up your wallet")."</a>"
                );
            }
            if (empty($data['error'])) {

                $getAdminCommission = $music->config->commission;
                $final_price = round((($songData->price * $getAdminCommission ) / 100), 2);
                $new_price = $songData->price - $final_price;
                $addPurchase = [
                    'track_id' => $songData->id,
                    'user_id' => $user->id,
                    'price' => $songData->price,
                    'title' => $songData->title,
                    'track_owner_id' => $songData->user_id,
                    'final_price' => $new_price,
                    'commission' => $getAdminCommission,
                    'time' => time()
                ];
                $createPayment = $db->insert(T_PURCHAES, $addPurchase);
                if ($createPayment) {
                    $db->where('id', $music->user->id)->update(T_USERS, ['wallet' => $db->dec($songData->price)]);
                    CreatePayment(array(
                        'user_id'   => $user->id,
                        'amount'    => $new_price,
                        'type'      => 'TRACK',
                        'pro_plan'  => 0,
                        'info'      => $songData->audio_id,
                        'via'       => 'wallet'
                    ));
                    $addUserWallet = $db->where('id', $songData->user_id)->update(T_USERS, ['balance' => $db->inc($new_price)]);
                     $create_notification = createNotification([
                         'notifier_id' => $user->id,
                         'recipient_id' => $songData->user_id,
                         'type' => 'purchased',
                         'track_id' => $songData->id,
                         'url' => "track/$songData->audio_id"
                     ]);
                    $data['status'] = 200;
                    $data['url'] = $site_url."/track/{$songData->audio_id}";
                } else {
                    runPlugin('AfterFailedWalletPurchase');
                    $data['status'] = 400;
                    $data['error'] = lang("something_went_wrong_please_try_again_later_");
                }
            }
        }
        elseif ($_POST['type'] == 'buy_album' && !empty($_POST['id'])) {
            $album = $db->where('album_id',secure($_POST['id']))->getOne(T_ALBUMS);
            if ($album->price <= $music->user->org_wallet) {
                if (!empty($album) && !empty($album->price) && is_numeric($album->price) && $album->price > 0) {
                    $price    = $album->price;
                    $albumData = albumData($album->id, true, true, true);
                    if (!empty($albumData) && !empty($albumData->price) && is_numeric($albumData->price) && $albumData->price > 0) {
                        $album_id = $albumData->album_id;

                        $getAdminCommission = $music->config->commission;
                        $final_price = 0;

                        $createPayment = false;
                        foreach ($albumData->songs as $key => $song){
                            $final_price += round((($getAdminCommission * $song->price) / 100), 2);
                            $addPurchase = [
                                'track_id' => $song->id,
                                'user_id' => $user->id,
                                'price' => $song->price,
                                'title' => $song->title,
                                'track_owner_id' => $song->user_id,
                                'final_price' => round((($getAdminCommission * $song->price) / 100), 2),
                                'commission' => $getAdminCommission,
                                'time' => time()
                            ];

                            $createPayment = $db->insert(T_PURCHAES, $addPurchase);
                            if ($createPayment) {
                                CreatePayment(array(
                                    'user_id'   => $user->id,
                                    'amount'    => $final_price,
                                    'type'      => 'TRACK',
                                    'pro_plan'  => 0,
                                    'info'      => $song->audio_id,
                                    'via'       => 'wallet'
                                ));
                                $create_notification = createNotification([
                                    'notifier_id' => $user->id,
                                    'recipient_id' => $song->user_id,
                                    'type' => 'purchased',
                                    'track_id' => $song->id,
                                    'url' => "track/$song->audio_id"
                                ]);
                            }
                        }

                        if ($createPayment) {
                            $db->where('id', $music->user->id)->update(T_USERS, ['wallet' => $db->dec($album->price)]);
                            $updatealbumpurchases = $db->where('album_id', $album_id)->update(T_ALBUMS, array('purchases' => $db->inc(1) ));
                            $addUserWallet = $db->where('id', $albumData->user_id)->update(T_USERS, ['balance' => $db->inc($final_price)]);
                            $data['status'] = 200;
                            $data['url'] = $site_url."/album/{$album_id}";
                        } else {
                            runPlugin('AfterFailedWalletPurchase');
                            $data['status'] = 400;
                            $data['error'] = lang("something_went_wrong_please_try_again_later_");
                        }
                    }
                    else{
                        runPlugin('AfterFailedWalletPurchase');
                        $data['status'] = 400;
                        $data['error'] = lang("something_went_wrong_please_try_again_later_");
                    }
                }
                else{
                    $data['status'] = 400;
                    $data['error'] = lang("Please check your details");
                }
            }
            else{
                $data = array(
                    'status' => 400,
                    'error' => "<a href='".getLink("settings/".$music->user->username."/wallet")."'>".lang("You don't have enough money please top up your wallet")."</a>"
                );
            }
        }
        elseif ($_POST['type'] == 'go_pro' && !empty($_POST['id']) && in_array($_POST['id'], array_keys($music->pro_packages))) {
            $pro_package = $music->pro_packages[$_POST['id']];
            if ($pro_package['price'] <= $music->user->org_wallet) {
                $price = $pro_package['price'];
                $update_data = ['is_pro' => 1, 'pro_time' => time(), 'pro_type' => $pro_package['id']];
                if ($pro_package['verified_badge']) {
                    $update_data['verified'] = 1;
                }
                if ($pro_package['artist_member']) {
                    $update_data['artist'] = 1;
                }
                $updateUser = $db->where('id', $user->id)->update(T_USERS, $update_data);
                if ($updateUser) {
                    $db->where('id', $music->user->id)->update(T_USERS, ['wallet' => $db->dec($pro_package['price'])]);
                    CreatePayment(array(
                        'user_id'   => $user->id,
                        'amount'    => $pro_package['price'],
                        'type'      => 'PRO',
                        'pro_plan'  => $pro_package['id'],
                        'info'      => '',
                        'via'       => 'wallet'
                    ));
                    if ((!empty($_SESSION['ref']) || !empty($user->ref_user_id)) && $music->config->affiliate_type == 1 && $user->referrer == 0) {
                        if ($music->config->amount_percent_ref > 0) {
                            if (!empty($_SESSION['ref'])) {
                                $ref_user_id = $db->where('username', secure($_SESSION['ref']))->getValue(T_USERS, 'id');
                            }
                            elseif (!empty($user->ref_user_id)) {
                                $ref_user_id = $db->where('id', $user->ref_user_id)->getValue(T_USERS, 'id');
                            }
                            if (!empty($ref_user_id) && is_numeric($ref_user_id)) {
                                $db->where('id', $user->user_id)->update(T_USERS,array(
                                    'referrer' => $ref_user_id,
                                    'src' => 'Referrer'
                                ));
                                $ref_amount     = ($music->config->amount_percent_ref * $pro_package['price']) / 100;
                                $db->where('id', $ref_user_id)->update(T_USERS,array('balance' => $db->inc($ref_amount)));
                                unset($_SESSION['ref']);
                            }
                        } else if ($music->config->amount_ref > 0) {
                            if (!empty($_SESSION['ref'])) {
                                $ref_user_id = $db->where('username', secure($_SESSION['ref']))->getValue(T_USERS, 'id');
                            }
                            elseif (!empty($user->ref_user_id)) {
                                $ref_user_id = $db->where('id', $user->ref_user_id)->getValue(T_USERS, 'id');
                            }
                            if (!empty($ref_user_id) && is_numeric($ref_user_id)) {
                                $db->where('id', $user->user_id)->update(T_USERS,array(
                                    'referrer' => $ref_user_id,
                                    'src' => 'Referrer'
                                ));
                                $db->where('id', $ref_user_id)->update(T_USERS,array('balance' => $db->inc($music->config->amount_ref)));
                                unset($_SESSION['ref']);
                            }
                        }
                    }
                    $data['status'] = 200;
                    $data['url'] = $site_url."/upgraded";
                } else {
                    runPlugin('AfterFailedWalletPurchase');
                    $data['status'] = 400;
                    $data['error'] = lang("something_went_wrong_please_try_again_later_");
                }
            }
            else{
                $data = array(
                    'status' => 400,
                    'error' => "<a href='".getLink("settings/".$music->user->username."/wallet")."'>".lang("You don't have enough money please top up your wallet")."</a>"
                );
            }
        }
    }
}
