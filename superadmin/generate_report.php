<?php
/**
 * Advanced Report Generation Script
 * Grama Voice - Village Governance Platform
 * Generates comprehensive PDF and Excel reports for Super Admin
 */

require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if super admin is logged in
requireSuperAdminLogin();

$db = getDB();

// Get report parameters
$report_type = $_GET['type'] ?? 'comprehensive';
$format = $_GET['format'] ?? 'pdf';
$date_range = $_GET['date_range'] ?? 'all';

// Calculate date filters
$date_condition = '';
$params = [];
$param_types = '';

if ($date_range !== 'all') {
    switch ($date_range) {
        case 'today':
            $date_condition = "AND DATE(i.created_at) = CURDATE()";
            break;
        case 'week':
            $date_condition = "AND i.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $date_condition = "AND i.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'quarter':
            $date_condition = "AND i.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
        case 'year':
            $date_condition = "AND i.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
}

// Get comprehensive statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT u.id) as total_users,
        COUNT(i.id) as total_issues,
        COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) as resolved_issues,
        COUNT(CASE WHEN i.status = 'Pending' THEN 1 END) as pending_issues,
        COUNT(CASE WHEN i.status = 'In Progress' THEN 1 END) as in_progress_issues,
        COUNT(DISTINCT pa.id) as total_admins,
        CASE 
            WHEN COUNT(i.id) = 0 THEN 0
            ELSE ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100)
        END as resolution_rate,
        AVG(CASE WHEN i.status = 'Resolved' THEN DATEDIFF(i.updated_at, i.created_at) END) as avg_resolution_days
    FROM users u
    LEFT JOIN issues i ON u.id = i.user_id $date_condition
    LEFT JOIN panchayat_admins pa ON i.assigned_to = pa.id
    WHERE u.verified = 1
";

$stats_result = $db->getConnection()->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get panchayat performance data
$panchayat_query = "
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
            WHEN COUNT(i.id) = 0 THEN 'New'
            WHEN COUNT(i.id) >= 50 AND ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100) >= 90 THEN 'Champion'
            WHEN COUNT(i.id) >= 30 AND ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100) >= 80 THEN 'Expert'
            WHEN COUNT(i.id) >= 20 AND ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100) >= 70 THEN 'Professional'
            WHEN COUNT(i.id) >= 10 THEN 'Active'
            ELSE 'Beginner'
        END as performance_tier
    FROM panchayat_admins pa 
    LEFT JOIN issues i ON pa.id = i.assigned_to $date_condition
    GROUP BY pa.id, pa.name, pa.phone, pa.village_name, pa.created_at
    ORDER BY resolution_rate DESC, total_issues DESC
";

$panchayat_result = $db->getConnection()->query($panchayat_query);
$panchayat_data = $panchayat_result->fetch_all(MYSQLI_ASSOC);

// Get heatmap data
$heatmap_query = "
    SELECT 
        pa.id,
        pa.village_name,
        pa.name as admin_name,
        pa.phone as admin_phone,
        COUNT(i.id) as total_issues,
        COUNT(CASE WHEN i.status = 'Pending' THEN 1 END) as pending_issues,
        COUNT(CASE WHEN i.status = 'In Progress' THEN 1 END) as in_progress_issues,
        COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) as resolved_issues,
        CASE 
            WHEN COUNT(i.id) = 0 THEN 0
            ELSE ROUND((COUNT(CASE WHEN i.status = 'Resolved' THEN 1 END) / COUNT(i.id)) * 100)
        END as resolution_rate,
        AVG(CASE WHEN i.status = 'Resolved' THEN DATEDIFF(i.updated_at, i.created_at) END) as avg_resolution_days,
        COUNT(CASE WHEN i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_issues,
        COUNT(CASE WHEN i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as monthly_issues,
        CASE 
            WHEN COUNT(i.id) = 0 THEN 'low'
            WHEN COUNT(i.id) > 20 THEN 'critical'
            WHEN COUNT(i.id) > 15 THEN 'high'
            WHEN COUNT(i.id) > 10 THEN 'medium'
            ELSE 'low'
        END as intensity_level
    FROM panchayat_admins pa 
    LEFT JOIN issues i ON pa.id = i.assigned_to $date_condition
    GROUP BY pa.id, pa.village_name, pa.name, pa.phone
    ORDER BY total_issues DESC, resolution_rate DESC
";

$heatmap_result = $db->getConnection()->query($heatmap_query);
$heatmap_data = $heatmap_result->fetch_all(MYSQLI_ASSOC);

// Get recent issues
$issues_query = "
    SELECT i.*, u.name as user_name, u.phone as user_phone, pa.name as admin_name, pa.village_name 
    FROM issues i 
    JOIN users u ON i.user_id = u.id 
    LEFT JOIN panchayat_admins pa ON i.assigned_to = pa.id 
    WHERE 1=1 $date_condition
    ORDER BY i.created_at DESC 
    LIMIT 100
";

$issues_result = $db->getConnection()->query($issues_query);
$recent_issues = $issues_result->fetch_all(MYSQLI_ASSOC);

// Generate report based on format
if ($format === 'pdf') {
    generatePDFReport($stats, $panchayat_data, $heatmap_data, $recent_issues, $report_type, $date_range);
} elseif ($format === 'excel') {
    generateExcelReport($stats, $panchayat_data, $heatmap_data, $recent_issues, $report_type, $date_range);
} else {
    // Default to HTML report
    generateHTMLReport($stats, $panchayat_data, $heatmap_data, $recent_issues, $report_type, $date_range);
}

function generatePDFReport($stats, $panchayat_data, $heatmap_data, $recent_issues, $report_type, $date_range) {
    // Set headers for PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="grama_voice_report_' . date('Y-m-d') . '.pdf"');
    
    // Simple PDF generation using HTML to PDF conversion
    $html = generateReportHTML($stats, $panchayat_data, $heatmap_data, $recent_issues, $report_type, $date_range);
    
    // For a more robust solution, you would use libraries like TCPDF or mPDF
    // For now, we'll output HTML that can be printed as PDF
    echo $html;
}

function generateExcelReport($stats, $panchayat_data, $heatmap_data, $recent_issues, $report_type, $date_range) {
    // Set headers for Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="grama_voice_report_' . date('Y-m-d') . '.xlsx"');
    
    $output = fopen('php://output', 'w');
    
    // Summary Sheet
    fputcsv($output, ['Grama Voice - Super Admin Report']);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Report Type: ' . ucfirst($report_type)]);
    fputcsv($output, ['Date Range: ' . ucfirst($date_range)]);
    fputcsv($output, []);
    
    fputcsv($output, ['SUMMARY STATISTICS']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Users', $stats['total_users']]);
    fputcsv($output, ['Total Issues', $stats['total_issues']]);
    fputcsv($output, ['Resolved Issues', $stats['resolved_issues']]);
    fputcsv($output, ['Pending Issues', $stats['pending_issues']]);
    fputcsv($output, ['In Progress Issues', $stats['in_progress_issues']]);
    fputcsv($output, ['Resolution Rate', $stats['resolution_rate'] . '%']);
    fputcsv($output, ['Average Resolution Time', round($stats['avg_resolution_days'], 1) . ' days']);
    fputcsv($output, ['Total Panchayat Admins', $stats['total_admins']]);
    fputcsv($output, []);
    
    // Panchayat Performance Sheet
    fputcsv($output, ['PANCHAYAT PERFORMANCE LEADERBOARD']);
    fputcsv($output, ['Rank', 'Admin Name', 'Village', 'Total Issues', 'Resolved', 'Pending', 'In Progress', 'Resolution Rate', 'Performance Tier', 'Avg Resolution Days', 'Monthly Issues', 'Monthly Resolved']);
    
    foreach ($panchayat_data as $index => $panchayat) {
        fputcsv($output, [
            $index + 1,
            $panchayat['name'],
            $panchayat['village_name'],
            $panchayat['total_issues'],
            $panchayat['resolved_issues'],
            $panchayat['pending_issues'],
            $panchayat['in_progress_issues'],
            $panchayat['resolution_rate'] . '%',
            $panchayat['performance_tier'],
            $panchayat['avg_resolution_days'] ? round($panchayat['avg_resolution_days'], 1) . ' days' : 'N/A',
            $panchayat['monthly_issues'],
            $panchayat['monthly_resolved']
        ]);
    }
    
    fputcsv($output, []);
    
    // Heatmap Data Sheet
    fputcsv($output, ['VILLAGE HEATMAP DATA']);
    fputcsv($output, ['Village', 'Admin Name', 'Admin Phone', 'Total Issues', 'Pending', 'In Progress', 'Resolved', 'Resolution Rate', 'Recent Issues (7 days)', 'Monthly Issues', 'Intensity Level', 'Avg Resolution Days']);
    
    foreach ($heatmap_data as $village) {
        fputcsv($output, [
            $village['village_name'],
            $village['admin_name'],
            $village['admin_phone'],
            $village['total_issues'],
            $village['pending_issues'],
            $village['in_progress_issues'],
            $village['resolved_issues'],
            $village['resolution_rate'] . '%',
            $village['recent_issues'],
            $village['monthly_issues'],
            ucfirst($village['intensity_level']),
            $village['avg_resolution_days'] ? round($village['avg_resolution_days'], 1) . ' days' : 'N/A'
        ]);
    }
    
    fputcsv($output, []);
    
    // Recent Issues Sheet
    fputcsv($output, ['RECENT ISSUES']);
    fputcsv($output, ['Issue ID', 'Title', 'Citizen Name', 'Citizen Phone', 'Status', 'Assigned Admin', 'Village', 'Created Date', 'Updated Date', 'Description']);
    
    foreach ($recent_issues as $issue) {
        fputcsv($output, [
            '#' . str_pad($issue['id'], 3, '0', STR_PAD_LEFT),
            $issue['title'],
            $issue['user_name'],
            $issue['user_phone'],
            $issue['status'],
            $issue['admin_name'] ?: 'Unassigned',
            $issue['village_name'] ?: 'N/A',
            date('Y-m-d H:i:s', strtotime($issue['created_at'])),
            $issue['updated_at'] ? date('Y-m-d H:i:s', strtotime($issue['updated_at'])) : 'N/A',
            substr($issue['description'], 0, 100)
        ]);
    }
    
    fclose($output);
}

function generateHTMLReport($stats, $panchayat_data, $heatmap_data, $recent_issues, $report_type, $date_range) {
    $html = generateReportHTML($stats, $panchayat_data, $heatmap_data, $recent_issues, $report_type, $date_range);
    echo $html;
}

function generateReportHTML($stats, $panchayat_data, $heatmap_data, $recent_issues, $report_type, $date_range) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Grama Voice - Super Admin Report</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="shortcut icon" type="image/png" href="../images/GramaVoice-Logo.png">
    <link rel="apple-touch-icon" href="../images/GramaVoice-Logo.png">
    

        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f8f9fa;
                line-height: 1.6;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            .header {
                text-align: center;
                margin-bottom: 40px;
                padding-bottom: 20px;
                border-bottom: 3px solid #2563eb;
            }
            .header h1 {
                color: #2563eb;
                margin: 0;
                font-size: 2.5rem;
            }
            .header p {
                color: #6b7280;
                margin: 10px 0 0 0;
                font-size: 1.1rem;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            .stat-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 25px;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            }
            .stat-value {
                font-size: 2.5rem;
                font-weight: bold;
                margin-bottom: 10px;
            }
            .stat-label {
                font-size: 1rem;
                opacity: 0.9;
            }
            .section {
                margin: 40px 0;
                padding: 25px;
                background: #f8f9fa;
                border-radius: 10px;
                border-left: 5px solid #2563eb;
            }
            .section h2 {
                color: #2563eb;
                margin-top: 0;
                font-size: 1.8rem;
            }
            .section h3 {
                color: #374151;
                margin-top: 30px;
                font-size: 1.4rem;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            th, td {
                padding: 15px;
                text-align: left;
                border-bottom: 1px solid #e5e7eb;
            }
            th {
                background: #2563eb;
                color: white;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 0.9rem;
                letter-spacing: 0.5px;
            }
            tr:hover {
                background-color: #f8f9fa;
            }
            .badge {
                padding: 5px 12px;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
                text-transform: uppercase;
            }
            .badge-champion { background: #fbbf24; color: #92400e; }
            .badge-expert { background: #3b82f6; color: white; }
            .badge-professional { background: #10b981; color: white; }
            .badge-active { background: #6b7280; color: white; }
            .badge-beginner { background: #9ca3af; color: white; }
            .badge-critical { background: #dc2626; color: white; }
            .badge-high { background: #f59e0b; color: white; }
            .badge-medium { background: #f97316; color: white; }
            .badge-low { background: #10b981; color: white; }
            .heatmap-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .heatmap-card {
                background: white;
                padding: 20px;
                border-radius: 10px;
                border-left: 5px solid;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            .heatmap-card.critical { border-left-color: #dc2626; }
            .heatmap-card.high { border-left-color: #f59e0b; }
            .heatmap-card.medium { border-left-color: #f97316; }
            .heatmap-card.low { border-left-color: #10b981; }
            .heatmap-title {
                font-weight: bold;
                font-size: 1.2rem;
                margin-bottom: 10px;
            }
            .heatmap-stats {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-top: 15px;
            }
            .heatmap-stat {
                text-align: center;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 5px;
            }
            .heatmap-stat-value {
                font-size: 1.5rem;
                font-weight: bold;
                color: #2563eb;
            }
            .heatmap-stat-label {
                font-size: 0.9rem;
                color: #6b7280;
            }
            .footer {
                text-align: center;
                margin-top: 50px;
                padding-top: 20px;
                border-top: 2px solid #e5e7eb;
                color: #6b7280;
            }
            @media print {
                body { background: white; }
                .container { box-shadow: none; }
                .section { break-inside: avoid; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Grama Voice</h1>
                <p>Super Admin Dashboard Report</p>
                <p>Generated on: <?php echo date('F j, Y \a\t g:i A'); ?></p>
                <p>Report Type: <?php echo ucfirst($report_type); ?> | Date Range: <?php echo ucfirst($date_range); ?></p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_issues']; ?></div>
                    <div class="stat-label">Total Issues</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['resolved_issues']; ?></div>
                    <div class="stat-label">Resolved Issues</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['resolution_rate']; ?>%</div>
                    <div class="stat-label">Resolution Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo round($stats['avg_resolution_days'], 1); ?></div>
                    <div class="stat-label">Avg Resolution Days</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_admins']; ?></div>
                    <div class="stat-label">Panchayat Admins</div>
                </div>
            </div>

            <div class="section">
                <h2>Panchayat Performance Leaderboard</h2>
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
                            <th>Monthly Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($panchayat_data as $index => $panchayat): ?>
                            <tr>
                                <td><strong><?php echo $index + 1; ?></strong></td>
                                <td><?php echo htmlspecialchars($panchayat['name']); ?></td>
                                <td><?php echo htmlspecialchars($panchayat['village_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($panchayat['performance_tier']); ?>">
                                        <?php echo $panchayat['performance_tier']; ?>
                                    </span>
                                </td>
                                <td><?php echo $panchayat['total_issues']; ?></td>
                                <td><?php echo $panchayat['resolved_issues']; ?></td>
                                <td><?php echo $panchayat['resolution_rate']; ?>%</td>
                                <td><?php echo $panchayat['avg_resolution_days'] ? round($panchayat['avg_resolution_days'], 1) . ' days' : 'N/A'; ?></td>
                                <td><?php echo $panchayat['monthly_resolved']; ?>/<?php echo $panchayat['monthly_issues']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h2>Village Heatmap Analysis</h2>
                <div class="heatmap-grid">
                    <?php foreach ($heatmap_data as $village): ?>
                        <div class="heatmap-card <?php echo $village['intensity_level']; ?>">
                            <div class="heatmap-title"><?php echo htmlspecialchars($village['village_name']); ?></div>
                            <p><strong>Admin:</strong> <?php echo htmlspecialchars($village['admin_name']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($village['admin_phone']); ?></p>
                            
                            <div class="heatmap-stats">
                                <div class="heatmap-stat">
                                    <div class="heatmap-stat-value"><?php echo $village['total_issues']; ?></div>
                                    <div class="heatmap-stat-label">Total Issues</div>
                                </div>
                                <div class="heatmap-stat">
                                    <div class="heatmap-stat-value"><?php echo $village['resolved_issues']; ?></div>
                                    <div class="heatmap-stat-label">Resolved</div>
                                </div>
                                <div class="heatmap-stat">
                                    <div class="heatmap-stat-value"><?php echo $village['recent_issues']; ?></div>
                                    <div class="heatmap-stat-label">Last 7 Days</div>
                                </div>
                                <div class="heatmap-stat">
                                    <div class="heatmap-stat-value"><?php echo $village['resolution_rate']; ?>%</div>
                                    <div class="heatmap-stat-label">Resolution Rate</div>
                                </div>
                            </div>
                            
                            <p style="margin-top: 15px;">
                                <span class="badge badge-<?php echo $village['intensity_level']; ?>">
                                    <?php echo ucfirst($village['intensity_level']); ?> Intensity
                                </span>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="section">
                <h2>Recent Issues Summary</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Issue ID</th>
                            <th>Title</th>
                            <th>Citizen</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Village</th>
                            <th>Created Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($recent_issues, 0, 20) as $issue): ?>
                            <tr>
                                <td>#<?php echo str_pad($issue['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars(substr($issue['title'], 0, 30)) . '...'; ?></td>
                                <td><?php echo htmlspecialchars($issue['user_name']); ?></td>
                                <td><?php echo $issue['status']; ?></td>
                                <td><?php echo $issue['admin_name'] ? htmlspecialchars($issue['admin_name']) : 'Unassigned'; ?></td>
                                <td><?php echo $issue['village_name'] ? htmlspecialchars($issue['village_name']) : 'N/A'; ?></td>
                                <td><?php echo date('M j, Y', strtotime($issue['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="footer">
                <p>This report was generated automatically by the Grama Voice Super Admin Dashboard</p>
                <p>For questions or support, please contact the system administrator</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>
