<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

if (!$conn) {
    echo "DB connection failed\n";
    exit(1);
}

$username = 'admin';
$res = $conn->query("SELECT user_id, username, email, password_hash FROM users WHERE username = 'admin' LIMIT 1");
if (!$res) {
    echo "Query error: " . $conn->error . "\n";
    exit(1);
}

$row = $res->fetch_assoc();
if (!$row) {
    echo "Admin user not found\n";
    exit(0);
}

$hash = $row['password_hash'];

$tests = ['admin123', 'Admin123!', 'password', 'Admin@123'];
foreach ($tests as $pwd) {
    $ok = password_verify($pwd, $hash) ? 'MATCH' : 'NO MATCH';
    echo "Test '$pwd': $ok\n";
}

echo "Hash: $hash\n";