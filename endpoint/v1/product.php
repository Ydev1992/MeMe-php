<?php
if ($music->config->store_system != 'on') {
	$errors = 'store system is off';
}
$offset             = (isset($_POST['offset']) && is_numeric($_POST['offset']) && $_POST['offset'] > 0) ? secure($_POST['offset']) : 0;
$limit             = (isset($_POST['limit']) && is_numeric($_POST['limit']) && $_POST['limit'] > 0) ? secure($_POST['limit']) : 20;
if ($option == 'song_search') {
    if (!empty($_POST['word'])) {
        $search_keyword = secure($_POST['word']);
        if (!empty($offset)) {
			$db->where('id',$offset,'<');
		}
        $results = $db->where("(title LIKE '%$search_keyword%')")
                      ->where('user_id',$music->user->id)
                      ->orderBy('id', 'DESC')
                      ->get(T_SONGS,$limit);
        if (!empty($results)) {
            $array = array();
            foreach ($results as $key => $value) {
            	$song = songData($value->id);
            	unset($song->publisher->password);
			    unset($song->publisher->email_code);
            	$array[] = $song;
            }
            $data['status'] = 200;
            $data['data'] = $array;
        }
        else{
        	$errors = 'something went wrong';
        }
    }
    else{
        $errors = 'word can not be empty';
    }
}
if ($option == 'create') {
    if (!empty($_POST['title']) && !empty($_POST['desc']) && !empty($_POST['tags']) && !empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0 && !empty($_POST['units']) && is_numeric($_POST['units']) && $_POST['units'] > 0 && !empty($_POST['related']) && !empty($_FILES['image']) && !empty($_POST['category']) && in_array($_POST['category'], array_keys($music->products_categories))) {
        if (strlen($_POST['title']) < 4) {
            $errors = 'Title is too short';
        }
        elseif (strlen($_POST['desc']) < 10) {
        	$errors = 'Description is too short';
        }
        $related_song = songData(secure($_POST['related']));
        if (empty($related_song) || $related_song->user_id != $music->user->id) {
        	$errors = 'Please select a song';
        }
        $files = array();
        if ($_FILES['image']) {
            foreach ($_FILES['image']['name'] as $key => $value) {
                $file_info = array(
                    'file' => $_FILES['image']['tmp_name'][$key],
                    'size' => $_FILES['image']['size'][$key],
                    'name' => $_FILES['image']['name'][$key],
                    'type' => $_FILES['image']['type'][$key]
                );
                $file_upload = ShareFile($file_info);
                if (!empty($file_upload) && !empty($file_upload['filename'])) {
                    $files[] = $file_upload['filename'];
                }
            }
        }
        if (empty($files)) {
            $errors = 'Please select a valid image';
        }
        if (empty($errors)) {
            $id = $db->insert(T_PRODUCTS,array('title' => secure($_POST['title']),
                                                 'desc' => secure($_POST['desc']),
                                                 'price' => secure($_POST['price']),
                                                 'units' => secure($_POST['units']),
                                                 'related_song' => secure($_POST['related']),
                                                 'cat_id' => secure($_POST['category']),
                                                 'user_id' => $music->user->id,
                                                 'active' => ($music->config->store_review_system == 'off' ? 1 : 0),
                                                 'time' => time(),
                                                 'tags' => secure(str_replace('#', '', $_POST['tags']))));
            if (!empty($id)) {
                $create_activity = createActivity([
                    'user_id' => $music->user->id,
                    'type' => 'created_product',
                    'product_id' => $id,
                ]);
                $db->where('id',$id)->update(T_PRODUCTS,array('hash_id' => uniqid($id)));
                foreach ($files as $key => $value) {
                    $db->insert(T_MEDIA,array('product_id' => $id,
                                              'image' => $value,
                                              'time' => time()));
                }
                $data['status'] = 200;
                if ($music->config->store_review_system == 'off') {
                    $data['message'] = lang('Your product has been published successfully');
                }
                else{
                    $data['message'] = lang('Your product is under review');
                }
                $product = GetProduct($id);
                if (!empty($product) && !empty($product->related_song) && !empty($product->related_song->publisher)) {
                	unset($product->related_song->publisher->password);
				    unset($product->related_song->publisher->email_code);
                }
                if (!empty($product) && !empty($product->user_data)) {
                	unset($product->user_data->password);
				    unset($product->user_data->email_code);
                }
                $data['data'] = $product;
            }
            else{
                $errors = 'Error 500 internal server error!';
            }
        }
    }
    else{
        if (empty($_POST['title'])) {
        	$errors = 'title can not be empty';
        }
        elseif (empty($_POST['desc'])) {
        	$errors = 'description can not be empty';
        }
        elseif (empty($_POST['tags'])) {
        	$errors = 'tags can not be empty';
        }
        elseif (empty($_POST['price'])) {
        	$errors = 'price can not be empty';
        }
        elseif (empty($_POST['units'])) {
        	$errors = 'units can not be empty';
        }
        elseif (empty($_POST['related'])) {
        	$errors = 'related song can not be empty';
        }
        elseif (empty($_POST['category'])) {
        	$errors = 'category can not be empty';
        }
        elseif (empty($_FILES['image'])) {
        	$errors = 'image can not be empty';
        }
        else{
        	$errors = 'Please check the details';
        } 
    }
}
if ($option == 'edit') {
    if (!empty($_POST['title']) && !empty($_POST['desc']) && !empty($_POST['tags']) && !empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0 && !empty($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] > 0 && !empty($_POST['units']) && is_numeric($_POST['units']) && $_POST['units'] > 0 && !empty($_POST['related']) && !empty($_POST['category']) && in_array($_POST['category'], array_keys($music->products_categories))) {
        $product = GetProduct(secure($_POST['id']));
        if (empty($product) || ($product->user_id != $music->user->id && !isAdmin())) {
        	$errors = 'Please check the details';
        }
        if (strlen($_POST['title']) < 4) {
        	$errors = 'Title is too short';
        }
        elseif (strlen($_POST['desc']) < 10) {
        	$errors = 'Description is too short';
        }
        $related_song = songData(secure($_POST['related']));
        if (empty($related_song) || $related_song->user_id != $music->user->id) {
        	$errors = 'Please select a song';
        }
        $files = array();
        if (!empty($_FILES['image'])) {
            foreach ($_FILES['image']['name'] as $key => $value) {
                $file_info = array(
                    'file' => $_FILES['image']['tmp_name'][$key],
                    'size' => $_FILES['image']['size'][$key],
                    'name' => $_FILES['image']['name'][$key],
                    'type' => $_FILES['image']['type'][$key]
                );
                $file_upload = ShareFile($file_info);
                if (!empty($file_upload) && !empty($file_upload['filename'])) {
                    $files[] = $file_upload['filename'];
                }
            }
        }
        if (empty($errors)) {
            $db->where('id',$product->id)->update(T_PRODUCTS,array('title' => secure($_POST['title']),
                                                                         'desc' => secure($_POST['desc']),
                                                                         'price' => secure($_POST['price']),
                                                                         'units' => secure($_POST['units']),
                                                                         'cat_id' => secure($_POST['category']),
                                                                         'related_song' => secure($_POST['related']),
                                                                         'tags' => secure(str_replace('#', '', $_POST['tags']))));
            if (!empty($product->id)) {
                if (!empty($files)) {
                    foreach ($product->images as $key => $value) {
                        @unlink($value['org_image']);
                        PT_DeleteFromToS3($value['org_image']);
                    }
                    $db->where('product_id',$product->id)->delete(T_MEDIA);
                    foreach ($files as $key => $value) {
                        $db->insert(T_MEDIA,array('product_id' => $product->id,
                                                  'image' => $value,
                                                  'time' => time()));
                    }
                }
                $data['status'] = 200;
                $data['message'] = 'Your product has been edited successfully';
                $product = GetProduct($product->id);
                if (!empty($product) && !empty($product->related_song) && !empty($product->related_song->publisher)) {
                	unset($product->related_song->publisher->password);
				    unset($product->related_song->publisher->email_code);
                }
                if (!empty($product) && !empty($product->user_data)) {
                	unset($product->user_data->password);
				    unset($product->user_data->email_code);
                }
                $data['data'] = $product;
                    
            }
            else{
            	$errors = 'Error 500 internal server error!';
            }
        }
    }
    else{
        if (empty($_POST['title'])) {
        	$errors = 'title can not be empty';
        }
        elseif (empty($_POST['desc'])) {
        	$errors = 'description can not be empty';
        }
        elseif (empty($_POST['tags'])) {
        	$errors = 'tags can not be empty';
        }
        elseif (empty($_POST['price'])) {
        	$errors = 'price can not be empty';
        }
        elseif (empty($_POST['units'])) {
        	$errors = 'units can not be empty';
        }
        elseif (empty($_POST['related'])) {
        	$errors = 'related song can not be empty';
        }
        elseif (empty($_POST['category'])) {
        	$errors = 'category can not be empty';
        }
        else{
        	$errors = 'Please check the details';
        }
    }
}
if ($option == 'product_search') {
    $category = (isset($_POST['category']) && in_array($_GET['category'], array_keys($music->products_categories))) ? $_POST['category'] : '';
    $price_from = (isset($_POST['price_from']) && is_numeric($_POST['price_from'])) ? $_POST['price_from'] : 1;
    $price_to = (isset($_POST['price_to']) && is_numeric($_POST['price_from'])) ? $_POST['price_to'] : 10000;
    $text = "";
    if (!empty($_POST['filter_search_keyword'])) {
        $search_keyword = secure($_POST['filter_search_keyword']);
        $text = " AND (`title` LIKE '%$search_keyword%' OR `desc` LIKE '%$search_keyword%') ";
    }
    if (!empty($_POST['tag'])) {
        $tag = secure($_POST['tag']);
        $text = " AND (`tags` LIKE '%$tag%') ";
    }

    if( empty($price_from) || empty($price_to) ){
        exit('Empty parameters, hmm?');
    }
    $and = [];
    $sql = 'SELECT * FROM `'. T_PRODUCTS .'` WHERE ';
    $and[] = " `price` BETWEEN ". $price_from ." AND ". $price_to . $text ;
    if(is_array($category) && !empty($category)) {
        $and[] = " `cat_id` IN ('" . implode("','",$category) . "') ";
    }
    $query_text = "";
	if (!empty($offset)) {
		$query_text = " AND `id` < '". $offset ."' ";
	}

    $sql .= implode(' AND ', $and ) .$query_text.' ORDER BY `id` DESC LIMIT '.$limit;
    $array = array();
    $products = $db->rawQuery($sql);
    if (!empty($products)) {
        $records = count($products);
        $html_list = '';
        foreach ($products as $key => $value) {
            $product = GetProduct($value->id);
            if (!empty($product)) {
            	if (!empty($product) && !empty($product->related_song) && !empty($product->related_song->publisher)) {
                	unset($product->related_song->publisher->password);
				    unset($product->related_song->publisher->email_code);
                }
                if (!empty($product) && !empty($product->user_data)) {
                	unset($product->user_data->password);
				    unset($product->user_data->email_code);
                }
                $array[] = $product;
            }
        }
    }
    $data['data'] = $array;
    $data['status'] = 200;
}
if ($option == 'buy') {
    $data['status'] = 400;
    if (!empty($_POST['address_id']) && is_numeric($_POST['address_id']) && $_POST['address_id'] > 0) {
        $address = $db->where('id',secure($_POST['address_id']))->where('user_id',$music->user->id)->getOne(T_ADDRESS);
        if (!empty($address)) {
            $music->items = $db->where('user_id',$music->user->id)->get(T_CARD);
            $html = '';
            $total = 0;
            $insert = array();
            $main_product = '';

            if (!empty($music->items)) {
                foreach ($music->items as $key => $music->item) {
                    $product = $main_product = GetProduct($music->item->product_id);
                    if ($music->item->units <= $product->units) {
                        $total += ($product->price * $music->item->units);
                        if (!in_array($product->user_id, array_keys($insert))) {
                            $insert[$product->user_id] = array();
                            $insert[$product->user_id][] = array('product_id' => $product->id,
                                                                 'price' => $product->price,
                                                                 'units' => $music->item->units);
                        }
                        else{
                            $insert[$product->user_id][] = array('product_id' => $product->id,
                                                                 'price' => $product->price,
                                                                 'units' => $music->item->units);
                        }
                    }
                    else{
                    	$data = array('status' => 400, 'error' => "Some products don't have enough of units");
					    echo json_encode($data);
					    exit();
                    }
                }
                if ($music->user->org_wallet < $total) {
                	$data = array('status' => 400, 'error' => "You don't have enough wallet Please top up your wallet");
				    echo json_encode($data);
				    exit();
                }

                if (!empty($insert)) {
                    foreach ($insert as $key => $value) {
                        $hash_id = uniqid(rand(11111,999999));
                        $total = 0;
                        $total_commission = 0;
                        $total_final_price = 0;
                        foreach ($value as $key2 => $value2) {
                            $db->where('id',$value2['product_id'])->update(T_PRODUCTS,array('units' => $db->dec($value2['units'])));
                            $store_commission = 0;
                            if (!empty($music->config->store_commission)) {
                                $store_commission = round((($music->config->store_commission * ($value2['price'] * $value2['units'])) / 100), 2);
                            }
                            $total += ($value2['price'] * $value2['units']);
                            $total_commission += $store_commission;
                            $total_final_price += ($value2['price'] * $value2['units']) - $store_commission;
                                
                            $db->insert(T_ORDERS,array('user_id' => $music->user->id,
                                                       'product_owner_id' => $key,
                                                       'product_id' => $value2['product_id'],
                                                       'price' => ($value2['price'] * $value2['units']),
                                                       'commission' => $store_commission,
                                                       'final_price' => ($value2['price'] * $value2['units']) - $store_commission,
                                                       'hash_id' => $hash_id,
                                                       'units' => $value2['units'],
                                                       'status' => 'placed',
                                                       'address_id' => $address->id,
                                                       'time' => time()));
                        }
                        $db->insert(T_PURCHAES,array('user_id' => $music->user->id,
                                                         'order_hash_id' => $hash_id,
                                                         'price' => $total,
                                                         'title' => !empty($main_product) && !empty($main_product->title) ? $main_product->title : '',
                                                         'commission' => $total_commission,
                                                         'final_price' => $total_final_price,
                                                         'time' => time()));
                        $create_notification = createNotification([
                            'notifier_id' => $music->user->id,
                            'recipient_id' => $key,
                            'type' => 'new_orders',
                            'url' => Secure('orders')
                        ]);
                    }
                    $db->where('user_id',$music->user->id)->delete(T_CARD);
                    $data['status'] = 200;
                    $data['message'] = lang('Your order has been placed successfully');
                }
                else{
                    $errors = 'Error 500 internal server error!';
                }
            }
            else{
                $errors = 'Card is empty';
            }
        }
        else{
            $errors = 'Address not found';
        }
    }
    else{
    	$errors = 'Address can not be empty';
    }
}
if ($option == 'add_cart') {
    if (!empty($_POST['product_id']) && is_numeric($_POST['product_id']) && $_POST['product_id'] > 0) {
        $is_added = $db->where('product_id', secure($_POST['product_id']))->where('user_id',$music->user->id)->getOne(T_CARD);
        if (!empty($is_added)) {
            $db->where('product_id',secure($_POST['product_id']))->where('user_id',$music->user->id)->delete(T_CARD);
            $data['status'] = 200;
            $data['type'] = 'removed';
        }
        else{
            $qty = 1;
            if (!empty($_POST['qty']) && is_numeric($_POST['qty']) && $_POST['qty'] > 0) {
                $qty = secure($_POST['qty']);
            }
            $db->insert(T_CARD,array('user_id' => $music->user->id,
                                     'units' => $qty,
                                     'product_id' => secure($_POST['product_id'])));
            $data['status'] = 200;
            $data['type'] = 'added';
        }
        $data['count'] = $db->where('user_id',$music->user->id)->getValue(T_CARD,'COUNT(*)');
        if ($data['count'] < 1) {
            $data['count'] = '';
        }
    }
    else{
    	$errors = 'product_id can not be empty';
    }
}
if ($option == 'remove_cart') {
    if (!empty($_POST['product_id']) && is_numeric($_POST['product_id']) && $_POST['product_id'] > 0) {
        $is_added = $db->where('product_id', secure($_POST['product_id']))->where('user_id',$music->user->id)->getOne(T_CARD);
        if (!empty($is_added)) {
            $db->where('product_id',secure($_POST['product_id']))->where('user_id',$music->user->id)->delete(T_CARD);
            $data['status'] = 200;
            $data['count'] = $db->where('user_id',$music->user->id)->getValue(T_CARD,'COUNT(*)');
            if ($data['count'] < 1) {
                $data['count'] = '';
            }
        }
        else{
        	$errors = 'product not added to cart';
        }
    }
    else{
        $errors = 'product_id can not be empty';
    }
}
if ($option == 'get_cart') {
    $array = array();
    if (!empty($offset)) {
		$db->where('id',$offset,'<');
	}
    $carts = $db->where('user_id',$music->user->id)->orderBy('id', 'DESC')->get(T_CARD,$limit);
    if (!empty($carts)) {
        foreach ($carts as $key => $music->cart) {
            $product = GetProduct($music->cart->product_id);
            if (!empty($product) && !empty($product->related_song) && !empty($product->related_song->publisher)) {
                unset($product->related_song->publisher->password);
				unset($product->related_song->publisher->email_code);
            }
            if (!empty($product) && !empty($product->user_data)) {
                unset($product->user_data->password);
				unset($product->user_data->email_code);
            }
            $music->cart->product = $product;
            $array[] = $music->cart;
        }
    }
    $data['status'] = 200;
    $data['array'] = $array;
}
if ($option == 'change_qty') {
    if (!empty($_POST['product_id']) && is_numeric($_POST['product_id']) && $_POST['product_id'] > 0 && !empty($_POST['qty']) && is_numeric($_POST['qty']) && $_POST['qty'] > 0) {
        $product = GetProduct(secure($_POST['product_id']));
        $qty = secure($_POST['qty']);
        if ($product->units >= $qty) {
            $db->where('product_id',$product->id)->where('user_id',$music->user->id)->update(T_CARD,array('units' => $qty));
            $data['status'] = 200;
        }
        else{
        	$errors = 'wrong qty';
        }
    }
    else{
        $errors = 'product_id , qty can not be empty';
    }
}
if ($option == 'change_status') {
    if (!empty($_POST['hash_order']) && !empty($_POST['status'])) {
        $hash_id = secure($_POST['hash_order']);
        $status = secure($_POST['status']);
        $order = $db->where('hash_id',$hash_id)->getOne(T_ORDERS);
        if (!empty($order)) {
            $types = array();
            if ($order->product_owner_id == $music->user->id) {
                if ($order->status == 'placed') {
                    $types = array('canceled','accepted','packed','shipped');
                }
                if ($order->status == 'accepted') {
                    $types = array('packed','shipped');
                }
                if ($order->status == 'packed') {
                    $types = array('shipped');
                }
                if ($order->status == 'shipped') {
                    $types = array('delivered');
                }
            }
            elseif ($order->user_id == $music->user->id) {
                if ($order->status == 'shipped') {
                    $types = array('delivered');
                }
            }
            if (in_array($status, $types)) {
                $db->where('hash_id',$hash_id)->update(T_ORDERS,array('status' => $status));
                if ($order->product_owner_id == $music->user->id) {
                    $create_notification = createNotification([
                        'notifier_id' => $music->user->id,
                        'recipient_id' => $order->user_id,
                        'type' => 'status_changed',
                        'url' => Secure('customer_order/'.$hash_id)
                    ]);
                    $data['status'] = 200;
                    $data['message'] = 'status changed';
                }
                else{
                	$errors = 'you are not the owner';
                }
            }
            else{
            	$errors = 'you can not change the order to this status';
            }
        }
        else{
            $errors = 'Order not found';
        }
    }
    else{
    	$errors = 'hash_order , status can not be empty';
    }
}
if ($option == 'tracking') {
    if (!empty($_POST['tracking_url']) && !empty($_POST['tracking_id']) && !empty($_POST['hash']) && pt_is_url($_POST['tracking_url'])) {
        $hash_id = secure($_POST['hash']);
        $tracking_url = secure($_POST['tracking_url']);
        $tracking_id = secure($_POST['tracking_id']);
        $order = $db->where('hash_id',$hash_id)->where('product_owner_id',$music->user->id)->getOne(T_ORDERS);
        if (!empty($order)) {
            $db->where('hash_id',$hash_id)->update(T_ORDERS,array('tracking_url' => $tracking_url,
                                                                  'tracking_id' => $tracking_id));
            $create_notification = createNotification([
                'notifier_id' => $music->user->id,
                'recipient_id' => $order->user_id,
                'type' => 'added_tracking',
                'url' => Secure('customer_order/'.$hash_id)
            ]);
            $data['status'] = 200;
            $data['message'] = 'Tracking info has been saved successfully';
        }
        else{
            $errors = 'Order not found';
        }
    }
    else{
        if (empty($_POST['tracking_url'])) {
        	$errors = 'Tracking url can not be empty';
        }
        elseif (empty($_POST['tracking_id'])) {
        	$errors = 'Tracking number can not be empty';
        }
        elseif (!pt_is_url($_POST['tracking_url'])) {
        	$errors = 'Please enter a valid url';
        }
        else{
        	$errors = 'Please check the details';
        }
    }
}
if ($option == 'review') {
    if (!empty($_POST['rating']) && in_array($_POST['rating'], array(1,2,3,4,5)) && !empty($_POST['review']) && !empty($_POST['product_id']) && is_numeric($_POST['product_id']) && $_POST['product_id'] > 0) {
        $product_id = secure($_POST['product_id']);
        $rating = secure($_POST['rating']);
        $review = secure($_POST['review']);
        $files = array();
        if (!empty($_FILES['images'])) {
            foreach ($_FILES['images']['name'] as $key => $value) {
                $file_info = array(
                    'file' => $_FILES['images']['tmp_name'][$key],
                    'size' => $_FILES['images']['size'][$key],
                    'name' => $_FILES['images']['name'][$key],
                    'type' => $_FILES['images']['type'][$key]
                );
                $file_upload = ShareFile($file_info);
                if (!empty($file_upload) && !empty($file_upload['filename'])) {
                    $files[] = $file_upload['filename'];
                }
            }
        }
        $id = $db->insert(T_REVIEW,array('user_id' => $music->user->id,
                                       'product_id' => $product_id,
                                       'review' => $review,
                                       'time' => time(),
                                       'star' => $rating));
        if (!empty($id)) {
            if (!empty($files)) {
                foreach ($files as $key => $value) {
                    $db->insert(T_MEDIA,array('review_id' => $id,
                                              'image' => $value,
                                              'time' => time()));
                }
            }
                
            $data['status'] = 200;
            $data['message'] = 'Review has been sent successfully';
        }
        else{
            $errors = 'Error 500 internal server error!';
        }
    }
    else{
        if (empty($_POST['rating'])) {
        	$errors = 'rating can not be empty';
        }
        elseif (empty($_POST['review'])) {
        	$errors = 'review can not be empty';
        }
        else{
        	$errors = 'Please check the details';
        }
    }
}
if ($option == 'refund') {
    if (!empty($_POST['hash_order']) && !empty($_POST['message'])) {
        $hash = secure($_POST['hash_order']);
        $message = secure($_POST['message']);
        $order = $db->where('hash_id',$hash)->where('user_id',$music->user->id)->getOne(T_ORDERS);
        if (!empty($order)) {
            $db->insert(T_REFUND,array('order_hash_id' => $hash,
                                      'user_id' => $music->user->id,
                                      'message' => $message,
                                      'time' => time()));
            $notif_data = array(
                'recipient_id' => 0,
                'type' => 'refund',
                'admin' => 1,
                'time' => time()
            );
            $db->insert(T_NOTIFICATION,$notif_data);
            $data['status'] = 200;
            $data['message'] = 'Your request is under review';

        }
        else{
            $errors = 'Order not found';
        }
    }
    else{
        if (empty($_POST['message'])) {
            $errors = 'Please explain the reason';
        }
        else{
            $errors = 'Please check the details';
        } 
    }
}
if ($option == 'download') {
    if (!empty($_POST['id'])) {
        $id = secure($_POST['id']);
        $music->purchase = $db->where('order_hash_id',$id)->getOne(T_PURCHAES);
        if (!empty($music->purchase)) {
            $music->orders = $db->where('hash_id',$music->purchase->order_hash_id)->get(T_ORDERS);
            if (!empty($music->orders)) {
                $music->total = 0;
                $music->total_commission = 0;
                $music->total_final_price = 0;
                $music->address_id = 0;
                $music->user_id = 0;
                $music->html = '';
                $music->main_product = '';
                foreach ($music->orders as $key => $music->order) {
                    $music->order->product = GetProduct($music->order->product_id);
                    if (empty($music->main_product)) {
                        $music->main_product = $music->order->product;
                        $music->main_product->in_title = url_slug($music->main_product->title, array(
                            'delimiter' => '-',
                            'limit' => 100,
                            'lowercase' => true,
                            'replacements' => array(
                                '/\b(an)\b/i' => 'a',
                                '/\b(example)\b/i' => 'Test'
                            )
                        ));
                    }
                    $music->total += $music->order->price;
                    $music->total_commission += $music->order->commission;
                    $music->total_final_price += $music->order->final_price;
                    $music->address_id = $music->order->address_id;
                    $music->user_id = $music->order->product->user_id;
                    $music->html .= '<tr><td><h6 class="mb-0">'.$music->order->product->title.'</h6></td><td>'.($music->order->price/$music->order->units).'</td>
                                    <td>'.$music->order->units.'</td><td><span class="font-weight-semibold">'.$music->config->currency_symbol.$music->order->price.'</span></td></tr>';
                }
                $music->product_owner = userData($music->user_id);
                $music->address = $db->where('id',$music->address_id)->getOne(T_ADDRESS);


                $html = loadPage('pdf/invoice');
                $data['status'] = 200;
                $data['html'] = $html;
            }
            else{
                $errors = 'Order not found';
            }
        }
        else{
            $errors = 'You are not purchased';
        }
    }
    else{
    	$errors = 'id can not be empty';
    }       
}
if ($option == 'get_products') {
	if (!empty($offset)) {
		$db->where('id', $offset, '<');
	}
		
    if (IS_LOGGED) {
        $db->where("user_id NOT IN (SELECT user_id FROM blocks WHERE blocked_id = ".$music->user->id.")");
    }
    $html = "";
    $db->where('price', 0, '>');

    $price_from = 1;
    $price_to = 10000;
    $categories = '';
    if (!empty($_POST['price_from']) && is_numeric($_POST['price_from']) && !empty($_POST['price_to']) && is_numeric($_POST['price_to'])) {
    	$price_from = secure($_POST['price_from']);
    	$price_to = secure($_POST['price_to']);
    	$db->where('price', Array ($price_from, $price_to), 'BETWEEN');
    }
    if (!empty($_POST['category'])) {
    	$categories = explode(',', $_POST['category']);
        $db->where('cat_id', $categories, 'IN');
    }

    $db->orderBy('id', 'DESC');
    $products = $db->get(T_PRODUCTS, $limit);
    $array = array();
    if (!empty($products)) {
        $records = count($products);
        foreach ($products as $key => $value) {
            $product = GetProduct($value->id);
            if (!empty($product)) {
            	if (!empty($product) && !empty($product->related_song) && !empty($product->related_song->publisher)) {
                	unset($product->related_song->publisher->password);
				    unset($product->related_song->publisher->email_code);
                }
                if (!empty($product) && !empty($product->user_data)) {
                	unset($product->user_data->password);
				    unset($product->user_data->email_code);
                }
                $array[] = $product;
            }
        }
    }
    $data['status'] = 200;
    $data['data'] = $array;
}
if ($option == 'get_product_by_id') {
	if (!empty($_POST['product_id']) && is_numeric($_POST['product_id']) && $_POST['product_id'] > 0) {
		$id = secure($_POST['product_id']);
		$product = GetProduct($id);
        if (!empty($product)) {
        	if (!empty($product) && !empty($product->related_song) && !empty($product->related_song->publisher)) {
            	unset($product->related_song->publisher->password);
			    unset($product->related_song->publisher->email_code);
            }
            if (!empty($product) && !empty($product->user_data)) {
            	unset($product->user_data->password);
			    unset($product->user_data->email_code);
            }
            $data['status'] = 200;
		    $data['data'] = $product;
        }
        else{
        	$errors = 'product not found';
        }
	}
	else{
		$errors = 'product_id can not be empty';
	}
}
if ($option == 'get_my_products') {
    $user_id = $music->user->id;
    if (!empty($_POST['user_id']) && is_numeric($_POST['user_id']) && $_POST['user_id'] > 0){
        $user_id = secure($_POST['user_id']);
    }
	if (!empty($offset)) {
		$db->where('id', $offset, '<');
	}
	$array = array();
	$music->products = $db->where('user_id',$user_id)->where('active',1)->orderBy('id', 'DESC')->get(T_PRODUCTS,$limit,array('id'));
	if (!empty($music->products)) {
		foreach ($music->products as $key => $value) {
			$product = GetProduct($value->id);
			if (!empty($product)) {
            	if (!empty($product) && !empty($product->related_song) && !empty($product->related_song->publisher)) {
                	unset($product->related_song->publisher->password);
				    unset($product->related_song->publisher->email_code);
                }
                if (!empty($product) && !empty($product->user_data)) {
                	unset($product->user_data->password);
				    unset($product->user_data->email_code);
                }
                $array[] = $product;
            }
		}
	}
    $data['status'] = 200;
    $data['data'] = $array;
}
if ($option == 'reviews') {
	if (!empty($_POST['product_id']) && is_numeric($_POST['product_id']) && $_POST['product_id'] > 0) {
		$id = secure($_POST['product_id']);
		if (!empty($offset)) {
			$db->where('id', $offset, '<');
		}
		$array = array();
		$reviews = $db->where('product_id',$id)->orderBy('id', 'DESC')->get(T_REVIEW,$limit);
		if (!empty($reviews)) {
			foreach ($reviews as $key => $value) {
				$review = GetReview($value->id);
				$review->user_data = userData($review->user_id);
				unset($review->user_data->password);
				unset($review->user_data->email_code);
				$array[] = $review;
			}
		}
		$data['status'] = 200;
	    $data['data'] = $array;
	}
	else{
		$errors = 'product_id can not be empty';
	}
}
if ($option == 'orders') {
	if (!empty($offset)) {
		$db->where('id', $offset, '<');
	}
	$orders = $db->where('product_owner_id',$music->user->id)->orderBy('id', 'DESC')->groupBy('hash_id')->get(T_ORDERS,$limit);

	$array = array();
	if (!empty($orders)) {
		foreach ($orders as $key => $value) {
			$value->products = array();
			$products = $db->where('hash_id',$value->hash_id)->get(T_ORDERS);
			if (!empty($products)) {
				foreach ($products as $key2 => $value2) {
					$product = GetProduct($value2->product_id);
			        if (!empty($product)) {
			        	if (!empty($product) && !empty($product->related_song) && !empty($product->related_song->publisher)) {
			            	unset($product->related_song->publisher->password);
						    unset($product->related_song->publisher->email_code);
			            }
			            if (!empty($product) && !empty($product->user_data)) {
			            	unset($product->user_data->password);
						    unset($product->user_data->email_code);
			            }
			            $value->products[] = $product;
			        }
				}
			}
			$array[] = $value;
		}
	}
	$data['status'] = 200;
    $data['data'] = $array;
}
if ($option == 'get_order_by_id') {
	if (!empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0) {
		$id = secure($_POST['id']);
		$order = $db->where('id',$id)->getOne(T_ORDERS);
		if (!empty($order)) {
			$product = GetProduct($order->product_id);
	        if (!empty($product)) {
	        	if (!empty($product) && !empty($product->related_song) && !empty($product->related_song->publisher)) {
	            	unset($product->related_song->publisher->password);
				    unset($product->related_song->publisher->email_code);
	            }
	            if (!empty($product) && !empty($product->user_data)) {
	            	unset($product->user_data->password);
				    unset($product->user_data->email_code);
	            }
	            $order->products[] = $product;
	        }
	        $data['status'] = 200;
		    $data['data'] = $order;
		}
		else{
			$errors = 'order not found';
		}
	}
	else{
		$errors = 'id can not be empty';
	}
}
if ($option == 'delete_product') {
    $data["status"] = 400;
    if (!empty($_POST["id"]) && is_numeric($_POST["id"]) && $_POST["id"] > 0) {
        $product = GetProduct(Secure($_POST["id"]));
        if (!empty($product) && $product->user_id == $music->user->id) {
            foreach ($product->images as $key => $value) {
                @unlink($value["org_image"]);
                PT_DeleteFromToS3($value["org_image"]);
            }
            $db->where("id", $product->id)->delete(T_PRODUCTS);
            $db->where("product_id", $product->id)->delete(T_MEDIA);
            $db->where("product_id", $product->id)->delete(T_CARD);
            $db->where("product_id", $product->id)->delete(T_ORDERS);
            $db->where("product_id", $product->id)->delete(T_REVIEW);
            deleteActivity([
                "user_id" => $music->user->id,
                "type" => "created_product",
                "product_id" => $product->id,
            ]);
            $data["status"] = 200;
            $data["message"] = "Your product has been removed successfully";
        } else {
            $errors = 'Product not found';
        }
    }
    else{
        $errors = 'id can not be empty';
    }
}

if (!empty($errors)) {
	$data = array('status' => 400, 'error' => $errors);
    echo json_encode($data);
    exit();
}