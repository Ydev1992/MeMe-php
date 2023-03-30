<?php 
runPlugin("OnTermsPage");
$pages = ['about', 'privacy', 'terms', 'dmca' , 'faqs'];


if (empty($path['options'][1])) {
	header("Location: $site_url/404");
	exit();
}

if (!in_array($path['options'][1], $pages)) {
	header("Location: $site_url/404");
	exit();
}

$page_to_load = secure($path['options'][1]);

switch ($page_to_load) {
	case 'terms':
		$music->site_title = lang("Terms");
		break;
	case 'about':
		$music->site_title = lang("About Us");
		break;
	case 'privacy':
		$music->site_title = lang("Privacy Policy");
		break;
    case 'dmca':
        $music->site_title = lang("DMCA");
        break;
    case 'faqs':
        $music->site_title = lang("faqs");
        break;
}
$music->page_to_load = $page_to_load;
$terms_content = '';
if ($page_to_load != 'faqs') {
	$terms_pages = array('terms' => 'terms_of_use_page',
                         'about' => 'about_page',
                         'privacy' => 'privacy_policy_page',
                         'dmca' => 'dmca_terms_page');
	// $terms_content = htmlspecialchars_decode($db->where('type', $page_to_load)->getValue(T_TERMS, 'content'));
	if ($music->{$terms_pages[$page_to_load]} == 1) {
		$terms_content = htmlspecialchars_decode(lang($terms_pages[$page_to_load]));
	}
	else{
		header("Location: $site_url/404");
		exit();
	}
}
else{
	$faqs = $db->objectbuilder()->orderBy('id', 'DESC')->get(T_FAQS);
	foreach ($faqs as $key => $value) {
		$terms_content .= loadPage("terms/faqs_list",array('ID' => $value->id,
	                                                       'QUESTION' => $value->question,
	                                                       'ANSWER' => $value->answer));
	}
}
	

$music->site_description = $music->config->description;
$music->site_pagename = "terms";

$music->site_content = loadPage("terms/content", ['terms_header' => loadPage('terms/header'), 'terms_content' => $terms_content]);