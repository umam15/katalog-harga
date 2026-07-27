<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
ensure_session();

$_SESSION = [];
session_destroy();

header('Location: ../index.php');
exit;
