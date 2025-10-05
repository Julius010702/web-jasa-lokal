
<?php
// pages/auth/logout.php
require_once '../../config/config.php';

if (isLoggedIn()) {
    // Log activity
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity, ip_address, user_agent) VALUES (?, 'logout', ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
}

// Destroy session
session_destroy();

// Redirect to home
redirect('../../index.php');
?>