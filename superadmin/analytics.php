<?php
/**
 * Super Admin Analytics & Reports
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if super admin is logged in
requireSuperAdminLogin();

$db = getDB();

// Get overall statistics
$stats = [];

// Total users
$result = $db->getConnection()->query("SELECT COUNT(*) as count FROM users WHERE verified = 1");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Total issues
$result = $db->getConnection()->query("SELECT COUNT(*) as count FROM issues");
$stats['total_issues'] = $result->fetch_assoc()['count'];

// Resolved issues
$result = $db->getConnection()->query("SELECT COUNT(*) as count FROM issues WHERE status = 'Resolved'");
$stats['resolved_issues'] = $result->fetch_assoc()['count'];

// Pending issues
$result = $db->getConnection()->query("SELECT COUNT(*) as count FROM issues WHERE status = 'Pending'");
$stats['pending_issues'] = $result->fetch_assoc()['count'];

// In progress issues
$result = $db->getConnection()->query("SELECT COUNT(*) as count FROM issues WHERE status = 'In Progress'");
$stats['in_progress_issues'] = $result->fetch_assoc()['count'];

// Total panchayat admins
$result = $db->getConnection()->query("SELECT COUNT(*) as count FROM panchayat_admins");
$stats['total_admins'] = $result->fetch_assoc()['count'];

// Resolution rate
$stats['resolution_rate'] = $stats['total_issues'] > 0 ? round(($stats['resolved_issues'] / $stats['total_issues']) * 100) : 0;

// Average resolution time (in days)
$result = $db->getConnection()->query("
    SELECT AVG(DATEDIFF(updated_at, created_at)) as avg_days 
    FROM issues 
    WHERE status = 'Resolved' AND updated_at IS NOT NULL
");
$avg_resolution = $result->fetch_assoc()['avg_days'];
$stats['avg_resolution_time'] = $avg_resolution ? round($avg_resolution, 1) : 0;

// Get chart data for the last 12 months
$chart_data = [];
$months = [];
$total_issues_data = [];
$resolved_issues_data = [];

for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m-01', strtotime("-$i months"));
    $month_name = date('M', strtotime($date));
    $months[] = $month_name;
    
    // Get total issues for this month
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM issues 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $month_year = date('Y-m', strtotime($date));
    $stmt->bind_param("s", $month_year);
    $stmt->execute();
    $total_count = $stmt->get_result()->fetch_assoc()['count'];
    $total_issues_data[] = $total_count;
    
    // Get resolved issues for this month
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM issues 
        WHERE status = 'Resolved'
        AND DATE_FORMAT(updated_at, '%Y-%m') = ?
    ");
    $month_year = date('Y-m', strtotime($date));
    $stmt->bind_param("s", $month_year);
    $stmt->execute();
    $resolved_count = $stmt->get_result()->fetch_assoc()['count'];
    $resolved_issues_data[] = $resolved_count;
}

// Get village performance data
$village_performance = $db->getConnection()->query("
    SELECT pa.village_name, 
           COUNT(i.id) as total_issues,
           COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) as resolved_issues,
           COUNT(CASE WHEN i.status = 'Pending' THEN 1 END) as pending_issues,
           COUNT(CASE WHEN i.status = 'In Progress' THEN 1 END) as in_progress_issues,
           CASE 
               WHEN COUNT(i.id) = 0 THEN 0
               ELSE ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100)
           END as resolution_rate
    FROM panchayat_admins pa 
    LEFT JOIN issues i ON pa.id = i.assigned_to 
    GROUP BY pa.id, pa.village_name 
    ORDER BY resolution_rate DESC
")->fetch_all(MYSQLI_ASSOC);

// Get recent activity
$recent_activity = $db->getConnection()->query("
    SELECT 'issue' as type, i.id, i.title, i.status, i.created_at, u.name as user_name, pa.village_name
    FROM issues i 
    JOIN users u ON i.user_id = u.id 
    LEFT JOIN panchayat_admins pa ON i.assigned_to = pa.id 
    ORDER BY i.created_at DESC 
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$success_message = getFlashMessage('success');
$error_message = getFlashMessage('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - Super Admin - Grama Voice</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="shortcut icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="apple-touch-icon" href="../images/GramaVoice-Logo.png">
    

    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #dbeafe;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --danger-red: #ef4444;
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
        }

        body {
            background-color: var(--gray-50);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #1e293b 0%, #334155 50%, #475569 100%);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            z-index: 1000;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            border-right: 1px solid rgba(255,255,255,0.1);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
        }
        
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
        }
        
        .brand-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        
        .brand-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .sidebar-nav {
            padding: 1rem 0;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            max-height: calc(100vh - 200px);
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.3) rgba(255,255,255,0.1);
        }
        
        /* Custom scrollbar for sidebar */
        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
            transition: background 0.3s ease;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb:active {
            background: rgba(255,255,255,0.7);
        }
        
        .nav-section {
            margin-bottom: 2rem;
        }
        
        .nav-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 0.875rem 1.5rem;
            border-radius: 0;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            margin: 0.25rem 0.75rem;
            border-radius: 8px;
        }
        
        .sidebar-nav .nav-link:hover {
            color: white;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(29, 78, 216, 0.2) 100%);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        
        .sidebar-nav .nav-link.active {
            color: white;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transform: translateX(4px);
        }
        
        .sidebar-nav .nav-link.active::before {
            content: '';
            position: absolute;
            left: -0.75rem;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 20px;
            background: #3b82f6;
            border-radius: 0 2px 2px 0;
        }
        
        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .sidebar-nav .nav-link:hover .nav-icon {
            transform: scale(1.1);
        }
        
        .user-profile {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            margin-top: auto;
            flex-shrink: 0;
            position: sticky;
            bottom: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transition: all 0.3s ease;
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        
        .user-details h6 {
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            margin: 0;
        }
        
        .user-details small {
            color: rgba(255,255,255,0.7);
            font-size: 0.75rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 0;
        }
        
        .top-header {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .btn-outline {
            border: 1px solid var(--gray-300);
            color: var(--gray-600);
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-outline:hover {
            border-color: var(--gray-400);
            background: var(--gray-50);
            color: var(--gray-700);
        }
        
        .btn-primary {
            background: var(--primary-blue);
            border: 1px solid var(--primary-blue);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background: var(--secondary-blue);
            border-color: var(--secondary-blue);
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--gray-200);
            transition: all 0.2s ease;
        }
        
        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
        }
        
        .stat-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
            margin: 0;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        
        .stat-icon.users { background: var(--primary-blue); }
        .stat-icon.issues { background: var(--warning-orange); }
        .stat-icon.resolved { background: var(--success-green); }
        .stat-icon.time { background: var(--danger-red); }
        
        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        /* Table */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        .table {
            margin: 0;
        }
        
        .table th {
            background-color: var(--gray-50);
            border: none;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table td {
            border: none;
            padding: 1rem;
            vertical-align: middle;
            font-size: 0.875rem;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-100);
        }
        
        .table tbody tr:hover {
            background-color: var(--gray-50);
        }
        
        .performance-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .performance-excellent {
            background: #dcfce7;
            color: var(--success-green);
        }
        
        .performance-good {
            background: #dbeafe;
            color: var(--primary-blue);
        }
        
        .performance-average {
            background: #fef3c7;
            color: var(--warning-orange);
        }
        
        .performance-poor {
            background: #fee2e2;
            color: var(--danger-red);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .top-header {
                padding: 1rem;
            }
            
            .page-title {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logo">
                <div class="brand-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="brand-text">Grama Voice</div>
            </div>
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-th-large nav-icon"></i>Dashboard
                    </a>
                    <a class="nav-link" href="issue_management.php">
                        <i class="fas fa-exclamation-triangle nav-icon"></i>Issue Management
                    </a>
                    <a class="nav-link" href="citizens.php">
                        <i class="fas fa-users nav-icon"></i>Citizens
                    </a>
                    <a class="nav-link" href="villages.php">
                        <i class="fas fa-map-marker-alt nav-icon"></i>Villages
                    </a>
                    <a class="nav-link" href="complaints.php">
                        <i class="fas fa-clipboard-list nav-icon"></i>Complaints
                    </a>
                    <a class="nav-link active" href="analytics.php">
                        <i class="fas fa-chart-bar nav-icon"></i>Analytics & Reports
                    </a>
                </nav>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="admins.php">
                        <i class="fas fa-user-tie nav-icon"></i>Panchayat Admins
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-user-cog nav-icon"></i>User Management
                    </a>
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog nav-icon"></i>Settings
                    </a>
                    <a class="nav-link" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt nav-icon"></i>Logout
                    </a>
                </nav>
            </div>
        </div>
        
        <div class="user-profile">
            <div class="user-info">
                <div class="user-avatar">
                    SA
                </div>
                <div class="user-details">
                    <h6>Super Admin</h6>
                    <small>System Administrator</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <div class="header-content">
                <h1 class="page-title">Analytics & Reports</h1>
                <div class="header-actions">
                    <button class="btn-outline" onclick="exportToPDF()">
                        <i class="fas fa-download me-1"></i>Export PDF
                    </button>
                    <button class="btn-primary" onclick="exportToCSV()">
                        <i class="fas fa-file-csv me-1"></i>Export CSV
                    </button>
                </div>
            </div>
        </div>
    
        <!-- Main Content Area -->
        <div class="p-4">
            <!-- Flash Messages -->
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
            
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon users me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <h3 class="stat-value"><?php echo $stats['total_users']; ?></h3>
                                <p class="stat-label">Total Users</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon issues me-3">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h3 class="stat-value"><?php echo $stats['total_issues']; ?></h3>
                                <p class="stat-label">Total Issues</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon resolved me-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <h3 class="stat-value"><?php echo $stats['resolution_rate']; ?>%</h3>
                                <p class="stat-label">Resolution Rate</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon time me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h3 class="stat-value"><?php echo $stats['avg_resolution_time']; ?></h3>
                                <p class="stat-label">Avg Resolution Time (Days)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h5 class="chart-title">Issue Resolution Trends</h5>
                        </div>
                        <canvas id="issuesChart" style="height: 300px !important; max-height: 300px !important;"></canvas>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h5 class="chart-title">Issue Status Distribution</h5>
                        </div>
                        <canvas id="statusChart" style="height: 300px !important; max-height: 300px !important;"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Village Performance Table -->
            <div class="table-container">
                <div class="table-header">
                    <h5 class="table-title">Village Performance Rankings</h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Village</th>
                                <th>Total Issues</th>
                                <th>Resolved</th>
                                <th>Pending</th>
                                <th>In Progress</th>
                                <th>Resolution Rate</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($village_performance as $index => $village): ?>
                                <?php
                                $performance_class = 'performance-poor';
                                $performance_text = 'Poor';
                                
                                if ($village['resolution_rate'] >= 80) {
                                    $performance_class = 'performance-excellent';
                                    $performance_text = 'Excellent';
                                } elseif ($village['resolution_rate'] >= 60) {
                                    $performance_class = 'performance-good';
                                    $performance_text = 'Good';
                                } elseif ($village['resolution_rate'] >= 40) {
                                    $performance_class = 'performance-average';
                                    $performance_text = 'Average';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($village['village_name']); ?></td>
                                    <td><?php echo $village['total_issues']; ?></td>
                                    <td><?php echo $village['resolved_issues']; ?></td>
                                    <td><?php echo $village['pending_issues']; ?></td>
                                    <td><?php echo $village['in_progress_issues']; ?></td>
                                    <td><?php echo $village['resolution_rate']; ?>%</td>
                                    <td>
                                        <span class="performance-badge <?php echo $performance_class; ?>">
                                            <?php echo $performance_text; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Chart data from PHP
        const chartData = {
            labels: <?php echo json_encode($months); ?>,
            totalIssues: <?php echo json_encode($total_issues_data); ?>,
            resolvedIssues: <?php echo json_encode($resolved_issues_data); ?>
        };
        
        // Initialize Issues Chart
        const issuesCtx = document.getElementById('issuesChart').getContext('2d');
        new Chart(issuesCtx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Total Issues',
                    data: chartData.totalIssues,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }, {
                    label: 'Resolved Issues',
                    data: chartData.resolvedIssues,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                aspectRatio: 2,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
        
        // Initialize Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Resolved', 'Pending', 'In Progress'],
                datasets: [{
                    data: [<?php echo $stats['resolved_issues']; ?>, <?php echo $stats['pending_issues']; ?>, <?php echo $stats['in_progress_issues']; ?>],
                    backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
        
        // Export functions
        function exportToPDF() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Analytics Report - ${new Date().toLocaleDateString()}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .stats { display: flex; justify-content: space-around; margin: 20px 0; }
                            .stat-item { text-align: center; }
                            .stat-value { font-size: 24px; font-weight: bold; color: #2563eb; }
                            .stat-label { color: #6b7280; }
                            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                            th { background-color: #f2f2f2; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>Grama Voice - Analytics Report</h1>
                            <p>Generated on: ${new Date().toLocaleDateString()}</p>
                        </div>
                        <div class="stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                                <div class="stat-label">Total Users</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['total_issues']; ?></div>
                                <div class="stat-label">Total Issues</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['resolution_rate']; ?>%</div>
                                <div class="stat-label">Resolution Rate</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['avg_resolution_time']; ?></div>
                                <div class="stat-label">Avg Resolution Time</div>
                            </div>
                        </div>
                        <h3>Village Performance</h3>
                        <table>
                            <tr>
                                <th>Village</th>
                                <th>Total Issues</th>
                                <th>Resolved</th>
                                <th>Resolution Rate</th>
                            </tr>
                            <?php foreach ($village_performance as $village): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($village['village_name']); ?></td>
                                <td><?php echo $village['total_issues']; ?></td>
                                <td><?php echo $village['resolved_issues']; ?></td>
                                <td><?php echo $village['resolution_rate']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        function exportToCSV() {
            let csv = 'Village,Total Issues,Resolved,Pending,In Progress,Resolution Rate\n';
            <?php foreach ($village_performance as $village): ?>
            csv += '<?php echo htmlspecialchars($village['village_name']); ?>,<?php echo $village['total_issues']; ?>,<?php echo $village['resolved_issues']; ?>,<?php echo $village['pending_issues']; ?>,<?php echo $village['in_progress_issues']; ?>,<?php echo $village['resolution_rate']; ?>%\n';
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'village_performance_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
