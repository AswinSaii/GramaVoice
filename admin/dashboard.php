<?php
/**
 * Panchayat Admin Dashboard
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../config/error_handler.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

// Check if panchayat admin is logged in
requirePanchayatAdminLogin();

$db = getDB();
$admin_id = $_SESSION['admin_id'];

// Get admin details
$stmt = $db->prepare("SELECT * FROM panchayat_admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Get assigned issues
$stmt = $db->prepare("
    SELECT i.*, u.name as user_name, u.phone as user_phone 
    FROM issues i 
    JOIN users u ON i.user_id = u.id 
    WHERE i.assigned_to = ? 
    ORDER BY i.created_at DESC
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$total_issues = count($issues);
$pending_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'Pending'; }));
$in_progress_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'In Progress'; }));
$resolved_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'Resolved'; }));

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
        WHERE assigned_to = ? 
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $month_year = date('Y-m', strtotime($date));
    $stmt->bind_param("is", $admin_id, $month_year);
    $stmt->execute();
    $total_count = $stmt->get_result()->fetch_assoc()['count'];
    $total_issues_data[] = $total_count;
    
    // Get resolved issues for this month
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM issues 
        WHERE assigned_to = ? 
        AND status = 'Resolved'
        AND DATE_FORMAT(updated_at, '%Y-m') = ?
    ");
    $month_year = date('Y-m', strtotime($date));
    $stmt->bind_param("is", $admin_id, $month_year);
    $stmt->execute();
    $resolved_count = $stmt->get_result()->fetch_assoc()['count'];
    $resolved_issues_data[] = $resolved_count;
}

// Handle filter requests
$filter_period = $_GET['period'] ?? 'monthly';
$filter_status = $_GET['status'] ?? 'all';

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="issues_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Issue ID', 'Citizen Name', 'Issue Type', 'Status', 'Submitted Date', 'Description', 'Priority']);
    
    foreach ($issues as $issue) {
        $priority = 'routine';
        if ($issue['status'] === 'Pending') $priority = 'high';
        elseif ($issue['status'] === 'In Progress') $priority = 'medium';
        
        fputcsv($output, [
            '#' . str_pad($issue['id'], 3, '0', STR_PAD_LEFT),
            $issue['user_name'],
            'Village Issue',
            $issue['status'],
            date('M j, Y', strtotime($issue['created_at'])),
            substr($issue['title'], 0, 50),
            ucfirst($priority)
        ]);
    }
    
    fclose($output);
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $issue_id = (int)$_POST['issue_id'];
    $new_status = sanitizeInput($_POST['status']);
    $admin_notes = sanitizeInput($_POST['admin_notes']);
    
    try {
        // Get issue details before update
        $stmt = $db->prepare("SELECT user_id, title, status FROM issues WHERE id = ?");
        $stmt->bind_param("i", $issue_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $issue = $result->fetch_assoc();
        
        if (!$issue) {
            setFlashMessage('error', 'Issue not found.');
            header('Location: dashboard.php');
            exit();
        }
        
        $old_status = $issue['status'];
        $resolution_photo = null;
        
        // Handle resolution photo upload if status is being changed to Resolved
        if ($new_status === 'Resolved') {
            // Check if resolution photo is required and provided
            if (!isset($_FILES['resolution_photo']) || $_FILES['resolution_photo']['error'] !== UPLOAD_ERR_OK) {
                setFlashMessage('error', 'Resolution photo is required when marking issue as resolved.');
                header('Location: dashboard.php');
                exit();
            }
            
            if ($_FILES['resolution_photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/resolutions/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['resolution_photo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    setFlashMessage('error', 'Invalid file type. Only JPG, PNG, GIF, and WebP files are allowed.');
                    header('Location: dashboard.php');
                    exit();
                }
                
                $file_size = $_FILES['resolution_photo']['size'];
                if ($file_size > 5 * 1024 * 1024) { // 5MB limit
                    setFlashMessage('error', 'File size too large. Maximum size is 5MB.');
                    header('Location: dashboard.php');
                    exit();
                }
                
                $resolution_photo = 'resolution_' . $issue_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $resolution_photo;
                
                if (!move_uploaded_file($_FILES['resolution_photo']['tmp_name'], $upload_path)) {
                    setFlashMessage('error', 'Failed to upload resolution photo.');
                    header('Location: dashboard.php');
                    exit();
                }
            }
        }
        
        // Update issue status and resolution photo
        if ($resolution_photo) {
            $stmt = $db->prepare("UPDATE issues SET status = ?, admin_notes = ?, resolution_photo = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND assigned_to = ?");
            $stmt->bind_param("sssii", $new_status, $admin_notes, $resolution_photo, $issue_id, $admin_id);
        } else {
            $stmt = $db->prepare("UPDATE issues SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND assigned_to = ?");
            $stmt->bind_param("ssii", $new_status, $admin_notes, $issue_id, $admin_id);
        }
        
        if ($stmt->execute()) {
            // Create notification for user if status changed
            if ($old_status !== $new_status) {
                $notification_data = [
                    'user_id' => $issue['user_id'],
                    'type' => 'issue_status',
                    'title' => 'Issue Status Updated',
                    'message' => "Your issue '{$issue['title']}' status has been updated from '$old_status' to '$new_status'.",
                    'data' => [
                        'issue_id' => $issue_id,
                        'old_status' => $old_status,
                        'new_status' => $new_status,
                        'admin_name' => $admin['name']
                    ]
                ];
                
                createNotification($notification_data);
                
                // Log the status change
                logApplicationEvent("Issue status updated", "Issue ID: $issue_id, Status: $old_status -> $new_status");
            }
            
            setFlashMessage('success', 'Issue status updated successfully!');
            header('Location: dashboard.php');
            exit();
        } else {
            setFlashMessage('error', 'Failed to update issue status.');
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Database error: ' . $e->getMessage());
    }
}


// Handle AJAX requests for real-time updates
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    // Get updated statistics
    $total_issues = count($issues);
    $pending_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'Pending'; }));
    $in_progress_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'In Progress'; }));
    $resolved_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'Resolved'; }));
    
    echo json_encode([
        'success' => true,
        'total_issues' => $total_issues,
        'pending_issues' => $pending_issues,
        'in_progress_issues' => $in_progress_issues,
        'resolved_issues' => $resolved_issues,
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
    <title>Admin Dashboard - Grama Voice</title>
    
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
        
        .search-container {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .search-box {
            background-color: var(--gray-100);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            width: 100%;
            font-size: 0.875rem;
        }
        
        .search-box:focus {
            background-color: white;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
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
            color: var(--success-green);
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
        
        .kpi-icon.appointments { background: var(--primary-blue); }
        .kpi-icon.waiting { background: var(--warning-orange); }
        .kpi-icon.completed { background: var(--success-green); }
        .kpi-icon.pending { background: var(--danger-red); }
        
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
        
        .legend-dot.primary { background: var(--primary-blue); }
        .legend-dot.success { background: var(--success-green); }
        
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
                    <i class="fas fa-microphone-alt"></i>
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
                    <a class="nav-link" href="locations.php">
                        <i class="fas fa-map-marker-alt nav-icon"></i>Locations
                    </a>
                    <a class="nav-link" href="complaints.php">
                        <i class="fas fa-clipboard-list nav-icon"></i>Complaints
                    </a>
                    <a class="nav-link" href="analytics.php">
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
                <div class="user-avatar" style="background-image: <?php echo !empty($admin['profile_image']) && file_exists('../uploads/profiles/' . $admin['profile_image']) ? 'url(../uploads/profiles/' . htmlspecialchars($admin['profile_image']) . ')' : 'none'; ?>; background-size: cover; background-position: center;">
                    <?php if (empty($admin['profile_image']) || !file_exists('../uploads/profiles/' . $admin['profile_image'])): ?>
                        <?php echo strtoupper(substr($admin['name'], 0, 1)); ?>
                    <?php endif; ?>
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
                <h1 class="page-title">Dashboard</h1>
                <div class="header-actions">
                    <?php include '../includes/notification_component.php'; ?>
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
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?export=csv">Export as CSV</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportToPDF()">Export as PDF</a></li>
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
                            <h6 class="kpi-title">Total Issues</h6>
                            <i class="fas fa-ellipsis-h" style="color: var(--gray-400);"></i>
                        </div>
                        <h2 class="kpi-value"><?php echo $total_issues; ?></h2>
                        <div class="kpi-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>↑ 10%</span>
                            <span style="color: var(--gray-500);">from last month</span>
                        </div>
                        <div class="kpi-icon appointments">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <h6 class="kpi-title">Pending Issues</h6>
                            <i class="fas fa-ellipsis-h" style="color: var(--gray-400);"></i>
                        </div>
                        <h2 class="kpi-value"><?php echo $pending_issues; ?></h2>
                        <div class="kpi-change negative">
                            <i class="fas fa-arrow-down"></i>
                            <span>↓ 2.1%</span>
                            <span style="color: var(--gray-500);">from last month</span>
                        </div>
                        <div class="kpi-icon waiting">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <h6 class="kpi-title">Resolved Issues</h6>
                            <i class="fas fa-ellipsis-h" style="color: var(--gray-400);"></i>
                        </div>
                        <h2 class="kpi-value"><?php echo $resolved_issues; ?></h2>
                        <div class="kpi-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>↑ 05%</span>
                            <span style="color: var(--gray-500);">from last day</span>
                        </div>
                        <div class="kpi-icon completed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <h6 class="kpi-title">In Progress</h6>
                            <i class="fas fa-ellipsis-h" style="color: var(--gray-400);"></i>
                        </div>
                        <h2 class="kpi-value"><?php echo $in_progress_issues; ?></h2>
                        <div class="kpi-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>↑ 13.5%</span>
                            <span style="color: var(--gray-500);">from last month</span>
                        </div>
                        <div class="kpi-icon pending">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts and Calendar Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h5 class="chart-title">Issue Resolution Statistics</h5>
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
                        
                        <div class="calendar-month">September 2024</div>
                        
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
            
            <!-- Citizens Queue & Issue List -->
            <div class="table-container">
                <div class="table-header">
                    <h5 class="table-title">Assigned Issues & Citizen Complaints</h5>
                    <div class="table-actions">
                        <button class="btn-primary">
                            <i class="fas fa-plus me-1"></i>View All Issues
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
                                <th>Submitted</th>
                                <th>Description</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($issues)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted mt-3">No issues assigned yet</h5>
                                        <p class="text-muted">Issues will appear here when they are assigned to your panchayat.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($issues as $index => $issue): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($issue['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($issue['user_name']); ?></td>
                                        <td>Village Issue</td>
                                        <td>
                                            <span class="severity-badge severity-<?php echo strtolower(str_replace(' ', '-', $issue['status'])); ?>">
                                                <?php echo $issue['status']; ?>
                                            </span>
                                        </td>
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
                                            <button class="btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#issueModal<?php echo $issue['id']; ?>">
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-footer">
                    <div class="pagination-info">Showing <?php echo count($issues); ?> of <?php echo count($issues); ?></div>
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
    
    <!-- Issue Detail Modals -->
    <?php foreach ($issues as $issue): ?>
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
                                
                                <?php if ($issue['admin_notes']): ?>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-comment me-1"></i>Your Notes:</strong>
                                        <div class="bg-light p-3 rounded mt-2">
                                            <?php echo nl2br(htmlspecialchars($issue['admin_notes'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
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
                                
                                <!-- Status Update Form -->
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <input type="hidden" name="issue_id" value="<?php echo $issue['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select" required id="statusSelect<?php echo $issue['id']; ?>" onchange="toggleResolutionPhoto(<?php echo $issue['id']; ?>)">
                                            <option value="Pending" <?php echo $issue['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="In Progress" <?php echo $issue['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="Resolved" <?php echo $issue['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3" id="resolutionPhotoDiv<?php echo $issue['id']; ?>" style="<?php echo $issue['status'] === 'Resolved' ? '' : 'display: none;'; ?>">
                                        <label class="form-label">Resolution Photo <span class="text-danger">*</span></label>
                                        <input type="file" name="resolution_photo" class="form-control" accept="image/*" id="resolutionPhoto<?php echo $issue['id']; ?>">
                                        <small class="text-muted">Upload a photo showing the issue has been resolved (JPG, PNG, GIF, WebP - Max 5MB)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea name="admin_notes" class="form-control" rows="3" 
                                                  placeholder="Add your notes or updates..."><?php echo htmlspecialchars($issue['admin_notes']); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" name="update_status" class="btn btn-primary w-100">
                                        <i class="fas fa-save me-1"></i>Update Status
                                    </button>
                                </form>
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
        // Toggle resolution photo field based on status selection
        function toggleResolutionPhoto(issueId) {
            const statusSelect = document.getElementById('statusSelect' + issueId);
            const resolutionPhotoDiv = document.getElementById('resolutionPhotoDiv' + issueId);
            const resolutionPhotoInput = document.getElementById('resolutionPhoto' + issueId);
            
            if (statusSelect.value === 'Resolved') {
                resolutionPhotoDiv.style.display = 'block';
                resolutionPhotoInput.required = true;
            } else {
                resolutionPhotoDiv.style.display = 'none';
                resolutionPhotoInput.required = false;
                resolutionPhotoInput.value = '';
            }
        }
        
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
            // Create a new window with the chart
            const chartCanvas = document.getElementById('issuesChart');
            const chartImage = chartCanvas.toDataURL('image/png');
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Issues Report - ${new Date().toLocaleDateString()}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .chart-container { text-align: center; margin: 20px 0; }
                            .stats { display: flex; justify-content: space-around; margin: 20px 0; }
                            .stat-item { text-align: center; }
                            .stat-value { font-size: 24px; font-weight: bold; color: #2563eb; }
                            .stat-label { color: #6b7280; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>Grama Voice - Issues Report</h1>
                            <p>Generated on: ${new Date().toLocaleDateString()}</p>
                        </div>
                        <div class="stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $total_issues; ?></div>
                                <div class="stat-label">Total Issues</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $resolved_issues; ?></div>
                                <div class="stat-label">Resolved</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $pending_issues; ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $in_progress_issues; ?></div>
                                <div class="stat-label">In Progress</div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <img src="${chartImage}" style="max-width: 100%; height: auto;">
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
                        document.querySelector('.kpi-card:nth-child(1) .kpi-value').textContent = data.total_issues;
                        document.querySelector('.kpi-card:nth-child(2) .kpi-value').textContent = data.pending_issues;
                        document.querySelector('.kpi-card:nth-child(3) .kpi-value').textContent = data.resolved_issues;
                        document.querySelector('.kpi-card:nth-child(4) .kpi-value').textContent = data.in_progress_issues;
                        
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
        
        // Initialize chart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initChart();
            
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
