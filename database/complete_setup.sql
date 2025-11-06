-- Grama Voice Complete Database Setup
-- This script creates all necessary tables and columns for the application
-- Run this script to ensure all database components are properly set up

-- =============================================
-- CORE TABLES
-- =============================================

-- Users table (citizens)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    otp VARCHAR(10),
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Panchayat Admins table
CREATE TABLE IF NOT EXISTS panchayat_admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    village_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    profile_image VARCHAR(500) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Super Admin table
CREATE TABLE IF NOT EXISTS super_admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Issues table (complaints) - Enhanced with all columns
CREATE TABLE IF NOT EXISTS issues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    photo VARCHAR(500),
    location VARCHAR(255),
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    location_accuracy DECIMAL(8, 2) NULL,
    status ENUM('Pending', 'In Progress', 'Resolved') DEFAULT 'Pending',
    assigned_to INT,
    admin_notes TEXT,
    resolution_photo VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES panchayat_admins(id) ON DELETE SET NULL
);

-- =============================================
-- NOTIFICATION SYSTEM TABLES
-- =============================================

-- Notifications table
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
);

-- Notification preferences table
CREATE TABLE IF NOT EXISTS notification_preferences (
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
CREATE TABLE IF NOT EXISTS notification_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(100) NOT NULL,
    title_template VARCHAR(255) NOT NULL,
    message_template TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- ACHIEVEMENT SYSTEM TABLES
-- =============================================

-- User Achievements table
CREATE TABLE IF NOT EXISTS user_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    achievement_type VARCHAR(100) NOT NULL,
    achievement_name VARCHAR(255) NOT NULL,
    description TEXT,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Panchayat Achievements table
CREATE TABLE IF NOT EXISTS panchayat_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    achievement_type VARCHAR(100) NOT NULL,
    achievement_name VARCHAR(255) NOT NULL,
    description TEXT,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES panchayat_admins(id) ON DELETE CASCADE
);

-- Achievements table
CREATE TABLE IF NOT EXISTS achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(100) NOT NULL,
    count INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================

-- =============================================
-- INDEXES FOR PERFORMANCE
-- =============================================

-- Core table indexes
CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone);
CREATE INDEX IF NOT EXISTS idx_panchayat_admins_phone ON panchayat_admins(phone);
CREATE INDEX IF NOT EXISTS idx_issues_user_id ON issues(user_id);
CREATE INDEX IF NOT EXISTS idx_issues_status ON issues(status);
CREATE INDEX IF NOT EXISTS idx_issues_assigned_to ON issues(assigned_to);
CREATE INDEX IF NOT EXISTS idx_issues_created_at ON issues(created_at);
CREATE INDEX IF NOT EXISTS idx_issues_location ON issues(latitude, longitude);

-- Notification system indexes
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_admin_id ON notifications(admin_id);
CREATE INDEX IF NOT EXISTS idx_notifications_super_admin_id ON notifications(super_admin_id);
CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type);
CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at);
CREATE INDEX IF NOT EXISTS idx_notification_preferences_user_id ON notification_preferences(user_id);
CREATE INDEX IF NOT EXISTS idx_notification_preferences_admin_id ON notification_preferences(admin_id);
CREATE INDEX IF NOT EXISTS idx_notification_preferences_super_admin_id ON notification_preferences(super_admin_id);

-- =============================================
-- DEFAULT DATA
-- =============================================

-- Insert default super admin if not exists
INSERT IGNORE INTO super_admin (username, password) VALUES 
('Grama', '$2y$10$q4U2JKa1UvhdmhKtkicgh.SWrwh2tr7t3iTSiXQ8ZqOAAN/MhEdL.'); -- password: sadmin@grama

-- Insert default achievements if not exists
INSERT IGNORE INTO achievements (type, count) VALUES 
('Issues Solved', 0),
('Panchayats Connected', 1),
('Citizens Benefited', 1),
('Satisfaction Rate', 0);

-- Insert default notification templates if not exists
INSERT IGNORE INTO notification_templates (type, title_template, message_template) VALUES 
('issue_status', 'Issue Status Updated', 'Your issue "{issue_title}" status has been updated to {status}'),
('issue_assigned', 'Issue Assigned', 'Your issue "{issue_title}" has been assigned to {admin_name}'),
('issue_resolved', 'Issue Resolved', 'Great news! Your issue "{issue_title}" has been resolved'),
('new_issue', 'New Issue Received', 'A new issue "{issue_title}" has been submitted by {user_name}'),
('admin_message', 'Message from Admin', '{admin_name} has sent you a message: {message}'),
('system_alert', 'System Alert', '{message}'),
('achievement_earned', 'Achievement Earned', 'Congratulations! You have earned the "{achievement_name}" achievement');

-- Insert sample panchayat admin if not exists
INSERT IGNORE INTO panchayat_admins (name, phone, password, village_name) VALUES 
('Sai', '7093488939', '$2y$10$kDeWSuMF13adZ.YrwzVABuj2Pbb2IHXBKvG2ukd0H98RoOZk62Qz.', 'Grama Village'); -- password: admin@grama

-- Insert sample user if not exists
INSERT IGNORE INTO users (name, phone, verified) VALUES 
('Akash', '9849600480', TRUE);

-- No sample issues - fresh start

-- No sample user achievements - fresh start

-- No sample panchayat achievements - fresh start

-- =============================================
-- VERIFICATION QUERIES
-- =============================================

-- Show all tables
SHOW TABLES;

-- Show issues table structure
DESCRIBE issues;

-- Show notifications table structure
DESCRIBE notifications;

-- Show notification_preferences table structure
DESCRIBE notification_preferences;

-- Show notification_templates table structure
DESCRIBE notification_templates;

-- Count records in each table
SELECT 'users' as table_name, COUNT(*) as record_count FROM users
UNION ALL
SELECT 'panchayat_admins', COUNT(*) FROM panchayat_admins
UNION ALL
SELECT 'super_admin', COUNT(*) FROM super_admin
UNION ALL
SELECT 'issues', COUNT(*) FROM issues
UNION ALL
SELECT 'notifications', COUNT(*) FROM notifications
UNION ALL
SELECT 'notification_preferences', COUNT(*) FROM notification_preferences
UNION ALL
SELECT 'notification_templates', COUNT(*) FROM notification_templates
UNION ALL
SELECT 'user_achievements', COUNT(*) FROM user_achievements
UNION ALL
SELECT 'panchayat_achievements', COUNT(*) FROM panchayat_achievements
UNION ALL
SELECT 'achievements', COUNT(*) FROM achievements;

-- =============================================
-- COMPLETION MESSAGE
-- =============================================

SELECT 'Database setup completed successfully!' as status;
SELECT 'All tables, columns, indexes, and default data have been created.' as message;
