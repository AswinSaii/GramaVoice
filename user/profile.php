<?php
/**
 * User Profile
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
requireUserLogin();

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get user data
$selectSql = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($selectSql);
if (!$stmt) {
    Database::logApplicationError('Prepare failed for fetching user details: ' . $db->getError(), __FILE__, __LINE__);
    die('Database error: failed to fetch user details. Check logs for details.');
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($name)) {
        $error_message = 'Name is required';
    } else {
        $updateSql = "UPDATE users SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?";
        $stmt = $db->prepare($updateSql);
        if (!$stmt) {
            // Log prepare error for debugging
            Database::logApplicationError('Prepare failed for user profile update: ' . $db->getError(), __FILE__, __LINE__);
            $error_message = 'Failed to update profile (DB prepare error)';
        } else {
            $stmt->bind_param("ssssi", $name, $phone, $email, $address, $user_id);

            if ($stmt->execute()) {
            $_SESSION['user_name'] = $name;
            $_SESSION['user_phone'] = $phone;
            $success_message = 'Profile updated successfully';
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            } else {
                // Log execute error for debugging
                Database::logApplicationError('Execute failed for user profile update: ' . $db->getError(), __FILE__, __LINE__);
                $error_message = 'Failed to update profile';
            }
        }
    }
}

// Get user statistics
$stmt = $db->prepare("SELECT COUNT(*) as total_issues FROM issues WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_issues = $stmt->get_result()->fetch_assoc()['total_issues'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as resolved_issues FROM issues WHERE user_id = ? AND status = 'Resolved'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resolved_issues = $stmt->get_result()->fetch_assoc()['resolved_issues'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as pending_issues FROM issues WHERE user_id = ? AND status = 'Pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_issues = $stmt->get_result()->fetch_assoc()['pending_issues'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Grama Voice</title>

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
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --purple: #8b5cf6;
            --teal: #14b8a6;
            --orange: #f97316;
            --blue: #3b82f6;
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
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1rem;
            border-bottom: 1px solid var(--gray-200);
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
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
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
            background: var(--orange);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 0.7rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .user-info p {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .nav-menu {
            padding: 1rem 0;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: var(--gray-600);
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: var(--gray-100);
            color: var(--gray-900);
        }

        .nav-link.active {
            background: var(--purple);
            color: white !important;
            border-radius: 0 25px 25px 0;
            margin-right: 1rem;
        }

        .nav-link.active i {
            color: white !important;
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
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
        }

        /* Header */
        .header {
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .greeting {
            display: flex;
            flex-direction: column;
        }

        .greeting h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .greeting p {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Enhanced Back to Dashboard Button */
        .btn-back-dashboard {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 0.875rem 1.75rem;
            border-radius: 16px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .btn-back-dashboard::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .btn-back-dashboard:hover::before {
            left: 100%;
        }

        .btn-back-dashboard:hover {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .btn-back-dashboard:active {
            transform: translateY(0);
            box-shadow: var(--shadow-md);
        }

        .btn-back-dashboard i {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }

        .btn-back-dashboard:hover i {
            transform: translateX(-2px);
        }

        /* Profile Content */
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .profile-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .profile-card:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-2px);
            border-color: var(--primary-color);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), var(--info-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 3.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: var(--shadow-lg);
            border: 4px solid var(--white);
            position: relative;
            transition: all 0.3s ease;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-xl);
        }

        .profile-avatar::before {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-avatar:hover::before {
            opacity: 1;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .profile-role {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--gray-50), var(--white));
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-item:hover::before {
            opacity: 1;
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 2px solid var(--gray-200);
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-secondary:hover {
            background: var(--gray-200);
            border-color: var(--gray-300);
            color: var(--gray-800);
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 1rem;
            padding: 1rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-600);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .mobile-menu-btn:hover {
            background: var(--gray-100);
            color: var(--gray-900);
        }

        /* Mobile Overlay */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .mobile-overlay.active {
            display: block;
        }

        /* Enhanced Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .profile-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .main-content {
                padding: 1rem;
                padding-bottom: 80px; /* Space for mobile nav */
            }
            
            .dashboard-container {
                padding-bottom: 80px;
            }

            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header {
                padding: 1rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-right {
                width: 100%;
                justify-content: space-between;
            }

            .greeting h1 {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .profile-stats {
                grid-template-columns: 1fr;
            }
            
            .profile-card {
                padding: 1rem;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="../index.php" class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-microphone-alt"></i>
                    </div>
                    Grama Voice
                </a>
            </div>
            
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    <div class="time-badge"><?php echo $total_issues; ?>h</div>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                    <p><?php echo htmlspecialchars($user['phone']); ?></p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="track_issue.php" class="nav-link">
                        <i class="fas fa-list-check"></i>
                        My Issues
                    </a>
                </div>
                <div class="nav-item">
                    <a href="submit_issue.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        Report Issue
                    </a>
                </div>
                <div class="nav-item">
                    <a href="profile.php" class="nav-link active">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                </div>
                <div class="nav-item">
                    <a href="view_all_notifications.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        Notifications
                    </a>
                </div>
                <div class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../auth/logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="greeting">
                        <h1>My Profile</h1>
                        <p>Manage your personal information and account settings</p>
                    </div>
                </div>
                <div class="header-right">
                    <a href="dashboard.php" class="btn-back-dashboard">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
            
            <!-- Flash Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Profile Content -->
            <div class="profile-content">
                <!-- Profile Info -->
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <h2 class="profile-name"><?php echo htmlspecialchars($user['name']); ?></h2>
                        <p class="profile-role">Village Citizen</p>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $total_issues; ?></div>
                            <div class="stat-label">Total Issues</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $resolved_issues; ?></div>
                            <div class="stat-label">Resolved</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $pending_issues; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    
                    <div style="background: var(--gray-50); padding: 1rem; border-radius: 12px; margin-bottom: 1rem;">
                        <h4 style="font-size: 1rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.5rem;">
                            <i class="fas fa-info-circle me-1"></i>Account Information
                        </h4>
                        <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 0.5rem;">
                            <strong>Member since:</strong> <?php echo date('F Y', strtotime($user['created_at'])); ?>
                        </p>
                        <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 0;">
                            <strong>Status:</strong> 
                            <span style="color: var(--success-color); font-weight: 600;">Active</span>
                        </p>
                    </div>
                </div>
                
                <!-- Edit Profile Form -->
                <div class="profile-card">
                    <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--gray-900); margin-bottom: 1.5rem;">
                        <i class="fas fa-edit me-2"></i>Edit Profile
                    </h3>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3" 
                                      placeholder="Enter your village address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                    
                    <a href="../auth/logout.php" class="btn-secondary">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>
            </div>
        </main>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const name = document.getElementById('name').value.trim();
                    const phone = document.getElementById('phone').value.trim();
                    
                    if (!name) {
                        e.preventDefault();
                        alert('Name is required');
                        return;
                    }
                    
                    if (!phone) {
                        e.preventDefault();
                        alert('Phone number is required');
                        return;
                    }
                    
                    // Basic phone validation
                    if (!/^[0-9+\-\s()]+$/.test(phone)) {
                        e.preventDefault();
                        alert('Please enter a valid phone number');
                        return;
                    }
                });
            }
            
            // Add hover effects
            const interactiveElements = document.querySelectorAll('.profile-card, .stat-item');
            interactiveElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                element.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
    
    <!-- Mobile Menu JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            if (mobileMenuBtn && sidebar && mobileOverlay) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                    mobileOverlay.classList.toggle('active');
                });
                
                mobileOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    mobileOverlay.classList.remove('active');
                });
                
                // Close menu when clicking nav links
                const navLinks = document.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        sidebar.classList.remove('open');
                        mobileOverlay.classList.remove('active');
                    });
                });
                
                // Close menu on window resize if screen becomes large
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        sidebar.classList.remove('open');
                        mobileOverlay.classList.remove('active');
                    }
                });
            }
        });
    </script>

    <!-- Mobile Bottom Navigation -->
    <?php include '../includes/mobile_navbar.php'; ?>
</body>
</html>
