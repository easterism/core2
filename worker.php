#!/usr/bin/env php
<?php

if (!function_exists("gearman_version")) {
    print "Gearman not installed.";
    die;
}
print gearman_version() . PHP_EOL;

$autoload = __DIR__ . "/vendor/autoload.php";
if (!file_exists($autoload)) {
    \Core2\Error::Exception("Composer autoload is missing.");
}
require_once($autoload);
$_SERVER['SERVER_NAME'] = '_';
$gm = __DIR__ . "/inc/WorkerManager.php";
if (!file_exists($gm)) {
    print $gm . " not exists.";
    die;
}
//define("DOC_ROOT", "/var/www/autoparts24.by/htdocs");
require_once __DIR__ . "/inc/WorkerManager.php";

// Create Worker Object
$wm = new Core2\WorkerManager();