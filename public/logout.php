<?php
define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/AuthController.php';
$auth = new AuthController();
$auth->logout();
