<?php
runPlugin("OnTwoAuthLoginPage");
//http://localhost/deepsound.com/unusual-login?type=two-factor
if (empty($_SESSION['code_id'])) {
    header("Location: $site_url");
    exit();
}
if (!empty($_GET['type'])) {
	if ($_GET['type'] == 'two-factor') {
	} else {
        header("Location: $site_url");
        exit();
	}
}
$music->site_title = lang("Unusual login");
$music->site_description = '';
$music->site_pagename = "unusual";
$music->site_content = loadPage("home/unusual-login");
