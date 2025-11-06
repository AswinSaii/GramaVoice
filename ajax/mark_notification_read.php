<?php
/**
 * AJAX endpoint to mark notification as read
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['notification_id'])) {
        echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        exit();
    }
    
    $notification_id = (int)$input['notification_id'];
    
    // Get current user information
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
        if (markNotificationAsRead($notification_id, $user_id, $user_type)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read or notification not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    
} catch (Exception $e) {
    logError("Error in mark_notification_read.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
