<?php
if (IS_LOGGED == false) {
    exit("You ain't logged in!");
}

$data["status"] = 400;
if ($option == "search") {
    if (!empty($_POST["word"])) {
        $search_keyword = secure($_POST["word"]);
        $results = $db
            ->where("(title LIKE '%$search_keyword%')")
            ->where("user_id", $music->user->id)
            ->orderBy("id", "DESC")
            ->get(T_SONGS, 10);
        if (!empty($results)) {
            $html = "";
            foreach ($results as $key => $value) {
                $html .=
                    '<div class="serach_trend" onclick="AddRelated(this,' .
                    $value->id .
                    ')"><a href="javascript:void(0)">' .
                    $value->title .
                    "</a></div>";
            }
            $data["status"] = 200;
            $data["html"] = $html;
        }
    } else {
        $results = $db
            ->where("user_id", $music->user->id)
            ->orderBy("id", "DESC")
            ->get(T_SONGS);
        if (!empty($results)) {
            $html = "";
            foreach ($results as $key => $value) {
                $html .=
                    '<div class="serach_trend" onclick="AddRelated(this,' .
                    $value->id .
                    ')"><a href="javascript:void(0)">' .
                    $value->title .
                    "</a></div>";
            }
            $data["status"] = 200;
            $data["html"] = $html;
        }
    }
}
if ($option == "create") {
    runPlugin('PreProductCreate', $_REQUEST);
    if (
        !empty($_POST["title"]) &&
        !empty($_POST["desc"]) &&
        !empty($_POST["tags"]) &&
        !empty($_POST["price"]) &&
        is_numeric($_POST["price"]) &&
        $_POST["price"] > 0 &&
        !empty($_POST["units"]) &&
        is_numeric($_POST["units"]) &&
        $_POST["units"] > 0 &&
        !empty($_POST["related"]) &&
        !empty($_FILES["image"]) &&
        !empty($_POST["category"]) &&
        in_array($_POST["category"], array_keys($music->products_categories))
    ) {
        if (strlen($_POST["title"]) < 4) {
            $data["message"] = lang("Title is too short");
        } elseif (strlen($_POST["desc"]) < 10) {
            $data["message"] = lang("Description is too short");
        }
        $related_song = songData(secure($_POST["related"]));
        if (
            empty($related_song) ||
            $related_song->user_id != $music->user->id
        ) {
            $data["message"] = lang("Please select a song");
        }
        $files = [];
        if ($_FILES["image"]) {
            foreach ($_FILES["image"]["name"] as $key => $value) {
                $file_info = [
                    "file" => $_FILES["image"]["tmp_name"][$key],
                    "size" => $_FILES["image"]["size"][$key],
                    "name" => $_FILES["image"]["name"][$key],
                    "type" => $_FILES["image"]["type"][$key],
                ];
                $file_upload = ShareFile($file_info);
                if (!empty($file_upload) && !empty($file_upload["filename"])) {
                    $files[] = $file_upload["filename"];
                }
            }
        }
        if (empty($files)) {
            $data["message"] = lang("Please select a valid image");
        }
        if (empty($data["message"])) {
            $insertData = [
                "title" => secure($_POST["title"]),
                "desc" => secure($_POST["desc"]),
                "price" => secure($_POST["price"]),
                "units" => secure($_POST["units"]),
                "related_song" => secure($_POST["related"]),
                "cat_id" => secure($_POST["category"]),
                "user_id" => $music->user->id,
                "active" =>
                    $music->config->store_review_system == "off" ? 1 : 0,
                "time" => time(),
                "tags" => secure(str_replace("#", "", $_POST["tags"])),
            ];
            $id = $db->insert(T_PRODUCTS, $insertData);
            if (!empty($id)) {
                runPlugin('AfterProductCreated', ['data' => $insertData, 'files' => $files]);
                $create_activity = createActivity([
                    "user_id" => $music->user->id,
                    "type" => "created_product",
                    "product_id" => $id,
                ]);
                $db->where("id", $id)->update(T_PRODUCTS, [
                    "hash_id" => uniqid($id),
                ]);
                foreach ($files as $key => $value) {
                    $db->insert(T_MEDIA, [
                        "product_id" => $id,
                        "image" => $value,
                        "time" => time(),
                    ]);
                }
                $data["status"] = 200;
                if ($music->config->store_review_system == "off") {
                    $data["message"] = lang(
                        "Your product has been published successfully"
                    );
                } else {
                    $data["message"] = lang("Your product is under review");
                }
            } else {
                $data["message"] = lang("Error 500 internal server error!");
            }
        }
    } else {
        if (empty($_POST["title"])) {
            $data["message"] = lang("Product title can not be empty");
        } elseif (empty($_POST["desc"])) {
            $data["message"] = lang("Product description can not be empty");
        } elseif (empty($_POST["tags"])) {
            $data["message"] = lang("Product tags can not be empty");
        } elseif (empty($_POST["price"])) {
            $data["message"] = lang("Product price can not be empty");
        } elseif (empty($_POST["units"])) {
            $data["message"] = lang("Product units can not be empty");
        } elseif (empty($_POST["related"])) {
            $data["message"] = lang("Product related song can not be empty");
        } elseif (empty($_POST["category"])) {
            $data["message"] = lang("Product category can not be empty");
        } elseif (empty($_FILES["image"])) {
            $data["message"] = lang("Product image can not be empty");
        } else {
            $data["message"] = lang("Please check the details");
        }
    }
}
if ($option == "delete") {
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
            runPlugin('AfterProductDeleted', ['id' => $product->id]);
            deleteActivity([
                "user_id" => $music->user->id,
                "type" => "created_product",
                "product_id" => $product->id,
            ]);
        } else {
            $data["message"] = lang("Product not found");
        }
        $data["status"] = 200;
    }
}
if ($option == "edit") {
    runPlugin('PreProductUpdate', $_REQUEST);
    if (
        !empty($_POST["title"]) &&
        !empty($_POST["desc"]) &&
        !empty($_POST["tags"]) &&
        !empty($_POST["id"]) &&
        is_numeric($_POST["id"]) &&
        $_POST["id"] > 0 &&
        !empty($_POST["price"]) &&
        is_numeric($_POST["price"]) &&
        $_POST["price"] > 0 &&
        !empty($_POST["units"]) &&
        is_numeric($_POST["units"]) &&
        $_POST["units"] > 0 &&
        !empty($_POST["related"]) &&
        !empty($_POST["category"]) &&
        in_array($_POST["category"], array_keys($music->products_categories))
    ) {
        $product = GetProduct(secure($_POST["id"]));
        if (
            empty($product) ||
            ($product->user_id != $music->user->id && !isAdmin())
        ) {
            $data["message"] = lang("Please check the details");
        }
        if (strlen($_POST["title"]) < 4) {
            $data["message"] = lang("Title is too short");
        } elseif (strlen($_POST["desc"]) < 10) {
            $data["message"] = lang("Description is too short");
        }
        $related_song = songData(secure($_POST["related"]));
        if (
            empty($related_song) ||
            $related_song->user_id != $music->user->id
        ) {
            $data["message"] = lang("Please select a song");
        }
        $files = [];
        if (!empty($_FILES["image"])) {
            foreach ($_FILES["image"]["name"] as $key => $value) {
                $file_info = [
                    "file" => $_FILES["image"]["tmp_name"][$key],
                    "size" => $_FILES["image"]["size"][$key],
                    "name" => $_FILES["image"]["name"][$key],
                    "type" => $_FILES["image"]["type"][$key],
                ];
                $file_upload = ShareFile($file_info);
                if (!empty($file_upload) && !empty($file_upload["filename"])) {
                    $files[] = $file_upload["filename"];
                }
            }
        }
        if (empty($data["message"])) {
            $insertData = [
                "title" => secure($_POST["title"]),
                "desc" => secure($_POST["desc"]),
                "price" => secure($_POST["price"]),
                "units" => secure($_POST["units"]),
                "cat_id" => secure($_POST["category"]),
                "related_song" => secure($_POST["related"]),
                "tags" => secure(str_replace("#", "", $_POST["tags"])),
            ];
            $db->where("id", $product->id)->update(T_PRODUCTS, $insertData);
            if (!empty($product->id)) {
                runPlugin('AfterProductUpdated', ['data' => $insertData, 'files' => $files]);
                if (!empty($files)) {
                    foreach ($product->images as $key => $value) {
                        @unlink($value["org_image"]);
                        PT_DeleteFromToS3($value["org_image"]);
                    }
                    $db->where("product_id", $product->id)->delete(T_MEDIA);
                    foreach ($files as $key => $value) {
                        $db->insert(T_MEDIA, [
                            "product_id" => $product->id,
                            "image" => $value,
                            "time" => time(),
                        ]);
                    }
                }
                $data["status"] = 200;
                $data["message"] = lang(
                    "Your product has been edited successfully"
                );
            } else {
                $data["message"] = lang("Error 500 internal server error!");
            }
        }
    } else {
        if (empty($_POST["title"])) {
            $data["message"] = lang("Product title can not be empty");
        } elseif (empty($_POST["desc"])) {
            $data["message"] = lang("Product description can not be empty");
        } elseif (empty($_POST["tags"])) {
            $data["message"] = lang("Product tags can not be empty");
        } elseif (empty($_POST["price"])) {
            $data["message"] = lang("Product price can not be empty");
        } elseif (empty($_POST["units"])) {
            $data["message"] = lang("Product units can not be empty");
        } elseif (empty($_POST["related"])) {
            $data["message"] = lang("Product related song can not be empty");
        } elseif (empty($_POST["category"])) {
            $data["message"] = lang("Product category can not be empty");
        } else {
            $data["message"] = lang("Please check the details");
        }
    }
}
if ($option == "product_search") {
    $category =
        isset($_GET["category"]) &&
        in_array($_GET["category"], array_keys($music->products_categories))
            ? $_GET["category"]
            : "";
    $price_from =
        isset($_GET["price_from"]) && is_numeric($_GET["price_from"])
            ? $_GET["price_from"]
            : 1;
    $price_to =
        isset($_GET["price_to"]) && is_numeric($_GET["price_from"])
            ? $_GET["price_to"]
            : 10000;
    $text = "";
    if (!empty($_GET["filter_search_keyword"])) {
        $search_keyword = secure($_GET["filter_search_keyword"]);
        $text = " AND (`title` LIKE '%$search_keyword%' OR `desc` LIKE '%$search_keyword%') ";
    }
    if (!empty($_GET["tag"])) {
        $tag = secure($_GET["tag"]);
        $text = " AND (`tags` LIKE '%$tag%') ";
    }

    if (empty($price_from) || empty($price_to)) {
        exit("Empty parameters, hmm?");
    }
    $and = [];
    $sql = "SELECT * FROM `" . T_PRODUCTS . "` WHERE ";
    $and[] = " `price` BETWEEN " . $price_from . " AND " . $price_to . $text;
    if (is_array($category) && !empty($category)) {
        $and[] = " `cat_id` IN ('" . implode("','", $category) . "') ";
    }

    $sql .= implode(" AND ", $and) . " ORDER BY `id` DESC LIMIT 10";
    $html_list = "";
    $products = $db->rawQuery($sql);
    if (!empty($products)) {
        $records = count($products);
        $html_list = "";
        foreach ($products as $key => $value) {
            $music->product = GetProduct($value->id);
            if (!empty($music->product)) {
                $html_list .= loadPage("store/product_list", [
                    "id" => $music->product->id,
                    "url" => $music->product->url,
                    "data_load" => $music->product->data_load,
                    "image" => $music->product->images[0]["image"],
                    "title" => $music->product->title,
                    "rating" => $music->product->rating,
                    "f_price" => $music->product->formatted_price,
                ]);
            }
        }
    }
    $data["html"] = $html_list;
    $data["status"] = 200;
}
if ($option == "buy") {
    $data["status"] = 400;
    if (
        !empty($_POST["address_id"]) &&
        is_numeric($_POST["address_id"]) &&
        $_POST["address_id"] > 0
    ) {
        $address = $db
            ->where("id", secure($_POST["address_id"]))
            ->where("user_id", $music->user->id)
            ->getOne(T_ADDRESS);
        if (!empty($address)) {
            $music->items = $db
                ->where("user_id", $music->user->id)
                ->get(T_CARD);
            $html = "";
            $total = 0;
            $insert = [];
            $main_product = "";

            if (!empty($music->items)) {
                foreach ($music->items as $key => $music->item) {
                    $product = $main_product = GetProduct(
                        $music->item->product_id
                    );
                    if ($music->item->units <= $product->units) {
                        $total += $product->price * $music->item->units;
                        if (!in_array($product->user_id, array_keys($insert))) {
                            $insert[$product->user_id] = [];
                            $insert[$product->user_id][] = [
                                "product_id" => $product->id,
                                "price" => $product->price,
                                "units" => $music->item->units,
                            ];
                        } else {
                            $insert[$product->user_id][] = [
                                "product_id" => $product->id,
                                "price" => $product->price,
                                "units" => $music->item->units,
                            ];
                        }
                    } else {
                        $data["message"] = lang(
                            "Some products don't have enough of units"
                        );
                        header("Content-Type: application/json");
                        echo json_encode($data);
                        exit();
                    }
                }
                if ($music->user->org_wallet < $total) {
                    $data["message"] =
                        lang("You don't have enough wallet") .
                        " <a href='" .
                        getLink(
                            "settings/" . $music->user->username . "/wallet"
                        ) .
                        "'>" .
                        lang("Please top up your wallet") .
                        "</a>";
                    header("Content-Type: application/json");
                    echo json_encode($data);
                    exit();
                }

                if (!empty($insert)) {
                    foreach ($insert as $key => $value) {
                        $hash_id = rand(11111, 999999999);
                        $hash_found = $db
                            ->where("hash_id", $hash_id)
                            ->getValue(T_ORDERS, "COUNT(*)");
                        if (!empty($hash_found) && $hash_found > 0) {
                            $hash_id = rand(11111, 999999999);
                        }
                        $total = 0;
                        $total_commission = 0;
                        $total_final_price = 0;

                        foreach ($value as $key2 => $value2) {
                            $db->where("id", $value2["product_id"])->update(
                                T_PRODUCTS,
                                ["units" => $db->dec($value2["units"])]
                            );

                            $store_commission = 0;
                            if (!empty($music->config->store_commission)) {
                                $store_commission = round(
                                    ($music->config->store_commission *
                                        ($value2["price"] * $value2["units"])) /
                                        100,
                                    2
                                );
                            }

                            $total += $value2["price"] * $value2["units"];
                            $total_commission += $store_commission;
                            $total_final_price +=
                                $value2["price"] * $value2["units"] -
                                $store_commission;
                            $db->where("id", $music->user->id)->update(
                                T_USERS,
                                ["wallet" => $db->dec($total)]
                            );
                            $orderData = [
                                "user_id" => $music->user->id,
                                "product_owner_id" => $key,
                                "product_id" => $value2["product_id"],
                                "price" => $value2["price"] * $value2["units"],
                                "commission" => $store_commission,
                                "final_price" =>
                                    $value2["price"] * $value2["units"] -
                                    $store_commission,
                                "hash_id" => $hash_id,
                                "units" => $value2["units"],
                                "status" => "placed",
                                "address_id" => $address->id,
                                "time" => time(),
                            ];
                            $db->insert(T_ORDERS, $orderData);
                            runPlugin('AfterProductOrder', $orderData);
                        }
                        $db->insert(T_PURCHAES, [
                            "user_id" => $music->user->id,
                            "order_hash_id" => $hash_id,
                            "price" => $total,
                            "title" =>
                                !empty($main_product) &&
                                !empty($main_product->title)
                                    ? $main_product->title
                                    : "",
                            "commission" => $total_commission,
                            "final_price" => $total_final_price,
                            "time" => time(),
                        ]);
                        $db->where('id', $key)->update(T_USERS, array('balance' => $db->inc($total_final_price)));
                        $create_notification = createNotification([
                            "notifier_id" => $music->user->id,
                            "recipient_id" => $key,
                            "type" => "new_orders",
                            "url" => Secure("orders"),
                        ]);
                    }
                    $db->where("user_id", $music->user->id)->delete(T_CARD);
                    $data["status"] = 200;
                    $data["message"] = lang(
                        "Your order has been placed successfully"
                    );
                } else {
                    $data["message"] = lang("Error 500 internal server error!");
                }
            } else {
                $data["message"] = lang("Card is empty");
            }
        } else {
            $data["message"] = lang("Address not found");
        }
    } else {
        $data["message"] = lang("Address can not be empty");
    }
}
if ($option == "add_cart") {
    if (
        !empty($_POST["product_id"]) &&
        is_numeric($_POST["product_id"]) &&
        $_POST["product_id"] > 0
    ) {
        $is_added = $db
            ->where("product_id", secure($_POST["product_id"]))
            ->where("user_id", $music->user->id)
            ->getOne(T_CARD);
        if (!empty($is_added)) {
            $db->where("product_id", secure($_POST["product_id"]))
                ->where("user_id", $music->user->id)
                ->delete(T_CARD);
            $data["status"] = 200;
            $data["type"] = "removed";
        } else {
            $qty = 1;
            if (
                !empty($_POST["qty"]) &&
                is_numeric($_POST["qty"]) &&
                $_POST["qty"] > 0
            ) {
                $qty = secure($_POST["qty"]);
            }
            $orderData = [
                "user_id" => $music->user->id,
                "units" => $qty,
                "product_id" => secure($_POST["product_id"]),
            ];
            $db->insert(T_CARD, $orderData);
            runPlugin('AfterProductAddedToCart', $orderData);
            $data["status"] = 200;
            $data["type"] = "added";
        }
        $data["count"] = $db
            ->where("user_id", $music->user->id)
            ->getValue(T_CARD, "COUNT(*)");
        if ($data["count"] < 1) {
            $data["count"] = "";
        }
    } else {
        $data["message"] = lang("Please check the details");
    }
}
if ($option == "remove_cart") {
    if (
        !empty($_POST["product_id"]) &&
        is_numeric($_POST["product_id"]) &&
        $_POST["product_id"] > 0
    ) {
        $is_added = $db
            ->where("product_id", secure($_POST["product_id"]))
            ->where("user_id", $music->user->id)
            ->getOne(T_CARD);
        if (!empty($is_added)) {
            runPlugin('AfterProductRemovedFromCart', ["id" => $_POST["product_id"]]);
            $db->where("product_id", secure($_POST["product_id"]))
                ->where("user_id", $music->user->id)
                ->delete(T_CARD);
            $data["status"] = 200;
            $data["count"] = $db
                ->where("user_id", $music->user->id)
                ->getValue(T_CARD, "COUNT(*)");
            if ($data["count"] < 1) {
                $data["count"] = "";
            }
        }
    } else {
        $data["message"] = lang("Please check the details");
    }
}
if ($option == "get_cart") {
    $html = "";
    $carts = $db
        ->where("user_id", $music->user->id)
        ->orderBy("id", "DESC")
        ->get(T_CARD, 5);
    if (!empty($carts)) {
        foreach ($carts as $key => $music->cart) {
            $music->cart->product = GetProduct($music->cart->product_id);
            $html .= loadPage("header/cart_list");
        }
    }
    $data["status"] = 200;
    $data["html"] = $html;
}
if ($option == "check_wallet") {
    $music->items = $db->where("user_id", $music->user->id)->get(T_CARD);
    $data["topup"] = "show";
    $total = 0;
    if (!empty($music->items)) {
        foreach ($music->items as $key => $music->item) {
            $music->item->product = GetProduct($music->item->product_id);
            $total += $music->item->product->price;
        }
        $data["topup"] = $music->user->org_wallet < $total ? "show" : "hide";
    }
    $data["status"] = 200;
}
if ($option == "change_qty") {
    if (
        !empty($_POST["product_id"]) &&
        is_numeric($_POST["product_id"]) &&
        $_POST["product_id"] > 0 &&
        !empty($_POST["qty"]) &&
        is_numeric($_POST["qty"]) &&
        $_POST["qty"] > 0
    ) {
        $product = GetProduct(secure($_POST["product_id"]));
        $qty = secure($_POST["qty"]);
        if ($product->units >= $qty) {
            $db->where("product_id", $product->id)
                ->where("user_id", $music->user->id)
                ->update(T_CARD, ["units" => $qty]);
            $data["status"] = 200;
        }
    } else {
        $data["message"] = lang("Please check the details");
    }
}
if ($option == "change_status") {
    if (!empty($_POST["hash_order"]) && !empty($_POST["status"])) {
        $hash_id = secure($_POST["hash_order"]);
        $status = secure($_POST["status"]);
        $order = $db->where("hash_id", $hash_id)->getOne(T_ORDERS);
        if (!empty($order)) {
            $types = [];
            if ($order->product_owner_id == $music->user->id) {
                if ($order->status == "placed") {
                    $types = ["canceled", "accepted", "packed", "shipped"];
                }
                if ($order->status == "accepted") {
                    $types = ["packed", "shipped"];
                }
                if ($order->status == "packed") {
                    $types = ["shipped"];
                }
                if ($order->status == "shipped") {
                    $types = ["delivered"];
                }
            } elseif ($order->user_id == $music->user->id) {
                if ($order->status == "shipped") {
                    $types = ["delivered"];
                }
            }
            if (in_array($status, $types)) {
                $db->where("hash_id", $hash_id)->update(T_ORDERS, [
                    "status" => $status,
                ]);
                runPlugin('AfterProductStatusUpdated', ["status" => $status, "hash_id" => $hash_id]);
                if ($order->product_owner_id == $music->user->id) {
                    $create_notification = createNotification([
                        "notifier_id" => $music->user->id,
                        "recipient_id" => $order->user_id,
                        "type" => "status_changed",
                        "url" => Secure("customer_order/" . $hash_id),
                    ]);
                }

                $data["status"] = 200;
            }
        } else {
            $data["message"] = lang("Order not found");
        }
    } else {
        $data["message"] = lang("Please check the details");
    }
}
if ($option == "tracking") {
    if (
        !empty($_POST["tracking_url"]) &&
        !empty($_POST["tracking_id"]) &&
        !empty($_POST["hash"]) &&
        pt_is_url($_POST["tracking_url"])
    ) {
        $hash_id = secure($_POST["hash"]);
        $tracking_url = secure($_POST["tracking_url"]);
        $tracking_id = secure($_POST["tracking_id"]);
        $order = $db
            ->where("hash_id", $hash_id)
            ->where("product_owner_id", $music->user->id)
            ->getOne(T_ORDERS);
        if (!empty($order)) {
            $db->where("hash_id", $hash_id)->update(T_ORDERS, [
                "tracking_url" => $tracking_url,
                "tracking_id" => $tracking_id,
            ]);
            $create_notification = createNotification([
                "notifier_id" => $music->user->id,
                "recipient_id" => $order->user_id,
                "type" => "added_tracking",
                "url" => Secure("customer_order/" . $hash_id),
            ]);
            $data["status"] = 200;
            $data["message"] = lang(
                "Tracking info has been saved successfully"
            );
        } else {
            $data["message"] = lang("Order not found");
        }
    } else {
        if (empty($_POST["tracking_url"])) {
            $data["message"] = lang("Tracking url can not be empty");
        } elseif (empty($_POST["tracking_id"])) {
            $data["message"] = lang("Tracking number can not be empty");
        } elseif (!pt_is_url($_POST["tracking_url"])) {
            $data["message"] = lang("Please enter a valid url");
        } else {
            $data["message"] = lang("Please check the details");
        }
    }
}
if ($option == "review") {
    if (
        !empty($_POST["rating"]) &&
        in_array($_POST["rating"], [1, 2, 3, 4, 5]) &&
        !empty($_POST["review"]) &&
        !empty($_POST["product_id"]) &&
        is_numeric($_POST["product_id"]) &&
        $_POST["product_id"] > 0
    ) {
        $product_id = secure($_POST["product_id"]);
        $rating = secure($_POST["rating"]);
        $review = secure($_POST["review"]);
        $files = [];
        if (!empty($_FILES["images"])) {
            foreach ($_FILES["images"]["name"] as $key => $value) {
                $file_info = [
                    "file" => $_FILES["images"]["tmp_name"][$key],
                    "size" => $_FILES["images"]["size"][$key],
                    "name" => $_FILES["images"]["name"][$key],
                    "type" => $_FILES["images"]["type"][$key],
                ];
                $file_upload = ShareFile($file_info);
                if (!empty($file_upload) && !empty($file_upload["filename"])) {
                    $files[] = $file_upload["filename"];
                }
            }
        }
        $insertData =  [
            "user_id" => $music->user->id,
            "product_id" => $product_id,
            "review" => $review,
            "time" => time(),
            "star" => $rating,
        ];
        $id = $db->insert(T_REVIEW,);
        if (!empty($id)) {
            runPlugin('AfterProductReview', $insertData);
            if (!empty($files)) {
                foreach ($files as $key => $value) {
                    $db->insert(T_MEDIA, [
                        "review_id" => $id,
                        "image" => $value,
                        "time" => time(),
                    ]);
                }
            }

            $data["status"] = 200;
            $data["message"] = lang("Review has been sent successfully");
        } else {
            $data["message"] = lang("Error 500 internal server error!");
        }
    } else {
        if (empty($_POST["rating"])) {
            $data["message"] = lang("rating can not be empty");
        } elseif (empty($_POST["review"])) {
            $data["message"] = lang("review can not be empty");
        } else {
            $data["message"] = lang("Please check the details");
        }
    }
}
if ($option == "refund") {
    if (!empty($_POST["hash_order"]) && !empty($_POST["message"])) {
        $hash = secure($_POST["hash_order"]);
        $message = secure($_POST["message"]);
        $order = $db
            ->where("hash_id", $hash)
            ->where("user_id", $music->user->id)
            ->getOne(T_ORDERS);
        if (!empty($order)) {
            $insertData = [
                "order_hash_id" => $hash,
                "user_id" => $music->user->id,
                "message" => $message,
                "time" => time(),
            ];
            $db->insert(T_REFUND, $insertData);

            runPlugin('AfterProductRefundCreated', $insertData);

            $notif_data = [
                "recipient_id" => 0,
                "type" => "refund",
                "admin" => 1,
                "time" => time(),
            ];
            $db->insert(T_NOTIFICATION, $notif_data);
            $data["status"] = 200;
            $data["message"] = lang("Your request is under review");
        } else {
            $data["message"] = lang("Order not found");
        }
    } else {
        if (empty($_POST["message"])) {
            $data["message"] = lang("Please explain the reason");
        } else {
            $data["message"] = lang("Please check the details");
        }
    }
}
if ($option == "download") {
    if (!empty($_POST["id"])) {
        $id = secure($_POST["id"]);
        $music->orders = $db
            ->where("hash_id", $id)
            ->get(T_ORDERS);
        if (!empty($music->orders) && !empty($music->orders[0]) && ($music->orders[0]->user_id == $music->user->id || $music->orders[0]->product_owner_id == $music->user->id)) {
            $music->total = 0;
            $music->total_commission = 0;
            $music->total_final_price = 0;
            $music->address_id = 0;
            $music->user_id = 0;
            $music->html = "";
            $music->main_product = "";
            foreach ($music->orders as $key => $music->order) {
                $music->order->product = GetProduct(
                    $music->order->product_id
                );
                if (empty($music->main_product)) {
                    $music->main_product = $music->order->product;
                    $music->main_product->in_title = url_slug(
                        $music->main_product->title,
                        [
                            "delimiter" => "-",
                            "limit" => 100,
                            "lowercase" => true,
                            "replacements" => [
                                "/\b(an)\b/i" => "a",
                                "/\b(example)\b/i" => "Test",
                            ],
                        ]
                    );
                }
                $music->total += $music->order->price;
                $music->total_commission += $music->order->commission;
                $music->total_final_price += $music->order->final_price;
                $music->address_id = $music->order->address_id;
                $music->user_id = $music->order->product->user_id;
                $music->html .=
                    '<tr><td><h6 class="mb-0">' .
                    $music->order->product->title .
                    "</h6></td><td>" .
                    number_format($music->order->price / $music->order->units) .
                    "</td><td>" .
                    $music->order->units .
                    '</td><td><span class="font-weight-semibold">' .
                    $music->config->currency_symbol .
                    number_format($music->order->price) .
                    "</span></td></tr>";
            }
            $music->product_owner = userData($music->user_id);
            $music->address = $db
                ->where("id", $music->address_id)
                ->getOne(T_ADDRESS);
            $music->total = number_format($music->total);
            $music->total_commission = number_format($music->total_commission);
            $music->total_final_price = number_format($music->total_final_price);
            $music->order_hash_id = $id;

            $html = loadPage("pdf/invoice");
            $data["status"] = 200;
            $data["html"] = $html;
        } else {
            $data["message"] = lang("Order not found");
        }
    } else {
        $data["message"] = lang("id can not be empty");
    }
}
