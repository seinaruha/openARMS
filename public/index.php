<?php
/**
 * openARMS - Entry Point
 * 
 * Main index file that redirects to login or dashboard
 * XAMPP compatible - place in htdocs/openARMS/
 */

// Start session
session_start();

// If user is logged in, redirect to dashboard
if (isset($_SESSION['user_id']) || isset($_SESSION['asrms_user'])) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: login.html');
}
exit;
?>
