<?php
/**
 * AJAX endpoint to get notifications
 * Grama Voice - Village Governance Platform
 */

header('Content-Type: application/json');

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

// Check if user is logged in
if (!isUserLoggedIn() && !isPanchayatAdminLoggedIn() && !isSuperAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    $user_id = null;
    $user_type = null;
    
    if (isUserLoggedIn()) {
        $user_id = $_SESSION['user_id'];
        $user_type = 'user';
    } elseif (isPanchayatAdminLoggedIn()) {
        $user_id = $_SESSION['admin_id'];
        $user_type = 'admin';
    } elseif (isSuperAdminLoggedIn()) {
        $user_id = $_SESSION['super_admin_id'];
        $user_type = 'super_admin';
    }
    
    if ($user_id && $user_type) {
        $unread_count = getUnreadNotificationCount($user_id, $user_type);
        echo json_encode(['success' => true, 'unread_count' => $unread_count]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    
} catch (Exception $e) {
    logError("Error in get_notifications.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
