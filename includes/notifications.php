<?php
/**
 * Notifications System
 * Grama Voice - Village Governance Platform
 */

require_once __DIR__ . '/../config/db.php';

class NotificationSystem {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create a new notification
     */
    public function createNotification($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, admin_id, super_admin_id, type, title, message, data) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $json_data = isset($data['data']) ? json_encode($data['data']) : null;
            
            $user_id = $data['user_id'] ?? null;
            $admin_id = $data['admin_id'] ?? null;
            $super_admin_id = $data['super_admin_id'] ?? null;
            $type = $data['type'];
            $title = $data['title'];
            $message = $data['message'];
            
            $stmt->bind_param("iiissss", 
                $user_id,
                $admin_id, 
                $super_admin_id,
                $type,
                $title,
                $message,
                $json_data
            );
            
            if ($stmt->execute()) {
                $notification_id = $this->db->getLastInsertId();
                logApplicationEvent("Notification created", "ID: $notification_id, Type: {$data['type']}");
                return $notification_id;
            } else {
                logError("Failed to create notification: " . $this->db->getConnection()->error);
                return false;
            }
        } catch (Exception $e) {
            logError("Notification creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notifications for a user
     */
    public function getUserNotifications($user_id, $limit = 20, $unread_only = false) {
        try {
            // Check if notifications table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'notifications'");
            if ($checkTable->num_rows == 0) {
                // Table doesn't exist, create it
                $this->createNotificationsTable();
            }
            
            $sql = "SELECT * FROM notifications WHERE user_id = ?";
            if ($unread_only) {
                $sql .= " AND is_read = FALSE";
            }
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                logError("Failed to prepare statement for getUserNotifications: " . $this->db->error);
                return [];
            }
            
            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            logError("Failed to get user notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get notifications for an admin
     */
    public function getAdminNotifications($admin_id, $limit = 20, $unread_only = false) {
        try {
            // Check if notifications table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'notifications'");
            if ($checkTable->num_rows == 0) {
                // Table doesn't exist, create it
                $this->createNotificationsTable();
            }
            
            $sql = "SELECT * FROM notifications WHERE admin_id = ?";
            if ($unread_only) {
                $sql .= " AND is_read = FALSE";
            }
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                logError("Failed to prepare statement for getAdminNotifications: " . $this->db->error);
                return [];
            }
            
            $stmt->bind_param("ii", $admin_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            logError("Failed to get admin notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get notifications for super admin
     */
    public function getSuperAdminNotifications($super_admin_id, $limit = 20, $unread_only = false) {
        try {
            // Check if notifications table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'notifications'");
            if ($checkTable->num_rows == 0) {
                // Table doesn't exist, create it
                $this->createNotificationsTable();
            }
            
            $sql = "SELECT * FROM notifications WHERE super_admin_id = ?";
            if ($unread_only) {
                $sql .= " AND is_read = FALSE";
            }
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                logError("Failed to prepare statement for getSuperAdminNotifications: " . $this->db->error);
                return [];
            }
            
            $stmt->bind_param("ii", $super_admin_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            logError("Failed to get super admin notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id = null, $user_type = 'user') {
        try {
            // Build the query to verify ownership
            $sql = "UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE id = ?";
            $params = [$notification_id];
            $param_types = "i";
            
            // Add ownership verification if user_id is provided
            if ($user_id !== null) {
                $field = $user_type . '_id';
                $sql .= " AND $field = ?";
                $params[] = $user_id;
                $param_types .= "i";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($param_types, ...$params);
            
            if ($stmt->execute()) {
                return $stmt->affected_rows > 0; // Return true only if a row was actually updated
            } else {
                logError("Failed to mark notification as read: " . $this->db->getConnection()->error);
                return false;
            }
        } catch (Exception $e) {
            logError("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($user_id, $user_type = 'user') {
        try {
            $field = $user_type . '_id';
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = TRUE, read_at = NOW() 
                WHERE $field = ? AND is_read = FALSE
            ");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                return $stmt->affected_rows;
            } else {
                logError("Failed to mark all notifications as read: " . $this->db->getConnection()->error);
                return false;
            }
        } catch (Exception $e) {
            logError("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id, $user_type = 'user') {
        try {
            // Check if notifications table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'notifications'");
            if ($checkTable->num_rows == 0) {
                // Table doesn't exist, create it
                $this->createNotificationsTable();
            }
            
            $field = $user_type . '_id';
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE $field = ? AND is_read = FALSE
            ");
            
            if (!$stmt) {
                logError("Failed to prepare statement for getUnreadCount: " . $this->db->error);
                return 0;
            }
            
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return $row['count'] ?? 0;
        } catch (Exception $e) {
            logError("Failed to get unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Create notifications table if it doesn't exist
     */
    private function createNotificationsTable() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT,
                    admin_id INT,
                    super_admin_id INT,
                    type ENUM('issue_status', 'issue_assigned', 'issue_resolved', 'new_issue', 'admin_message', 'system_alert', 'achievement_earned') NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    data JSON,
                    is_read BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    read_at TIMESTAMP NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (admin_id) REFERENCES panchayat_admins(id) ON DELETE CASCADE,
                    FOREIGN KEY (super_admin_id) REFERENCES super_admin(id) ON DELETE CASCADE
                )
            ";
            
            if ($this->db->query($sql)) {
                logApplicationEvent("Notifications table created successfully");
            } else {
                logError("Failed to create notifications table: " . $this->db->error);
            }
        } catch (Exception $e) {
            logError("Error creating notifications table: " . $e->getMessage());
        }
    }
    
    /**
     * Delete old notifications (cleanup)
     */
    public function cleanupOldNotifications($days = 30) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->bind_param("i", $days);
            
            if ($stmt->execute()) {
                $deleted = $stmt->affected_rows;
                logApplicationEvent("Notification cleanup", "Deleted $deleted old notifications");
                return $deleted;
            } else {
                logError("Failed to cleanup old notifications: " . $this->db->getConnection()->error);
                return false;
            }
        } catch (Exception $e) {
            logError("Error cleaning up notifications: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification from template
     */
    public function createFromTemplate($type, $recipient_data, $template_data) {
        try {
            // Get template
            $stmt = $this->db->prepare("SELECT * FROM notification_templates WHERE type = ? AND is_active = TRUE");
            $stmt->bind_param("s", $type);
            $stmt->execute();
            $template = $stmt->get_result()->fetch_assoc();
            
            if (!$template) {
                logError("Notification template not found: $type");
                return false;
            }
            
            // Replace placeholders in title and message
            $title = $this->replacePlaceholders($template['title_template'], $template_data);
            $message = $this->replacePlaceholders($template['message_template'], $template_data);
            
            // Create notification
            $notification_data = array_merge($recipient_data, [
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $template_data
            ]);
            
            return $this->createNotification($notification_data);
        } catch (Exception $e) {
            logError("Error creating notification from template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Replace placeholders in template strings
     */
    private function replacePlaceholders($template, $data) {
        $result = $template;
        foreach ($data as $key => $value) {
            $result = str_replace("{" . $key . "}", $value, $result);
        }
        return $result;
    }
}

// Global notification functions
function createNotification($data) {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->createNotification($data);
}

function getUserNotifications($user_id, $limit = 20, $unread_only = false) {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->getUserNotifications($user_id, $limit, $unread_only);
}

function getAdminNotifications($admin_id, $limit = 20, $unread_only = false) {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->getAdminNotifications($admin_id, $limit, $unread_only);
}

function getSuperAdminNotifications($super_admin_id, $limit = 20, $unread_only = false) {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->getSuperAdminNotifications($super_admin_id, $limit, $unread_only);
}

function markNotificationAsRead($notification_id, $user_id = null, $user_type = 'user') {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->markAsRead($notification_id, $user_id, $user_type);
}

function markAllNotificationsAsRead($user_id, $user_type = 'user') {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->markAllAsRead($user_id, $user_type);
}

function getUnreadNotificationCount($user_id, $user_type = 'user') {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->getUnreadCount($user_id, $user_type);
}

function createNotificationFromTemplate($type, $recipient_data, $template_data) {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->createFromTemplate($type, $recipient_data, $template_data);
}

/**
 * Create a notification for an admin
 */
function createAdminNotification($admin_id, $type, $title, $message, $data = null) {
    $notificationData = [
        'admin_id' => $admin_id,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'data' => $data
    ];
    
    return createNotification($notificationData);
}

/**
 * Create a notification for all admins
 */
function createNotificationForAllAdmins($type, $title, $message, $data = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM panchayat_admins WHERE status = 'active'");
    $stmt->execute();
    $admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $results = [];
    foreach ($admins as $admin) {
        $results[] = createAdminNotification($admin['id'], $type, $title, $message, $data);
    }
    
    return $results;
}
?>
