<?php
/**
 * Notification Display Component
 * Grama Voice - Village Governance Platform
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

// Get current user type and ID
$user_id = null;
$user_type = null;
$unread_count = 0;

// Check user roles based on current page context
// This ensures users see their own notifications, not admin notifications

// Determine user type based on current page context first
$current_dir = dirname($_SERVER['PHP_SELF']);
$current_url = $_SERVER['REQUEST_URI'];

$is_admin_page = (strpos($current_dir, '/admin') !== false) || (strpos($current_url, '/admin/') !== false);
$is_superadmin_page = (strpos($current_dir, '/superadmin') !== false) || (strpos($current_url, '/superadmin/') !== false);
$is_user_page = (strpos($current_dir, '/user') !== false) || (strpos($current_url, '/user/') !== false);

// If we're on a user page, prioritize user session
if ($is_user_page && isUserLoggedIn() && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_type = 'user';
    $unread_count = getUnreadNotificationCount($user_id, 'user');
} elseif ($is_admin_page && isPanchayatAdminLoggedIn() && isset($_SESSION['admin_id'])) {
    $user_id = $_SESSION['admin_id'];
    $user_type = 'admin';
    $unread_count = getUnreadNotificationCount($user_id, 'admin');
} elseif ($is_superadmin_page && isSuperAdminLoggedIn() && isset($_SESSION['super_admin_id'])) {
    $user_id = $_SESSION['super_admin_id'];
    $user_type = 'super_admin';
    $unread_count = getUnreadNotificationCount($user_id, 'super_admin');
} else {
    // Fallback to original logic if page context is unclear
    if (isPanchayatAdminLoggedIn() && isset($_SESSION['admin_id'])) {
        $user_id = $_SESSION['admin_id'];
        $user_type = 'admin';
        $unread_count = getUnreadNotificationCount($user_id, 'admin');
    } elseif (isSuperAdminLoggedIn() && isset($_SESSION['super_admin_id'])) {
        $user_id = $_SESSION['super_admin_id'];
        $user_type = 'super_admin';
        $unread_count = getUnreadNotificationCount($user_id, 'super_admin');
    } elseif (isUserLoggedIn() && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $user_type = 'user';
        $unread_count = getUnreadNotificationCount($user_id, 'user');
    }
}

// Get recent notifications
$notifications = [];
if ($user_id && $user_type) {
    if ($user_type === 'super_admin') {
        $notifications = getSuperAdminNotifications($user_id, 10, false);
    } else {
        $notifications = call_user_func("get" . ucfirst($user_type) . "Notifications", $user_id, 10, false);
    }
}

// Determine the correct path based on current directory and user type
$current_dir = dirname($_SERVER['PHP_SELF']);
$current_url = $_SERVER['REQUEST_URI'];

// More robust directory detection
$is_admin_page = (strpos($current_dir, '/admin') !== false) || (strpos($current_url, '/admin/') !== false);
$is_superadmin_page = (strpos($current_dir, '/superadmin') !== false) || (strpos($current_url, '/superadmin/') !== false);
$is_user_page = (strpos($current_dir, '/user') !== false) || (strpos($current_url, '/user/') !== false);
?>

<!-- Notification Bell -->
<div class="notification-container position-relative">
    <button class="btn btn-outline-primary position-relative" id="notificationBell" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell"></i>
        <?php if ($unread_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
            </span>
        <?php endif; ?>
    </button>
    
    <!-- Notification Dropdown -->
    <div class="dropdown-menu dropdown-menu-end notification-dropdown" id="notificationDropdown">
        <div class="notification-header d-flex justify-content-between align-items-center p-3 border-bottom">
            <h6 class="mb-0">
                <i class="fas fa-bell me-2"></i>Notifications
            </h6>
            <?php if ($unread_count > 0): ?>
                <button class="btn btn-sm btn-outline-primary" id="markAllRead">
                    <i class="fas fa-check-double me-1"></i>Mark All Read
                </button>
            <?php endif; ?>
        </div>
        
        <div class="notification-list" id="notificationList">
            <?php if (empty($notifications)): ?>
                <div class="text-center p-4 text-muted">
                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                    <p class="mb-0">No notifications yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item p-3 border-bottom <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                         data-notification-id="<?php echo $notification['id']; ?>">
                        <div class="d-flex align-items-start">
                            <div class="notification-icon me-3">
                                <?php
                                $icon_class = 'fas fa-info-circle';
                                switch ($notification['type']) {
                                    case 'issue_status':
                                        $icon_class = 'fas fa-sync-alt text-primary';
                                        break;
                                    case 'issue_assigned':
                                        $icon_class = 'fas fa-user-tie text-info';
                                        break;
                                    case 'issue_resolved':
                                        $icon_class = 'fas fa-check-circle text-success';
                                        break;
                                    case 'new_issue':
                                        $icon_class = 'fas fa-plus-circle text-warning';
                                        break;
                                    case 'admin_message':
                                        $icon_class = 'fas fa-comment text-primary';
                                        break;
                                    case 'system_alert':
                                        $icon_class = 'fas fa-exclamation-triangle text-danger';
                                        break;
                                    case 'achievement_earned':
                                        $icon_class = 'fas fa-trophy text-warning';
                                        break;
                                }
                                ?>
                                <i class="<?php echo $icon_class; ?>"></i>
                            </div>
                            <div class="notification-content flex-grow-1">
                                <h6 class="notification-title mb-1 <?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </h6>
                                <p class="notification-message mb-1 text-muted small">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                                <small class="notification-time text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo formatDate($notification['created_at']); ?>
                                </small>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                                <div class="notification-indicator">
                                    <span class="badge bg-primary rounded-pill"></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notification-footer p-3 border-top text-center">
            <?php
            // Debug: Show what's detected (disabled for production)
            // echo "<!-- Debug: User Type: $user_type, User ID: $user_id, Admin Page: " . ($is_admin_page ? 'true' : 'false') . ", Current Dir: $current_dir, Current URL: $current_url -->";
            
            // Determine the correct link based on user type and current directory
            $notifications_link = '#';
            
            // Force admin redirect if we're on admin pages, regardless of user type detection
            if ($is_admin_page) {
                $notifications_link = 'view_all_notifications.php';
            } elseif ($is_superadmin_page) {
                $notifications_link = 'view_all_notifications.php';
            } elseif ($is_user_page) {
                $notifications_link = 'view_all_notifications.php';
            } else {
                // Fallback based on user type
                if ($user_type === 'user') {
                    $notifications_link = '../user/view_all_notifications.php';
                } elseif ($user_type === 'admin') {
                    $notifications_link = '../admin/view_all_notifications.php';
                } elseif ($user_type === 'super_admin') {
                    $notifications_link = '../superadmin/view_all_notifications.php';
                }
            }
            ?>
            <a href="<?php echo $notifications_link; ?>" class="btn btn-sm btn-outline-primary" id="viewAllNotifications">
                <i class="fas fa-list me-1"></i>View All Notifications
            </a>
        </div>
    </div>
</div>

<style>
.notification-dropdown {
    width: 350px;
    max-height: 500px;
    overflow-y: auto;
}

.notification-item {
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #e3f2fd;
    border-left: 3px solid #2196f3;
}

.notification-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-indicator {
    margin-left: 10px;
}

.notification-indicator .badge {
    width: 8px;
    height: 8px;
}

.notification-title {
    font-size: 0.9rem;
    line-height: 1.3;
}

.notification-message {
    font-size: 0.8rem;
    line-height: 1.4;
}

.notification-time {
    font-size: 0.75rem;
}

@media (max-width: 768px) {
    .notification-dropdown {
        width: 300px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const notificationBell = document.getElementById('notificationBell');
    const notificationList = document.getElementById('notificationList');
    const markAllReadBtn = document.getElementById('markAllRead');
    
    // Mark notification as read when clicked
    notificationList.addEventListener('click', function(e) {
        const notificationItem = e.target.closest('.notification-item');
        if (notificationItem) {
            const notificationId = notificationItem.dataset.notificationId;
            markNotificationAsRead(notificationId);
        }
    });
    
    // Mark all notifications as read
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            markAllNotificationsAsRead();
        });
    }
    
    // Auto-refresh notifications every 30 seconds
    setInterval(function() {
        refreshNotifications();
    }, 30000);
});

function markNotificationAsRead(notificationId) {
    fetch('../ajax/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            notification_id: notificationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.classList.remove('unread');
                const title = notificationItem.querySelector('.notification-title');
                if (title) title.classList.remove('fw-bold');
                const indicator = notificationItem.querySelector('.notification-indicator');
                if (indicator) indicator.remove();
            }
            
            // Update badge count
            updateNotificationBadge();
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function markAllNotificationsAsRead() {
    fetch('../ajax/mark_all_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                const title = item.querySelector('.notification-title');
                if (title) title.classList.remove('fw-bold');
                const indicator = item.querySelector('.notification-indicator');
                if (indicator) indicator.remove();
            });
            
            // Update badge count
            updateNotificationBadge();
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
}

function refreshNotifications() {
    fetch('../ajax/get_notifications.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationBadge(data.unread_count);
        }
    })
    .catch(error => {
        console.error('Error refreshing notifications:', error);
    });
}

function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-container .badge');
    if (count === 0) {
        if (badge) badge.remove();
    } else {
        if (!badge) {
            const bell = document.getElementById('notificationBell');
            const span = document.createElement('span');
            span.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
            bell.appendChild(span);
        }
        const badgeElement = document.querySelector('.notification-container .badge');
        if (badgeElement) {
            badgeElement.textContent = count > 99 ? '99+' : count;
        }
    }
}
</script>
