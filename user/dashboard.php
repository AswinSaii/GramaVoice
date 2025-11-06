<?php
/**
 * User Dashboard - Redesigned with Modern UI
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

// Check if user is logged in
requireUserLogin();

$db = getDB();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get complete user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fallback to session data if user not found
if (!$user) {
    $user = [
        'id' => $user_id,
        'name' => $user_name,
        'phone' => $_SESSION['user_phone'] ?? 'N/A',
        'verified' => 1
    ];
}

// Get comprehensive user statistics
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

$stmt = $db->prepare("SELECT COUNT(*) as in_progress_issues FROM issues WHERE user_id = ? AND status = 'In Progress'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$in_progress_issues = $stmt->get_result()->fetch_assoc()['in_progress_issues'] ?? 0;

// Calculate resolution rate
$resolution_rate = $total_issues > 0 ? round(($resolved_issues / $total_issues) * 100) : 0;

// Get recent issues with admin details
$stmt = $db->prepare("
    SELECT i.*, pa.name as admin_name, pa.village_name 
    FROM issues i 
    LEFT JOIN panchayat_admins pa ON i.assigned_to = pa.id 
    WHERE i.user_id = ? 
    ORDER BY i.created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];

// Get notification count (unread status updates)
$stmt = $db->prepare("
    SELECT COUNT(*) as notification_count 
    FROM issues 
    WHERE user_id = ? AND updated_at > created_at AND updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notification_count = $stmt->get_result()->fetch_assoc()['notification_count'] ?? 0;

// Get flash messages
$success_message = getFlashMessage('success');
$error_message = getFlashMessage('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Grama Voice</title>
    
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
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--gray-900);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--success-color), var(--info-color));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .user-profile {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), var(--info-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
            position: relative;
            margin-bottom: 1rem;
        }

        .time-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--orange);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            min-width: 30px;
            text-align: center;
        }

        .user-info h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .user-info p {
            color: var(--gray-500);
            font-size: 0.875rem;
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
            background: var(--gray-50);
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

        .search-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .search-icon:hover {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-add-issue {
            background: var(--blue);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add-issue:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
        }

        /* Issue Category Cards */
        .issue-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .category-card {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .category-card.purple {
            background: linear-gradient(135deg, #8b5cf6, #a855f7);
            color: white;
        }

        .category-card.teal {
            background: linear-gradient(135deg, #14b8a6, #06b6d4);
            color: white;
        }

        .category-card.orange {
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .category-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .category-menu {
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .category-menu:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .category-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .category-stats {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .progress-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        /* Recent Issues and Statistics */
        .content-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .content-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .issue-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .issue-item:last-child {
            border-bottom: none;
        }

        .issue-indicator {
            width: 4px;
            height: 40px;
            border-radius: 2px;
            background: var(--orange);
        }

        .issue-content {
            flex: 1;
        }

        .issue-title {
            font-weight: 500;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .issue-description {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .issue-status {
            color: var(--success-color);
            font-size: 1.25rem;
        }

        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 8px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .report-issue-card {
            border: 2px dashed var(--gray-300);
            background: var(--gray-50);
            padding: 1.5rem;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .report-issue-card:hover {
            border-color: var(--purple);
            background: var(--purple);
            color: white;
        }

        .report-icon {
            font-size: 2rem;
            color: var(--purple);
            margin-bottom: 0.5rem;
        }

        .report-issue-card:hover .report-icon {
            color: white;
        }

        .report-text {
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* Village Services */
        .services-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
        }

        .services-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .services-subtitle {
            color: var(--gray-500);
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }

        .services-description {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                width: 250px;
            }
            
            .main-content {
                margin-left: 250px;
            }
            
            .issue-categories {
                grid-template-columns: 1fr;
            }
            
            .content-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
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
            
            .header {
                padding: 1rem;
            }
            
            .content-area {
                padding: 1rem;
            }
            
            .greeting h1 {
                font-size: 1.25rem;
            }
            
            .btn-add-issue {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
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

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
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

        /* Enhanced Mobile Responsiveness */
        @media (max-width: 768px) {
            .main-content {
                padding-bottom: 80px; /* Space for mobile nav */
            }
            
            .dashboard-container {
                padding-bottom: 80px;
            }
            
            .issue-categories {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .content-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .category-card {
                padding: 1rem;
            }
            
            .content-card {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-right {
                width: 100%;
                justify-content: space-between;
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
                    <a href="dashboard.php" class="nav-link active">
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
                    <a href="profile.php" class="nav-link">
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
                        <h1>Hello, <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?></h1>
                        <p>Today is <?php echo date('l, j F Y'); ?></p>
                    </div>
                </div>
                <div class="header-right">
                    
                    <?php include '../includes/notification_component.php'; ?>
                    <a href="submit_issue.php" class="btn-add-issue">
                        <i class="fas fa-plus"></i>
                        Add New Issue
                    </a>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Issue Categories -->
                <div class="issue-categories">
                    <div class="category-card purple">
                        <div class="category-header">
                            <div class="category-number"><?php echo $resolved_issues; ?></div>
                            <div class="category-menu">
                                <i class="fas fa-ellipsis-v"></i>
                            </div>
                        </div>
                        <div class="category-title">Infrastructure & Roads</div>
                        <div class="category-stats"><?php echo $resolved_issues; ?> total issues</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $resolution_rate; ?>%"></div>
                        </div>
                        <div class="category-stats"><?php echo $resolution_rate; ?>% resolved</div>
                    </div>

                    <div class="category-card teal">
                        <div class="category-header">
                            <div class="category-number"><?php echo $pending_issues; ?></div>
                            <div class="category-menu">
                                <i class="fas fa-ellipsis-v"></i>
                            </div>
                        </div>
                        <div class="category-title">Public Services</div>
                        <div class="category-stats"><?php echo $pending_issues; ?> pending</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="category-stats">0% resolved</div>
                    </div>

                    <div class="category-card orange">
                        <div class="category-header">
                            <div class="category-number"><?php echo $in_progress_issues; ?></div>
                            <div class="category-menu">
                                <i class="fas fa-ellipsis-v"></i>
                            </div>
                        </div>
                        <div class="category-title">Community Development</div>
                        <div class="category-stats"><?php echo $in_progress_issues; ?> in progress</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="category-stats">0% resolved</div>
                    </div>
                </div>

                <!-- Recent Issues and Statistics -->
                <div class="content-row">
                    <div class="content-card">
                        <h3 class="card-title">Recent Village Issues</h3>
                        <?php if (!empty($recent_issues)): ?>
                            <?php foreach ($recent_issues as $issue): ?>
                                <div class="issue-item">
                                    <div class="issue-indicator"></div>
                                    <div class="issue-content">
                                        <div class="issue-title">Village Issue</div>
                                        <div class="issue-description"><?php echo htmlspecialchars($issue['title']); ?></div>
                                    </div>
                                    <div class="issue-status">
                                        <?php if ($issue['status'] === 'Resolved'): ?>
                                            <i class="fas fa-check-circle"></i>
                                        <?php elseif ($issue['status'] === 'In Progress'): ?>
                                            <i class="fas fa-clock"></i>
                                        <?php else: ?>
                                            <i class="fas fa-hourglass-half"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>No issues submitted yet</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="content-card">
                        <h3 class="card-title">Village Statistics</h3>
                        <div class="stats-grid">
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
                        <div class="report-issue-card" onclick="window.location.href='submit_issue.php'">
                            <div class="report-icon">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="report-text">Report Issue</div>
                        </div>
                    </div>
                </div>

                <!-- Village Services -->
                <div class="services-card">
                    <h3 class="services-title">Village Services</h3>
                    <p class="services-subtitle">Community Support</p>
                    <p class="services-description">Access to all village governance services!</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Bottom Navigation -->
    <?php include '../includes/mobile_navbar.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Dashboard JavaScript -->
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
</body>
</html>
