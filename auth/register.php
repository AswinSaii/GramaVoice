<?php
/**
 * User Registration with OTP
 * Grama Voice - Village Governance Platform
 */

require_once '../config/db.php';
require_once '../config/error_handler.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = sanitizeInput($_POST['phone']);
    $name = sanitizeInput($_POST['name']);
    
    // Validate input
    if (empty($phone) || empty($name)) {
        $error = 'Please fill in all fields.';
    } elseif (!validatePhoneNumber($phone)) {
        $error = 'Please enter a valid 10-digit phone number.';
    } else {
        try {
            $db = getDB();
            
            // Check if phone number already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                logError("Registration attempt with existing phone: " . $phone);
                setFlashMessage('error', 'Mobile number already exists! Please login instead.');
                header('Location: login.php');
                exit();
            } else {
                // Generate OTP
                $otp = generateOTP(4);
                
                // Insert or update user with OTP (default to verified/unblocked)
                $stmt = $db->prepare("INSERT INTO users (name, phone, otp, verified) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE otp = ?, verified = 1");
                $stmt->bind_param("ssss", $name, $phone, $otp, $otp);
                
                if ($stmt->execute()) {
                    // Store OTP in session for verification
                    $_SESSION['temp_phone'] = $phone;
                    $_SESSION['temp_name'] = $name;
                    $_SESSION['temp_otp'] = $otp;
                    
                    // For demo purposes, show OTP on screen
                    $success = "OTP sent to your phone: <strong>$otp</strong> (Demo Mode)";
                } else {
                    logError("Registration failed for phone: " . $phone);
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch (Exception $e) {
            $error_msg = handleException($e, 'Registration failed');
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Grama Voice</title>
    
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

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
            display: block;
            transition: all 0.3s ease;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(45, 90, 39, 0.15);
            background: white;
            transform: translateY(-2px);
        }

        .form-control::placeholder {
            color: #adb5bd;
            font-weight: 400;
        }

        /* Input Icons */
        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-green);
            font-size: 1.1rem;
            z-index: 3;
            transition: all 0.3s ease;
        }

        .form-control:focus + .input-icon {
            color: var(--secondary-green);
            transform: translateY(-50%) scale(1.1);
        }

        .form-control.has-icon {
            padding-left: 3rem;
        }

        /* Validation States */
        .form-control.is-valid {
            border-color: #28a745;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.94-.94 1.44 1.44L7.7 4.3l.94.94L4.68 9.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 4.6 1.4 1.4m0-1.4-1.4 1.4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .valid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #28a745;
            font-weight: 500;
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #dc3545;
            font-weight: 500;
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

        /* OTP Display */
        .otp-display {
            background: linear-gradient(135deg, rgba(45, 90, 39, 0.1), rgba(74, 124, 89, 0.05));
            border: 2px dashed var(--primary-green);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
            overflow: hidden;
        }

        .otp-display::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(45, 90, 39, 0.05), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .otp-display h4 {
            font-family: 'Inter', sans-serif;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .otp-display h2 {
            font-family: 'Inter', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-green);
            letter-spacing: 0.2em;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .otp-display p {
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            color: var(--gray);
            margin: 0;
            position: relative;
            z-index: 2;
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

            .form-control {
                padding: 0.875rem 1rem;
                font-size: 0.95rem;
            }

            .btn-primary {
                padding: 0.875rem 1.5rem;
                font-size: 0.95rem;
            }

            .otp-display {
                padding: 1.5rem;
            }

            .otp-display h2 {
                font-size: 2rem;
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
        .form-control:focus {
            outline: none;
        }

        /* High Contrast Mode Support */
        @media (prefers-contrast: high) {
            .auth-card {
                border: 2px solid var(--primary-green);
            }
            
            .form-control {
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
            <i class="fas fa-user-plus"></i>
            <span>Join Community</span>
        </div>
        <div class="floating-card">
            <i class="fas fa-shield-alt"></i>
            <span>Secure Registration</span>
        </div>
        <div class="floating-card">
            <i class="fas fa-mobile-alt"></i>
            <span>OTP Verification</span>
        </div>
    </div>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2><i class="fas fa-microphone-alt brand-icon"></i>Grama Voice</h2>
                <p>Register as Citizen</p>
            </div>
            
            <div class="auth-body">
                <!-- Progress Indicator -->
                <div class="progress-indicator">
                    <div class="progress-step active"></div>
                    <div class="progress-step"></div>
                    <div class="progress-step"></div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger fade-in" role="alert">
                        <i class="fas fa-exclamation-circle"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success fade-in" role="alert">
                        <i class="fas fa-check-circle"></i><?php echo $success; ?>
                    </div>
                    
                    <div class="otp-display bounce-in">
                        <h4><i class="fas fa-key"></i>Your OTP</h4>
                        <h2><?php echo $_SESSION['temp_otp']; ?></h2>
                        <p>Enter this OTP to verify your phone number</p>
                    </div>
                    
                    <div class="text-center">
                        <a href="otp_verify.php?phone=<?php echo urlencode($_SESSION['temp_phone']); ?>&name=<?php echo urlencode($_SESSION['temp_name']); ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-right"></i>Verify OTP
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" id="registerForm">
                        <div class="form-group">
                            <label for="name" class="form-label">Full Name</label>
                            <div class="input-group">
                                <input type="text" class="form-control has-icon" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? $_POST['name'] : ''; ?>" 
                                       placeholder="Enter your full name" required>
                                <i class="fas fa-user input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <input type="tel" class="form-control has-icon" id="phone" name="phone" 
                                       value="<?php echo isset($_POST['phone']) ? $_POST['phone'] : ''; ?>" 
                                       placeholder="10-digit phone number" maxlength="10" required>
                                <i class="fas fa-mobile-alt input-icon"></i>
                            </div>
                            <div class="form-text">Enter your 10-digit mobile number</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane"></i> Send OTP
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <p class="text-muted">
                        Already have an account? 
                        <a href="login.php" class="auth-link">Login here</a>
                    </p>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced form validation and UX
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('name');
            const phoneInput = document.getElementById('phone');
            const submitBtn = document.getElementById('submitBtn');
            const registerForm = document.getElementById('registerForm');
            
            // Name validation
            if (nameInput) {
                nameInput.addEventListener('input', function(e) {
                    validateName(e.target.value);
                });
                
                nameInput.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                nameInput.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            }
            
            // Phone validation
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 10) {
                        value = value.substring(0, 10);
                    }
                    e.target.value = value;
                    
                    validatePhoneNumber(value);
                });
                
                phoneInput.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                phoneInput.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            }
            
            // Form submission with loading state
            if (registerForm) {
                registerForm.addEventListener('submit', function(e) {
                    const name = nameInput.value;
                    const phone = phoneInput.value;
                    
                    if (!validateName(name) || !validatePhoneNumber(phone)) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Show loading state
                    if (submitBtn) {
                        submitBtn.classList.add('loading');
                        submitBtn.disabled = true;
                    }
                });
            }
            
            // Validation functions
            function validateName(name) {
                const nameRegex = /^[a-zA-Z\s]{2,50}$/;
                const isValid = nameRegex.test(name.trim());
                
                if (nameInput) {
                    if (name.length === 0) {
                        nameInput.classList.remove('is-valid', 'is-invalid');
                    } else if (isValid) {
                        nameInput.classList.remove('is-invalid');
                        nameInput.classList.add('is-valid');
                    } else {
                        nameInput.classList.remove('is-valid');
                        nameInput.classList.add('is-invalid');
                    }
                }
                
                return isValid;
            }
            
            function validatePhoneNumber(phone) {
                const phoneRegex = /^[6-9]\d{9}$/;
                const isValid = phoneRegex.test(phone);
                
                if (phoneInput) {
                    if (phone.length === 0) {
                        phoneInput.classList.remove('is-valid', 'is-invalid');
                    } else if (isValid) {
                        phoneInput.classList.remove('is-invalid');
                        phoneInput.classList.add('is-valid');
                    } else {
                        phoneInput.classList.remove('is-valid');
                        phoneInput.classList.add('is-invalid');
                    }
                }
                
                return isValid;
            }
            
            // Auto-focus on first input
            setTimeout(() => {
                if (nameInput) {
                    nameInput.focus();
                }
            }, 500);
            
            // Add keyboard navigation support
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    if (nameInput && nameInput === document.activeElement) {
                        phoneInput.focus();
                        e.preventDefault();
                    } else if (phoneInput && phoneInput === document.activeElement) {
                        const name = nameInput.value;
                        const phone = phoneInput.value;
                        if (validateName(name) && validatePhoneNumber(phone)) {
                            registerForm.submit();
                        }
                    }
                }
            });
            
            // Add accessibility features
            if (nameInput) {
                nameInput.setAttribute('aria-label', 'Full name');
                nameInput.setAttribute('aria-describedby', 'name-help');
            }
            
            if (phoneInput) {
                phoneInput.setAttribute('aria-label', 'Phone number');
                phoneInput.setAttribute('aria-describedby', 'phone-help');
            }
            
            // Add screen reader support
            const nameHelp = document.createElement('div');
            nameHelp.id = 'name-help';
            nameHelp.className = 'sr-only';
            nameHelp.textContent = 'Enter your full name';
            if (nameInput && nameInput.parentNode) {
                nameInput.parentNode.appendChild(nameHelp);
            }
            
            const phoneHelp = document.createElement('div');
            phoneHelp.id = 'phone-help';
            phoneHelp.className = 'sr-only';
            phoneHelp.textContent = 'Enter your 10-digit mobile number';
            if (phoneInput && phoneInput.parentNode) {
                phoneInput.parentNode.appendChild(phoneHelp);
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
