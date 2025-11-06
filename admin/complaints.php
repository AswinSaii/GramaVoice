<?php
/**
 * Complaints Management Page
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

// Handle filter requests
$filter_status = $_GET['status'] ?? 'all';
$filter_search = $_GET['search'] ?? '';

// Build the query with filters
$where_conditions = ["i.assigned_to = ?"];
$params = [$admin_id];
$param_types = "i";

if ($filter_status !== 'all') {
    $where_conditions[] = "i.status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

if (!empty($filter_search)) {
    $where_conditions[] = "(i.title LIKE ? OR i.description LIKE ? OR u.name LIKE ?)";
    $search_param = "%$filter_search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

$where_clause = implode(" AND ", $where_conditions);

// Get all complaints/issues assigned to this admin with detailed information
$stmt = $db->prepare("
    SELECT i.*, u.name as user_name, u.phone as user_phone 
    FROM issues i 
    JOIN users u ON i.user_id = u.id 
    WHERE $where_clause
    ORDER BY i.created_at DESC
");
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="complaints_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Complaint ID', 'Title', 'Citizen Name', 'Phone', 'Status', 'Priority', 'Location', 'Submitted Date', 'Description']);
    
    foreach ($complaints as $complaint) {
        $priority = 'routine';
        if ($complaint['status'] === 'Pending') $priority = 'high';
        elseif ($complaint['status'] === 'In Progress') $priority = 'medium';
        
        fputcsv($output, [
            '#' . str_pad($complaint['id'], 3, '0', STR_PAD_LEFT),
            $complaint['title'],
            $complaint['user_name'],
            $complaint['user_phone'],
            $complaint['status'],
            ucfirst($priority),
            $complaint['location'],
            date('M j, Y', strtotime($complaint['created_at'])),
            substr($complaint['description'], 0, 100)
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
            header('Location: complaints.php');
            exit();
        }
        
        $old_status = $issue['status'];
        $resolution_photo = null;
        
        // Handle resolution photo upload if status is being changed to Resolved
        if ($new_status === 'Resolved') {
            // Check if resolution photo is required and provided
            if (!isset($_FILES['resolution_photo']) || $_FILES['resolution_photo']['error'] !== UPLOAD_ERR_OK) {
                setFlashMessage('error', 'Resolution photo is required when marking issue as resolved.');
                header('Location: complaints.php');
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
                    header('Location: complaints.php');
                    exit();
                }
                
                $file_size = $_FILES['resolution_photo']['size'];
                if ($file_size > 5 * 1024 * 1024) { // 5MB limit
                    setFlashMessage('error', 'File size too large. Maximum size is 5MB.');
                    header('Location: complaints.php');
                    exit();
                }
                
                $resolution_photo = 'resolution_' . $issue_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $resolution_photo;
                
                if (!move_uploaded_file($_FILES['resolution_photo']['tmp_name'], $upload_path)) {
                    setFlashMessage('error', 'Failed to upload resolution photo.');
                    header('Location: complaints.php');
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
            
            setFlashMessage('success', 'Complaint status updated successfully!');
            header('Location: complaints.php');
            exit();
        } else {
            setFlashMessage('error', 'Failed to update complaint status.');
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Database error: ' . $e->getMessage());
    }
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
    <title>Complaints - Grama Voice</title>

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
        
        .complaint-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--light-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-blue);
            font-size: 1.2rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .status-pending {
            background: #fee2e2;
            color: var(--danger-red);
        }
        
        .status-in-progress {
            background: #fef3c7;
            color: var(--warning-orange);
        }
        
        .status-resolved {
            background: #dcfce7;
            color: var(--success-green);
        }
        
        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .priority-high {
            background: #fee2e2;
            color: var(--danger-red);
        }
        
        .priority-medium {
            background: #fef3c7;
            color: var(--warning-orange);
        }
        
        .priority-routine {
            background: var(--light-blue);
            color: var(--primary-blue);
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
                    <a class="nav-link active" href="complaints.php">
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
                <h1 class="page-title">Complaints</h1>
                <div class="header-actions">
                    <div class="dropdown">
                        <button class="btn-outline dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?status=all">All Complaints</a></li>
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
            
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon primary">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?php echo count($complaints); ?></h3>
                        <p class="text-muted mb-0">Total Complaints</p>
                        <small class="text-primary">All complaints</small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?php echo count(array_filter($complaints, function($c) { return $c['status'] === 'Resolved'; })); ?></h3>
                        <p class="text-muted mb-0">Resolved</p>
                        <small class="text-success">Successfully resolved</small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?php echo count(array_filter($complaints, function($c) { return $c['status'] === 'Pending'; })); ?></h3>
                        <p class="text-muted mb-0">Pending</p>
                        <small class="text-warning">Awaiting action</small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon info">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?php echo count(array_filter($complaints, function($c) { return $c['status'] === 'In Progress'; })); ?></h3>
                        <p class="text-muted mb-0">In Progress</p>
                        <small class="text-info">Being worked on</small>
                    </div>
                </div>
            </div>
            
            <!-- Complaints Table -->
            <div class="table-container">
                <div class="table-header">
                    <h5 class="table-title">All Complaints</h5>
                    <div class="table-actions">
                        <span class="badge bg-primary"><?php echo count($complaints); ?> Total</span>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Complaint</th>
                                <th>Citizen</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Location</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($complaints)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-clipboard-list text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted mt-3">No complaints found</h5>
                                        <p class="text-muted">Complaints will appear here when citizens submit issues to your panchayat.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($complaints as $complaint): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="complaint-icon">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars(substr($complaint['title'], 0, 30)) . '...'; ?></div>
                                                    <small class="text-muted">ID: #<?php echo str_pad($complaint['id'], 3, '0', STR_PAD_LEFT); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($complaint['user_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($complaint['user_phone']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                <?php echo $complaint['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $priority = 'routine';
                                            if ($complaint['status'] === 'Pending') $priority = 'high';
                                            elseif ($complaint['status'] === 'In Progress') $priority = 'medium';
                                            ?>
                                            <span class="priority-badge priority-<?php echo $priority; ?>">
                                                <?php echo ucfirst($priority); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($complaint['location'], 0, 20)) . '...'; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></td>
                                        <td>
                                            <button class="btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#complaintModal<?php echo $complaint['id']; ?>">
                                                <i class="fas fa-eye me-1"></i>View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Complaint Detail Modals -->
    <?php foreach ($complaints as $complaint): ?>
        <div class="modal fade" id="complaintModal<?php echo $complaint['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Complaint Details - <?php echo htmlspecialchars($complaint['title']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="fw-bold">Complaint Description</h6>
                                <p class="text-muted mb-3"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-user me-1"></i>Complainant:</strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($complaint['user_name']); ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-phone me-1"></i>Phone:</strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($complaint['user_phone']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <strong><i class="fas fa-map-marker-alt me-1"></i>Location:</strong>
                                    <span class="text-muted"><?php echo htmlspecialchars($complaint['location']); ?></span>
                                    
                                    <?php if (!empty($complaint['latitude']) && !empty($complaint['longitude'])): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-crosshairs me-1"></i>
                                                GPS: <?php echo number_format($complaint['latitude'], 6); ?>, <?php echo number_format($complaint['longitude'], 6); ?>
                                                <?php if (!empty($complaint['location_accuracy'])): ?>
                                                    (±<?php echo round($complaint['location_accuracy']); ?>m)
                                                <?php endif; ?>
                                            </small>
                                            <br>
                                            <a href="https://www.google.com/maps?q=<?php echo $complaint['latitude']; ?>,<?php echo $complaint['longitude']; ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                                <i class="fas fa-external-link-alt me-1"></i>View on Map
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong><i class="fas fa-calendar me-1"></i>Submitted:</strong>
                                    <span class="text-muted"><?php echo formatDate($complaint['created_at']); ?></span>
                                </div>
                                
                                <?php if ($complaint['admin_notes']): ?>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-comment me-1"></i>Admin Notes:</strong>
                                        <div class="bg-light p-3 rounded mt-2">
                                            <?php echo nl2br(htmlspecialchars($complaint['admin_notes'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <?php if ($complaint['photo']): ?>
                                    <div class="mb-3">
                                        <img src="../uploads/issues/<?php echo htmlspecialchars($complaint['photo']); ?>" 
                                             class="img-fluid rounded issue-image-clickable" 
                                             alt="Complaint Photo"
                                             data-bs-toggle="modal" 
                                             data-bs-target="#imageModal"
                                             data-image="../uploads/issues/<?php echo htmlspecialchars($complaint['photo']); ?>"
                                             style="cursor: pointer; max-height: 200px; object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Status Update Form -->
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <input type="hidden" name="issue_id" value="<?php echo $complaint['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select" required id="statusSelect<?php echo $complaint['id']; ?>" onchange="toggleResolutionPhoto(<?php echo $complaint['id']; ?>)">
                                            <option value="Pending" <?php echo $complaint['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="In Progress" <?php echo $complaint['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="Resolved" <?php echo $complaint['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3" id="resolutionPhotoDiv<?php echo $complaint['id']; ?>" style="<?php echo $complaint['status'] === 'Resolved' ? '' : 'display: none;'; ?>">
                                        <label class="form-label">Resolution Photo <span class="text-danger">*</span></label>
                                        <input type="file" name="resolution_photo" class="form-control" accept="image/*" id="resolutionPhoto<?php echo $complaint['id']; ?>">
                                        <small class="text-muted">Upload a photo showing the issue has been resolved (JPG, PNG, GIF, WebP - Max 5MB)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Response Notes</label>
                                        <textarea name="admin_notes" class="form-control" rows="3" 
                                                  placeholder="Add your response or updates..."><?php echo htmlspecialchars($complaint['admin_notes']); ?></textarea>
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
                    <h5 class="modal-title">Complaint Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" class="img-fluid" alt="Complaint Photo">
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
        
        // Export to PDF function
        function exportToPDF() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Complaints Report - ${new Date().toLocaleDateString()}</title>
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
                            <h1>Grama Voice - Complaints Report</h1>
                            <p>Generated on: ${new Date().toLocaleDateString()}</p>
                        </div>
                        <div class="stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo count($complaints); ?></div>
                                <div class="stat-label">Total Complaints</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo count(array_filter($complaints, function($c) { return $c['status'] === 'Resolved'; })); ?></div>
                                <div class="stat-label">Resolved</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo count(array_filter($complaints, function($c) { return $c['status'] === 'Pending'; })); ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo count(array_filter($complaints, function($c) { return $c['status'] === 'In Progress'; })); ?></div>
                                <div class="stat-label">In Progress</div>
                            </div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Complaint ID</th>
                                    <th>Title</th>
                                    <th>Citizen</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Location</th>
                                    <th>Submitted Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complaints as $complaint): ?>
                                <tr>
                                    <td>#<?php echo str_pad($complaint['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars(substr($complaint['title'], 0, 30)) . '...'; ?></td>
                                    <td><?php echo htmlspecialchars($complaint['user_name']); ?></td>
                                    <td><?php echo $complaint['status']; ?></td>
                                    <td><?php 
                                        $priority = 'routine';
                                        if ($complaint['status'] === 'Pending') $priority = 'high';
                                        elseif ($complaint['status'] === 'In Progress') $priority = 'medium';
                                        echo ucfirst($priority);
                                    ?></td>
                                    <td><?php echo htmlspecialchars(substr($complaint['location'], 0, 20)) . '...'; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>
