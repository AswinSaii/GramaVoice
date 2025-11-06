<?php
/**
 * Track Issues
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../includes/functions.php';

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

// Get all issues for the user
$stmt = $db->prepare("
    SELECT i.*, pa.name as admin_name, pa.village_name 
    FROM issues i 
    LEFT JOIN panchayat_admins pa ON i.assigned_to = pa.id 
    WHERE i.user_id = ? 
    ORDER BY i.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$total_issues = count($issues);
$pending_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'Pending'; }));
$in_progress_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'In Progress'; }));
$resolved_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'Resolved'; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Issues - Grama Voice</title>

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


        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            align-items: stretch;
        }

        .stat-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 140px;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-4px);
            border-color: var(--primary-color);
        }

        .stat-card.total::before {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card.pending::before {
            background: linear-gradient(90deg, var(--warning-color), var(--orange));
        }

        .stat-card.progress::before {
            background: linear-gradient(90deg, var(--info-color), var(--blue));
        }

        .stat-card.resolved::before {
            background: linear-gradient(90deg, var(--success-color), var(--teal));
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 600;
            text-align: center;
            margin: 0;
        }

        .stat-card.total .stat-number { color: var(--primary-color); }
        .stat-card.pending .stat-number { color: var(--warning-color); }
        .stat-card.progress .stat-number { color: var(--info-color); }
        .stat-card.resolved .stat-number { color: var(--success-color); }

        /* Filter Buttons */
        .filter-section {
            margin-bottom: 2rem;
        }

        .filter-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--gray-200);
            background: var(--white);
            border-radius: 16px;
            color: var(--gray-600);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .filter-btn:hover {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.05);
            color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .filter-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .filter-btn.active:hover {
            background: #4f46e5;
            border-color: #4f46e5;
            color: white;
        }

        /* Issues List */
        .issues-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .issue-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .issue-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .issue-card:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-4px);
            border-color: var(--primary-color);
        }

        .issue-card.resolved::before {
            background: linear-gradient(90deg, var(--success-color), var(--teal));
        }

        .issue-card.pending::before {
            background: linear-gradient(90deg, var(--warning-color), var(--orange));
        }

        .issue-card.in-progress::before {
            background: linear-gradient(90deg, var(--info-color), var(--blue));
        }

        .issue-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .issue-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .issue-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-pending { background: rgba(245, 158, 11, 0.2); color: #92400e; }
        .status-in-progress { background: rgba(6, 182, 212, 0.2); color: #155e75; }
        .status-resolved { background: rgba(16, 185, 129, 0.2); color: #065f46; }

        .issue-content {
            margin-bottom: 1rem;
        }

        .issue-description {
            color: var(--gray-600);
            margin-bottom: 1rem;
        }

        .issue-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .detail-item i {
            color: var(--primary-color);
            width: 16px;
        }


        .issue-image {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .issue-image:hover {
            transform: scale(1.05);
        }

        .progress-timeline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 12px;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }

        .progress-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: var(--gray-300);
            z-index: 0;
        }

        .progress-step.active::after,
        .progress-step.completed::after {
            background: var(--success-color);
        }

        .progress-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-300);
            color: var(--gray-500);
            margin-bottom: 0.5rem;
            z-index: 1;
            position: relative;
        }

        .progress-step.active .progress-icon {
            background: var(--warning-color);
            color: white;
        }

        .progress-step.completed .progress-icon {
            background: var(--success-color);
            color: white;
        }

        .progress-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            text-align: center;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }

        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .empty-state-text {
            color: var(--gray-500);
            margin-bottom: 2rem;
        }

        /* Modal */
        .modal-image {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
        }

        /* Responsive Design */
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
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
                padding-bottom: 80px; /* Space for mobile nav */
            }
            
            .dashboard-container {
                padding-bottom: 80px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                align-items: stretch;
            }
            
            .stat-card {
                min-height: 120px;
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

            .btn-add-issue {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }

            .filter-buttons {
                justify-content: center;
            }

            .issue-details {
                grid-template-columns: 1fr;
            }

            /* Show mobile elements on mobile */
            .mobile-menu-btn {
                display: block;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
                align-items: stretch;
            }
            
            .stat-card {
                min-height: 100px;
            }

            .filter-buttons {
                flex-direction: column;
            }
            
            .issue-card {
                padding: 1rem;
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
                    <a href="track_issue.php" class="nav-link active">
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
                        <h1>My Village Issues</h1>
                        <p>Track the progress of your submitted issues</p>
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
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-number"><?php echo $total_issues; ?></div>
                    <div class="stat-label">Total Issues</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $pending_issues; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card progress">
                    <div class="stat-number"><?php echo $in_progress_issues; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card resolved">
                    <div class="stat-number"><?php echo $resolved_issues; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">
                        <i class="fas fa-list"></i>
                        All Issues
                    </button>
                    <button class="filter-btn" data-filter="pending">
                        <i class="fas fa-clock"></i>
                        Pending
                    </button>
                    <button class="filter-btn" data-filter="in-progress">
                        <i class="fas fa-spinner"></i>
                        In Progress
                    </button>
                    <button class="filter-btn" data-filter="resolved">
                        <i class="fas fa-check"></i>
                        Resolved
                    </button>
                </div>
            </div>
            
            <!-- Issues List -->
            <?php if (empty($issues)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3 class="empty-state-title">No issues submitted yet</h3>
                    <p class="empty-state-text">Start by reporting your first issue to make a difference in your village.</p>
                    <a href="submit_issue.php" class="add-issue-btn">
                        <i class="fas fa-plus"></i>
                        Submit First Issue
                    </a>
                </div>
            <?php else: ?>
                <div class="issues-list">
                    <?php foreach ($issues as $issue): ?>
                        <div class="issue-card" data-status="<?php echo strtolower(str_replace(' ', '-', $issue['status'])); ?>">
                            <div class="issue-header">
                                <div>
                                    <h3 class="issue-title"><?php echo htmlspecialchars($issue['title']); ?></h3>
                                    <div class="issue-meta">
                                        <span><i class="fas fa-calendar"></i> <?php echo formatDate($issue['created_at']); ?></span>
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($issue['location']); ?></span>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $issue['status'])); ?>">
                                    <?php echo $issue['status']; ?>
                                </span>
                            </div>
                            
                            <div class="issue-content">
                                <p class="issue-description"><?php echo nl2br(htmlspecialchars($issue['description'])); ?></p>
                                
                                <div class="issue-details">
                                    <?php if ($issue['admin_name']): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-user-tie"></i>
                                            <span>Assigned to: <?php echo htmlspecialchars($issue['admin_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail-item">
                                        <i class="fas fa-building"></i>
                                        <span>Village: <?php echo htmlspecialchars($issue['village_name'] ?? 'Local'); ?></span>
                                    </div>
                                    
                                    <?php if ($issue['admin_notes']): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-comment"></i>
                                            <span>Admin Notes Available</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($issue['photo'] || $issue['resolution_photo']): ?>
                                    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                                        <?php if ($issue['photo']): ?>
                                            <img src="../uploads/issues/<?php echo htmlspecialchars($issue['photo']); ?>" 
                                                 class="issue-image" 
                                                 alt="Issue Photo"
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#imageModal"
                                                 data-image="../uploads/issues/<?php echo htmlspecialchars($issue['photo']); ?>">
                                        <?php endif; ?>
                                        
                                        <?php if ($issue['resolution_photo']): ?>
                                            <img src="../uploads/resolutions/<?php echo htmlspecialchars($issue['resolution_photo']); ?>" 
                                                 class="issue-image" 
                                                 alt="Resolution Photo"
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#imageModal"
                                                 data-image="../uploads/resolutions/<?php echo htmlspecialchars($issue['resolution_photo']); ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($issue['admin_notes']): ?>
                                    <div style="background: var(--gray-50); padding: 1rem; border-radius: 12px; margin-bottom: 1rem;">
                                        <strong><i class="fas fa-comment me-1"></i>Admin Notes:</strong>
                                        <p style="margin-top: 0.5rem; margin-bottom: 0;"><?php echo nl2br(htmlspecialchars($issue['admin_notes'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Progress Timeline -->
                            <div class="progress-timeline">
                                <div class="progress-step <?php echo $issue['status'] === 'Pending' ? 'active' : ($issue['status'] === 'In Progress' || $issue['status'] === 'Resolved' ? 'completed' : ''); ?>">
                                    <div class="progress-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="progress-label">Submitted</div>
                                </div>
                                
                                <div class="progress-step <?php echo $issue['status'] === 'In Progress' ? 'active' : ($issue['status'] === 'Resolved' ? 'completed' : ''); ?>">
                                    <div class="progress-icon">
                                        <i class="fas fa-spinner"></i>
                                    </div>
                                    <div class="progress-label">In Progress</div>
                                </div>
                                
                                <div class="progress-step <?php echo $issue['status'] === 'Resolved' ? 'active completed' : ''; ?>">
                                    <div class="progress-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="progress-label">Resolved</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Issue Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" class="modal-image" alt="Issue Photo">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter functionality
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove active class from all buttons
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const filter = this.dataset.filter;
                    const issueCards = document.querySelectorAll('.issue-card');
                    
                    issueCards.forEach(card => {
                        if (filter === 'all' || card.dataset.status === filter) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
            
            // Image modal functionality
            const imageModal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            
            if (imageModal && modalImage) {
                imageModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const imageSrc = button.dataset.image;
                    modalImage.src = imageSrc;
                });
            }
            
            // Auto-refresh every 30 seconds to check for updates
            setInterval(() => {
                console.log('Checking for issue updates...');
                // You can implement AJAX refresh here if needed
            }, 30000);
            
            // Add hover effects for interactive elements
            const interactiveElements = document.querySelectorAll('.issue-card, .stat-card, .filter-btn');
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
    
    <!-- Mobile Bottom Navigation -->
    <?php include '../includes/mobile_navbar.php'; ?>
</body>
</html>
