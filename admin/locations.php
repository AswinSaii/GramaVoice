<?php
/**
 * Locations Management Page
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

// Handle filter requests
$filter_status = $_GET['status'] ?? 'all';
$filter_search = $_GET['search'] ?? '';

// Build the query with filters
$where_conditions = ["assigned_to = ?", "location IS NOT NULL", "location != ''"];
$params = [$admin_id];
$param_types = "i";

if ($filter_status !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

if (!empty($filter_search)) {
    $where_conditions[] = "location LIKE ?";
    $search_param = "%$filter_search%";
    $params[] = $search_param;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get all unique locations from issues assigned to this admin
$stmt = $db->prepare("
    SELECT location, 
           COUNT(*) as issue_count,
           COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved_count,
           COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
           COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress_count
    FROM issues 
    WHERE $where_clause
    GROUP BY location
    ORDER BY issue_count DESC
");
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$locations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="locations_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Location', 'Total Issues', 'Resolved', 'Pending', 'In Progress', 'Resolution Rate']);
    
    foreach ($locations as $location) {
        $rate = $location['issue_count'] > 0 ? round(($location['resolved_count'] / $location['issue_count']) * 100) : 0;
        fputcsv($output, [
            $location['location'],
            $location['issue_count'],
            $location['resolved_count'],
            $location['pending_count'],
            $location['in_progress_count'],
            $rate . '%'
        ]);
    }
    
    fclose($output);
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
    <title>Locations - Grama Voice</title>

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
        
        .location-icon {
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
                    <a class="nav-link active" href="locations.php">
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
                <h1 class="page-title">Locations</h1>
                <div class="header-actions">
                    <div class="dropdown">
                        <button class="btn-outline dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?status=all">All Locations</a></li>
                            <li><a class="dropdown-item" href="?status=Pending">With Pending Issues</a></li>
                            <li><a class="dropdown-item" href="?status=In Progress">With In Progress Issues</a></li>
                            <li><a class="dropdown-item" href="?status=Resolved">With Resolved Issues</a></li>
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
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?php echo count($locations); ?></h3>
                        <p class="text-muted mb-0">Total Locations</p>
                        <small class="text-primary">With reported issues</small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?php echo array_sum(array_column($locations, 'resolved_count')); ?></h3>
                        <p class="text-muted mb-0">Issues Resolved</p>
                        <small class="text-success">Across all locations</small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?php echo array_sum(array_column($locations, 'pending_count')); ?></h3>
                        <p class="text-muted mb-0">Pending Issues</p>
                        <small class="text-warning">Awaiting resolution</small>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon info">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <h3 class="fw-bold mb-1">
                            <?php 
                            $total_issues = array_sum(array_column($locations, 'issue_count'));
                            $resolved_issues = array_sum(array_column($locations, 'resolved_count'));
                            $resolution_rate = $total_issues > 0 ? round(($resolved_issues / $total_issues) * 100) : 0;
                            echo $resolution_rate;
                            ?>%
                        </h3>
                        <p class="text-muted mb-0">Resolution Rate</p>
                        <small class="text-info">Location-wise performance</small>
                    </div>
                </div>
            </div>
            
            <!-- Locations Table -->
            <div class="table-container">
                <div class="table-header">
                    <h5 class="table-title">Issue Locations</h5>
                    <div class="table-actions">
                        <span class="badge bg-primary"><?php echo count($locations); ?> Locations</span>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th>Total Issues</th>
                                <th>Resolved</th>
                                <th>Pending</th>
                                <th>In Progress</th>
                                <th>Resolution Rate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($locations)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-map-marker-alt text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted mt-3">No locations found</h5>
                                        <p class="text-muted">Locations will appear here when citizens report issues from different areas.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($locations as $location): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="location-icon">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($location['location']); ?></div>
                                                    <small class="text-muted">Issue hotspot</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary"><?php echo $location['issue_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-success"><?php echo $location['resolved_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-warning"><?php echo $location['pending_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-danger"><?php echo $location['in_progress_count']; ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $rate = $location['issue_count'] > 0 ? round(($location['resolved_count'] / $location['issue_count']) * 100) : 0;
                                            $rate_class = $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge badge-<?php echo $rate_class; ?>"><?php echo $rate; ?>%</span>
                                        </td>
                                        <td>
                                            <button class="btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#locationModal<?php echo md5($location['location']); ?>">
                                                <i class="fas fa-eye me-1"></i>View Details
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
    
    <!-- Location Detail Modals -->
    <?php foreach ($locations as $location): ?>
        <div class="modal fade" id="locationModal<?php echo md5($location['location']); ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Location Details - <?php echo htmlspecialchars($location['location']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Location Information</h6>
                                <div class="mb-3">
                                    <strong><i class="fas fa-map-marker-alt me-1"></i>Location:</strong>
                                    <span class="text-muted"><?php echo htmlspecialchars($location['location']); ?></span>
                                </div>
                                
                                <h6 class="fw-bold mb-3">Issue Statistics</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <h4 class="text-primary mb-1"><?php echo $location['issue_count']; ?></h4>
                                            <small class="text-muted">Total Issues</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <h4 class="text-success mb-1"><?php echo $location['resolved_count']; ?></h4>
                                            <small class="text-muted">Resolved</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Status Breakdown</h6>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span>Resolved</span>
                                        <span class="fw-bold text-success"><?php echo $location['resolved_count']; ?></span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $location['issue_count'] > 0 ? ($location['resolved_count'] / $location['issue_count']) * 100 : 0; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span>In Progress</span>
                                        <span class="fw-bold text-warning"><?php echo $location['in_progress_count']; ?></span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-warning" style="width: <?php echo $location['issue_count'] > 0 ? ($location['in_progress_count'] / $location['issue_count']) * 100 : 0; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span>Pending</span>
                                        <span class="fw-bold text-danger"><?php echo $location['pending_count']; ?></span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-danger" style="width: <?php echo $location['issue_count'] > 0 ? ($location['pending_count'] / $location['issue_count']) * 100 : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Export to PDF function
        function exportToPDF() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Locations Report - ${new Date().toLocaleDateString()}</title>
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
                            <h1>Grama Voice - Locations Report</h1>
                            <p>Generated on: ${new Date().toLocaleDateString()}</p>
                        </div>
                        <div class="stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo count($locations); ?></div>
                                <div class="stat-label">Total Locations</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo array_sum(array_column($locations, 'resolved_count')); ?></div>
                                <div class="stat-label">Issues Resolved</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo array_sum(array_column($locations, 'pending_count')); ?></div>
                                <div class="stat-label">Pending Issues</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo array_sum(array_column($locations, 'issue_count')); ?></div>
                                <div class="stat-label">Total Issues</div>
                            </div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Location</th>
                                    <th>Total Issues</th>
                                    <th>Resolved</th>
                                    <th>Pending</th>
                                    <th>In Progress</th>
                                    <th>Resolution Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($locations as $location): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($location['location']); ?></td>
                                    <td><?php echo $location['issue_count']; ?></td>
                                    <td><?php echo $location['resolved_count']; ?></td>
                                    <td><?php echo $location['pending_count']; ?></td>
                                    <td><?php echo $location['in_progress_count']; ?></td>
                                    <td><?php 
                                        $rate = $location['issue_count'] > 0 ? round(($location['resolved_count'] / $location['issue_count']) * 100) : 0;
                                        echo $rate . '%';
                                    ?></td>
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
