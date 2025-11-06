<?php
/**
 * Notification Settings Page
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../config/error_handler.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

// Check if user is logged in
requireUserLogin();

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get notification preferences
$stmt = $db->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$preferences = $stmt->get_result()->fetch_assoc();

// If no preferences exist, create default ones
if (!$preferences) {
    $stmt = $db->prepare("
        INSERT INTO notification_preferences 
        (user_id, email_notifications, sms_notifications, push_notifications, 
         issue_status_updates, admin_messages, system_alerts, achievement_notifications) 
        VALUES (?, 1, 0, 1, 1, 1, 1, 1)
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Get the newly created preferences
    $stmt = $db->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $preferences = $stmt->get_result()->fetch_assoc();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        $issue_status_updates = isset($_POST['issue_status_updates']) ? 1 : 0;
        $admin_messages = isset($_POST['admin_messages']) ? 1 : 0;
        $system_alerts = isset($_POST['system_alerts']) ? 1 : 0;
        $achievement_notifications = isset($_POST['achievement_notifications']) ? 1 : 0;
        
        $stmt = $db->prepare("
            UPDATE notification_preferences 
            SET email_notifications = ?, sms_notifications = ?, push_notifications = ?, 
                issue_status_updates = ?, admin_messages = ?, system_alerts = ?, 
                achievement_notifications = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->bind_param("iiiiiiii", 
            $email_notifications, $sms_notifications, $push_notifications,
            $issue_status_updates, $admin_messages, $system_alerts, 
            $achievement_notifications, $user_id
        );
        
        if ($stmt->execute()) {
            $success_message = 'Notification preferences updated successfully!';
            
            // Refresh preferences
            $stmt = $db->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $preferences = $stmt->get_result()->fetch_assoc();
            
            // Log the preference update
            logApplicationEvent("Notification preferences updated", "User ID: $user_id");
        } else {
            $error_message = 'Failed to update notification preferences.';
        }
    } catch (Exception $e) {
        $error_msg = handleException($e, 'Notification preferences update failed');
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// Get notification statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_notifications,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_notifications,
        SUM(CASE WHEN type = 'issue_status' THEN 1 ELSE 0 END) as status_notifications,
        SUM(CASE WHEN type = 'new_issue' THEN 1 ELSE 0 END) as new_issue_notifications,
        SUM(CASE WHEN type = 'admin_message' THEN 1 ELSE 0 END) as admin_message_notifications
    FROM notifications 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Settings - Grama Voice</title>

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .settings-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .settings-header h1 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .settings-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .preference-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .preference-item:last-child {
            border-bottom: none;
        }
        
        .preference-info h6 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .preference-info p {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .form-check-input:checked {
            background-color: #3498db;
            border-color: #3498db;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-card p {
            margin: 0;
            opacity: 0.9;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(39, 174, 96, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d5f4e6, #a8e6cf);
            color: #27ae60;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fadbd8, #f1948a);
            color: #e74c3c;
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, #f5f7fa, #c3cfe2); min-height: 100vh;">
    <div class="settings-container">
        <div class="settings-header">
            <h1><i class="fas fa-bell me-2"></i>Notification Settings</h1>
            <p>Manage your notification preferences and stay updated</p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Notification Statistics -->
        <div class="settings-card">
            <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Notification Statistics</h5>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $stats['total_notifications']; ?></h3>
                    <p>Total Notifications</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['unread_notifications']; ?></h3>
                    <p>Unread</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['status_notifications']; ?></h3>
                    <p>Status Updates</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['admin_message_notifications']; ?></h3>
                    <p>Admin Messages</p>
                </div>
            </div>
        </div>
        
        <!-- Notification Preferences -->
        <div class="settings-card">
            <h5 class="mb-3"><i class="fas fa-cog me-2"></i>Notification Preferences</h5>
            <form method="POST">
                <div class="preference-item">
                    <div class="preference-info">
                        <h6><i class="fas fa-envelope me-2"></i>Email Notifications</h6>
                        <p>Receive notifications via email</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="email_notifications" 
                               <?php echo $preferences['email_notifications'] ? 'checked' : ''; ?>>
                    </div>
                </div>
                
                <div class="preference-item">
                    <div class="preference-info">
                        <h6><i class="fas fa-sms me-2"></i>SMS Notifications</h6>
                        <p>Receive notifications via SMS (Future Feature)</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="sms_notifications" 
                               <?php echo $preferences['sms_notifications'] ? 'checked' : ''; ?> disabled>
                    </div>
                </div>
                
                <div class="preference-item">
                    <div class="preference-info">
                        <h6><i class="fas fa-mobile-alt me-2"></i>Push Notifications</h6>
                        <p>Receive real-time notifications in browser</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="push_notifications" 
                               <?php echo $preferences['push_notifications'] ? 'checked' : ''; ?>>
                    </div>
                </div>
                
                <div class="preference-item">
                    <div class="preference-info">
                        <h6><i class="fas fa-sync-alt me-2"></i>Issue Status Updates</h6>
                        <p>Get notified when your issue status changes</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="issue_status_updates" 
                               <?php echo $preferences['issue_status_updates'] ? 'checked' : ''; ?>>
                    </div>
                </div>
                
                <div class="preference-item">
                    <div class="preference-info">
                        <h6><i class="fas fa-comment me-2"></i>Admin Messages</h6>
                        <p>Receive messages from administrators</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="admin_messages" 
                               <?php echo $preferences['admin_messages'] ? 'checked' : ''; ?>>
                    </div>
                </div>
                
                <div class="preference-item">
                    <div class="preference-info">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>System Alerts</h6>
                        <p>Receive important system notifications</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="system_alerts" 
                               <?php echo $preferences['system_alerts'] ? 'checked' : ''; ?>>
                    </div>
                </div>
                
                <div class="preference-item">
                    <div class="preference-info">
                        <h6><i class="fas fa-trophy me-2"></i>Achievement Notifications</h6>
                        <p>Get notified when you earn achievements</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="achievement_notifications" 
                               <?php echo $preferences['achievement_notifications'] ? 'checked' : ''; ?>>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-save">
                        <i class="fas fa-save me-2"></i>Save Preferences
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Back Button -->
        <div class="text-center">
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Mobile Bottom Navigation -->
    <?php include '../includes/mobile_navbar.php'; ?>
</body>
</html>
