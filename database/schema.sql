-- Grama Voice Database Schema
-- Created for transparent village governance platform

CREATE DATABASE IF NOT EXISTS grama_voice;
USE grama_voice;

CREATE TABLE users (
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

CREATE TABLE panchayat_admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    village_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    profile_image VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Super Admin table
CREATE TABLE super_admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Issues table (complaints)
CREATE TABLE issues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    photo VARCHAR(500),
    location VARCHAR(255),
    status ENUM('Pending', 'In Progress', 'Resolved') DEFAULT 'Pending',
    assigned_to INT,
    admin_notes TEXT,
    resolution_photo VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES panchayat_admins(id) ON DELETE SET NULL
);

-- User Achievements table
CREATE TABLE user_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    achievement_type VARCHAR(100) NOT NULL,
    achievement_name VARCHAR(255) NOT NULL,
    description TEXT,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Panchayat Achievements table
CREATE TABLE panchayat_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    achievement_type VARCHAR(100) NOT NULL,
    achievement_name VARCHAR(255) NOT NULL,
    description TEXT,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES panchayat_admins(id) ON DELETE CASCADE
);

-- Achievements table
CREATE TABLE achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(100) NOT NULL,
    count INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default achievements
INSERT INTO achievements (type, count) VALUES 
('Issues Solved', 120),
('Panchayats Connected', 10),
('Citizens Benefited', 500),
('Satisfaction Rate', 95);

-- Insert default super admin
INSERT INTO super_admin (username, password) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password: password

-- Insert sample panchayat admins
INSERT INTO panchayat_admins (name, phone, password, village_name) VALUES 
('Rajesh Kumar', '9876543210', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Village A'),
('Priya Sharma', '9876543211', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Village B'),
('Amit Singh', '9876543212', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Village C');

-- Insert sample users
INSERT INTO users (name, phone, verified) VALUES 
('Ravi Kumar', '9123456789', TRUE),
('Sunita Devi', '9123456790', TRUE),
('Mohan Lal', '9123456791', TRUE);

-- Insert sample issues
INSERT INTO issues (user_id, title, description, location, status, assigned_to) VALUES 
(1, 'Broken Street Light', 'Street light near temple is not working for past 3 days', 'Near Temple, Main Road', 'Resolved', 1),
(2, 'Water Leakage', 'Water pipeline is leaking near school causing water wastage', 'Near School, Village Center', 'In Progress', 2),
(3, 'Road Repair Needed', 'Potholes on main road making it difficult for vehicles', 'Main Road, Village Entrance', 'Pending', 1);

-- Insert sample user achievements
INSERT INTO user_achievements (user_id, achievement_type, achievement_name, description) VALUES 
(1, 'first_issue', 'First Reporter', 'Submitted your first issue'),
(1, 'voice_recorder', 'Voice Champion', 'Used voice recording feature'),
(2, 'first_issue', 'First Reporter', 'Submitted your first issue'),
(2, 'active_citizen', 'Active Citizen', 'Submitted 3 or more issues'),
(3, 'first_issue', 'First Reporter', 'Submitted your first issue');

-- Insert sample panchayat achievements
INSERT INTO panchayat_achievements (admin_id, achievement_type, achievement_name, description) VALUES 
(1, 'fast_resolver', 'Fast Resolver', 'Resolved issues within 24 hours'),
(1, 'high_performer', 'High Performer', 'Maintained 90%+ resolution rate'),
(2, 'community_helper', 'Community Helper', 'Resolved 10+ issues'),
(3, 'new_admin', 'New Administrator', 'Recently joined the platform');

-- Create indexes for better performance
CREATE INDEX idx_users_phone ON users(phone);
CREATE INDEX idx_panchayat_admins_phone ON panchayat_admins(phone);
CREATE INDEX idx_issues_user_id ON issues(user_id);
CREATE INDEX idx_issues_status ON issues(status);
CREATE INDEX idx_issues_assigned_to ON issues(assigned_to);
CREATE INDEX idx_issues_created_at ON issues(created_at);
