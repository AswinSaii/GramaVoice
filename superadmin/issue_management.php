<?php
/**
 * Super Admin Issue Management
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if super admin is logged in
requireSuperAdminLogin();

$db = getDB();

// Handle issue assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_issue'])) {
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
    
    header('Location: issue_management.php');
    exit();
}

// Handle issue editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_issue'])) {
    $issue_id = (int)$_POST['issue_id'];
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $status = sanitizeInput($_POST['status']);
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $admin_notes = sanitizeInput($_POST['admin_notes']);
    
    try {
        $stmt = $db->prepare("UPDATE issues SET title = ?, description = ?, status = ?, assigned_to = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("sssiss", $title, $description, $status, $assigned_to, $admin_notes, $issue_id);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Issue updated successfully!');
        } else {
            setFlashMessage('error', 'Failed to update issue.');
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Database error: ' . $e->getMessage());
    }
    
    header('Location: issue_management.php');
    exit();
}

// Get all issues with filters
$status_filter = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($search_query)) {
    $where_conditions[] = "(i.title LIKE ? OR u.name LIKE ? OR i.location LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "
    SELECT i.*, u.name as user_name, u.phone as user_phone, pa.name as admin_name, pa.village_name 
    FROM issues i 
    JOIN users u ON i.user_id = u.id 
    LEFT JOIN panchayat_admins pa ON i.assigned_to = pa.id 
    $where_clause
    ORDER BY i.created_at DESC
";

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all panchayat admins for assignment
$admins = $db->getConnection()->query("SELECT id, name, village_name FROM panchayat_admins ORDER BY village_name")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$total_issues = count($issues);
$pending_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'Pending'; }));
$in_progress_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'In Progress'; }));
$resolved_issues = count(array_filter($issues, function($issue) { return $issue['status'] === 'Resolved'; }));

$success_message = getFlashMessage('success');
$error_message = getFlashMessage('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Management - Super Admin - Grama Voice</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="shortcut icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="apple-touch-icon" href="../images/GramaVoice-Logo.png">
    

    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
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
            color: white;
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
        
        .stat-icon.total { background: var(--primary-blue); }
        .stat-icon.pending { background: var(--danger-red); }
        .stat-icon.progress { background: var(--warning-orange); }
        .stat-icon.resolved { background: var(--success-green); }
        
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
        
        .severity-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
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
                    <a class="nav-link active" href="issue_management.php">
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
                <h1 class="page-title">Issue Management</h1>
                <div class="header-actions">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Search issues..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <select name="status" class="form-select" style="width: auto;">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Resolved" <?php echo $status_filter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                        <button type="submit" class="btn-outline">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                    </form>
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
                            <div class="stat-icon total me-3">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h3 class="stat-value"><?php echo $total_issues; ?></h3>
                                <p class="stat-label">Total Issues</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon pending me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h3 class="stat-value"><?php echo $pending_issues; ?></h3>
                                <p class="stat-label">Pending</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon progress me-3">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div>
                                <h3 class="stat-value"><?php echo $in_progress_issues; ?></h3>
                                <p class="stat-label">In Progress</p>
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
                                <h3 class="stat-value"><?php echo $resolved_issues; ?></h3>
                                <p class="stat-label">Resolved</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Issues Table -->
            <div class="table-container">
                <div class="table-header">
                    <h5 class="table-title">All Issues</h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Issue ID</th>
                                <th>Title</th>
                                <th>Citizen</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($issues)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted mt-3">No issues found</h5>
                                        <p class="text-muted">No issues match your current filters.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($issues as $issue): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($issue['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars(substr($issue['title'], 0, 30)) . '...'; ?></td>
                                        <td><?php echo htmlspecialchars($issue['user_name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($issue['location'], 0, 20)) . '...'; ?></td>
                                        <td>
                                            <span class="severity-badge severity-<?php echo strtolower(str_replace(' ', '-', $issue['status'])); ?>">
                                                <?php echo $issue['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $issue['admin_name'] ? htmlspecialchars($issue['admin_name']) : 'Unassigned'; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($issue['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#issueModal<?php echo $issue['id']; ?>">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </button>
                                                <button class="btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                        data-issue-id="<?php echo $issue['id']; ?>"
                                                        data-issue-title="<?php echo htmlspecialchars($issue['title']); ?>"
                                                        data-issue-description="<?php echo htmlspecialchars($issue['description']); ?>"
                                                        data-issue-status="<?php echo $issue['status']; ?>"
                                                        data-issue-assigned="<?php echo $issue['assigned_to']; ?>"
                                                        data-issue-notes="<?php echo htmlspecialchars($issue['admin_notes'] ?? ''); ?>">
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </button>
                                            </div>
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
    
    <!-- Edit Issue Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="issue_id" id="editIssueId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Issue Title</label>
                                    <input type="text" name="title" id="editTitle" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" id="editStatus" class="form-select" required>
                                        <option value="Pending">Pending</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Resolved">Resolved</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="editDescription" class="form-control" rows="4" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Assign to Admin</label>
                                    <select name="assigned_to" id="editAssignedTo" class="form-select">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($admins as $admin): ?>
                                            <option value="<?php echo $admin['id']; ?>">
                                                <?php echo htmlspecialchars($admin['name'] . ' - ' . $admin['village_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Notes</label>
                            <textarea name="admin_notes" id="editAdminNotes" class="form-control" rows="3" placeholder="Add internal notes about this issue..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_issue" class="btn btn-primary">Update Issue</button>
                    </div>
                </form>
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
                                
                                <div class="mb-3">
                                    <strong><i class="fas fa-user-tie me-1"></i>Assigned To:</strong>
                                    <span class="text-muted"><?php echo $issue['admin_name'] ? htmlspecialchars($issue['admin_name']) : 'Unassigned'; ?></span>
                                </div>
                                
                                <?php if (!empty($issue['admin_notes'])): ?>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-sticky-note me-1"></i>Admin Notes:</strong>
                                        <div class="bg-light p-3 rounded mt-2">
                                            <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($issue['admin_notes'])); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <?php if ($issue['photo']): ?>
                                    <div class="mb-3">
                                        <h6><i class="fas fa-camera me-1"></i>Issue Photo</h6>
                                        <img src="../uploads/issues/<?php echo htmlspecialchars($issue['photo']); ?>" 
                                             class="img-fluid rounded issue-image-clickable" 
                                             alt="Issue Photo"
                                             data-bs-toggle="modal" 
                                             data-bs-target="#imageModal"
                                             data-image="../uploads/issues/<?php echo htmlspecialchars($issue['photo']); ?>"
                                             style="cursor: pointer; max-height: 200px; object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($issue['resolution_photo']): ?>
                                    <div class="mb-3">
                                        <h6><i class="fas fa-check-circle me-1 text-success"></i>Resolution Photo</h6>
                                        <img src="../uploads/resolutions/<?php echo htmlspecialchars($issue['resolution_photo']); ?>" 
                                             class="img-fluid rounded issue-image-clickable" 
                                             alt="Resolution Photo"
                                             data-bs-toggle="modal" 
                                             data-bs-target="#imageModal"
                                             data-image="../uploads/resolutions/<?php echo htmlspecialchars($issue['resolution_photo']); ?>"
                                             style="cursor: pointer; max-height: 200px; object-fit: cover;">
                                        <small class="text-muted">Proof that the issue has been resolved</small>
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
        // Edit modal functionality
        const editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const issueId = button.dataset.issueId;
            const issueTitle = button.dataset.issueTitle;
            const issueDescription = button.dataset.issueDescription;
            const issueStatus = button.dataset.issueStatus;
            const issueAssigned = button.dataset.issueAssigned;
            const issueNotes = button.dataset.issueNotes;
            
            // Populate form fields
            document.getElementById('editIssueId').value = issueId;
            document.getElementById('editTitle').value = issueTitle;
            document.getElementById('editDescription').value = issueDescription;
            document.getElementById('editStatus').value = issueStatus;
            document.getElementById('editAssignedTo').value = issueAssigned || '';
            document.getElementById('editAdminNotes').value = issueNotes || '';
        });
        
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
    </script>
</body>
</html>
