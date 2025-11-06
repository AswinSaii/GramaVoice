<?php
/**
 * Application Configuration
 * Grama Voice - Village Governance Platform
 */

// Application settings
define('APP_NAME', 'Grama Voice');
define('APP_VERSION', '1.0.0');
define('APP_DESCRIPTION', 'Village Governance Platform');
define('APP_URL', 'http://localhost/GramaVoice');

// File upload settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Session settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'grama_voice_session');

// OTP settings
define('OTP_LENGTH', 4);
define('OTP_EXPIRY', 300); // 5 minutes
define('OTP_DEMO_MODE', true); // Set to false for production

// Pagination settings
define('ITEMS_PER_PAGE', 10);
define('MAX_ITEMS_PER_PAGE', 50);

// Notification settings
define('NOTIFICATION_LIMIT', 20);
define('NOTIFICATION_CLEANUP_DAYS', 30);

// Security settings
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting (set to 0 for production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Memory and execution limits
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

// File permissions
umask(0022);

// Application constants
define('ISSUE_STATUS_PENDING', 'Pending');
define('ISSUE_STATUS_IN_PROGRESS', 'In Progress');
define('ISSUE_STATUS_RESOLVED', 'Resolved');

define('USER_TYPE_CITIZEN', 'user');
define('USER_TYPE_ADMIN', 'admin');
define('USER_TYPE_SUPER_ADMIN', 'super_admin');

define('NOTIFICATION_TYPE_ISSUE_STATUS', 'issue_status');
define('NOTIFICATION_TYPE_ISSUE_ASSIGNED', 'issue_assigned');
define('NOTIFICATION_TYPE_ISSUE_RESOLVED', 'issue_resolved');
define('NOTIFICATION_TYPE_NEW_ISSUE', 'new_issue');
define('NOTIFICATION_TYPE_ADMIN_MESSAGE', 'admin_message');
define('NOTIFICATION_TYPE_SYSTEM_ALERT', 'system_alert');
define('NOTIFICATION_TYPE_ACHIEVEMENT_EARNED', 'achievement_earned');

// Achievement types
define('ACHIEVEMENT_FIRST_ISSUE', 'first_issue');
define('ACHIEVEMENT_VOICE_RECORDER', 'voice_recorder');
define('ACHIEVEMENT_ACTIVE_CITIZEN', 'active_citizen');
define('ACHIEVEMENT_FAST_RESOLVER', 'fast_resolver');
define('ACHIEVEMENT_HIGH_PERFORMER', 'high_performer');
define('ACHIEVEMENT_COMMUNITY_HELPER', 'community_helper');

// File paths
define('TEMP_PATH', __DIR__ . '/../uploads/temp/');
define('ISSUES_PATH', __DIR__ . '/../uploads/issues/');
define('PROFILES_PATH', __DIR__ . '/../uploads/profiles/');
define('RESOLUTIONS_PATH', __DIR__ . '/../uploads/resolutions/');
define('DOCUMENTS_PATH', __DIR__ . '/../uploads/documents/');

// Log paths
define('LOG_PATH', __DIR__ . '/../logs/');
define('ERROR_LOG', LOG_PATH . 'error.log');
define('APPLICATION_LOG', LOG_PATH . 'application.log');
define('ACCESS_LOG', LOG_PATH . 'access.log');

// Ensure directories exist
$directories = [
    UPLOAD_PATH,
    TEMP_PATH,
    ISSUES_PATH,
    PROFILES_PATH,
    RESOLUTIONS_PATH,
    DOCUMENTS_PATH,
    LOG_PATH
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

// Set default timezone
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Kolkata');
}
?>
