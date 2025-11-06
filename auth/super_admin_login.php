<?php
/**
 * Super Admin Login
 * Grama Voice - Village Governance Platform
 */

session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT id, username, password FROM super_admin WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                
                if (password_verify($password, $admin['password'])) {
                    $_SESSION['super_admin_id'] = $admin['id'];
                    $_SESSION['super_admin_username'] = $admin['username'];
                    
                    header('Location: ../superadmin/dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid password.';
                }
            } else {
                $error = 'Username not found.';
            }
        } catch (Exception $e) {
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
    <title>Super Admin Login - Grama Voice</title>
    
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

        /* Button Groups */
        .btn-group {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-group .btn {
            flex: 1;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 8px;
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

            .btn-group {
                flex-direction: column;
            }

            .btn-group .btn {
                margin-bottom: 0.5rem;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .slide-in-up {
            animation: slideInUp 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
            <i class="fas fa-crown"></i>
            <span>Super Admin</span>
        </div>
        <div class="floating-card">
            <i class="fas fa-shield-alt"></i>
            <span>Security</span>
        </div>
        <div class="floating-card">
            <i class="fas fa-cogs"></i>
            <span>System Control</span>
        </div>
    </div>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2><i class="fas fa-crown brand-icon"></i>Grama Voice</h2>
                <p>Login as Super Administrator</p>
            </div>
            
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger fade-in" role="alert">
                        <i class="fas fa-exclamation-circle"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <input type="text" class="form-control has-icon" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>" 
                                   placeholder="Enter your username" required>
                            <i class="fas fa-user input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control has-icon" id="password" name="password" 
                                   placeholder="Enter your password" required>
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced form validation and UX
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const submitBtn = document.getElementById('submitBtn');
            const loginForm = document.getElementById('loginForm');
            
            if (usernameInput) {
                // Focus management
                usernameInput.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                usernameInput.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
                
                // Auto-focus on load
                setTimeout(() => {
                    usernameInput.focus();
                }, 500);
            }
            
            if (passwordInput) {
                // Focus management for password
                passwordInput.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                passwordInput.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            }
            
            // Form submission with loading state
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    const username = usernameInput.value;
                    const password = passwordInput.value;
                    
                    if (!username.trim() || !password.trim()) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Show loading state
                    if (submitBtn) {
                        submitBtn.classList.add('loading');
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
                    }
                });
            }
            
            // Add keyboard navigation support
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && usernameInput && usernameInput === document.activeElement) {
                    const username = usernameInput.value;
                    if (username.trim()) {
                        passwordInput.focus();
                    }
                }
            });
            
            // Add accessibility features
            if (usernameInput) {
                usernameInput.setAttribute('aria-label', 'Username');
            }
            
            if (passwordInput) {
                passwordInput.setAttribute('aria-label', 'Password');
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
