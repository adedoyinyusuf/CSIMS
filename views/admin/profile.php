<?php
/**
 * Profile Redirect
 * 
 * This file redirects to the correct admin profile page
 * to handle legacy URLs or incorrect direct access
 */

// Redirect to the correct admin profile page
header('Location: admin_profile.php');
exit;
?>