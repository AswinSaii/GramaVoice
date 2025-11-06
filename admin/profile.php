<?php
/**
 * Admin Profile Page
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if panchayat admin is logged in
requirePanchayatAdminLogin();

$db = getDB();
$admin_id = $_SESSION['admin_id'];

// Get admin details
$selectSql = "SELECT * FROM panchayat_admins WHERE id = ?";
$stmt = $db->prepare($selectSql);
if (!$stmt) {
    // Log prepare error and show a friendly message
    Database::logApplicationError('Prepare failed for fetching admin details: ' . $db->getError(), __FILE__, __LINE__);
    die('Database error: failed to fetch admin details. Check logs for details.');
}
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim(sanitizeInput($_POST['name']));
    $phone = trim(sanitizeInput($_POST['phone']));
    $village_name = trim(sanitizeInput($_POST['village_name']));
    $email = trim(sanitizeInput($_POST['email']));
    
    // Validation
    $errors = [];
    
    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters long';
    }
    
    if (empty($phone) || !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'Please enter a valid 10-digit phone number';
    }
    
    if (empty($village_name) || strlen($village_name) < 2) {
        $errors[] = 'Village name must be at least 2 characters long';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($errors)) {
        try {
            // Check if email is already taken by another admin
            if (!empty($email)) {
                $checkStmt = $db->prepare("SELECT id FROM panchayat_admins WHERE email = ? AND id != ?");
                $checkStmt->bind_param("si", $email, $admin_id);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    $errors[] = 'Email address is already taken by another admin';
                }
            }
            
            if (empty($errors)) {
                $updateSql = "UPDATE panchayat_admins SET name = ?, phone = ?, village_name = ?, email = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $db->prepare($updateSql);
                if (!$stmt) {
                    setFlashMessage('error', 'Failed to update profile (DB prepare error)');
                    Database::logApplicationError('Prepare failed for admin profile update: ' . $db->getError(), __FILE__, __LINE__);
                } else {
                    $stmt->bind_param("ssssi", $name, $phone, $village_name, $email, $admin_id);

                    if ($stmt->execute()) {
                    // Update session data
                    $_SESSION['admin_name'] = $name;
                    $_SESSION['admin_village'] = $village_name;
                    
                    setFlashMessage('success', 'Profile updated successfully!');
                    header('Location: profile.php');
                    exit();
                    } else {
                        setFlashMessage('error', 'Failed to update profile. Please try again.');
                        Database::logApplicationError('Execute failed for admin profile update: ' . $db->getError(), __FILE__, __LINE__);
                    }
                }
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

// Handle profile image removal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_image'])) {
    try {
        // Delete the image file if it exists
        if (!empty($admin['profile_image']) && file_exists('../uploads/profiles/' . $admin['profile_image'])) {
            unlink('../uploads/profiles/' . $admin['profile_image']);
        }
        
        // Update database to remove profile image reference
        $stmt = $db->prepare("UPDATE panchayat_admins SET profile_image = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Profile image removed successfully!');
            header('Location: profile.php');
            exit();
        } else {
            setFlashMessage('error', 'Failed to remove profile image.');
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Database error: ' . $e->getMessage());
    }
}

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_image'])) {
    $errors = [];
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = 'Please upload a valid image file (JPEG, PNG, or GIF)';
        }
        
        if ($file['size'] > $max_size) {
            $errors[] = 'Image size must be less than 2MB';
        }
        
        if (empty($errors)) {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if (!empty($admin['profile_image']) && file_exists('../uploads/profiles/' . $admin['profile_image'])) {
                    unlink('../uploads/profiles/' . $admin['profile_image']);
                }
                
                try {
                    $stmt = $db->prepare("UPDATE panchayat_admins SET profile_image = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $new_filename, $admin_id);
                    
                    if ($stmt->execute()) {
                        setFlashMessage('success', 'Profile image updated successfully!');
                        header('Location: profile.php');
                        exit();
                    } else {
                        setFlashMessage('error', 'Failed to update profile image.');
                    }
                } catch (Exception $e) {
                    setFlashMessage('error', 'Database error: ' . $e->getMessage());
                }
            } else {
                setFlashMessage('error', 'Failed to upload image.');
            }
        } else {
            setFlashMessage('error', implode('<br>', $errors));
        }
    } else {
        setFlashMessage('error', 'Please select an image file.');
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($current_password)) {
        $errors[] = 'Current password is required';
    }
    
    if (empty($new_password)) {
        $errors[] = 'New password is required';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'New password must be at least 6 characters long';
    }
    
    if (empty($confirm_password)) {
        $errors[] = 'Please confirm your new password';
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match';
    }
    
    if (empty($errors)) {
        try {
            // Verify current password
            if (!password_verify($current_password, $admin['password'])) {
                $errors[] = 'Current password is incorrect';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE panchayat_admins SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $admin_id);
                
                if ($stmt->execute()) {
                    setFlashMessage('success', 'Password changed successfully!');
                    header('Location: profile.php');
                    exit();
                } else {
                    setFlashMessage('error', 'Failed to change password. Please try again.');
                }
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

// Get admin statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_issues,
        COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved_issues,
        COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_issues,
        COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress_issues
    FROM issues 
    WHERE assigned_to = ?
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get flash messages
$success_message = getFlashMessage('success');
$error_message = getFlashMessage('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Grama Voice</title>

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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.5;
            min-height: 100vh;
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
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0 0.5rem;
            border-radius: 8px;
        }
        
        .sidebar-nav .nav-link:hover {
            color: var(--primary-blue);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(59, 130, 246, 0.1));
            transform: translateX(5px);
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
            border-top: 1px solid rgba(0,0,0,0.1);
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
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
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
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
            background: transparent;
        }
        
        .top-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 1px solid rgba(255,255,255,0.2);
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
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Profile Cards */
        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        }
        
        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(59, 130, 246, 0.05));
            border-radius: 15px;
            border: 1px solid rgba(37, 99, 235, 0.1);
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 2.5rem;
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
            position: relative;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        .profile-avatar.has-image {
            background-image: none;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .profile-avatar .upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }
        
        .profile-avatar:hover .upload-overlay {
            opacity: 1;
        }
        
        .upload-overlay i {
            color: white;
            font-size: 1.5rem;
        }
        
        .profile-avatar::after {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            z-index: -1;
            opacity: 0.3;
        }
        
        .profile-info h4 {
            margin: 0;
            color: var(--gray-900);
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .profile-info p {
            margin: 0.5rem 0;
            color: var(--gray-600);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .profile-info p i {
            color: var(--primary-blue);
            width: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(59, 130, 246, 0.05));
            border-radius: 15px;
            border: 1px solid rgba(37, 99, 235, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        }
        
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.15);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin: 0.5rem 0 0 0;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: block;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid rgba(37, 99, 235, 0.1);
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            background: white;
            transform: translateY(-2px);
        }
        
        .form-control.is-valid {
            border-color: var(--success-green);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2310b981' d='m2.3 6.73.94-.94 1.06 1.06L6.73 4.3l.94.94L4.3 8.73z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .form-control.is-invalid {
            border-color: var(--danger-red);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23ef4444'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 4.6 1.4 1.4m0-1.4-1.4 1.4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: var(--danger-red);
            font-weight: 500;
        }
        
        .valid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: var(--success-green);
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
        }
        
        .btn-outline {
            border: 2px solid rgba(37, 99, 235, 0.2);
            color: var(--primary-blue);
            background: rgba(255, 255, 255, 0.8);
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-outline:hover {
            border-color: var(--primary-blue);
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(37, 99, 235, 0.1);
        }
        
        .section-title i {
            color: var(--primary-blue);
            font-size: 1.1rem;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
            color: var(--success-green);
            border-left: 4px solid var(--success-green);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            color: var(--danger-red);
            border-left: 4px solid var(--danger-red);
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
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }
        
        /* Animation for form elements */
        .form-control, .btn-primary, .btn-outline {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Loading animation */
        .btn-primary.loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .btn-primary.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                    <a class="nav-link active" href="profile.php">
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
                <h1 class="page-title">Profile Settings</h1>
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
            
            <!-- Profile Overview -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar <?php echo !empty($admin['profile_image']) ? 'has-image' : ''; ?>" onclick="document.getElementById('profileImageInput').click()">
                        <?php if (!empty($admin['profile_image']) && file_exists('../uploads/profiles/' . $admin['profile_image'])): ?>
                            <img src="../uploads/profiles/<?php echo htmlspecialchars($admin['profile_image']); ?>" alt="Profile Image">
                        <?php else: ?>
                            <?php echo strtoupper(substr($admin['name'], 0, 1)); ?>
                        <?php endif; ?>
                        <div class="upload-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    
                    <!-- Profile image actions -->
                    <div class="profile-actions mt-3">
                        <button type="button" class="btn-outline btn-sm me-2" onclick="document.getElementById('profileImageInput').click()">
                            <i class="fas fa-upload me-1"></i>Upload Image
                        </button>
                        <?php if (!empty($admin['profile_image']) && file_exists('../uploads/profiles/' . $admin['profile_image'])): ?>
                        <button type="button" class="btn-outline btn-sm" onclick="removeImage()" style="color: var(--danger-red); border-color: var(--danger-red);">
                            <i class="fas fa-trash me-1"></i>Remove Image
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h4><?php echo htmlspecialchars($admin['name']); ?></h4>
                        <p>Panchayat Admin - <?php echo htmlspecialchars($admin['village_name']); ?></p>
                        <p><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($admin['email'] ?? 'admin@gramavoice.com'); ?></p>
                        <p><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($admin['phone']); ?></p>
                    </div>
                </div>
                
                <!-- Hidden file input for image upload -->
                <form method="POST" enctype="multipart/form-data" id="imageUploadForm" style="display: none;">
                    <input type="file" name="profile_image" id="profileImageInput" accept="image/*" onchange="uploadImage()">
                    <input type="hidden" name="upload_image" value="1">
                </form>
                
                <!-- Remove image form -->
                <?php if (!empty($admin['profile_image']) && file_exists('../uploads/profiles/' . $admin['profile_image'])): ?>
                <form method="POST" action="" id="removeImageForm" style="display: none;">
                    <input type="hidden" name="remove_image" value="1">
                </form>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <p class="stat-value"><?php echo $stats['total_issues']; ?></p>
                        <p class="stat-label">Total Issues</p>
                    </div>
                    <div class="stat-item">
                        <p class="stat-value"><?php echo $stats['resolved_issues']; ?></p>
                        <p class="stat-label">Resolved</p>
                    </div>
                    <div class="stat-item">
                        <p class="stat-value"><?php echo $stats['pending_issues']; ?></p>
                        <p class="stat-label">Pending</p>
                    </div>
                    <div class="stat-item">
                        <p class="stat-value"><?php echo $stats['in_progress_issues']; ?></p>
                        <p class="stat-label">In Progress</p>
                    </div>
                </div>
            </div>
            
            <!-- Profile Information -->
            <div class="profile-card">
                <h5 class="section-title">
                    <i class="fas fa-user"></i>
                    Profile Information
                </h5>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Village Name</label>
                                <input type="text" name="village_name" class="form-control" value="<?php echo htmlspecialchars($admin['village_name']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn-primary">
                        <i class="fas fa-save me-1"></i>Update Profile
                    </button>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="profile-card">
                <h5 class="section-title">
                    <i class="fas fa-lock"></i>
                    Change Password
                </h5>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" minlength="6" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn-primary">
                        <i class="fas fa-key me-1"></i>Change Password
                    </button>
                </form>
            </div>
            
            <!-- Account Information -->
            <div class="profile-card">
                <h5 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Account Information
                </h5>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Admin ID</label>
                            <input type="text" class="form-control" value="<?php echo 'ADM' . str_pad($admin['id'], 3, '0', STR_PAD_LEFT); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="Panchayat Administrator" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Account Created</label>
                            <input type="text" class="form-control" value="<?php echo date('M j, Y', strtotime($admin['created_at'])); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Last Updated</label>
                            <input type="text" class="form-control" value="<?php echo date('M j, Y', strtotime($admin['updated_at'] ?? $admin['created_at'])); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation and submission handling
        document.addEventListener('DOMContentLoaded', function() {
            // Profile update form
            const profileForm = document.querySelector('form[action=""]');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[name="update_profile"]');
                    if (submitBtn) {
                        submitBtn.classList.add('loading');
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';
                    }
                });
            }
            
            // Password change form
            const passwordForm = document.querySelector('form[action=""]');
            if (passwordForm && passwordForm.querySelector('button[name="change_password"]')) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = this.querySelector('input[name="new_password"]').value;
                    const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        showAlert('New passwords do not match!', 'danger');
                        return;
                    }
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        showAlert('Password must be at least 6 characters long!', 'danger');
                        return;
                    }
                    
                    const submitBtn = this.querySelector('button[name="change_password"]');
                    if (submitBtn) {
                        submitBtn.classList.add('loading');
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Changing...';
                    }
                });
            }
            
            // Real-time form validation
            const formInputs = document.querySelectorAll('.form-control');
            formInputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateField(this);
                });
                
                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        validateField(this);
                    }
                });
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
            
            // Add smooth scrolling for better UX
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
        
        // Field validation function
        function validateField(field) {
            const value = field.value.trim();
            const fieldName = field.name;
            let isValid = true;
            let errorMessage = '';
            
            // Remove existing validation classes
            field.classList.remove('is-valid', 'is-invalid');
            
            // Validation rules
            switch(fieldName) {
                case 'name':
                    if (value.length < 2) {
                        isValid = false;
                        errorMessage = 'Name must be at least 2 characters long';
                    }
                    break;
                case 'phone':
                    const phoneRegex = /^[0-9]{10}$/;
                    if (!phoneRegex.test(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid 10-digit phone number';
                    }
                    break;
                case 'email':
                    if (value && !isValidEmail(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid email address';
                    }
                    break;
                case 'village_name':
                    if (value.length < 2) {
                        isValid = false;
                        errorMessage = 'Village name must be at least 2 characters long';
                    }
                    break;
                case 'new_password':
                case 'confirm_password':
                    if (value.length > 0 && value.length < 6) {
                        isValid = false;
                        errorMessage = 'Password must be at least 6 characters long';
                    }
                    break;
            }
            
            // Apply validation classes
            if (isValid) {
                field.classList.add('is-valid');
            } else {
                field.classList.add('is-invalid');
                showFieldError(field, errorMessage);
            }
            
            return isValid;
        }
        
        // Email validation helper
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        // Show field error message
        function showFieldError(field, message) {
            // Remove existing error message
            const existingError = field.parentNode.querySelector('.invalid-feedback');
            if (existingError) {
                existingError.remove();
            }
            
            // Add new error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            errorDiv.textContent = message;
            field.parentNode.appendChild(errorDiv);
        }
        
        // Show alert function
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert at the top of the main content
            const mainContent = document.querySelector('.p-4');
            mainContent.insertBefore(alertDiv, mainContent.firstChild);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertDiv.style.opacity = '0';
                setTimeout(() => {
                    alertDiv.remove();
                }, 300);
            }, 5000);
        }
        
        // Add loading states to buttons
        function addLoadingState(button, text = 'Loading...') {
            button.classList.add('loading');
            button.disabled = true;
            const originalText = button.innerHTML;
            button.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i>${text}`;
            
            return function removeLoadingState() {
                button.classList.remove('loading');
                button.disabled = false;
                button.innerHTML = originalText;
            };
        }
        
        // Remove profile image function
        function removeImage() {
            if (confirm('Are you sure you want to remove your profile image? This action cannot be undone.')) {
                const removeForm = document.getElementById('removeImageForm');
                if (removeForm) {
                    removeForm.submit();
                }
            }
        }
        
        // Profile picture upload functionality
        function uploadImage() {
            const fileInput = document.getElementById('profileImageInput');
            const file = fileInput.files[0];
            
            if (!file) return;
            
            // Validate file
            if (file.size > 2 * 1024 * 1024) { // 2MB limit
                showAlert('File size must be less than 2MB', 'danger');
                fileInput.value = '';
                return;
            }
            
            if (!file.type.startsWith('image/')) {
                showAlert('Please select an image file', 'danger');
                fileInput.value = '';
                return;
            }
            
            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                const avatar = document.querySelector('.profile-avatar');
                if (avatar) {
                    avatar.classList.add('has-image');
                    avatar.innerHTML = `
                        <img src="${e.target.result}" alt="Profile Image">
                        <div class="upload-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    `;
                }
            };
            reader.readAsDataURL(file);
            
            // Submit form
            document.getElementById('imageUploadForm').submit();
        }
        
        // Handle profile picture upload
        function handleProfilePictureUpload(input) {
            const file = input.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) { // 2MB limit
                    showAlert('File size must be less than 2MB', 'danger');
                    input.value = '';
                    return;
                }
                
                if (!file.type.startsWith('image/')) {
                    showAlert('Please select an image file', 'danger');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatar = document.querySelector('.profile-avatar');
                    if (avatar) {
                        avatar.style.backgroundImage = `url(${e.target.result})`;
                        avatar.style.backgroundSize = 'cover';
                        avatar.style.backgroundPosition = 'center';
                    }
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Add animation to cards on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Observe all profile cards
        document.querySelectorAll('.profile-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    </script>
