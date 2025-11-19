<?php
require __DIR__ . '/../config/database.php';
$res = $conn->query("SHOW TABLES LIKE 'users'");
if (!$res) {
    echo 'ERR: ' . $conn->error . "\n";
    exit(1);
}
$row = $res->fetch_row();
var_export($row);