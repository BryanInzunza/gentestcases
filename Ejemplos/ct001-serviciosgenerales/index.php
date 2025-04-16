<?php

require_once './vendor/oc/rac/Application.php';
require_once './vendor/autoload.php';

$app = new \Coppel\RAC\Application("PHP_APP_CONFIG");

$app->runTestmode();
