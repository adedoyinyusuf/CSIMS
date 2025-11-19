<?php
require_once '../config/config.php';
require_once '../config/member_auth_check.php';

$session = Session::getInstance();
$session->logout();

header('Location: member_login.php');
exit();
?>