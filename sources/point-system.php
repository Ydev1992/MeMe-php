<?php
runPlugin("OnPointsPage");
if($music->config->point_system == 'off'){
    header("Location: $site_url/404");
    exit();
}
if (!$music->config->can_use_point_system) {
    header("Location: $site_url/404");
    exit();
}
$music->site_title = lang("Points System");
$music->site_description = $music->config->description;
$music->site_pagename = "payment_system";
$music->site_content = loadPage("payment_system/content");
