<?php
/**
 * Submit Issue Form
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../config/error_handler.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

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

// Get total issues count for the user
$stmt = $db->prepare("SELECT COUNT(*) as total FROM issues WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_issues = $result->fetch_assoc()['total'];

$error = '';
$success = '';

// Issue types with predefined titles
$issue_types = [
    'road_repair' => 'Road Repair Needed',
    'water_issue' => 'Water Supply Problem',
    'electricity' => 'Electricity Issue',
    'street_light' => 'Street Light Problem',
    'drainage' => 'Drainage Problem',
    'waste_management' => 'Waste Management Issue',
    'public_transport' => 'Public Transport Issue',
    'health_center' => 'Health Center Problem',
    'school_issue' => 'School/Education Issue',
    'other' => 'Other Issue'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debug: Log the received data
    logApplicationEvent("Form submission received", "POST data: " . json_encode($_POST));
    
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $location = sanitizeInput($_POST['location']);
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $location_accuracy = isset($_POST['location_accuracy']) ? floatval($_POST['location_accuracy']) : null;
    $user_id = $_SESSION['user_id'];
    
    // Debug: Log processed data
    logApplicationEvent("Form data processed", "Title: $title, Location: $location");
    
    // Validate input
    if (empty($title) || empty($description) || empty($location)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $db = getDB();
            
            $photo_path = null;
            
            // Handle file upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                $upload_result = uploadFile($_FILES['photo'], '../uploads/', 'issues');
                if ($upload_result['success']) {
                    $photo_path = $upload_result['filename'];
                } else {
                    $error = $upload_result['message'];
                }
            }
            
            if (empty($error)) {
                // Insert issue with GPS coordinates
                $stmt = $db->prepare("INSERT INTO issues (user_id, title, description, photo, location, latitude, longitude, location_accuracy, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                if (!$stmt) {
                    logError("Failed to prepare statement: " . $db->getError());
                    $error = 'Database error occurred. Please try again.';
                } else {
                    $stmt->bind_param("issssddd", $user_id, $title, $description, $photo_path, $location, $latitude, $longitude, $location_accuracy);
                }
                
                if (empty($error)) {
                    if ($stmt->execute()) {
                        $issue_id = $db->getLastInsertId();
                        
                        // Debug: Log successful insertion
                        logApplicationEvent("Issue inserted successfully", "Issue ID: $issue_id, User: $user_id");
                        
                        try {
                        // Create notification for user
                        $notification_data = [
                            'user_id' => $user_id,
                            'type' => 'new_issue',
                            'title' => 'Issue Submitted Successfully',
                            'message' => "Your issue '$title' has been submitted and is under review.",
                            'data' => [
                                'issue_id' => $issue_id,
                                'title' => $title,
                                'location' => $location
                            ]
                        ];
                        createNotification($notification_data);
                        } catch (Exception $e) {
                            logError("Failed to create user notification: " . $e->getMessage());
                        }
                        
                        try {
                        // Notify all admins about new issue
                            $admin_stmt = $db->prepare("SELECT id, name FROM panchayat_admins");
                            $admin_stmt->execute();
                            $admins = $admin_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        
                        foreach ($admins as $admin) {
                            $admin_notification = [
                                'admin_id' => $admin['id'],
                                'type' => 'new_issue',
                                'title' => 'New Issue Received',
                                'message' => "A new issue '$title' has been submitted by {$user['name']}.",
                                'data' => [
                                    'issue_id' => $issue_id,
                                    'user_name' => $user['name'],
                                    'title' => $title,
                                    'location' => $location
                                ]
                            ];
                            createNotification($admin_notification);
                            }
                        } catch (Exception $e) {
                            logError("Failed to create admin notifications: " . $e->getMessage());
                        }
                        
                        try {
                        // Check and award achievements
                        checkUserAchievements($user_id);
                        } catch (Exception $e) {
                            logError("Failed to check user achievements: " . $e->getMessage());
                        }
                        
                        // Log the issue submission
                        logApplicationEvent("New issue submitted", "Issue ID: $issue_id, User: {$user['name']}, Title: $title");
                        
                        setFlashMessage('success', 'Issue submitted successfully! Your complaint has been registered.');
                        header('Location: dashboard.php');
                        exit();
                    } else {
                        logError("Failed to execute statement: " . $stmt->error);
                        logError("Failed to submit issue for user: " . $user_id);
                        $error = 'Failed to submit issue. Please try again.';
                    }
                }
            }
        } catch (Exception $e) {
            $error_msg = handleException($e, 'Issue submission failed');
            $error = 'An internal error occurred. Please try again later.';
            logError("Issue submission error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Issue - Grama Voice</title>

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
            --primary-green: #2d5a27;
            --secondary-green: #4a7c59;
            --accent-brown: #8b4513;
            --warm-yellow: #f4d03f;
            --bright-orange: #ff8c00;
            --light-green: #90ee90;
            --dark-brown: #654321;
            --cream: #f5f5dc;
            --white: #ffffff;
            --gray: #666;
            --light-gray: #f8f9fa;
            --gradient-primary: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            --gradient-warm: linear-gradient(135deg, var(--warm-yellow), var(--bright-orange));
            --shadow-soft: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 15px 40px rgba(0, 0, 0, 0.15);
            --shadow-strong: 0 20px 50px rgba(0, 0, 0, 0.2);
            
            /* Dashboard Colors */
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
            background: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.6;
            overflow-x: hidden;
            min-height: 100vh;
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
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--gray-900);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--success-color), var(--info-color));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .user-profile {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), var(--info-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
            position: relative;
            margin-bottom: 1rem;
        }

        .time-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--orange);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            min-width: 30px;
            text-align: center;
        }

        .user-info h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .user-info p {
            color: var(--gray-500);
            font-size: 0.875rem;
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
            background: var(--gray-50);
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

        .search-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .search-icon:hover {
            background: var(--gray-200);
            color: var(--gray-700);
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

        /* Content Area */
        .content-area {
            padding: 2rem;
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

        /* Mobile Overlay */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .mobile-overlay.active {
            display: block;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                width: 250px;
            }
            
            .main-content {
                margin-left: 250px;
            }
        }

        @media (max-width: 768px) {
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
            
            .header {
                padding: 1rem;
            }
            
            .content-area {
                padding: 1rem;
            }
            
            .greeting h1 {
                font-size: 1.25rem;
            }
            
            .btn-add-issue {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
            
            .mobile-menu-btn {
                display: block;
            }
        }

        /* Modern Navigation */
        .modern-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-soft);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-family: 'Inter', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-green);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            color: var(--secondary-green);
            transform: scale(1.02);
        }

        .brand-icon {
            font-size: 1.5rem;
            color: var(--warm-yellow);
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover .brand-icon {
            transform: rotate(5deg) scale(1.1);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Form Container */
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            position: relative;
        }

        .form-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .form-title {
            font-family: 'Inter', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 400;
            opacity: 0.9;
        }

        /* Form Content */
        .form-content {
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-family: 'Inter', sans-serif;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--secondary-green);
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control, .form-select {
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            padding: 0.875rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.1);
            outline: none;
        }

        .form-control.is-invalid {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-control.is-valid {
            border-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .invalid-feedback {
            color: var(--danger-color);
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        .valid-feedback {
            color: var(--success-color);
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        /* File Upload Area */
        .file-upload-area {
            border: 2px dashed var(--gray-300);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            background: var(--gray-50);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: var(--primary-green);
            background: rgba(45, 90, 39, 0.05);
        }

        .file-upload-area.dragover {
            border-color: var(--primary-green);
            background: rgba(45, 90, 39, 0.1);
        }

        .upload-icon {
            font-size: 2.5rem;
            color: var(--primary-green);
            margin-bottom: 1rem;
        }

        .upload-text {
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .upload-subtext {
            color: var(--gray-500);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .upload-btn {
            background: var(--primary-green);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .upload-btn:hover {
            background: var(--secondary-green);
            color: white;
        }

        /* Image Preview */
        .image-preview {
            display: none;
            text-align: center;
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: 12px;
            border: 2px solid var(--gray-200);
        }

        .preview-image {
            max-width: 250px;
            max-height: 250px;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1rem;
        }

        .remove-btn {
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.4rem 1rem;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: #dc2626;
            color: white;
        }


        /* Submit Button */
        .submit-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Location Status */
        .location-status {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .location-status.info {
            background: #dbeafe;
            color: #1e40af;
        }

        .location-status.success {
            background: #d1fae5;
            color: #065f46;
        }

        .location-status.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-green);
            color: var(--primary-green);
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-green);
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .form-container {
                margin: 1rem;
                border-radius: 16px;
            }

            .form-header {
                padding: 1.5rem;
            }

            .form-title {
                font-size: 1.5rem;
            }

            .form-content {
                padding: 1.5rem;
            }

            .file-upload-area {
                padding: 1.5rem;
            }

            .upload-icon {
                font-size: 2rem;
            }
        }




        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
                    <a href="track_issue.php" class="nav-link">
                        <i class="fas fa-list-check"></i>
                        My Issues
                    </a>
                </div>
                <div class="nav-item">
                    <a href="submit_issue.php" class="nav-link active">
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
                        <h1>Submit New Issue</h1>
                        <p>Help improve your village by reporting issues</p>
                    </div>
                </div>
                <div class="header-right">
                    <div class="search-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <a href="dashboard.php" class="btn-add-issue">
                        <i class="fas fa-home"></i>
                        Back to Dashboard
                    </a>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Error/Success Messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Form Container -->
                <div class="form-container fade-in">
                    <!-- Form Header -->
                    <div class="form-header">
                        <h1 class="form-title">
                            <i class="fas fa-plus-circle me-2"></i>
                Submit New Issue
            </h1>
                        <p class="form-subtitle">Help improve your village by reporting issues that need attention</p>
        </div>

                    <!-- Form Content -->
                    <div class="form-content">
                        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" enctype="multipart/form-data" id="issueForm">
                            <!-- Issue Details Section -->
                            <div class="form-section">
                                <h3 class="section-title">
                        <i class="fas fa-edit"></i>
                                    Issue Details
                                </h3>
                
                <div class="form-group">
                    <label for="title" class="form-label">Issue Type <span class="text-danger">*</span></label>
                    <select class="form-control" id="title" name="title" required>
                        <option value="">Select issue type...</option>
                        <?php foreach ($issue_types as $key => $value): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select an issue type.</div>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Detailed Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="5" 
                              placeholder="Please provide detailed information about the issue..." required></textarea>
                    <div class="invalid-feedback">Please provide a detailed description.</div>
                </div>
            </div>

                            <!-- Location Section -->
                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Location Details
                                </h3>
                
                <div class="form-group">
                    <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                    
                                    <!-- Location Button -->
                                    <div class="text-center mb-3">
                                        <button type="button" class="btn btn-outline-primary" id="getLocationBtn" onclick="getCurrentLocation()">
                                            <i class="fas fa-crosshairs me-2"></i>
                                            Get My Location
                                        </button>
                                    </div>
                                    
                        <input type="text" class="form-control" id="location" name="location" 
                               placeholder="Enter location or landmark" required>
                                    
                                    <div class="location-status" id="locationStatus" style="display: none;">
                                        <i class="fas fa-info-circle"></i>
                                        <span id="locationStatusText">Getting your location...</span>
                </div>
                
                                    <div id="locationInfo" class="mt-2 p-2 bg-light rounded" style="display: none;">
                                        <small class="text-muted">
                                            <strong>Coordinates:</strong> <span id="locationCoords"></span><br>
                                            <strong>Accuracy:</strong> <span id="locationAccuracy"></span>
                                        </small>
                </div>
                
                                    <div class="invalid-feedback">Please provide the location of the issue.</div>
                </div>
            </div>

                            <!-- Media Upload Section -->
                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-camera"></i>
                                    Photo Upload (Optional)
                                </h3>
                                
                <div class="form-group">
                    <div class="file-upload-area" id="fileUploadArea">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <div class="upload-text">Upload Photo</div>
                        <div class="upload-subtext">Drag and drop an image here or click to browse</div>
                        <button type="button" class="upload-btn" onclick="document.getElementById('photo').click()">
                            <i class="fas fa-folder-open me-2"></i>Choose File
                        </button>
                        <input type="file" class="d-none" id="photo" name="photo" accept="image/*">
                        <div class="mt-2">
                            <small class="text-muted">Supported formats: JPG, PNG, GIF, WebP (Max 5MB)</small>
                        </div>
                    </div>
                    
                    <div class="image-preview" id="imagePreview">
                        <img id="previewImg" class="preview-image" alt="Preview">
                        <div class="mt-2">
                            <button type="button" class="remove-btn" onclick="removeImage()">
                                <i class="fas fa-trash me-1"></i>Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>

                            <!-- Hidden fields for GPS coordinates -->
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                            <input type="hidden" name="location_accuracy" id="location_accuracy">

                            <!-- Submit Button -->
                            <button type="submit" class="submit-btn" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i>
                Submit Issue
            </button>
                        </form>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form State Management
        let formData = {
            latitude: null,
            longitude: null,
            locationAccuracy: null
        };

        // Initialize Form
        document.addEventListener('DOMContentLoaded', function() {
            initializeFileUpload();
            initializeFormValidation();
        });

        // Geolocation Functions

        function getCurrentLocation() {
            console.log('Getting current location...');
            
            const statusDiv = document.getElementById('locationStatus');
            const statusText = document.getElementById('locationStatusText');
            const locationInfo = document.getElementById('locationInfo');
            const getLocationBtn = document.getElementById('getLocationBtn');
            
            if (!navigator.geolocation) {
                statusDiv.className = 'location-status error';
                statusText.innerHTML = 'Geolocation is not supported by this browser.';
                statusDiv.style.display = 'block';
                return;
            }
            
            statusDiv.className = 'location-status info';
            statusText.innerHTML = 'Getting your location...';
            statusDiv.style.display = 'block';
            getLocationBtn.disabled = true;
            
            const options = {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000 // 5 minutes
            };
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    console.log('Location obtained:', position.coords);
                    
                    formData.latitude = position.coords.latitude;
                    formData.longitude = position.coords.longitude;
                    formData.locationAccuracy = position.coords.accuracy;
                    
                    // Update hidden fields
                    document.getElementById('latitude').value = position.coords.latitude;
                    document.getElementById('longitude').value = position.coords.longitude;
                    document.getElementById('location_accuracy').value = position.coords.accuracy;
                    
                    // Update display
                    document.getElementById('locationCoords').textContent = 
                        `${position.coords.latitude.toFixed(6)}, ${position.coords.longitude.toFixed(6)}`;
                    document.getElementById('locationAccuracy').textContent = 
                        `±${Math.round(position.coords.accuracy)} meters`;
                    
                    // Set a fallback location immediately
                    const locationField = document.getElementById('location');
                    if (locationField && !locationField.value.trim()) {
                        locationField.value = `GPS Location: ${position.coords.latitude.toFixed(6)}, ${position.coords.longitude.toFixed(6)}`;
                        // Trigger validation to clear any error messages
                        locationField.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    
                    // Try to get address from coordinates
                    getAddressFromCoords(position.coords.latitude, position.coords.longitude);
                    
                    statusDiv.className = 'location-status success';
                    statusText.innerHTML = 'Location captured successfully!';
                    locationInfo.style.display = 'block';
                    getLocationBtn.disabled = false;
                },
                function(error) {
                    console.error('Geolocation error:', error);
                    
                    let errorMessage = 'Unable to get your location. ';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += 'Please allow location access and try again.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += 'Location information is unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMessage += 'Location request timed out.';
                            break;
                        default:
                            errorMessage += 'An unknown error occurred.';
                            break;
                    }
                    
                    statusDiv.className = 'location-status error';
                    statusText.innerHTML = errorMessage;
                    getLocationBtn.disabled = false;
                },
                options
            );
        }

        function getAddressFromCoords(lat, lng) {
            // Using OpenStreetMap Nominatim API for reverse geocoding
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.display_name) {
                        const locationField = document.getElementById('location');
                        if (locationField) {
                            // Extract a more readable address
                            let address = data.display_name;
                            // Try to get a shorter, more relevant address
                            if (data.address) {
                                const parts = [];
                                if (data.address.village) parts.push(data.address.village);
                                if (data.address.town) parts.push(data.address.town);
                                if (data.address.city) parts.push(data.address.city);
                                if (data.address.state) parts.push(data.address.state);
                                if (parts.length > 0) {
                                    address = parts.join(', ');
                                }
                            }
                            // Always auto-fill the location field when GPS coordinates are captured
                            locationField.value = address;
                            
                            // Trigger validation to clear any error messages
                            locationField.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }
                })
                .catch(error => {
                    console.log('Error getting address:', error);
                    // Keep the fallback GPS coordinates if reverse geocoding fails
                });
        }

        function reverseGeocode(lat, lng) {
            // Simple reverse geocoding using a free service
            fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`)
                .then(response => response.json())
                .then(data => {
                    if (data.locality && data.city) {
                        const locationField = document.getElementById('location');
                        if (locationField) {
                            // Always auto-fill the location field when GPS coordinates are captured
                            locationField.value = `${data.locality}, ${data.city}`;
                        }
                    }
                })
                .catch(error => {
                    console.log('Reverse geocoding failed:', error);
                });
        }

        // File Upload Functions
        function initializeFileUpload() {
            const fileUploadArea = document.getElementById('fileUploadArea');
            const photoInput = document.getElementById('photo');
            const imagePreview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');

            // Drag and drop functionality
            fileUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileUploadArea.classList.add('dragover');
            });

            fileUploadArea.addEventListener('dragleave', () => {
                fileUploadArea.classList.remove('dragover');
            });

            fileUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileUploadArea.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    photoInput.files = files;
                    handleFileSelect(files[0]);
                }
            });

            // File input change
            photoInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFileSelect(e.target.files[0]);
                }
            });
        }

        function handleFileSelect(file) {
            if (file && file.type.startsWith('image/')) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB.');
                    return;
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                    document.getElementById('fileUploadArea').style.display = 'none';
                };
                reader.readAsDataURL(file);
            } else {
                alert('Please select a valid image file.');
            }
        }

        function removeImage() {
            document.getElementById('photo').value = '';
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('fileUploadArea').style.display = 'block';
        }

        // Form Validation
        function initializeFormValidation() {
            const form = document.getElementById('issueForm');
            const submitBtn = document.getElementById('submitBtn');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                if (validateForm()) {
                    // Show loading state
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
                    submitBtn.disabled = true;

                    // Submit form
                    form.submit();
                }
            });
        }

        function validateForm() {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const location = document.getElementById('location').value.trim();

            // Clear previous validation states
            document.getElementById('title').classList.remove('is-invalid');
            document.getElementById('description').classList.remove('is-invalid');
            document.getElementById('location').classList.remove('is-invalid');

            let isValid = true;

            if (!title) {
                document.getElementById('title').classList.add('is-invalid');
                isValid = false;
            }

            if (!description) {
                document.getElementById('description').classList.add('is-invalid');
                isValid = false;
            }

            if (!location) {
                document.getElementById('location').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                // Scroll to first invalid field
                const firstInvalid = document.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
            }

            return isValid;
        }









    </script>
    
    <!-- Mobile Menu JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            if (mobileMenuBtn && sidebar && mobileOverlay) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                    mobileOverlay.classList.toggle('active');
                });
                
                mobileOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    mobileOverlay.classList.remove('active');
                });
                
                // Close menu when clicking nav links
                const navLinks = document.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        sidebar.classList.remove('open');
                        mobileOverlay.classList.remove('active');
                    });
                });
                
                // Close menu on window resize if screen becomes large
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        sidebar.classList.remove('open');
                        mobileOverlay.classList.remove('active');
                    }
                });
            }
        });
    </script>
            </div>
        </main>
    </div>

    <!-- Mobile Bottom Navigation -->
    <?php include '../includes/mobile_navbar.php'; ?>
</body>
</html>
