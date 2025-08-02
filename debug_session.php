<?php
session_start();
echo "<h2>Session Debug Information</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Current URL: " . $_SERVER['REQUEST_URI'] . "</h3>";
echo "<h3>HTTP Method: " . $_SERVER['REQUEST_METHOD'] . "</h3>";
echo "<h3>User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "</h3>";
?>