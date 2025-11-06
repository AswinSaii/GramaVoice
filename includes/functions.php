<?php
/**
 * Session Management and Security Functions
 * Grama Voice - Village Governance Platform
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    }
}

// Include database connection
require_once __DIR__ . '/../config/db.php';

/**
 * Check if user is logged in
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if panchayat admin is logged in
 */
function isPanchayatAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Check if super admin is logged in
 */
function isSuperAdminLoggedIn() {
    return isset($_SESSION['super_admin_id']) && !empty($_SESSION['super_admin_id']);
}

/**
 * Redirect to login if not authenticated
 */
function requireUserLogin() {
    if (!isUserLoggedIn()) {
        header('Location: ../auth/login.php');
        exit();
    }
}

function requirePanchayatAdminLogin() {
    if (!isPanchayatAdminLoggedIn()) {
        header('Location: ../auth/admin_login.php');
        exit();
    }
}

function requireSuperAdminLogin() {
    if (!isSuperAdminLoggedIn()) {
        header('Location: ../auth/super_admin_login.php');
        exit();
    }
}

/**
 * Generate random OTP
 */
function generateOTP($length = 4) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate phone number
 */
function validatePhoneNumber($phone) {
    return preg_match('/^[0-9]{10}$/', $phone);
}

/**
 * Handle and log exceptions
 */
function handleException($e, $context = '') {
    $message = $context ? "$context: " . $e->getMessage() : $e->getMessage();
    logError($message, $e->getFile(), $e->getLine());
    return $message;
}

/**
 * Upload file securely
 */
function uploadFile($file, $uploadDir = '../uploads/', $subDir = '') {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        logError("Invalid file type attempted: " . $file['type']);
        return ['success' => false, 'message' => 'Invalid file type. Only images are allowed.'];
    }
    
    if ($file['size'] > $maxSize) {
        logError("File size too large: " . $file['size'] . " bytes");
        return ['success' => false, 'message' => 'File size too large. Maximum 5MB allowed.'];
    }
    
    // Determine upload directory based on subdirectory
    $fullUploadDir = $uploadDir;
    if ($subDir) {
        $fullUploadDir = $uploadDir . $subDir . '/';
    }
    
    if (!is_dir($fullUploadDir)) {
        if (!mkdir($fullUploadDir, 0755, true)) {
            logError("Failed to create upload directory: " . $fullUploadDir);
            return ['success' => false, 'message' => 'Failed to create upload directory.'];
        }
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $fullUploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    } else {
        logError("Failed to move uploaded file: " . $file['name'] . " to " . $filepath);
        return ['success' => false, 'message' => 'Failed to upload file.'];
    }
}

/**
 * Set flash message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

/**
 * Get and clear flash message
 */
function getFlashMessage($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

/**
 * Format date for display
 */
function formatDate($date) {
    return date('d M Y, h:i A', strtotime($date));
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending':
            return 'bg-warning';
        case 'In Progress':
            return 'bg-info';
        case 'Resolved':
            return 'bg-success';
        default:
            return 'bg-secondary';
    }
}

/**
 * Award achievement to user
 */
function awardUserAchievement($user_id, $achievement_type, $achievement_name, $description) {
    try {
        $db = getDB();
        
        // Check if user already has this achievement
        $stmt = $db->prepare("SELECT id FROM user_achievements WHERE user_id = ? AND achievement_type = ?");
        $stmt->bind_param("is", $user_id, $achievement_type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Award new achievement
            $stmt = $db->prepare("INSERT INTO user_achievements (user_id, achievement_type, achievement_name, description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user_id, $achievement_type, $achievement_name, $description);
            $stmt->execute();
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error awarding user achievement: " . $e->getMessage());
        return false;
    }
}

/**
 * Award achievement to panchayat admin
 */
function awardPanchayatAchievement($admin_id, $achievement_type, $achievement_name, $description) {
    try {
        $db = getDB();
        
        // Check if admin already has this achievement
        $stmt = $db->prepare("SELECT id FROM panchayat_achievements WHERE admin_id = ? AND achievement_type = ?");
        $stmt->bind_param("is", $admin_id, $achievement_type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Award new achievement
            $stmt = $db->prepare("INSERT INTO panchayat_achievements (admin_id, achievement_type, achievement_name, description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $admin_id, $achievement_type, $achievement_name, $description);
            $stmt->execute();
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error awarding panchayat achievement: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user achievements
 */
function getUserAchievements($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM user_achievements WHERE user_id = ? ORDER BY earned_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting user achievements: " . $e->getMessage());
        return [];
    }
}

/**
 * Get panchayat admin achievements
 */
function getPanchayatAchievements($admin_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM panchayat_achievements WHERE admin_id = ? ORDER BY earned_at DESC");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting panchayat achievements: " . $e->getMessage());
        return [];
    }
}

/**
 * Check and award achievements for user
 */
function checkUserAchievements($user_id) {
    try {
        $db = getDB();
        
        // Get user's issue count
        $stmt = $db->prepare("SELECT COUNT(*) as issue_count FROM issues WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $issue_count = $result['issue_count'];
        
        // Award achievements based on criteria
        if ($issue_count >= 1) {
            awardUserAchievement($user_id, 'first_issue', 'First Reporter', 'Submitted your first issue');
        }
        
        if ($issue_count >= 3) {
            awardUserAchievement($user_id, 'active_citizen', 'Active Citizen', 'Submitted 3 or more issues');
        }
        
        if ($issue_count >= 10) {
            awardUserAchievement($user_id, 'super_citizen', 'Super Citizen', 'Submitted 10 or more issues');
        }
        
        // Check for photo upload usage
        $stmt = $db->prepare("SELECT COUNT(*) as photo_count FROM issues WHERE user_id = ? AND photo IS NOT NULL");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $photo_count = $result['photo_count'];
        
        if ($photo_count >= 1) {
            awardUserAchievement($user_id, 'photo_uploader', 'Photo Champion', 'Used photo upload feature');
        }
        
    } catch (Exception $e) {
        error_log("Error checking user achievements: " . $e->getMessage());
    }
}

/**
 * Check and award achievements for panchayat admin
 */
function checkPanchayatAchievements($admin_id) {
    try {
        $db = getDB();
        
        // Get admin's resolution stats
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_issues,
                COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved_issues,
                AVG(CASE WHEN status = 'Resolved' THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) END) as avg_resolution_time
            FROM issues 
            WHERE assigned_to = ?
        ");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $total_issues = $result['total_issues'];
        $resolved_issues = $result['resolved_issues'];
        $avg_resolution_time = $result['avg_resolution_time'];
        $resolution_rate = $total_issues > 0 ? ($resolved_issues / $total_issues) * 100 : 0;
        
        // Award achievements based on criteria
        if ($total_issues >= 1) {
            awardPanchayatAchievement($admin_id, 'new_admin', 'New Administrator', 'Recently joined the platform');
        }
        
        if ($resolved_issues >= 10) {
            awardPanchayatAchievement($admin_id, 'community_helper', 'Community Helper', 'Resolved 10+ issues');
        }
        
        if ($resolution_rate >= 90) {
            awardPanchayatAchievement($admin_id, 'high_performer', 'High Performer', 'Maintained 90%+ resolution rate');
        }
        
        if ($avg_resolution_time <= 24) {
            awardPanchayatAchievement($admin_id, 'fast_resolver', 'Fast Resolver', 'Resolved issues within 24 hours');
        }
        
    } catch (Exception $e) {
        error_log("Error checking panchayat achievements: " . $e->getMessage());
    }
}


?>
