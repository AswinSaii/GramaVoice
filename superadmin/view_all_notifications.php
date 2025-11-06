<?php
/**
 * View All Notifications Page - Super Admin
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

// Check if super admin is logged in
requireSuperAdminLogin();

$db = getDB();
$super_admin_id = $_SESSION['super_admin_id'];
$super_admin_name = $_SESSION['super_admin_name'];

// Get super admin data
$stmt = $db->prepare("SELECT * FROM super_admin WHERE id = ?");
$stmt->bind_param("i", $super_admin_id);
$stmt->execute();
$result = $stmt->get_result();
$super_admin = $result->fetch_assoc();

if (!$super_admin) {
    $super_admin = [
        'id' => $super_admin_id,
        'name' => $super_admin_name,
        'email' => $_SESSION['super_admin_email'] ?? 'N/A'
    ];
}

// Get all notifications for the super admin
$notifications = getSuperAdminNotifications($super_admin_id, 100, false); // Get up to 100 notifications

// Get notification counts
$unread_count = getUnreadNotificationCount($super_admin_id, 'super_admin');
$total_count = count($notifications);

// Get flash messages
$success_message = getFlashMessage('success');
$error_message = getFlashMessage('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications - Grama Voice Super Admin</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="shortcut icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="apple-touch-icon" href="../images/GramaVoice-Logo.png">
    

    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #2d5a27;
            --secondary-green: #4a7c59;
            --accent-brown: #8b4513;
            --warm-yellow: #f4d03f;
            --bright-orange: #ff8c00;
            --light-green: #90ee90;
            --dark-brown: #654321;
            --cream: #f5f5dc;
            --white: #ffffff;
            --gray: #666;
            --light-gray: #f8f9fa;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --success-color: #10b981;
            --info-color: #3b82f6;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --teal: #14b8a6;
            --orange: #f97316;
            --purple: #8b5cf6;
            --blue: #3b82f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--white);
            border-right: 1px solid var(--gray-200);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 1000;
            overflow-y: auto;
            padding: 2rem 0;
        }

        .sidebar-header {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            text-decoration: none;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--orange), var(--purple), var(--success-color), var(--blue));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0 2rem;
            margin-bottom: 2rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), var(--info-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
            position: relative;
        }

        .time-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--warning-color);
            color: white;
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 10px;
            font-weight: 600;
        }

        .user-info h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .user-info p {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .nav-menu {
            padding: 0 1rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--gray-600);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: var(--gray-100);
            color: var(--gray-900);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            box-shadow: 0 2px 8px rgba(45, 90, 39, 0.3);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 0;
            min-height: 100vh;
        }
        
        .notification-page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .notification-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            overflow: hidden;
        }
        
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .notification-card.unread {
            border-left-color: #2196f3;
            background-color: #f8f9ff;
        }
        
        .notification-card.read {
            opacity: 0.8;
        }
        
        .notification-card .card-body {
            padding: 1.5rem;
        }
        
        .notification-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .notification-icon.issue-status {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .notification-icon.issue-assigned {
            background-color: #e8f5e8;
            color: #388e3c;
        }
        
        .notification-icon.issue-resolved {
            background-color: #e8f5e8;
            color: #4caf50;
        }
        
        .notification-icon.new-issue {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .notification-icon.admin-message {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .notification-icon.system-alert {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .notification-icon.achievement-earned {
            background-color: #fff8e1;
            color: #fbc02d;
        }
        
        .notification-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 1rem;
            line-height: 1.3;
        }
        
        .notification-message {
            color: #6c757d;
            line-height: 1.5;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }
        
        .notification-time {
            color: #adb5bd;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
        }
        
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            align-items: center;
        }
        
        .notification-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid #e9ecef;
        }
        
        .notification-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .filter-tabs {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filter-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: #6c757d;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .filter-tab.active {
            background: #667eea;
            color: white;
        }
        
        .filter-tab:hover:not(.active) {
            background: #f8f9fa;
            color: #495057;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .mark-read-btn {
            background: #28a745;
            border: none;
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .mark-read-btn:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .mark-read-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .unread-indicator {
            width: 8px;
            height: 8px;
            background: #2196f3;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .notification-page-header {
                padding: 1.5rem 0;
            }
            
            .notification-card {
                margin-bottom: 0.75rem;
            }
            
            .filter-tabs {
                padding: 0.75rem;
            }
            
            .filter-tab {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Super Admin Navbar -->
        <?php include '../includes/super_admin_navbar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Notification Page Header -->
            <div class="notification-page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-bell me-3"></i>All Notifications
                    </h1>
                    <p class="mb-0 opacity-75">
                        Stay updated with all system-wide governance activities
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex justify-content-md-end gap-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $total_count; ?></div>
                            <div class="stats-label">Total</div>
                        </div>
                        <div class="stats-card">
                            <div class="stats-number text-warning"><?php echo $unread_count; ?></div>
                            <div class="stats-label">Unread</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Flash Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <div class="d-flex flex-wrap gap-2">
                <button class="filter-tab active" data-filter="all">
                    <i class="fas fa-list me-2"></i>All Notifications
                </button>
                <button class="filter-tab" data-filter="unread">
                    <i class="fas fa-envelope me-2"></i>Unread
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-warning ms-2"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </button>
                <button class="filter-tab" data-filter="read">
                    <i class="fas fa-envelope-open me-2"></i>Read
                </button>
                <button class="filter-tab" data-filter="issue">
                    <i class="fas fa-tasks me-2"></i>Issue Updates
                </button>
                <button class="filter-tab" data-filter="system">
                    <i class="fas fa-cog me-2"></i>System Alerts
                </button>
            </div>
        </div>
        
        <!-- Bulk Actions -->
        <?php if ($unread_count > 0): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <button class="btn btn-success" id="markAllReadBtn">
                        <i class="fas fa-check-double me-2"></i>Mark All as Read
                    </button>
                </div>
                <div class="text-muted">
                    <small><?php echo $unread_count; ?> unread notifications</small>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Notifications List -->
        <div id="notificationsList">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h4>No Notifications Yet</h4>
                    <p>You'll see all system-wide governance updates here when they arrive.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card <?php echo !$notification['is_read'] ? 'unread' : 'read'; ?>" 
                         data-notification-id="<?php echo $notification['id']; ?>"
                         data-type="<?php echo $notification['type']; ?>"
                         data-read="<?php echo $notification['is_read'] ? 'true' : 'false'; ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3">
                                <div class="notification-icon <?php echo str_replace('_', '-', $notification['type']); ?>">
                                    <?php
                                    $icon_class = 'fas fa-info-circle';
                                    switch ($notification['type']) {
                                        case 'issue_status':
                                            $icon_class = 'fas fa-sync-alt';
                                            break;
                                        case 'issue_assigned':
                                            $icon_class = 'fas fa-user-tie';
                                            break;
                                        case 'issue_resolved':
                                            $icon_class = 'fas fa-check-circle';
                                            break;
                                        case 'new_issue':
                                            $icon_class = 'fas fa-plus-circle';
                                            break;
                                        case 'admin_message':
                                            $icon_class = 'fas fa-comment';
                                            break;
                                        case 'system_alert':
                                            $icon_class = 'fas fa-exclamation-triangle';
                                            break;
                                        case 'achievement_earned':
                                            $icon_class = 'fas fa-trophy';
                                            break;
                                    }
                                    ?>
                                    <i class="<?php echo $icon_class; ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <h5 class="notification-title">
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="unread-indicator"></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h5>
                                    <p class="notification-message">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="notification-footer">
                                <small class="notification-time">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo formatDate($notification['created_at']); ?>
                                </small>
                                <?php if (!$notification['is_read']): ?>
                                    <button class="mark-read-btn" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                        <i class="fas fa-check me-1"></i>Mark as Read
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Load More Button (if needed) -->
        <?php if (count($notifications) >= 100): ?>
            <div class="text-center mt-4">
                <button class="btn btn-outline-primary" id="loadMoreBtn">
                    <i class="fas fa-chevron-down me-2"></i>Load More Notifications
                </button>
            </div>
        <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter functionality
            const filterTabs = document.querySelectorAll('.filter-tab');
            const notificationCards = document.querySelectorAll('.notification-card');
            
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Update active tab
                    filterTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.dataset.filter;
                    
                    // Filter notifications
                    notificationCards.forEach(card => {
                        const isRead = card.dataset.read === 'true';
                        const type = card.dataset.type;
                        
                        let show = true;
                        
                        switch (filter) {
                            case 'all':
                                show = true;
                                break;
                            case 'unread':
                                show = !isRead;
                                break;
                            case 'read':
                                show = isRead;
                                break;
                            case 'issue':
                                show = type.includes('issue');
                                break;
                            case 'system':
                                show = type === 'system_alert';
                                break;
                        }
                        
                        card.style.display = show ? 'block' : 'none';
                    });
                });
            });
            
            // Mark all as read functionality
            const markAllReadBtn = document.getElementById('markAllReadBtn');
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function() {
                    markAllAsRead();
                });
            }
        });
        
        function markAsRead(notificationId) {
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
                    const notificationCard = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (notificationCard) {
                        notificationCard.classList.remove('unread');
                        notificationCard.classList.add('read');
                        notificationCard.dataset.read = 'true';
                        
                        // Remove unread indicator
                        const indicator = notificationCard.querySelector('.unread-indicator');
                        if (indicator) indicator.remove();
                        
                        // Remove mark as read button
                        const markBtn = notificationCard.querySelector('.mark-read-btn');
                        if (markBtn) markBtn.remove();
                    }
                    
                    // Update stats
                    updateNotificationStats();
                } else {
                    alert('Failed to mark notification as read');
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
                alert('An error occurred while marking notification as read');
            });
        }
        
        function markAllAsRead() {
            fetch('../ajax/mark_all_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update all notification cards
                    document.querySelectorAll('.notification-card.unread').forEach(card => {
                        card.classList.remove('unread');
                        card.classList.add('read');
                        card.dataset.read = 'true';
                        
                        // Remove unread indicators
                        const indicator = card.querySelector('.unread-indicator');
                        if (indicator) indicator.remove();
                        
                        // Remove mark as read buttons
                        const markBtn = card.querySelector('.mark-read-btn');
                        if (markBtn) markBtn.remove();
                    });
                    
                    // Update stats
                    updateNotificationStats();
                    
                    // Show success message
                    showAlert('All notifications marked as read', 'success');
                } else {
                    alert('Failed to mark all notifications as read');
                }
            })
            .catch(error => {
                console.error('Error marking all notifications as read:', error);
                alert('An error occurred while marking all notifications as read');
            });
        }
        
        function updateNotificationStats() {
            const unreadCards = document.querySelectorAll('.notification-card[data-read="false"]');
            const unreadCount = unreadCards.length;
            
            // Update unread count in stats
            const unreadStats = document.querySelector('.stats-number.text-warning');
            if (unreadStats) {
                unreadStats.textContent = unreadCount;
            }
            
            // Update unread badge in filter tab
            const unreadBadge = document.querySelector('.filter-tab[data-filter="unread"] .badge');
            if (unreadBadge) {
                if (unreadCount > 0) {
                    unreadBadge.textContent = unreadCount;
                    unreadBadge.style.display = 'inline';
                } else {
                    unreadBadge.style.display = 'none';
                }
            }
            
            // Hide mark all read button if no unread notifications
            const markAllBtn = document.getElementById('markAllReadBtn');
            if (markAllBtn && unreadCount === 0) {
                markAllBtn.style.display = 'none';
            }
        }
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.container');
            container.insertBefore(alertDiv, container.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>
