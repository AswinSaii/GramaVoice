<?php
/**
 * OTP Verification
 * Grama Voice - Village Governance Platform
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Check if user came from registration or login
if (!isset($_SESSION['temp_phone']) || !isset($_SESSION['temp_otp'])) {
    // Fallback for cases where mobile browsers drop the session: try to recover using phone from query params
    $fallbackPhone = isset($_GET['phone']) ? sanitizeInput($_GET['phone']) : '';
    $fallbackName = isset($_GET['name']) ? sanitizeInput($_GET['name']) : '';

    if (!empty($fallbackPhone)) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT name, otp FROM users WHERE phone = ?");
            $stmt->bind_param("s", $fallbackPhone);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $_SESSION['temp_phone'] = $fallbackPhone;
                $_SESSION['temp_name'] = !empty($fallbackName) ? $fallbackName : ($row['name'] ?? '');
                $_SESSION['temp_otp'] = $row['otp'] ?? '';
            }
        } catch (Exception $e) {
            // ignore and fall through to redirect
        }
    }

    if (!isset($_SESSION['temp_phone']) || !isset($_SESSION['temp_otp'])) {
        header('Location: login.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entered_otp = sanitizeInput($_POST['otp']);
    $stored_otp = $_SESSION['temp_otp'];
    $phone = $_SESSION['temp_phone'];
    $name = $_SESSION['temp_name'];
    
    
    // Fallback: If OTP is empty, try to get it from individual input fields
    if (empty($entered_otp)) {
        $otp1 = isset($_POST['otp1']) ? sanitizeInput($_POST['otp1']) : '';
        $otp2 = isset($_POST['otp2']) ? sanitizeInput($_POST['otp2']) : '';
        $otp3 = isset($_POST['otp3']) ? sanitizeInput($_POST['otp3']) : '';
        $otp4 = isset($_POST['otp4']) ? sanitizeInput($_POST['otp4']) : '';
        $entered_otp = $otp1 . $otp2 . $otp3 . $otp4;
    }
    
    if (empty($entered_otp)) {
        $error = 'Please enter the OTP.';
    } else {
        // Check OTP from session first, then from database as fallback
        $otp_valid = false;
        
        if ($entered_otp === $stored_otp) {
            $otp_valid = true;
        } else {
            // Fallback: Check OTP from database
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT otp FROM users WHERE phone = ? AND otp = ?");
                $stmt->bind_param("ss", $phone, $entered_otp);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $otp_valid = true;
                }
            } catch (Exception $e) {
                error_log("Database error in OTP verification: " . $e->getMessage());
            }
        }
        
        if (!$otp_valid) {
            $error = 'Invalid OTP. Please try again.';
        } else {
            try {
                $db = getDB();
                
                // Check if this is login (user already exists) or registration (new user)
                $stmt = $db->prepare("SELECT id, verified FROM users WHERE phone = ?");
                $stmt->bind_param("s", $phone);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // User exists - this is a login
                    $user = $result->fetch_assoc();
                    
                    // Clear OTP from database
                    $stmt = $db->prepare("UPDATE users SET otp = NULL WHERE phone = ?");
                    $stmt->bind_param("s", $phone);
                    $stmt->execute();
                    
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_phone'] = $phone;
                    
                    // Clear temporary session data
                    unset($_SESSION['temp_phone']);
                    unset($_SESSION['temp_name']);
                    unset($_SESSION['temp_otp']);
                    
                    setFlashMessage('success', 'Login successful! Welcome back to Grama Voice.');
                    header('Location: ../user/dashboard.php');
                    exit();
                } else {
                    // User doesn't exist - this is registration
                    $stmt = $db->prepare("INSERT INTO users (name, phone, verified) VALUES (?, ?, 1)");
                    $stmt->bind_param("ss", $name, $phone);
                    
                    if ($stmt->execute()) {
                        $user_id = $db->getLastInsertId();
                        
                        // Set session
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_phone'] = $phone;
                        
                        // Clear temporary session data
                        unset($_SESSION['temp_phone']);
                        unset($_SESSION['temp_name']);
                        unset($_SESSION['temp_otp']);
                        
                        setFlashMessage('success', 'Registration successful! Welcome to Grama Voice.');
                        header('Location: ../user/dashboard.php');
                        exit();
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Grama Voice</title>

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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a3d1a, #2d5a27, #4a7c59);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .auth-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a3d1a, #2d5a27, #4a7c59);
            z-index: -2;
        }

        .auth-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="particles" width="50" height="50" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23particles)"/></svg>');
            animation: particleMove 20s linear infinite;
            z-index: -1;
        }

        @keyframes particleMove {
            0% { transform: translateY(0px); }
            100% { transform: translateY(-100px); }
        }

        /* Floating Elements */
        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .floating-card {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 15px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
            font-size: 0.9rem;
            font-weight: 500;
            animation: floatCard 6s ease-in-out infinite;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .floating-card:nth-child(1) {
            top: 10%;
            right: 10%;
            animation-delay: 0s;
        }

        .floating-card:nth-child(2) {
            top: 50%;
            left: 5%;
            animation-delay: 2s;
        }

        .floating-card:nth-child(3) {
            bottom: 20%;
            right: 20%;
            animation-delay: 4s;
        }

        @keyframes floatCard {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(5deg);
            }
        }

        .floating-card i {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: var(--warm-yellow);
        }

        /* Main Auth Container */
        .auth-container {
            width: 100%;
            max-width: 450px;
            padding: 2rem;
            position: relative;
            z-index: 10;
        }

        /* Glassmorphism Card */
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: var(--shadow-strong);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: slideInUp 0.8s ease-out;
        }

        .auth-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header */
        .auth-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .auth-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .auth-card:hover .auth-header::before {
            transform: translateX(100%);
        }

        .auth-header h2 {
            font-family: 'Inter', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.01em;
            position: relative;
            z-index: 2;
        }

        .auth-header p {
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 400;
            opacity: 0.9;
            margin: 0;
            position: relative;
            z-index: 2;
        }

        .brand-icon {
            font-size: 1.8rem;
            color: var(--warm-yellow);
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }

        .auth-card:hover .brand-icon {
            transform: rotate(5deg) scale(1.1);
        }

        /* Body */
        .auth-body {
            padding: 2.5rem 2rem;
        }

        /* SMS-like OTP Info */
        .otp-info {
            background: linear-gradient(135deg, rgba(45, 90, 39, 0.1), rgba(74, 124, 89, 0.05));
            border: 2px solid rgba(45, 90, 39, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .otp-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(45, 90, 39, 0.05), transparent);
            animation: shimmer 3s infinite;
        }

        .otp-info h5 {
            font-family: 'Inter', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .otp-info p {
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            color: var(--gray);
            margin: 0;
            position: relative;
            z-index: 2;
        }

        .phone-display {
            font-family: 'Inter', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        /* OTP Input Container */
        .otp-container {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .otp-input {
            width: 60px;
            height: 60px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .otp-input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(45, 90, 39, 0.15);
            background: white;
            transform: translateY(-2px);
        }

        .otp-input.filled {
            border-color: var(--primary-green);
            background: linear-gradient(135deg, rgba(45, 90, 39, 0.1), rgba(74, 124, 89, 0.05));
            color: var(--primary-green);
        }

        .otp-input.error {
            border-color: #dc3545;
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #dc3545;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }


        /* Buttons */
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            width: 100%;
            letter-spacing: 0.01em;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(45, 90, 39, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
            transition: all 0.1s ease;
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Loading State */
        .btn-primary.loading {
            pointer-events: none;
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

        /* Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: currentColor;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
            color: #28a745;
            border-left: 4px solid #28a745;
        }

        .alert i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }

        /* Links */
        .auth-link {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .auth-link:hover {
            color: var(--secondary-green);
            text-decoration: none;
        }

        .auth-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background: var(--primary-green);
            transition: width 0.3s ease;
        }

        .auth-link:hover::after {
            width: 100%;
        }

        /* Secondary Buttons */
        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }

        /* Progress Indicator */
        .progress-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 2rem;
            gap: 0.5rem;
        }

        .progress-step {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e9ecef;
            transition: all 0.3s ease;
        }

        .progress-step.active {
            background: var(--primary-green);
            transform: scale(1.2);
        }

        .progress-step.completed {
            background: var(--warm-yellow);
        }

        /* Resend Section */
        .resend-section {
            text-align: center;
            margin-top: 1.5rem;
        }

        .resend-btn {
            background: transparent;
            border: none;
            color: var(--primary-green);
            font-weight: 500;
            text-decoration: underline;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .resend-btn:hover {
            color: var(--secondary-green);
        }

        .resend-btn:disabled {
            color: #adb5bd;
            cursor: not-allowed;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .auth-container {
                padding: 1rem;
                max-width: 100%;
            }

            .auth-card {
                border-radius: 20px;
            }

            .auth-header {
                padding: 2rem 1.5rem;
            }

            .auth-header h2 {
                font-size: 1.75rem;
            }

            .auth-body {
                padding: 2rem 1.5rem;
            }

            .otp-container {
                gap: 0.75rem;
            }

            .otp-input {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }

            .floating-card {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .auth-header {
                padding: 1.5rem 1rem;
            }

            .auth-header h2 {
                font-size: 1.5rem;
            }

            .auth-body {
                padding: 1.5rem 1rem;
            }

            .otp-container {
                gap: 0.5rem;
            }

            .otp-input {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .slide-in-up {
            animation: slideInUp 0.8s ease-out;
        }

        .bounce-in {
            animation: bounceIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Focus Management */
        .otp-input:focus {
            outline: none;
        }

        /* High Contrast Mode Support */
        @media (prefers-contrast: high) {
            .auth-card {
                border: 2px solid var(--primary-green);
            }
            
            .otp-input {
                border-width: 2px;
            }
        }

        /* Reduced Motion Support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="auth-bg"></div>
    
    <!-- Floating Elements -->
    <div class="floating-elements">
        <div class="floating-card">
            <i class="fas fa-shield-alt"></i>
            <span>Secure Verification</span>
        </div>
        <div class="floating-card">
            <i class="fas fa-mobile-alt"></i>
            <span>SMS OTP</span>
        </div>
        <div class="floating-card">
            <i class="fas fa-check-circle"></i>
            <span>Quick Access</span>
        </div>
    </div>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2><i class="fas fa-shield-alt brand-icon"></i>Verify OTP</h2>
                <p>Enter the code sent to your phone</p>
            </div>
            
            <div class="auth-body">
                <!-- Progress Indicator -->
                <div class="progress-indicator">
                    <div class="progress-step completed"></div>
                    <div class="progress-step completed"></div>
                    <div class="progress-step active"></div>
                </div>
                
                <!-- SMS-like OTP Info -->
                <div class="otp-info fade-in">
                    <h5><i class="fas fa-mobile-alt"></i>Phone Number</h5>
                    <div class="phone-display"><?php echo $_SESSION['temp_phone']; ?></div>
                    <p>We sent a 4-digit code to this number</p>
                </div>
                
                
                <?php if ($error): ?>
                    <div class="alert alert-danger fade-in" role="alert">
                        <i class="fas fa-exclamation-circle"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="otpForm">
                    <!-- OTP Input Container -->
                    <div class="otp-container">
                        <input type="text" class="otp-input" id="otp1" name="otp1" maxlength="1" autocomplete="off">
                        <input type="text" class="otp-input" id="otp2" name="otp2" maxlength="1" autocomplete="off">
                        <input type="text" class="otp-input" id="otp3" name="otp3" maxlength="1" autocomplete="off">
                        <input type="text" class="otp-input" id="otp4" name="otp4" maxlength="1" autocomplete="off">
                    </div>
                    
                    <!-- Hidden input for form submission -->
                    <input type="hidden" id="otp" name="otp" value="">
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                        <i class="fas fa-check"></i>Verify & Continue
                    </button>
                </form>
                
                <!-- Resend Section -->
                <div class="resend-section">
                    <p class="text-muted">
                        Didn't receive the code? 
                        <button class="resend-btn" id="resendBtn">
                            Resend OTP
                        </button>
                    </p>
                </div>
                
                <div class="text-center mt-3">
                    <a href="register.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i>Back to Registration
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced OTP verification with SMS-like interface
        document.addEventListener('DOMContentLoaded', function() {
            const otpInputs = document.querySelectorAll('.otp-input');
            const otpForm = document.getElementById('otpForm');
            const submitBtn = document.getElementById('submitBtn');
            const resendBtn = document.getElementById('resendBtn');
            const hiddenOtpInput = document.getElementById('otp');
            
            // Auto-focus first input
            setTimeout(() => {
                otpInputs[0].focus();
            }, 500);
            
            // OTP Input handling
            otpInputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const value = e.target.value.replace(/\D/g, '');
                    e.target.value = value;
                    
                    if (value) {
                        e.target.classList.add('filled');
                        
                        // Move to next input
                        if (index < otpInputs.length - 1) {
                            otpInputs[index + 1].focus();
                        } else {
                            // All inputs filled, check OTP
                            checkOTP();
                        }
                    } else {
                        e.target.classList.remove('filled');
                    }
                    
                    updateSubmitButton();
                });
                
                input.addEventListener('keydown', function(e) {
                    // Handle backspace
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        otpInputs[index - 1].focus();
                        otpInputs[index - 1].classList.remove('filled');
                    }
                    
                    // Handle arrow keys
                    if (e.key === 'ArrowLeft' && index > 0) {
                        otpInputs[index - 1].focus();
                    }
                    if (e.key === 'ArrowRight' && index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                });
                
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').replace(/\D/g, '');
                    
                    if (pastedData.length === 4) {
                        pastedData.split('').forEach((digit, i) => {
                            if (otpInputs[i]) {
                                otpInputs[i].value = digit;
                                otpInputs[i].classList.add('filled');
                            }
                        });
                        checkOTP();
                        updateSubmitButton();
                    }
                });
                
                input.addEventListener('focus', function() {
                    this.select();
                });
            });
            
            // Form submission
            otpForm.addEventListener('submit', function(e) {
                const otp = getOTPValue();
                
                if (otp.length !== 4) {
                    e.preventDefault();
                    showError('Please enter all 4 digits');
                    return false;
                }
                
                // Show loading state
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                // Set hidden input value
                hiddenOtpInput.value = otp;
            });
            
            // Resend OTP functionality
            resendBtn.addEventListener('click', function() {
                if (!this.disabled) {
                    // Clear inputs
                    otpInputs.forEach(input => {
                        input.value = '';
                        input.classList.remove('filled', 'error');
                    });
                    
                    // Focus first input
                    otpInputs[0].focus();
                    
                    // Show success message
                    showSuccess('OTP resent successfully!');
                }
            });
            
            // Helper functions
            function getOTPValue() {
                return Array.from(otpInputs).map(input => input.value).join('');
            }
            
            function updateSubmitButton() {
                const otp = getOTPValue();
                submitBtn.disabled = otp.length !== 4;
            }
            
            function checkOTP() {
                const otp = getOTPValue();
                if (otp.length === 4) {
                    // Auto-submit after a short delay
                    setTimeout(() => {
                        if (otpForm.checkValidity()) {
                            otpForm.submit();
                        }
                    }, 500);
                }
            }
            
            function showError(message) {
                // Remove existing alerts
                const existingAlert = document.querySelector('.alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
                
                // Create error alert
                const alert = document.createElement('div');
                alert.className = 'alert alert-danger fade-in';
                alert.innerHTML = `<i class="fas fa-exclamation-circle"></i>${message}`;
                
                // Insert after progress indicator
                const progressIndicator = document.querySelector('.progress-indicator');
                progressIndicator.insertAdjacentElement('afterend', alert);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    alert.remove();
                }, 5000);
            }
            
            function showSuccess(message) {
                // Remove existing alerts
                const existingAlert = document.querySelector('.alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
                
                // Create success alert
                const alert = document.createElement('div');
                alert.className = 'alert alert-success fade-in';
                alert.innerHTML = `<i class="fas fa-check-circle"></i>${message}`;
                
                // Insert after progress indicator
                const progressIndicator = document.querySelector('.progress-indicator');
                progressIndicator.insertAdjacentElement('afterend', alert);
                
                // Auto-remove after 3 seconds
                setTimeout(() => {
                    alert.remove();
                }, 3000);
            }
            
            // Add keyboard navigation support
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const otp = getOTPValue();
                    if (otp.length === 4 && !submitBtn.disabled) {
                        otpForm.submit();
                    }
                }
            });
            
            // Add accessibility features
            otpInputs.forEach((input, index) => {
                input.setAttribute('aria-label', `OTP digit ${index + 1}`);
                input.setAttribute('aria-describedby', 'otp-help');
            });
            
            // Add screen reader support
            const otpHelp = document.createElement('div');
            otpHelp.id = 'otp-help';
            otpHelp.className = 'sr-only';
            otpHelp.textContent = 'Enter the 4-digit verification code';
            document.querySelector('.otp-container').appendChild(otpHelp);
            
            // Add vibration feedback for mobile devices
            if ('vibrate' in navigator) {
                otpInputs.forEach(input => {
                    input.addEventListener('input', function() {
                        if (this.value) {
                            navigator.vibrate(50); // Short vibration
                        }
                    });
                });
            }
        });
        
        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
        
        // Add reduced motion support
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.style.setProperty('--animation-duration', '0.01ms');
        }
    </script>
</body>
</html>
