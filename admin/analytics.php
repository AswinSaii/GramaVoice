<?php
/**
 * Analytics & Reports Page
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if panchayat admin is logged in
requirePanchayatAdminLogin();

$db = getDB();
$admin_id = $_SESSION['admin_id'];

// Get admin details
$stmt = $db->prepare("SELECT * FROM panchayat_admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Handle date filtering
$dateFilter = "";
$dateParams = [];

if (isset($_GET['date_range'])) {
    $dateRange = $_GET['date_range'];
    switch ($dateRange) {
        case '7days':
            $dateFilter = " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case '30days':
            $dateFilter = " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case '90days':
            $dateFilter = " AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
        case '1year':
            $dateFilter = " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
} elseif (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
    $dateFilter = " AND DATE(created_at) BETWEEN ? AND ?";
    $dateParams = [$startDate, $endDate];
}

// Export functionality will be handled after data is fetched

// Get comprehensive analytics data
$sql = "
    SELECT 
        COUNT(*) as total_issues,
        COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved_issues,
        COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_issues,
        COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress_issues,
        AVG(CASE WHEN status = 'Resolved' THEN DATEDIFF(updated_at, created_at) END) as avg_resolution_days
    FROM issues 
    WHERE assigned_to = ?" . $dateFilter;

$stmt = $db->prepare($sql);
if (!empty($dateParams)) {
    $stmt->bind_param("iss", $admin_id, $dateParams[0], $dateParams[1]);
} else {
    $stmt->bind_param("i", $admin_id);
}
$stmt->execute();
$analytics = $stmt->get_result()->fetch_assoc();

// Get monthly data for charts
$monthlySql = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total_issues,
        COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved_issues
    FROM issues 
    WHERE assigned_to = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)" . $dateFilter . "
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
";

$stmt = $db->prepare($monthlySql);
if (!empty($dateParams)) {
    $stmt->bind_param("iss", $admin_id, $dateParams[0], $dateParams[1]);
} else {
    $stmt->bind_param("i", $admin_id);
}
$stmt->execute();
$monthly_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get top locations
$locationsSql = "
    SELECT location, COUNT(*) as issue_count
    FROM issues 
    WHERE assigned_to = ? AND location IS NOT NULL AND location != ''" . $dateFilter . "
    GROUP BY location
    ORDER BY issue_count DESC
    LIMIT 5
";

$stmt = $db->prepare($locationsSql);
if (!empty($dateParams)) {
    $stmt->bind_param("iss", $admin_id, $dateParams[0], $dateParams[1]);
} else {
    $stmt->bind_param("i", $admin_id);
}
$stmt->execute();
$top_locations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent activity
$activitySql = "
    SELECT i.*, u.name as user_name
    FROM issues i 
    JOIN users u ON i.user_id = u.id 
    WHERE i.assigned_to = ?" . $dateFilter . "
    ORDER BY i.updated_at DESC 
    LIMIT 10
";

$stmt = $db->prepare($activitySql);
if (!empty($dateParams)) {
    $stmt->bind_param("iss", $admin_id, $dateParams[0], $dateParams[1]);
} else {
    $stmt->bind_param("i", $admin_id);
}
$stmt->execute();
$recent_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate resolution rate
$resolution_rate = $analytics['total_issues'] > 0 ? round(($analytics['resolved_issues'] / $analytics['total_issues']) * 100) : 0;
$avg_resolution_days = $analytics['avg_resolution_days'] ? round($analytics['avg_resolution_days']) : 0;

// Handle export functionality after data is calculated
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    
    if ($exportType === 'csv') {
        // Generate CSV export
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="analytics_report_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Add BOM for UTF-8
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Write CSV headers
        fputcsv($output, ['Grama Voice Analytics Report']);
        fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, ['']);
        
        // Write main metrics
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Issues', $analytics['total_issues']]);
        fputcsv($output, ['Resolved Issues', $analytics['resolved_issues']]);
        fputcsv($output, ['Pending Issues', $analytics['pending_issues']]);
        fputcsv($output, ['In Progress Issues', $analytics['in_progress_issues']]);
        fputcsv($output, ['Resolution Rate (%)', $resolution_rate]);
        fputcsv($output, ['Average Resolution Days', $avg_resolution_days]);
        fputcsv($output, ['']);
        
        // Write monthly data
        fputcsv($output, ['Monthly Data']);
        fputcsv($output, ['Month', 'Total Issues', 'Resolved Issues']);
        foreach ($monthly_data as $month) {
            fputcsv($output, [$month['month'], $month['total_issues'], $month['resolved_issues']]);
        }
        fputcsv($output, ['']);
        
        // Write top locations
        fputcsv($output, ['Top Issue Locations']);
        fputcsv($output, ['Location', 'Issue Count', 'Percentage']);
        foreach ($top_locations as $location) {
            $percentage = $analytics['total_issues'] > 0 ? round(($location['issue_count'] / $analytics['total_issues']) * 100) : 0;
            fputcsv($output, [$location['location'], $location['issue_count'], $percentage . '%']);
        }
        
        fclose($output);
        exit;
        
    } elseif ($exportType === 'pdf') {
        // Generate PDF export - using HTML that can be printed as PDF
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="analytics_report_' . date('Y-m-d_H-i-s') . '.html"');
        
        $html = generatePDFContent($analytics, $monthly_data, $top_locations, $recent_activity, $resolution_rate, $avg_resolution_days);
        echo $html;
        exit;
    }
}

// PDF Generation Function
function generatePDFContent($analytics, $monthly_data, $top_locations, $recent_activity, $resolution_rate, $avg_resolution_days) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Analytics Report - Grama Voice</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="shortcut icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="apple-touch-icon" href="../images/GramaVoice-Logo.png">
    

        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #2563eb; margin: 0; }
            .header p { color: #666; margin: 5px 0; }
            .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
            .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
            .stat-number { font-size: 24px; font-weight: bold; color: #2563eb; }
            .stat-label { color: #666; margin-top: 5px; }
            .section { margin-bottom: 30px; }
            .section h2 { color: #333; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f8f9fa; font-weight: bold; }
            .footer { margin-top: 50px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Grama Voice Analytics Report</h1>
            <p>Generated on ' . date('F j, Y') . '</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">' . $resolution_rate . '%</div>
                <div class="stat-label">Resolution Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $avg_resolution_days . '</div>
                <div class="stat-label">Avg Resolution Time (Days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $analytics['total_issues'] . '</div>
                <div class="stat-label">Total Issues</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $analytics['resolved_issues'] . '</div>
                <div class="stat-label">Resolved Issues</div>
            </div>
        </div>
        
        <div class="section">
            <h2>Issue Status Breakdown</h2>
            <table>
                <tr><th>Status</th><th>Count</th><th>Percentage</th></tr>
                <tr><td>Resolved</td><td>' . $analytics['resolved_issues'] . '</td><td>' . round(($analytics['resolved_issues'] / max($analytics['total_issues'], 1)) * 100) . '%</td></tr>
                <tr><td>Pending</td><td>' . $analytics['pending_issues'] . '</td><td>' . round(($analytics['pending_issues'] / max($analytics['total_issues'], 1)) * 100) . '%</td></tr>
                <tr><td>In Progress</td><td>' . $analytics['in_progress_issues'] . '</td><td>' . round(($analytics['in_progress_issues'] / max($analytics['total_issues'], 1)) * 100) . '%</td></tr>
            </table>
        </div>
        
        <div class="section">
            <h2>Top Issue Locations</h2>
            <table>
                <tr><th>Location</th><th>Issue Count</th><th>Percentage</th></tr>';
    
    foreach ($top_locations as $location) {
        $percentage = $analytics['total_issues'] > 0 ? round(($location['issue_count'] / $analytics['total_issues']) * 100) : 0;
        $html .= '<tr><td>' . htmlspecialchars($location['location']) . '</td><td>' . $location['issue_count'] . '</td><td>' . $percentage . '%</td></tr>';
    }
    
    $html .= '
            </table>
        </div>
        
        <div class="section">
            <h2>Recent Activity</h2>
            <table>
                <tr><th>Issue</th><th>Citizen</th><th>Status</th><th>Updated</th></tr>';
    
    foreach ($recent_activity as $activity) {
        $html .= '<tr><td>' . htmlspecialchars(substr($activity['title'], 0, 30)) . '...</td><td>' . htmlspecialchars($activity['user_name']) . '</td><td>' . $activity['status'] . '</td><td>' . date('M j, Y', strtotime($activity['updated_at'])) . '</td></tr>';
    }
    
    $html .= '
            </table>
        </div>
        
        <div class="footer">
            <p>This report was generated by Grama Voice Analytics System</p>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - Grama Voice</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles.css">
    
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
            background: white;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            z-index: 1000;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-right: 1px solid var(--gray-200);
        }
        
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .brand-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .brand-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-section {
            margin-bottom: 2rem;
        }
        
        .nav-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .sidebar-nav .nav-link {
            color: var(--gray-600);
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            transition: all 0.2s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .sidebar-nav .nav-link:hover {
            color: var(--primary-blue);
            background-color: var(--light-blue);
        }
        
        .sidebar-nav .nav-link.active {
            color: var(--primary-blue);
            background-color: var(--light-blue);
            border-right: 3px solid var(--primary-blue);
        }
        
        .nav-icon {
            width: 20px;
            text-align: center;
        }
        
        .user-profile {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            background: white;
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
            background: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .user-details h6 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        .user-details small {
            color: var(--gray-500);
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
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--gray-200);
            transition: all 0.2s ease;
        }
        
        .stats-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .stats-icon.primary { background: var(--primary-blue); }
        .stats-icon.success { background: var(--success-green); }
        .stats-icon.warning { background: var(--warning-orange); }
        .stats-icon.info { background: var(--danger-red); }
        
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
        
        /* Table Container */
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
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success { background: #dcfce7; color: var(--success-green); }
        .badge-warning { background: #fef3c7; color: var(--warning-orange); }
        .badge-danger { background: #fee2e2; color: var(--danger-red); }
        .badge-primary { background: var(--light-blue); color: var(--primary-blue); }
        
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
                    <i class="fas fa-microphone-alt"></i>
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
                    <a class="nav-link" href="locations.php">
                        <i class="fas fa-map-marker-alt nav-icon"></i>Locations
                    </a>
                    <a class="nav-link" href="complaints.php">
                        <i class="fas fa-clipboard-list nav-icon"></i>Complaints
                    </a>
                    <a class="nav-link active" href="analytics.php">
                        <i class="fas fa-chart-bar nav-icon"></i>Analytics & Reports
                    </a>
                    <a class="nav-link" href="view_all_notifications.php">
                        <i class="fas fa-bell nav-icon"></i>Notifications
                    </a>
                </nav>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Other</div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="profile.php">
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
                    <?php echo strtoupper(substr($admin['name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h6><?php echo $admin['name']; ?></h6>
                    <small><?php echo $admin['email'] ?? 'admin@gramavoice.com'; ?></small>
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
                    <button class="btn-outline" onclick="exportReport('csv')">
                        <i class="fas fa-download me-1"></i>Export CSV
                    </button>
                    <button class="btn-primary" onclick="generatePDF()">
                        <i class="fas fa-file-pdf me-1"></i>Generate PDF
                    </button>
                </div>
            </div>
        </div>
    
        <!-- Main Content Area -->
        <div class="p-4">
            <!-- Key Performance Indicators -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon primary">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?php echo $resolution_rate; ?>%</h3>
                        <p class="text-muted mb-0">Resolution Rate</p>
                        <small class="text-primary">Overall performance</small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon success">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?php echo $avg_resolution_days; ?></h3>
                        <p class="text-muted mb-0">Avg Resolution Time</p>
                        <small class="text-success">Days to resolve</small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?php echo $analytics['total_issues']; ?></h3>
                        <p class="text-muted mb-0">Total Issues</p>
                        <small class="text-warning">All time</small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon info">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?php echo $analytics['resolved_issues']; ?></h3>
                        <p class="text-muted mb-0">Resolved Issues</p>
                        <small class="text-info">Successfully closed</small>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h5 class="chart-title">Monthly Issue Trends</h5>
                        </div>
                        <canvas id="monthlyChart" style="height: 300px !important; max-height: 300px !important;"></canvas>
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
            
            <!-- Data Tables Row -->
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="table-container">
                        <div class="table-header">
                            <h5 class="table-title">Top Issue Locations</h5>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Location</th>
                                        <th>Issues</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_locations as $location): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($location['location']); ?></td>
                                            <td>
                                                <span class="badge badge-primary"><?php echo $location['issue_count']; ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                $percentage = $analytics['total_issues'] > 0 ? round(($location['issue_count'] / $analytics['total_issues']) * 100) : 0;
                                                ?>
                                                <span class="badge badge-success"><?php echo $percentage; ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="table-container">
                        <div class="table-header">
                            <h5 class="table-title">Recent Activity</h5>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Issue</th>
                                        <th>Citizen</th>
                                        <th>Status</th>
                                        <th>Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(substr($activity['title'], 0, 20)) . '...'; ?></td>
                                            <td><?php echo htmlspecialchars($activity['user_name']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $activity['status'])) === 'resolved' ? 'success' : (strtolower(str_replace(' ', '-', $activity['status'])) === 'pending' ? 'danger' : 'warning'); ?>">
                                                    <?php echo $activity['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j', strtotime($activity['updated_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Custom Date Range Modal -->
    <div class="modal fade" id="customDateRangeModal" tabindex="-1" aria-labelledby="customDateRangeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customDateRangeModalLabel">Select Custom Date Range</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="startDate" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="startDate" name="startDate">
                    </div>
                    <div class="mb-3">
                        <label for="endDate" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="endDate" name="endDate">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="applyCustomDateRange()">Apply Filter</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global variables for charts
        let monthlyChart, statusChart;
        let currentDateRange = 'all';
        
        // Initialize charts
        function initializeCharts() {
            // Monthly Chart
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            const monthlyData = <?php echo json_encode($monthly_data); ?>;
            
            const months = monthlyData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            });
            
            const totalIssues = monthlyData.map(item => parseInt(item.total_issues));
            const resolvedIssues = monthlyData.map(item => parseInt(item.resolved_issues));
            
            monthlyChart = new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Total Issues',
                        data: totalIssues,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Resolved Issues',
                        data: resolvedIssues,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
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
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    }
                }
            });
            
            // Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Resolved', 'Pending', 'In Progress'],
                    datasets: [{
                        data: [<?php echo $analytics['resolved_issues']; ?>, <?php echo $analytics['pending_issues']; ?>, <?php echo $analytics['in_progress_issues']; ?>],
                        backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    aspectRatio: 1,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Date Range Functions
        function setDateRange(range) {
            currentDateRange = range;
            const button = document.getElementById('dateRangeDropdown');
            const rangeText = {
                '7days': 'Last 7 Days',
                '30days': 'Last 30 Days',
                '90days': 'Last 90 Days',
                '1year': 'Last Year'
            };
            
            button.innerHTML = `<i class="fas fa-calendar me-1"></i>${rangeText[range]}`;
            
            // Reload page with date filter
            const url = new URL(window.location);
            url.searchParams.set('date_range', range);
            window.location.href = url.toString();
        }
        
        function showCustomDateRange() {
            const modal = new bootstrap.Modal(document.getElementById('customDateRangeModal'));
            modal.show();
        }
        
        function applyCustomDateRange() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }
            
            if (new Date(startDate) > new Date(endDate)) {
                alert('Start date cannot be after end date.');
                return;
            }
            
            // Reload page with custom date filter
            const url = new URL(window.location);
            url.searchParams.set('start_date', startDate);
            url.searchParams.set('end_date', endDate);
            window.location.href = url.toString();
        }
        
        // Export Report Function
        function exportReport(format) {
            const url = new URL(window.location);
            url.searchParams.set('export', format);
            
            // Add current date range parameters if they exist
            const urlParams = new URLSearchParams(window.location.search);
            const dateRange = urlParams.get('date_range');
            if (dateRange) {
                url.searchParams.set('date_range', dateRange);
            }
            
            const startDate = urlParams.get('start_date');
            const endDate = urlParams.get('end_date');
            if (startDate) url.searchParams.set('start_date', startDate);
            if (endDate) url.searchParams.set('end_date', endDate);
            
            // Create a temporary link to trigger download
            const link = document.createElement('a');
            link.href = url.toString();
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Generate PDF Function
        function generatePDF() {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
            button.disabled = true;
            
            // Create URL for PDF generation
            const url = new URL(window.location);
            url.searchParams.set('export', 'pdf');
            
            // Add current date range parameters if they exist
            const urlParams = new URLSearchParams(window.location.search);
            const dateRange = urlParams.get('date_range');
            if (dateRange) {
                url.searchParams.set('date_range', dateRange);
            }
            
            const startDate = urlParams.get('start_date');
            const endDate = urlParams.get('end_date');
            if (startDate) url.searchParams.set('start_date', startDate);
            if (endDate) url.searchParams.set('end_date', endDate);
            
            // Open in new window for PDF generation
            const pdfWindow = window.open(url.toString(), '_blank');
            
            // Reset button after a delay
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 3000);
        }
        
        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            
            // Set current date range from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const dateRange = urlParams.get('date_range');
            if (dateRange) {
                currentDateRange = dateRange;
                const button = document.getElementById('dateRangeDropdown');
                const rangeText = {
                    '7days': 'Last 7 Days',
                    '30days': 'Last 30 Days',
                    '90days': 'Last 90 Days',
                    '1year': 'Last Year',
                    'custom': 'Custom Range'
                };
                
                if (rangeText[dateRange]) {
                    button.innerHTML = `<i class="fas fa-calendar me-1"></i>${rangeText[dateRange]}`;
                }
            }
        });
    </script>
</body>
</html>
