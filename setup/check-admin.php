<?php
require __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT user_id, username, email, role, status FROM users WHERE username='admin'");
if (!$res) { echo 'ERR: ' . $conn->error . "\n"; exit(1);} 
$row = $res->fetch_assoc();
var_export($row);