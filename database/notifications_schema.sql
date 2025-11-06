-- Grama Voice Notifications System
-- Add to existing database schema

-- Notifications table
CREATE TABLE notifications (
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
);

-- Notification preferences table
CREATE TABLE notification_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    admin_id INT,
    super_admin_id INT,
    email_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT FALSE,
    push_notifications BOOLEAN DEFAULT TRUE,
    issue_status_updates BOOLEAN DEFAULT TRUE,
    admin_messages BOOLEAN DEFAULT TRUE,
    system_alerts BOOLEAN DEFAULT TRUE,
    achievement_notifications BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES panchayat_admins(id) ON DELETE CASCADE,
    FOREIGN KEY (super_admin_id) REFERENCES super_admin(id) ON DELETE CASCADE
);

-- Notification templates table
CREATE TABLE notification_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(100) NOT NULL,
    title_template VARCHAR(255) NOT NULL,
    message_template TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default notification templates
INSERT INTO notification_templates (type, title_template, message_template) VALUES 
('issue_status', 'Issue Status Updated', 'Your issue "{issue_title}" status has been updated to {status}'),
('issue_assigned', 'Issue Assigned', 'Your issue "{issue_title}" has been assigned to {admin_name}'),
('issue_resolved', 'Issue Resolved', 'Great news! Your issue "{issue_title}" has been resolved'),
('new_issue', 'New Issue Received', 'A new issue "{issue_title}" has been submitted by {user_name}'),
('admin_message', 'Message from Admin', '{admin_name} has sent you a message: {message}'),
('system_alert', 'System Alert', '{message}'),
('achievement_earned', 'Achievement Earned', 'Congratulations! You have earned the "{achievement_name}" achievement');

-- Create indexes for better performance
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_admin_id ON notifications(admin_id);
CREATE INDEX idx_notifications_super_admin_id ON notifications(super_admin_id);
CREATE INDEX idx_notifications_type ON notifications(type);
CREATE INDEX idx_notifications_is_read ON notifications(is_read);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);
CREATE INDEX idx_notification_preferences_user_id ON notification_preferences(user_id);
CREATE INDEX idx_notification_preferences_admin_id ON notification_preferences(admin_id);
CREATE INDEX idx_notification_preferences_super_admin_id ON notification_preferences(super_admin_id);
