<?php
// Canonical redirect to the new savings details page
require_once '../../config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$target = BASE_URL . '/views/admin/savings_details.php' . ($id ? ('?id=' . $id) : '');
header('Location: ' . $target);
exit();