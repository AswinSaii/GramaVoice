<?php
/**
 * Super Admin Dashboard
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if super admin is logged in
requireSuperAdminLogin();

$db = getDB();
$super_admin_id = $_SESSION['super_admin_id'];

// Get super admin details
$stmt = $db->prepare("SELECT * FROM super_admin WHERE id = ?");
$stmt->bind_param("i", $super_admin_id);
$stmt->execute();
$super_admin = $stmt->get_result()->fetch_assoc();

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

// Get recent issues
$recent_issues = $db->getConnection()->query("
    SELECT i.*, u.name as user_name, u.phone as user_phone, pa.name as admin_name, pa.village_name 
    FROM issues i 
    JOIN users u ON i.user_id = u.id 
    LEFT JOIN panchayat_admins pa ON i.assigned_to = pa.id 
    ORDER BY i.created_at DESC 
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);


// Get enhanced panchayat performance for leaderboards
$panchayat_performance = $db->getConnection()->query("
    SELECT 
        pa.id, 
        pa.name, 
        pa.phone, 
        pa.village_name,
        pa.created_at as admin_since,
        COUNT(i.id) as total_issues,
        COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) as resolved_issues,
        COUNT(CASE WHEN i.status = 'Pending' THEN 1 END) as pending_issues,
        COUNT(CASE WHEN i.status = 'In Progress' THEN 1 END) as in_progress_issues,
        CASE 
            WHEN COUNT(i.id) = 0 THEN 0
            ELSE ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100)
        END as resolution_rate,
        AVG(CASE WHEN i.status = 'Resolved' THEN DATEDIFF(i.updated_at, i.created_at) END) as avg_resolution_days,
        COUNT(CASE WHEN i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as monthly_issues,
        COUNT(CASE WHEN i.status = 'Resolved' AND i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as monthly_resolved,
        CASE 
            WHEN COUNT(i.id) = 0 THEN 0
            ELSE ROUND((COUNT(CASE WHEN i.status = 'Resolved' AND i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) / COUNT(CASE WHEN i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END)) * 100)
        END as monthly_resolution_rate,
        CASE 
            WHEN COUNT(i.id) = 0 THEN 'New'
            WHEN COUNT(i.id) >= 50 AND ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100) >= 90 THEN 'Champion'
            WHEN COUNT(i.id) >= 30 AND ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100) >= 80 THEN 'Expert'
            WHEN COUNT(i.id) >= 20 AND ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100) >= 70 THEN 'Professional'
            WHEN COUNT(i.id) >= 10 THEN 'Active'
            ELSE 'Beginner'
        END as performance_tier,
        CASE 
            WHEN COUNT(i.id) = 0 THEN 0
            WHEN COUNT(i.id) >= 50 AND ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100) >= 90 THEN 100
            WHEN COUNT(i.id) >= 30 AND ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100) >= 80 THEN 85
            WHEN COUNT(i.id) >= 20 AND ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100) >= 70 THEN 70
            WHEN COUNT(i.id) >= 10 THEN 55
            ELSE 30
        END as performance_score
    FROM panchayat_admins pa 
    LEFT JOIN issues i ON pa.id = i.assigned_to 
    GROUP BY pa.id, pa.name, pa.phone, pa.village_name, pa.created_at
    ORDER BY performance_score DESC, resolution_rate DESC, total_issues DESC
")->fetch_all(MYSQLI_ASSOC);

// Get all users for management
$all_users = $db->getConnection()->query("
    SELECT u.*, COUNT(i.id) as total_issues,
           COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) as resolved_issues
    FROM users u 
    LEFT JOIN issues i ON u.id = i.user_id 
    WHERE u.verified = 1
    GROUP BY u.id, u.name, u.phone, u.created_at
    ORDER BY u.created_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

// Handle filter requests
$filter_period = $_GET['period'] ?? 'monthly';
$filter_status = $_GET['status'] ?? 'all';

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $report_type = $_GET['report_type'] ?? 'comprehensive';
    
    if ($export_type === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="superadmin_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($report_type === 'comprehensive') {
            fputcsv($output, ['Issue ID', 'Citizen Name', 'Issue Type', 'Status', 'Assigned To', 'Village', 'Submitted Date', 'Description', 'Priority', 'Resolution Days']);
            
            foreach ($recent_issues as $issue) {
                $priority = 'routine';
                if ($issue['status'] === 'Pending') $priority = 'high';
                elseif ($issue['status'] === 'In Progress') $priority = 'medium';
                
                $resolution_days = $issue['status'] === 'Resolved' ? 
                    DATEDIFF($issue['updated_at'], $issue['created_at']) : 'N/A';
                
                fputcsv($output, [
                    '#' . str_pad($issue['id'], 3, '0', STR_PAD_LEFT),
                    $issue['user_name'],
                    'Village Issue',
                    $issue['status'],
                    $issue['admin_name'] ?: 'Unassigned',
                    $issue['village_name'] ?: 'N/A',
                    date('M j, Y', strtotime($issue['created_at'])),
                    substr($issue['title'], 0, 50),
                    ucfirst($priority),
                    $resolution_days
                ]);
            }
        } elseif ($report_type === 'panchayat_performance') {
            fputcsv($output, ['Rank', 'Panchayat Admin', 'Village', 'Total Issues', 'Resolved', 'Pending', 'Resolution Rate', 'Performance Tier', 'Avg Resolution Days']);
            
            foreach ($panchayat_performance as $index => $panchayat) {
                fputcsv($output, [
                    $index + 1,
                    $panchayat['name'],
                    $panchayat['village_name'],
                    $panchayat['total_issues'],
                    $panchayat['resolved_issues'],
                    $panchayat['pending_issues'],
                    $panchayat['resolution_rate'] . '%',
                    $panchayat['performance_tier'],
                    round($panchayat['avg_resolution_days'], 1)
                ]);
            }
        }
        
        fclose($output);
        exit();
    }
    
    if ($export_type === 'excel') {
        // For Excel export, we'll create a more comprehensive report
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="superadmin_report_' . date('Y-m-d') . '.xlsx"');
        
        // Simple Excel-like CSV format with multiple sheets simulation
        $output = fopen('php://output', 'w');
        
        // Summary Sheet
        fputcsv($output, ['Grama Voice - Super Admin Report']);
        fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        fputcsv($output, ['SUMMARY STATISTICS']);
        fputcsv($output, ['Total Users', $stats['total_users']]);
        fputcsv($output, ['Total Issues', $stats['total_issues']]);
        fputcsv($output, ['Resolved Issues', $stats['resolved_issues']]);
        fputcsv($output, ['Pending Issues', $stats['pending_issues']]);
        fputcsv($output, ['In Progress Issues', $stats['in_progress_issues']]);
        fputcsv($output, ['Resolution Rate', $stats['resolution_rate'] . '%']);
        fputcsv($output, ['Average Resolution Time', $stats['avg_resolution_time'] . ' days']);
        fputcsv($output, ['Total Panchayat Admins', $stats['total_admins']]);
        fputcsv($output, []);
        
        // Panchayat Performance Sheet
        fputcsv($output, ['PANCHAYAT PERFORMANCE LEADERBOARD']);
        fputcsv($output, ['Rank', 'Admin Name', 'Village', 'Total Issues', 'Resolved', 'Resolution Rate', 'Performance Tier', 'Avg Resolution Days']);
        
        foreach ($panchayat_performance as $index => $panchayat) {
            fputcsv($output, [
                $index + 1,
                $panchayat['name'],
                $panchayat['village_name'],
                $panchayat['total_issues'],
                $panchayat['resolved_issues'],
                $panchayat['resolution_rate'] . '%',
                $panchayat['performance_tier'],
                round($panchayat['avg_resolution_days'], 1)
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_admin'])) {
        $name = sanitizeInput($_POST['name']);
        $phone = sanitizeInput($_POST['phone']);
        $password = password_hash(sanitizeInput($_POST['password']), PASSWORD_DEFAULT);
        $village_name = sanitizeInput($_POST['village_name']);
        
        try {
            $stmt = $db->prepare("INSERT INTO panchayat_admins (name, phone, password, village_name) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $phone, $password, $village_name);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Panchayat admin added successfully!');
            } else {
                setFlashMessage('error', 'Failed to add panchayat admin.');
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
    }
    
    if (isset($_POST['delete_admin'])) {
        $admin_id = (int)$_POST['admin_id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM panchayat_admins WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Panchayat admin deleted successfully!');
            } else {
                setFlashMessage('error', 'Failed to delete panchayat admin.');
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
    }
    
    if (isset($_POST['assign_issue'])) {
        $issue_id = (int)$_POST['issue_id'];
        $admin_id = (int)$_POST['admin_id'];
        
        try {
            $stmt = $db->prepare("UPDATE issues SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("ii", $admin_id, $issue_id);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Issue assigned successfully!');
            } else {
                setFlashMessage('error', 'Failed to assign issue.');
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
    }
    
    if (isset($_POST['block_user'])) {
        $user_id = (int)$_POST['user_id'];
        
        try {
            $stmt = $db->prepare("UPDATE users SET verified = 0 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'User blocked successfully!');
            } else {
                setFlashMessage('error', 'Failed to block user.');
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
    }
    
    if (isset($_POST['unblock_user'])) {
        $user_id = (int)$_POST['user_id'];
        
        try {
            $stmt = $db->prepare("UPDATE users SET verified = 1 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'User unblocked successfully!');
            } else {
                setFlashMessage('error', 'Failed to unblock user.');
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
    }
    
    header('Location: dashboard.php');
    exit();
}

// Handle AJAX requests for real-time updates
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    // Get updated statistics
    $result = $db->getConnection()->query("SELECT COUNT(*) as count FROM users WHERE verified = 1");
    $total_users = $result->fetch_assoc()['count'];
    
    $result = $db->getConnection()->query("SELECT COUNT(*) as count FROM issues");
    $total_issues = $result->fetch_assoc()['count'];
    
    $result = $db->getConnection()->query("SELECT COUNT(*) as count FROM issues WHERE status = 'Resolved'");
    $resolved_issues = $result->fetch_assoc()['count'];
    
    $result = $db->getConnection()->query("SELECT COUNT(*) as count FROM issues WHERE status = 'Pending'");
    $pending_issues = $result->fetch_assoc()['count'];
    
    $result = $db->getConnection()->query("SELECT COUNT(*) as count FROM issues WHERE status = 'In Progress'");
    $in_progress_issues = $result->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'total_users' => $total_users,
        'total_issues' => $total_issues,
        'resolved_issues' => $resolved_issues,
        'pending_issues' => $pending_issues,
        'in_progress_issues' => $in_progress_issues,
        'chart_data' => [
            'total_issues' => $total_issues_data,
            'resolved_issues' => $resolved_issues_data
        ]
    ]);
    exit();
}

// Get flash messages
$success_message = getFlashMessage('success');
$error_message = getFlashMessage('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Grama Voice</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="shortcut icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="apple-touch-icon" href="../images/GramaVoice-Logo.png">
    
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
            color: white !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            background: none !important;
            -webkit-background-clip: unset !important;
            -webkit-text-fill-color: white !important;
            background-clip: unset !important;
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
            color: white !important;
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
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333333;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
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
        
        /* KPI Cards */
        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--gray-200);
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .kpi-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .kpi-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
            margin: 0;
        }
        
        .kpi-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
            line-height: 1;
        }
        
        .kpi-change {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }
        
        .kpi-change.positive {
            color: white;
        }
        
        .kpi-change.negative {
            color: var(--danger-red);
        }
        
        .kpi-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        
        .kpi-icon.users { background: var(--primary-blue); }
        .kpi-icon.issues { background: var(--warning-orange); }
        .kpi-icon.resolved { background: var(--success-green); }
        .kpi-icon.admins { background: var(--danger-red); }
        
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
        
        .chart-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .chart-legend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        /* Enhanced Chart Container */
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .chart-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1f2937;
            margin: 0;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .chart-controls {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        
        .chart-legend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 15px;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .chart-legend:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.8);
        }
        
        .chart-legend span {
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        
        /* Beautiful Leaderboard Styles */
        .leaderboard-table {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
            backdrop-filter: blur(10px);
        }
        
        .leaderboard-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
            padding: 1.5rem 1rem;
            border: none;
            position: relative;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        
        .leaderboard-table th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, rgba(255,255,255,0.4) 0%, transparent 50%, rgba(255,255,255,0.4) 100%);
        }
        
        .leaderboard-table tbody tr {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            position: relative;
        }
        
        .leaderboard-table tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            transform: translateX(8px) scale(1.02);
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            z-index: 10;
        }
        
        .leaderboard-table tbody tr:hover::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }
        
        .leaderboard-table tbody tr:last-child {
            border-bottom: none;
        }
        
        .leaderboard-table td {
            padding: 1.2rem 1rem;
            vertical-align: middle;
            border: none;
        }
        
        /* Enhanced Rank Styling */
        .rank-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .trophy-icon {
            font-size: 1.5rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
            animation: trophyGlow 2s ease-in-out infinite alternate;
        }
        
        @keyframes trophyGlow {
            0% { filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); }
            100% { filter: drop-shadow(0 2px 8px rgba(255,215,0,0.4)); }
        }
        
        .rank-number {
            font-size: 1.2rem;
            font-weight: 800;
            color: #1f2937;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        /* Enhanced Admin Info */
        .admin-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .admin-name {
            font-size: 1rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        
        .admin-phone {
            font-size: 0.8rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .village-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        /* Beautiful Performance Tier Badges */
        .performance-tier-badge {
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .performance-tier-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }
        
        .performance-tier-badge:hover::before {
            left: 100%;
        }
        
        .performance-tier-badge:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .tier-champion { 
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); 
            color: #92400e; 
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4);
        }
        .tier-expert { 
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); 
            color: white; 
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }
        .tier-professional { 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
            color: white; 
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        .tier-active { 
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); 
            color: white; 
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
        .tier-beginner { 
            background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%); 
            color: white; 
            box-shadow: 0 4px 15px rgba(156, 163, 175, 0.4);
        }
        
        /* Enhanced Statistics */
        .stat-number {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1f2937;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .stat-resolved {
            color: #10b981;
            font-weight: 800;
        }
        
        /* Beautiful Progress Bars */
        .mini-progress {
            background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
            width: 80px;
            height: 10px;
        }
        
        .mini-progress-bar {
            height: 100% !important;
            border-radius: 10px;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .mini-progress-bar.bg-success {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%) !important;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .mini-progress-bar.bg-warning {
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%) !important;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        
        .mini-progress-bar.bg-danger {
            background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%) !important;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }
        
        /* Enhanced Action Buttons */
        .action-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            border-radius: 12px;
            padding: 0.6rem 1.2rem;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .action-btn:hover::before {
            left: 100%;
        }
        
        .action-btn:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }
        
        .action-btn:active {
            transform: translateY(0) scale(0.98);
        }
        
        /* Performance Metrics */
        .performance-metrics {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            align-items: center;
        }
        
        .metric-value {
            font-size: 1rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .metric-label {
            font-size: 0.7rem;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Table Row Loading Animation */
        .leaderboard-table tbody tr {
            animation: fadeInUp 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .leaderboard-table tbody tr:nth-child(1) { animation-delay: 0.1s; }
        .leaderboard-table tbody tr:nth-child(2) { animation-delay: 0.2s; }
        .leaderboard-table tbody tr:nth-child(3) { animation-delay: 0.3s; }
        .leaderboard-table tbody tr:nth-child(4) { animation-delay: 0.4s; }
        .leaderboard-table tbody tr:nth-child(5) { animation-delay: 0.5s; }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Enhanced Mobile Responsiveness */
        @media (max-width: 768px) {
            .chart-container {
                padding: 1.5rem;
                border-radius: 15px;
            }
            
            .chart-title {
                font-size: 1.3rem;
            }
            
            .chart-controls {
                gap: 1rem;
                justify-content: center;
            }
            
            .chart-legend {
                padding: 0.4rem 0.8rem;
            }
            
            .chart-legend span {
                font-size: 0.7rem;
            }
            
            .legend-dot {
                width: 10px;
                height: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .chart-container {
                padding: 1rem;
                border-radius: 12px;
            }
            
            .chart-title {
                font-size: 1.2rem;
                text-align: center;
            }
            
            .chart-controls {
                flex-direction: column;
                gap: 0.8rem;
            }
            
            .chart-legend {
                padding: 0.3rem 0.6rem;
            }
            
            .chart-legend span {
                font-size: 0.65rem;
            }
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .leaderboard-table th,
            .leaderboard-table td {
                padding: 1rem 0.8rem;
            }
            
            .admin-name {
                font-size: 0.9rem;
            }
            
            .performance-tier-badge {
                padding: 0.5rem 1rem;
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 992px) {
            .leaderboard-table {
                font-size: 0.9rem;
            }
            
            .leaderboard-table th {
                font-size: 0.75rem;
                padding: 1.2rem 0.6rem;
            }
            
            .leaderboard-table td {
                padding: 1rem 0.6rem;
            }
            
            .trophy-icon {
                font-size: 1.2rem;
            }
            
            .mini-progress {
                width: 60px;
                height: 8px;
            }
        }
        
        @media (max-width: 768px) {
            .leaderboard-table {
                border-radius: 15px;
                overflow-x: auto;
            }
            
            .leaderboard-table th,
            .leaderboard-table td {
                padding: 0.8rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .leaderboard-table th {
                font-size: 0.7rem;
                padding: 1rem 0.5rem;
            }
            
            .admin-info {
                gap: 0.1rem;
            }
            
            .admin-name {
                font-size: 0.8rem;
            }
            
            .admin-phone {
                font-size: 0.7rem;
            }
            
            .village-name {
                font-size: 0.8rem;
                padding: 0.3rem 0.6rem;
            }
            
            .performance-tier-badge {
                padding: 0.4rem 0.8rem;
                font-size: 0.65rem;
            }
            
            .stat-number {
                font-size: 1.1rem;
            }
            
            .mini-progress {
                width: 50px;
                height: 6px;
            }
            
            .action-btn {
                padding: 0.5rem 1rem;
                font-size: 0.7rem;
            }
            
            .performance-metrics {
                gap: 0.2rem;
            }
            
            .metric-value {
                font-size: 0.9rem;
            }
            
            .metric-label {
                font-size: 0.65rem;
            }
        }
        
        @media (max-width: 576px) {
            .leaderboard-table {
                border-radius: 12px;
            }
            
            .leaderboard-table th,
            .leaderboard-table td {
                padding: 0.6rem 0.4rem;
                font-size: 0.75rem;
            }
            
            .leaderboard-table th {
                font-size: 0.65rem;
                padding: 0.8rem 0.4rem;
            }
            
            .trophy-icon {
                font-size: 1rem;
            }
            
            .rank-number {
                font-size: 1rem;
            }
            
            .admin-name {
                font-size: 0.75rem;
            }
            
            .admin-phone {
                font-size: 0.65rem;
            }
            
            .village-name {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            
            .performance-tier-badge {
                padding: 0.3rem 0.6rem;
                font-size: 0.6rem;
            }
            
            .stat-number {
                font-size: 1rem;
            }
            
            .mini-progress {
                width: 40px;
                height: 5px;
            }
            
            .action-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.65rem;
            }
            
            .metric-value {
                font-size: 0.8rem;
            }
            
            .metric-label {
                font-size: 0.6rem;
            }
        }
        
        /* Ultra-Modern Leaderboard Design */
        .leaderboard-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 25px 80px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .leaderboard-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 50%, rgba(255,255,255,0.05) 100%);
            pointer-events: none;
        }
        
        .leaderboard-header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
            z-index: 2;
        }
        
        .leaderboard-title {
            font-size: 2.5rem;
            font-weight: 900;
            color: white;
            margin: 0 0 1rem 0;
            text-shadow: 0 4px 20px rgba(0,0,0,0.3);
            background: linear-gradient(45deg, #fff 0%, #f0f9ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .leaderboard-subtitle {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .performance-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            position: relative;
            z-index: 2;
        }
        
        .performance-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .performance-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .performance-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 60px rgba(0,0,0,0.15);
            background: rgba(255,255,255,0.98);
        }
        
        .card-rank {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        
        .rank-badge {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
            padding: 0.8rem 1.2rem;
            border-radius: 15px;
            font-weight: 800;
            font-size: 1.1rem;
            box-shadow: 0 8px 25px rgba(251, 191, 36, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .rank-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }
        
        .rank-badge:hover::before {
            left: 100%;
        }
        
        .trophy-icon-modern {
            font-size: 1.8rem;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.3));
            animation: trophyPulse 2s ease-in-out infinite;
        }
        
        @keyframes trophyPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .performance-tier-modern {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .admin-section {
            margin-bottom: 1.5rem;
        }
        
        .admin-name-modern {
            font-size: 1.4rem;
            font-weight: 800;
            color: #1f2937;
            margin: 0 0 0.5rem 0;
            text-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .admin-phone-modern {
            font-size: 0.9rem;
            color: #6b7280;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .village-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            margin-bottom: 1.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-value-modern {
            font-size: 1.8rem;
            font-weight: 900;
            color: #1f2937;
            margin: 0 0 0.3rem 0;
            text-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-label-modern {
            font-size: 0.8rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-resolved-modern {
            color: #10b981;
        }
        
        .progress-section {
            margin-bottom: 1.5rem;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
        }
        
        .progress-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
        }
        
        .progress-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: #1f2937;
        }
        
        .modern-progress {
            background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 100%);
            height: 12px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .modern-progress-bar {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .modern-progress-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .action-section {
            display: flex;
            justify-content: center;
            margin-top: 1.5rem;
        }
        
        .modern-action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 15px;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .modern-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .modern-action-btn:hover::before {
            left: 100%;
        }
        
        .modern-action-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
        }
        
        .modern-action-btn:active {
            transform: translateY(-1px) scale(1.02);
        }
        
        /* Responsive Design for Modern Cards */
        @media (max-width: 768px) {
            .leaderboard-container {
                padding: 1.5rem;
                border-radius: 20px;
            }
            
            .leaderboard-title {
                font-size: 2rem;
            }
            
            .performance-cards {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .performance-card {
                padding: 1.2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }
            
            .rank-badge {
                padding: 0.6rem 1rem;
                font-size: 1rem;
            }
            
            .trophy-icon-modern {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .leaderboard-container {
                padding: 1rem;
                border-radius: 16px;
            }
            
            .leaderboard-title {
                font-size: 1.8rem;
            }
            
            .performance-card {
                padding: 1rem;
            }
            
            .admin-name-modern {
                font-size: 1.2rem;
            }
            
            .stat-value-modern {
                font-size: 1.5rem;
            }
        }
        
        /* Trophy Icons */
        .trophy-icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
        
        .trophy-gold { color: #fbbf24; }
        .trophy-silver { color: #9ca3af; }
        .trophy-bronze { color: #cd7f32; }
        
        /* Progress Bars */
        .mini-progress {
            width: 60px;
            height: 8px;
            border-radius: 4px;
            background: #e5e7eb;
            overflow: hidden;
            position: relative;
        }
        
        .mini-progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        /* Enhanced Progress Bar Styles */
        .progress {
            background-color: #e5e7eb !important;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-bar {
            height: 100% !important;
            border-radius: 4px;
            transition: width 0.3s ease;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        /* Ensure progress bars are visible */
        .progress-bar.bg-success {
            background-color: #10b981 !important;
        }
        
        .progress-bar.bg-warning {
            background-color: #f59e0b !important;
        }
        
        .progress-bar.bg-danger {
            background-color: #ef4444 !important;
        }
        
        /* Export Button Enhancements */
        .export-dropdown .dropdown-menu {
            min-width: 250px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border: 1px solid #e5e7eb;
        }
        
        .export-dropdown .dropdown-header {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.5rem 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .export-dropdown .dropdown-item {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .export-dropdown .dropdown-item:hover {
            background: #f1f5f9;
            color: #2563eb;
        }
        
        /* Responsive Enhancements */
        @media (max-width: 768px) {
            
            .leaderboard-table {
                font-size: 0.875rem;
            }
            
            .leaderboard-table th,
            .leaderboard-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }
        
        .chart-select {
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            background: white;
            color: var(--gray-700);
        }
        
        /* Calendar */
        .calendar-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--gray-200);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .calendar-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        .calendar-month {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-700);
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.25rem;
            margin-bottom: 1rem;
        }
        
        .calendar-day-header {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-500);
            padding: 0.5rem 0;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .calendar-day:hover {
            background: var(--gray-100);
        }
        
        .calendar-day.has-event {
            background: var(--light-blue);
            color: var(--primary-blue);
        }
        
        .calendar-day.completed {
            background: #dcfce7;
            color: var(--success-green);
        }
        
        .calendar-day.pending {
            background: #fef3c7;
            color: var(--warning-orange);
        }
        
        .calendar-day.critical {
            background: #fee2e2;
            color: var(--danger-red);
        }
        
        .calendar-legend {
            margin-top: 1rem;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .legend-dot-small {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        
        /* Data Table */
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
        
        .table-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
        
        .severity-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .severity-high {
            background: #fee2e2;
            color: var(--danger-red);
        }
        
        .severity-medium {
            background: #fef3c7;
            color: var(--warning-orange);
        }
        
        .severity-routine {
            background: var(--light-blue);
            color: var(--primary-blue);
        }
        
        .severity-pending {
            background: #fee2e2;
            color: var(--danger-red);
        }
        
        .severity-in-progress {
            background: #fef3c7;
            color: var(--warning-orange);
        }
        
        .severity-resolved {
            background: #dcfce7;
            color: var(--success-green);
        }
        
        .table-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--gray-50);
        }
        
        .pagination-info {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .pagination {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-btn {
            width: 32px;
            height: 32px;
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--gray-600);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .page-btn:hover {
            border-color: var(--gray-400);
            background: var(--gray-50);
        }
        
        .page-btn.active {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
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
            
            .kpi-card {
                padding: 1rem;
            }
            
            .kpi-value {
                font-size: 1.5rem;
            }
            
            .chart-container,
            .calendar-container {
                padding: 1rem;
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
                    <a class="nav-link active" href="dashboard.php">
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
                    <a class="nav-link" href="analytics.php">
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
                <h1 class="page-title">Super Admin Dashboard</h1>
                <div class="header-actions">
                    <div class="dropdown">
                        <button class="btn-outline dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?status=all">All Issues</a></li>
                            <li><a class="dropdown-item" href="?status=Pending">Pending</a></li>
                            <li><a class="dropdown-item" href="?status=In Progress">In Progress</a></li>
                            <li><a class="dropdown-item" href="?status=Resolved">Resolved</a></li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <button class="btn-outline dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-1"></i>Export Reports
                        </button>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">CSV Reports</h6></li>
                            <li><a class="dropdown-item" href="?export=csv&report_type=comprehensive">Comprehensive Report</a></li>
                            <li><a class="dropdown-item" href="?export=csv&report_type=panchayat_performance">Panchayat Performance</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Excel Reports</h6></li>
                            <li><a class="dropdown-item" href="?export=excel">Complete Excel Report</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="generate_report.php?format=pdf&type=comprehensive">Generate PDF Report</a></li>
                            <li><a class="dropdown-item" href="generate_report.php?format=excel&type=comprehensive">Generate Excel Report</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportToPDF()">Quick PDF Export</a></li>
                        </ul>
                    </div>
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
            
            <!-- KPI Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <h6 class="kpi-title">Total Users</h6>
                            <i class="fas fa-ellipsis-h" style="color: var(--gray-400);"></i>
                        </div>
                        <h2 class="kpi-value"><?php echo $stats['total_users']; ?></h2>
                        <div class="kpi-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>↑ 10%</span>
                            <span style="color: var(--gray-500);">from last month</span>
                        </div>
                        <div class="kpi-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <h6 class="kpi-title">Total Issues</h6>
                            <i class="fas fa-ellipsis-h" style="color: var(--gray-400);"></i>
                        </div>
                        <h2 class="kpi-value"><?php echo $stats['total_issues']; ?></h2>
                        <div class="kpi-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>↑ 15%</span>
                            <span style="color: var(--gray-500);">from last month</span>
                        </div>
                        <div class="kpi-icon issues">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <h6 class="kpi-title">Resolved Issues</h6>
                            <i class="fas fa-ellipsis-h" style="color: var(--gray-400);"></i>
                        </div>
                        <h2 class="kpi-value"><?php echo $stats['resolved_issues']; ?></h2>
                        <div class="kpi-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>↑ 8%</span>
                            <span style="color: var(--gray-500);">from last day</span>
                        </div>
                        <div class="kpi-icon resolved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <h6 class="kpi-title">Panchayat Admins</h6>
                            <i class="fas fa-ellipsis-h" style="color: var(--gray-400);"></i>
                        </div>
                        <h2 class="kpi-value"><?php echo $stats['total_admins']; ?></h2>
                        <div class="kpi-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>↑ 2</span>
                            <span style="color: var(--gray-500);">this month</span>
                        </div>
                        <div class="kpi-icon admins">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts and Calendar Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h5 class="chart-title">System-wide Issue Resolution Statistics</h5>
                            <div class="chart-controls">
                                <div class="chart-legend">
                                    <div class="legend-dot primary"></div>
                                    <span>Total Issues</span>
                                </div>
                                <div class="chart-legend">
                                    <div class="legend-dot success"></div>
                                    <span>Resolved Issues</span>
                                </div>
                                <select class="chart-select" id="periodSelect" onchange="updateChart()">
                                    <option value="monthly" <?php echo $filter_period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="weekly" <?php echo $filter_period === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="daily" <?php echo $filter_period === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                </select>
                            </div>
                        </div>
                        <canvas id="issuesChart" style="height: 300px !important; max-height: 300px !important;"></canvas>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <h5 class="calendar-title">Calendar</h5>
                        </div>
                        
                        <div class="calendar-month"><?php echo date('F Y'); ?></div>
                        
                        <div class="calendar-grid">
                            <div class="calendar-day-header">Sun</div>
                            <div class="calendar-day-header">Mon</div>
                            <div class="calendar-day-header">Tue</div>
                            <div class="calendar-day-header">Wed</div>
                            <div class="calendar-day-header">Thu</div>
                            <div class="calendar-day-header">Fri</div>
                            <div class="calendar-day-header">Sat</div>
                            
                            <?php
                            $days = ['', '', '', '', '', '', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30'];
                            $eventTypes = [3 => 'completed', 5 => 'pending', 7 => 'critical', 15 => 'completed', 22 => 'pending', 25 => 'critical'];
                            
                            foreach ($days as $day): ?>
                                <div class="calendar-day <?php echo isset($eventTypes[$day]) ? $eventTypes[$day] : ''; ?>">
                                    <?php echo $day; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="calendar-legend">
                            <div class="legend-item">
                                <div class="legend-dot-small" style="background: var(--success-green);"></div>
                                <span>Completed</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot-small" style="background: var(--warning-orange);"></div>
                                <span>Pending</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot-small" style="background: var(--danger-red);"></div>
                                <span>Critical</span>
                            </div>
                        </div>
                        
                        <button class="btn-primary w-100 mt-3">View details</button>
                    </div>
                </div>
            </div>
            
            
            <!-- Ultra-Modern Panchayat Performance Leaderboards -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="leaderboard-container">
                        <div class="leaderboard-header">
                            <h2 class="leaderboard-title">🏆 Performance Champions</h2>
                            <p class="leaderboard-subtitle">Top performing panchayat administrators</p>
                        </div>
                        
                        <div class="performance-cards">
                            <?php foreach ($panchayat_performance as $index => $panchayat): ?>
                                <div class="performance-card">
                                    <div class="card-rank">
                                        <div class="rank-badge">
                                            <?php if ($index < 3): ?>
                                                <i class="fas fa-trophy trophy-icon-modern text-white"></i>
                                            <?php endif; ?>
                                            <span>#<?php echo $index + 1; ?></span>
                                        </div>
                                        <span class="performance-tier-modern"><?php echo $panchayat['performance_tier']; ?></span>
                                    </div>
                                    
                                    <div class="admin-section">
                                        <h3 class="admin-name-modern"><?php echo htmlspecialchars($panchayat['name']); ?></h3>
                                        <div class="admin-phone-modern">
                                            <i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($panchayat['phone']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="village-badge">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($panchayat['village_name']); ?>
                                    </div>
                                    
                                    <div class="stats-grid">
                                        <div class="stat-item">
                                            <div class="stat-value-modern"><?php echo $panchayat['total_issues']; ?></div>
                                            <div class="stat-label-modern">Total Issues</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-value-modern stat-resolved-modern"><?php echo $panchayat['resolved_issues']; ?></div>
                                            <div class="stat-label-modern">Resolved</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-value-modern"><?php echo $panchayat['monthly_issues']; ?></div>
                                            <div class="stat-label-modern">This Month</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-value-modern">
                                                <?php echo $panchayat['avg_resolution_days'] ? round($panchayat['avg_resolution_days'], 1) : 'N/A'; ?>
                                            </div>
                                            <div class="stat-label-modern">Avg Days</div>
                                        </div>
                                    </div>
                                    
                                    <div class="progress-section">
                                        <div class="progress-header">
                                            <span class="progress-label">Resolution Rate</span>
                                            <span class="progress-value"><?php echo $panchayat['resolution_rate']; ?>%</span>
                                        </div>
                                        <div class="modern-progress">
                                            <div class="modern-progress-bar" style="width: <?php echo $panchayat['resolution_rate']; ?>%;"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="action-section">
                                        <button class="modern-action-btn" onclick="viewPanchayatDetails(<?php echo $panchayat['id']; ?>)">
                                            <i class="fas fa-eye me-2"></i>View Details
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Overview Table -->
            <div class="table-container">
                <div class="table-header">
                    <h5 class="table-title">System Overview & Issue Management</h5>
                    <div class="table-actions">
                        <button class="btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                            <i class="fas fa-plus me-1"></i>Add Admin
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Issue ID</th>
                                <th>Citizen Name</th>
                                <th>Issue Type</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Submitted</th>
                                <th>Description</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_issues)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted mt-3">No issues in system</h5>
                                        <p class="text-muted">Issues will appear here when citizens submit complaints.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_issues as $index => $issue): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($issue['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($issue['user_name']); ?></td>
                                        <td>Village Issue</td>
                                        <td>
                                            <span class="severity-badge severity-<?php echo strtolower(str_replace(' ', '-', $issue['status'])); ?>">
                                                <?php echo $issue['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $issue['admin_name'] ? htmlspecialchars($issue['admin_name']) : 'Unassigned'; ?></td>
                                        <td><?php echo date('M j, g:i A', strtotime($issue['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr($issue['title'], 0, 20)) . '...'; ?></td>
                                        <td>
                                            <?php
                                            $priority = 'routine';
                                            if ($issue['status'] === 'Pending') $priority = 'high';
                                            elseif ($issue['status'] === 'In Progress') $priority = 'medium';
                                            ?>
                                            <span class="severity-badge severity-<?php echo $priority; ?>">
                                                <?php echo ucfirst($priority); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!$issue['assigned_to']): ?>
                                                <button class="btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#assignModal" 
                                                        data-issue-id="<?php echo $issue['id']; ?>">
                                                    <i class="fas fa-user-plus me-1"></i>Assign
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#issueModal<?php echo $issue['id']; ?>">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-footer">
                    <div class="pagination-info">Showing <?php echo count($recent_issues); ?> of <?php echo count($recent_issues); ?></div>
                    <div class="pagination">
                        <button class="page-btn">&lt;</button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <button class="page-btn">4</button>
                        <button class="page-btn">5</button>
                        <span style="margin: 0 0.5rem;">...</span>
                        <button class="page-btn">400</button>
                        <button class="page-btn">&gt;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Panchayat Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Village Name</label>
                            <input type="text" name="village_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_admin" class="btn btn-primary">Add Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Assign Issue Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="issue_id" id="assignIssueId">
                        <div class="mb-3">
                            <label class="form-label">Assign to Admin</label>
                            <select name="admin_id" class="form-select" required>
                                <option value="">Select Admin</option>
                                <?php
                                $admins = $db->getConnection()->query("SELECT id, name, village_name FROM panchayat_admins ORDER BY village_name");
                                while ($admin = $admins->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $admin['id']; ?>">
                                        <?php echo htmlspecialchars($admin['name'] . ' - ' . $admin['village_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_issue" class="btn btn-primary">Assign Issue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Issue Detail Modals -->
    <?php foreach ($recent_issues as $issue): ?>
        <div class="modal fade" id="issueModal<?php echo $issue['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Issue Details - <?php echo htmlspecialchars($issue['title']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="fw-bold">Description</h6>
                                <p class="text-muted mb-3"><?php echo nl2br(htmlspecialchars($issue['description'])); ?></p>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-user me-1"></i>Reporter:</strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($issue['user_name']); ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-phone me-1"></i>Phone:</strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($issue['user_phone']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <strong><i class="fas fa-map-marker-alt me-1"></i>Location:</strong>
                                    <span class="text-muted"><?php echo htmlspecialchars($issue['location']); ?></span>
                                    
                                    <?php if (!empty($issue['latitude']) && !empty($issue['longitude'])): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-crosshairs me-1"></i>
                                                GPS: <?php echo number_format($issue['latitude'], 6); ?>, <?php echo number_format($issue['longitude'], 6); ?>
                                                <?php if (!empty($issue['location_accuracy'])): ?>
                                                    (±<?php echo round($issue['location_accuracy']); ?>m)
                                                <?php endif; ?>
                                            </small>
                                            <br>
                                            <a href="https://www.google.com/maps?q=<?php echo $issue['latitude']; ?>,<?php echo $issue['longitude']; ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                                <i class="fas fa-external-link-alt me-1"></i>View on Map
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong><i class="fas fa-calendar me-1"></i>Submitted:</strong>
                                    <span class="text-muted"><?php echo formatDate($issue['created_at']); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong><i class="fas fa-user-tie me-1"></i>Assigned To:</strong>
                                    <span class="text-muted"><?php echo $issue['admin_name'] ? htmlspecialchars($issue['admin_name']) : 'Unassigned'; ?></span>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <?php if ($issue['photo']): ?>
                                    <div class="mb-3">
                                        <img src="../uploads/issues/<?php echo htmlspecialchars($issue['photo']); ?>" 
                                             class="img-fluid rounded issue-image-clickable" 
                                             alt="Issue Photo"
                                             data-bs-toggle="modal" 
                                             data-bs-target="#imageModal"
                                             data-image="../uploads/issues/<?php echo htmlspecialchars($issue['photo']); ?>"
                                             style="cursor: pointer; max-height: 200px; object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <strong>Status:</strong>
                                    <span class="severity-badge severity-<?php echo strtolower(str_replace(' ', '-', $issue['status'])); ?>">
                                        <?php echo $issue['status']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Issue Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" class="img-fluid" alt="Issue Photo">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Image modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const imageModal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            
            if (imageModal && modalImage) {
                imageModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const imageSrc = button.dataset.image;
                    modalImage.src = imageSrc;
                });
            }
        });
        
        // Real-time chart data from PHP
        const chartData = {
            labels: <?php echo json_encode($months); ?>,
            totalIssues: <?php echo json_encode($total_issues_data); ?>,
            resolvedIssues: <?php echo json_encode($resolved_issues_data); ?>
        };
        
        let issuesChart;
        
        // Initialize Chart
        function initChart() {
            const issuesCtx = document.getElementById('issuesChart').getContext('2d');
            issuesChart = new Chart(issuesCtx, {
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
                            display: false
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
        }
        
        // Update chart based on period selection
        function updateChart() {
            const period = document.getElementById('periodSelect').value;
            window.location.href = `?period=${period}`;
        }
        
        // Export to PDF function
        function exportToPDF() {
            // Create a new window with comprehensive report
            const chartCanvas = document.getElementById('issuesChart');
            const chartImage = chartCanvas.toDataURL('image/png');
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Grama Voice - Super Admin Report - ${new Date().toLocaleDateString()}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2563eb; padding-bottom: 20px; }
                            .chart-container { text-align: center; margin: 20px 0; }
                            .stats { display: flex; justify-content: space-around; margin: 20px 0; flex-wrap: wrap; }
                            .stat-item { text-align: center; margin: 10px; }
                            .stat-value { font-size: 24px; font-weight: bold; color: #2563eb; }
                            .stat-label { color: #6b7280; font-size: 14px; }
                            .section { margin: 30px 0; }
                            .section h3 { color: #2563eb; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; }
                            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                            th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
                            th { background-color: #f3f4f6; font-weight: bold; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>Grama Voice - Super Admin Dashboard Report</h1>
                            <p>Generated on: ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
                        </div>
                        
                        <div class="section">
                            <h3>System Overview</h3>
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
                                    <div class="stat-value"><?php echo $stats['resolved_issues']; ?></div>
                                    <div class="stat-label">Resolved Issues</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['total_admins']; ?></div>
                                    <div class="stat-label">Panchayat Admins</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['resolution_rate']; ?>%</div>
                                    <div class="stat-label">Resolution Rate</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['avg_resolution_time']; ?> days</div>
                                    <div class="stat-label">Avg Resolution Time</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="section">
                            <h3>Issue Resolution Trends</h3>
                            <div class="chart-container">
                                <img src="${chartImage}" style="max-width: 100%; height: auto;">
                            </div>
                        </div>
                        
                        
                        <div class="section">
                            <h3>Panchayat Performance Leaderboard</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Admin Name</th>
                                        <th>Village</th>
                                        <th>Performance Tier</th>
                                        <th>Total Issues</th>
                                        <th>Resolved</th>
                                        <th>Resolution Rate</th>
                                        <th>Avg Resolution Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($panchayat_performance as $index => $panchayat): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($panchayat['name']); ?></td>
                                            <td><?php echo htmlspecialchars($panchayat['village_name']); ?></td>
                                            <td><?php echo $panchayat['performance_tier']; ?></td>
                                            <td><?php echo $panchayat['total_issues']; ?></td>
                                            <td><?php echo $panchayat['resolved_issues']; ?></td>
                                            <td><?php echo $panchayat['resolution_rate']; ?>%</td>
                                            <td><?php echo $panchayat['avg_resolution_days'] ? round($panchayat['avg_resolution_days'], 1) . ' days' : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        
        // Real-time data update
        function updateRealTimeData() {
            fetch('dashboard.php?ajax=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update KPI cards
                        document.querySelector('.kpi-card:nth-child(1) .kpi-value').textContent = data.total_users;
                        document.querySelector('.kpi-card:nth-child(2) .kpi-value').textContent = data.total_issues;
                        document.querySelector('.kpi-card:nth-child(3) .kpi-value').textContent = data.resolved_issues;
                        
                        // Update chart data
                        if (issuesChart) {
                            issuesChart.data.datasets[0].data = data.chart_data.total_issues;
                            issuesChart.data.datasets[1].data = data.chart_data.resolved_issues;
                            issuesChart.update();
                        }
                    }
                })
                .catch(error => console.error('Error updating data:', error));
        }
        
        // Assign modal functionality
        const assignModal = document.getElementById('assignModal');
        assignModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const issueId = button.dataset.issueId;
            document.getElementById('assignIssueId').value = issueId;
        });
        
        // Initialize progress bars
        function initializeProgressBars() {
            const progressBars = document.querySelectorAll('.progress-bar, .mini-progress-bar');
            progressBars.forEach((bar, index) => {
                const width = bar.style.width;
                console.log(`Progress bar ${index}: ${width}`);
                
                // Reset to 0 and animate to target width
                bar.style.width = '0%';
                bar.style.opacity = '0';
                
                setTimeout(() => {
                    bar.style.width = width;
                    bar.style.opacity = '1';
                }, 100 + (index * 50)); // Stagger animations
            });
        }
        
        // Debug function to check progress bar values
        function debugProgressBars() {
            const progressBars = document.querySelectorAll('.progress-bar, .mini-progress-bar');
            console.log('Progress Bars Debug:');
            progressBars.forEach((bar, index) => {
                console.log(`Bar ${index}:`, {
                    width: bar.style.width,
                    backgroundColor: bar.style.backgroundColor,
                    computedStyle: window.getComputedStyle(bar).width
                });
            });
        }
        
        // Initialize chart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initChart();
            initializeProgressBars();
            
            // Update data every 30 seconds
            setInterval(updateRealTimeData, 30000);
        });
        
        // Add smooth animations to KPI cards
        document.querySelectorAll('.kpi-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>
