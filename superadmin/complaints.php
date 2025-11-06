<?php
/**
 * Super Admin Complaints Management
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if super admin is logged in
requireSuperAdminLogin();

$db = getDB();

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
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

// Get all complaints with detailed information
$sql = "
    SELECT i.*, u.name as user_name, u.phone as user_phone, pa.name as admin_name, pa.village_name,
           DATEDIFF(CURRENT_DATE, i.created_at) as days_since_submission,
           CASE 
               WHEN i.status = 'Pending' AND DATEDIFF(CURRENT_DATE, i.created_at) > 7 THEN 'Critical'
               WHEN i.status = 'Pending' AND DATEDIFF(CURRENT_DATE, i.created_at) > 3 THEN 'High'
               WHEN i.status = 'In Progress' THEN 'Medium'
               ELSE 'Low'
           END as priority
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
$complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Apply priority filter
if ($priority_filter !== 'all') {
    $complaints = array_filter($complaints, function($complaint) use ($priority_filter) {
        return $complaint['priority'] === $priority_filter;
    });
}

// Get statistics
$total_complaints = count($complaints);
$pending_complaints = count(array_filter($complaints, function($complaint) { return $complaint['status'] === 'Pending'; }));
$in_progress_complaints = count(array_filter($complaints, function($complaint) { return $complaint['status'] === 'In Progress'; }));
$resolved_complaints = count(array_filter($complaints, function($complaint) { return $complaint['status'] === 'Resolved'; }));
$critical_complaints = count(array_filter($complaints, function($complaint) { return $complaint['priority'] === 'Critical'; }));

$success_message = getFlashMessage('success');
$error_message = getFlashMessage('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints Management - Super Admin - Grama Voice</title>

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
        .stat-icon.critical { background: var(--danger-red); }
        
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
        
        .priority-critical {
            background: #fee2e2;
            color: var(--danger-red);
        }
        
        .priority-high {
            background: #fef3c7;
            color: var(--warning-orange);
        }
        
        .priority-medium {
            background: #dbeafe;
            color: var(--primary-blue);
        }
        
        .priority-low {
            background: #f3f4f6;
            color: var(--gray-600);
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
                    <a class="nav-link active" href="complaints.php">
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
                <h1 class="page-title">Complaints Management</h1>
                <div class="header-actions">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Search complaints..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <select name="status" class="form-select" style="width: auto;">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Resolved" <?php echo $status_filter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                        <select name="priority" class="form-select" style="width: auto;">
                            <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priority</option>
                            <option value="Critical" <?php echo $priority_filter === 'Critical' ? 'selected' : ''; ?>>Critical</option>
                            <option value="High" <?php echo $priority_filter === 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Medium" <?php echo $priority_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="Low" <?php echo $priority_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
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
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div>
                                <h3 class="stat-value"><?php echo $total_complaints; ?></h3>
                                <p class="stat-label">Total Complaints</p>
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
                                <h3 class="stat-value"><?php echo $pending_complaints; ?></h3>
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
                                <h3 class="stat-value"><?php echo $in_progress_complaints; ?></h3>
                                <p class="stat-label">In Progress</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon critical me-3">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h3 class="stat-value"><?php echo $critical_complaints; ?></h3>
                                <p class="stat-label">Critical</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Complaints Table -->
            <div class="table-container">
                <div class="table-header">
                    <h5 class="table-title">All Complaints</h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Complaint ID</th>
                                <th>Title</th>
                                <th>Citizen</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Assigned To</th>
                                <th>Days Since</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($complaints)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="fas fa-clipboard-list text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted mt-3">No complaints found</h5>
                                        <p class="text-muted">No complaints match your current filters.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($complaints as $complaint): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($complaint['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars(substr($complaint['title'], 0, 30)) . '...'; ?></td>
                                        <td><?php echo htmlspecialchars($complaint['user_name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($complaint['location'], 0, 20)) . '...'; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                <?php echo $complaint['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="priority-badge priority-<?php echo strtolower($complaint['priority']); ?>">
                                                <?php echo $complaint['priority']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $complaint['admin_name'] ? htmlspecialchars($complaint['admin_name']) : 'Unassigned'; ?></td>
                                        <td><?php echo $complaint['days_since_submission']; ?> days</td>
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
                                <h6 class="fw-bold">Description</h6>
                                <p class="text-muted mb-3"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-user me-1"></i>Reporter:</strong>
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
                                
                                <div class="mb-3">
                                    <strong><i class="fas fa-user-tie me-1"></i>Assigned To:</strong>
                                    <span class="text-muted"><?php echo $complaint['admin_name'] ? htmlspecialchars($complaint['admin_name']) : 'Unassigned'; ?></span>
                                </div>
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
                                
                                <div class="mb-3">
                                    <strong>Status:</strong>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                        <?php echo $complaint['status']; ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Priority:</strong>
                                    <span class="priority-badge priority-<?php echo strtolower($complaint['priority']); ?>">
                                        <?php echo $complaint['priority']; ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Days Since Submission:</strong>
                                    <span class="text-muted"><?php echo $complaint['days_since_submission']; ?> days</span>
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
