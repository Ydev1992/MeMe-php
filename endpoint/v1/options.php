<?php
$config['blog_categories'] = blog_categories();
$config['products_categories'] = $music->products_categories;
echo json_encode(array('status' => 200, 'data'=>$config));
exit();
