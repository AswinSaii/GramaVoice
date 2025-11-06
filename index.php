<?php
/**
 * Landing Page with Backend Integration
 * Grama Voice - Village Governance Platform
 */

require_once 'config/error_handler.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Get achievements/impact data (dynamic with safe fallbacks)
$db = getDB();
$achievements = [
    'Issues Solved' => 0,
    'Panchayats Connected' => 0,
    'Citizens Benefited' => 0,
    'Satisfaction Rate' => 0
];

try {
    // Dynamic metrics
    $res = $db->query("SELECT COUNT(*) AS c FROM issues WHERE status = 'Resolved'");
    $achievements['Issues Solved'] = (int)($res->fetch_assoc()['c'] ?? 0);

    $res = $db->query("SELECT COUNT(*) AS c FROM panchayat_admins");
    $achievements['Panchayats Connected'] = (int)($res->fetch_assoc()['c'] ?? 0);

    $res = $db->query("SELECT COUNT(*) AS c FROM users WHERE verified = 1");
    $achievements['Citizens Benefited'] = (int)($res->fetch_assoc()['c'] ?? 0);

    $resTotal = $db->query("SELECT COUNT(*) AS c FROM issues");
    $resResolved = $db->query("SELECT COUNT(*) AS c FROM issues WHERE status = 'Resolved'");
    $total = (int)($resTotal->fetch_assoc()['c'] ?? 0);
    $resolved = (int)($resResolved->fetch_assoc()['c'] ?? 0);
    $achievements['Satisfaction Rate'] = $total > 0 ? (int)round(($resolved / max($total, 1)) * 100) : 0;
} catch (Exception $e) {
    logError("Failed to compute dynamic achievements: " . $e->getMessage());
    // Fallback to achievements table if available
    try {
        $result = $db->query("SELECT type, count FROM achievements ORDER BY id");
        $fallback = [];
        while ($row = $result->fetch_assoc()) {
            $fallback[$row['type']] = $row['count'];
        }
        // Map known keys if present
        $achievements['Issues Solved'] = isset($fallback['Issues Solved']) ? (int)$fallback['Issues Solved'] : $achievements['Issues Solved'];
        $achievements['Panchayats Connected'] = isset($fallback['Panchayats Connected']) ? (int)$fallback['Panchayats Connected'] : $achievements['Panchayats Connected'];
        $achievements['Citizens Benefited'] = isset($fallback['Citizens Benefited']) ? (int)$fallback['Citizens Benefited'] : $achievements['Citizens Benefited'];
        $achievements['Satisfaction Rate'] = isset($fallback['Satisfaction Rate']) ? (int)$fallback['Satisfaction Rate'] : $achievements['Satisfaction Rate'];
    } catch (Exception $e2) {
        logError("Fallback fetch from achievements failed: " . $e2->getMessage());
        // Final static fallback
        $achievements = [
            'Issues Solved' => 120,
            'Panchayats Connected' => 10,
            'Citizens Benefited' => 500,
            'Satisfaction Rate' => 95
        ];
    }
}

// Get recent resolved issues for showcase
$recent_issues = [];
try {
    $result = $db->query("
        SELECT i.title, i.location, i.created_at, u.name as user_name, pa.village_name 
        FROM issues i 
        JOIN users u ON i.user_id = u.id 
        LEFT JOIN panchayat_admins pa ON i.assigned_to = pa.id 
        WHERE i.status = 'Resolved' 
        ORDER BY i.updated_at DESC 
        LIMIT 6
    ");
    $recent_issues = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    logError("Failed to fetch recent issues: " . $e->getMessage());
    // Fallback data
    $recent_issues = [
        ['title' => 'Street Light Repair', 'location' => 'Main Road', 'user_name' => 'Ravi Kumar', 'village_name' => 'Village A'],
        ['title' => 'Water Pipeline Fix', 'location' => 'Near School', 'user_name' => 'Sunita Devi', 'village_name' => 'Village B'],
        ['title' => 'Road Pothole Repair', 'location' => 'Village Entrance', 'user_name' => 'Mohan Lal', 'village_name' => 'Village C']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grama-Voice - Voice of the Villages</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/GramaVoice-Logo.png">
    <link rel="shortcut icon" type="image/png" href="images/GramaVoice-Logo.png">
    <link rel="apple-touch-icon" href="images/GramaVoice-Logo.png">

    <!-- Meta tags -->
    <meta name="description" content="Grama-Voice is a platform that empowers villagers to voice their concerns through technology-driven governance.">
    <meta name="keywords" content="Grama-Voice, Voice of the Villages, Village Governance, Citizen Engagement, Rural Development">
    <meta name="author" content="Grama-Voice">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta name="google" content="notranslate">
    <meta name="google-site-verification" content="V1MOgb3mW5SrkSBY3fQ1TnNnth000PoNZV6JzSP0KjU" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Footer Link Styles -->
    <style>
        /* Footer Link Hover Effects */
        .hover-link {
            transition: all 0.3s ease;
        }
        
        .hover-link:hover {
            color: #ffc107 !important;
            text-decoration: underline !important;
            transform: translateX(5px);
        }
        
        /* Footer Links Specific Styling */
        .footer-links a {
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 6px;
        }
        
        .footer-links a:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .footer-links {
                margin: 1rem 0;
            }
            
            .footer-links a {
                display: block;
                margin: 0.5rem 0;
                text-align: center;
            }
        }
        
        /* Social Media Button Hover Effects */
        .social-links .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
        }
        
        /* Testimonial avatar clarity */
        .testimonial-avatar img {
            width: 72px !important;
            height: 72px !important;
            border-radius: 50%;
            object-fit: cover;
            background-color: #ffffff;
            border: 3px solid #ffffff;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        
        @media (max-width: 576px) {
            .testimonial-avatar img {
                width: 64px !important;
                height: 64px !important;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Navbar with Bootstrap -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top custom-navbar">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3 text-primary" href="#">
                <i class="fas fa-microphone-alt me-2 brand-icon"></i>
                <span class="brand-text">Grama-Voice</span>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto d-lg-none">
                    <li class="nav-item">
                        <a class="nav-link fw-medium" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-medium" href="#process">Process</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-medium" href="#testimonials">Testimonials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-medium" href="#contact">Contact</a>
                    </li>
                </ul>
                <div class="ms-3">
                    <a href="auth/login.php" class="btn btn-outline-primary me-2">Login</a>
                    <a href="auth/register.php" class="btn btn-primary btn-gradient">Report Issue</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Redesigned Hero Section -->
    <section class="hero-section-new position-relative overflow-hidden">
        <!-- Background Video/Image -->
        <div class="hero-bg position-absolute w-100 h-100">
            <div class="hero-video-overlay"></div>
            <div class="hero-particles"></div>
        </div>
        
        <!-- Main Content -->
        <div class="container-fluid h-100">
            <div class="row h-100 align-items-center">
                <!-- Left Content -->
                <div class="col-lg-6 col-md-12">
                    <div class="hero-content-new" data-aos="fade-right" data-aos-duration="1200">
                        <!-- Badge -->
                        <div class="hero-badge mb-4" data-aos="fade-up" data-aos-delay="200">
                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">
                                <i class="fas fa-star me-2"></i>Trusted by <?php echo $achievements['Panchayats Connected'] ?? 10; ?>+ Villages
                            </span>
                        </div>
                        
                        <!-- Main Heading -->
                        <h1 class="hero-title-new mb-4" data-aos="fade-up" data-aos-delay="400">
                            <span class="text-primary">Voice</span> of the 
                            <span class="text-warning highlight-villages">Villages</span>
                        </h1>
                        
                        <!-- Subheading -->
                        <h2 class="hero-subtitle-text mb-4" data-aos="fade-up" data-aos-delay="500">
                            Powered by Technology to build transparent, citizen-driven governance.
                        </h2>
                        
                        <!-- Description -->
                        <p class="hero-description mb-5" data-aos="fade-up" data-aos-delay="600">
                            Citizens can submit complaints with photos, track progress in real-time, 
                            and receive transparent responses from local administration.
                        </p>
                        
                        <!-- CTA Buttons -->
                        <div class="hero-cta d-flex flex-column flex-sm-row gap-3 mb-5" data-aos="fade-up" data-aos-delay="800">
                            <a href="auth/register.php" class="btn btn-primary btn-hero-primary interactive-button">
                                <i class="fas fa-camera me-2"></i>
                                <span>Start Reporting</span>
                                <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                        
                        <!-- Trust Indicators -->
                        <div class="hero-stats row g-4" data-aos="fade-up" data-aos-delay="1000">
                            <div class="col-4">
                                <div class="stat-item-hero text-center">
                                    <div class="stat-number-hero text-warning fw-bold" data-target="<?php echo $achievements['Issues Solved'] ?? 120; ?>">0</div>
                                    <div class="stat-label-hero text-light">Issues Resolved</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-item-hero text-center">
                                    <div class="stat-number-hero text-warning fw-bold" data-target="<?php echo $achievements['Panchayats Connected'] ?? 10; ?>">0</div>
                                    <div class="stat-label-hero text-light">Panchayats</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-item-hero text-center">
                                    <div class="stat-number-hero text-warning fw-bold" data-target="<?php echo $achievements['Satisfaction Rate'] ?? 95; ?>">0</div>
                                    <div class="stat-label-hero text-light">Satisfaction %</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Content - Interactive Demo -->
                <div class="col-lg-6 col-md-12">
                    <div class="hero-demo position-relative" data-aos="fade-left" data-aos-duration="1200">
                        <!-- Phone Mockup -->
                        <div class="phone-mockup">
                            <div class="phone-screen">
                                <div class="app-interface">
                                    <!-- App Header -->
                                    <div class="app-header">
                                        <div class="app-logo">
                                            <i class="fas fa-microphone-alt"></i>
                                            <span>Grama-Voice</span>
                                        </div>
                                        <div class="app-status">
                                            <span class="status-dot"></span>
                                            <span>Online</span>
                                        </div>
                                    </div>
                                    
                                    <!-- App Content -->
                                    <div class="app-content">
                                        <div class="feature-card-app active-feature">
                                            <div class="feature-icon-app">
                                                <i class="fas fa-camera"></i>
                                            </div>
                                            <h4>Submit Issue</h4>
                                            <p>Report your complaint with photos</p>
                                            <div class="recording-indicator">
                                                <div class="pulse-dot"></div>
                                                <span>Uploading...</span>
                                            </div>
                                        </div>
                                        
                                        <div class="feature-card-app">
                                            <div class="feature-icon-app">
                                                <i class="fas fa-chart-line"></i>
                                            </div>
                                            <h4>Track Progress</h4>
                                            <p>Real-time status updates</p>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: 75%"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="feature-card-app">
                                            <div class="feature-icon-app">
                                                <i class="fas fa-bell"></i>
                                            </div>
                                            <h4>Get Updates</h4>
                                            <p>Transparent notifications</p>
                                            <div class="notification-badge">3</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Floating Elements -->
                        <div class="floating-elements-hero">
                            <div class="floating-card" style="--delay: 0s;">
                                <i class="fas fa-users"></i>
                                <span>Community</span>
                            </div>
                            <div class="floating-card" style="--delay: 2s;">
                                <i class="fas fa-shield-alt"></i>
                                <span>Secure</span>
                            </div>
                            <div class="floating-card" style="--delay: 4s;">
                                <i class="fas fa-clock"></i>
                                <span>24/7</span>
                            </div>
                        </div>
                        
                        <!-- Background Shapes -->
                        <div class="hero-shapes">
                            <div class="shape shape-1"></div>
                            <div class="shape shape-2"></div>
                            <div class="shape shape-3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scroll Indicator -->
        <div class="scroll-indicator" data-aos="fade-up" data-aos-delay="1200">
            <div class="scroll-text">Scroll to explore</div>
            <div class="scroll-arrow">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </section>

    <!-- Enhanced About Section -->
    <section id="about" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-4 fw-bold text-primary mb-4" data-aos="fade-up">
                        About <span class="text-warning">Grama-Voice</span>
                    </h2>
                    <p class="lead text-muted" data-aos="fade-up" data-aos-delay="200">
                        Empowering rural communities through technology-driven governance
                    </p>
                </div>
            </div>
            
            <div class="row align-items-center mb-5">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="about-content">
                        <h3 class="h2 fw-bold text-primary mb-4">Transforming Rural Governance</h3>
                        <p class="fs-5 text-muted mb-4">
                            Grama-Voice empowers villagers to voice their concerns through cutting-edge technology. 
                            Citizens can submit complaints with photos and detailed descriptions, track the progress of their 
                            issues in real-time, and receive timely responses from their local panchayat.
                        </p>
                        <p class="fs-5 text-muted mb-4">
                            Our platform ensures complete transparency and accountability in rural governance, 
                            bridging the digital gap between citizens and local administration.
                        </p>
                        <div class="d-flex gap-3">
                            <a href="auth/register.php" class="btn btn-primary btn-lg">
                            Get Started <i class="fas fa-arrow-right me-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="about-image position-relative" style="padding-top: 50px;">
                        <img src="images/Village Community.jpg" 
                             alt="Village Community" class="img-fluid rounded-4 shadow-lg">
                       
                    </div>
                </div>
            </div>
            
            <!-- Feature Cards -->
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card interactive-card h-100 text-center p-4 bg-white rounded-4 shadow-sm border-0">
                        <div class="feature-icon mb-4">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h4 class="fw-bold text-primary mb-3">Photo Documentation</h4>
                        <p class="text-muted">Submit complaints with photo evidence and detailed descriptions for better issue resolution</p>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card interactive-card h-100 text-center p-4 bg-white rounded-4 shadow-sm border-0">
                        <div class="feature-icon mb-4">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4 class="fw-bold text-primary mb-3">Track Progress</h4>
                        <p class="text-muted">Monitor issue resolution in real-time with our comprehensive tracking system</p>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card interactive-card h-100 text-center p-4 bg-white rounded-4 shadow-sm border-0">
                        <div class="feature-icon mb-4">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4 class="fw-bold text-primary mb-3">Transparent Process</h4>
                        <p class="text-muted">Open and accountable governance with complete transparency</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced Process Section -->
    <section id="process" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-4 fw-bold text-primary mb-4" data-aos="fade-up">
                        How It <span class="text-warning">Works</span>
                    </h2>
                    <p class="lead text-muted" data-aos="fade-up" data-aos-delay="200">
                        Simple steps to make your voice heard
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="process-step interactive-card text-center h-100">
                        <div class="step-number mb-4">
                            <div class="step-icon-wrapper">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="step-badge">01</div>
                        </div>
                        <h3 class="fw-bold text-primary mb-3">Citizen Reports Issue</h3>
                        <p class="text-muted fs-5">Villagers submit complaints with photos and detailed descriptions through our user-friendly platform</p>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="process-step interactive-card text-center h-100">
                        <div class="step-number mb-4">
                            <div class="step-icon-wrapper">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="step-badge">02</div>
                        </div>
                        <h3 class="fw-bold text-primary mb-3">Panchayat Reviews</h3>
                        <p class="text-muted fs-5">Local panchayat members review and work on resolving the reported issues efficiently</p>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="process-step interactive-card text-center h-100">
                        <div class="step-number mb-4">
                            <div class="step-icon-wrapper">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="step-badge">03</div>
                        </div>
                        <h3 class="fw-bold text-primary mb-3">Admin Monitors</h3>
                        <p class="text-muted fs-5">Administrators ensure transparency and track resolution progress for accountability</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced Stats Section -->
    <section class="stats-section py-5 bg-primary text-white">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-4 fw-bold mb-4" data-aos="fade-up">
                        Our <span class="text-warning">Impact</span>
                    </h2>
                    <p class="lead" data-aos="fade-up" data-aos-delay="200">
                        Numbers that speak for our success
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-card text-center p-4">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number h1 fw-bold text-warning mb-2" data-target="<?php echo $achievements['Issues Solved'] ?? 120; ?>">0</div>
                        <div class="stat-label fs-5">Issues Solved</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-card text-center p-4">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-number h1 fw-bold text-warning mb-2" data-target="<?php echo $achievements['Panchayats Connected'] ?? 10; ?>">0</div>
                        <div class="stat-label fs-5">Panchayats Connected</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-card text-center p-4">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number h1 fw-bold text-warning mb-2" data-target="<?php echo $achievements['Citizens Benefited'] ?? 500; ?>">0</div>
                        <div class="stat-label fs-5">Citizens Benefited</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-card text-center p-4">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-number h1 fw-bold text-warning mb-2" data-target="<?php echo $achievements['Satisfaction Rate'] ?? 95; ?>">0</div>
                        <div class="stat-label fs-5">Satisfaction Rate %</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Recent Resolved Issues Section -->
    <section id="recent-issues" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-4 fw-bold text-primary mb-4" data-aos="fade-up">
                        Recently <span class="text-warning">Resolved</span>
                    </h2>
                    <p class="lead text-muted" data-aos="fade-up" data-aos-delay="200">
                        See how we're making a difference in villages across the region
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <?php foreach ($recent_issues as $index => $issue): ?>
                    <div class="col-lg-4" data-aos="fade-up" data-aos-delay="<?php echo ($index + 1) * 100; ?>">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-success text-white rounded-circle p-2 me-3">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($issue['title']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($issue['location']); ?></small>
                                    </div>
                                </div>
                                <p class="text-muted mb-3">
                                    Reported by <strong><?php echo htmlspecialchars($issue['user_name']); ?></strong>
                                    <?php if (isset($issue['village_name'])): ?>
                                        from <?php echo htmlspecialchars($issue['village_name']); ?>
                                    <?php endif; ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-success">Resolved</span>
                                    <small class="text-muted">
                                        <?php echo isset($issue['created_at']) ? formatDate($issue['created_at']) : 'Recently'; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-4 fw-bold text-primary mb-4" data-aos="fade-up">
                        What People <span class="text-warning">Say</span>
                    </h2>
                    <p class="lead text-muted" data-aos="fade-up" data-aos-delay="200">
                        Real stories from citizens and panchayats who have experienced the power of Grama Voice
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-card h-100 p-4 bg-white rounded-4 shadow-sm">
                        <div class="testimonial-header d-flex align-items-center mb-3">
                            <div class="testimonial-avatar me-3">
                                <img src="images/farmer.webp" 
                                     alt="Suresh Reddy" class="rounded-circle" width="80" height="80" referrerpolicy="no-referrer">
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Suresh Reddy</h6>
                                <small class="text-muted">Farmer, Andhra Pradesh</small>
                            </div>
                        </div>
                        <div class="testimonial-rating mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="testimonial-text text-muted mb-0">
                            "Grama Voice helped me report a broken street light near our fields in Anantapur. Within 3 days,
                            the panchayat fixed it! Uploading a photo made it easy to show the exact location."
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-card h-100 p-4 bg-white rounded-4 shadow-sm">
                        <div class="testimonial-header d-flex align-items-center mb-3">
                            <div class="testimonial-avatar me-3">
                                <img src="images/teac.webp" 
                                     alt="Meenakshi Iyer" class="rounded-circle" width="60" height="60" referrerpolicy="no-referrer">
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Meenakshi Iyer</h6>
                                <small class="text-muted">Teacher, Tamil Nadu</small>
                            </div>
                        </div>
                        <div class="testimonial-rating mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="testimonial-text text-muted mb-0">
                            "I reported a water leakage near our school in Coimbatore. The transparency was amazing I could track every step.
                            Now the children are safe and we have clean drinking water."
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="testimonial-card h-100 p-4 bg-white rounded-4 shadow-sm">
                        <div class="testimonial-header d-flex align-items-center mb-3">
                            <div class="testimonial-avatar me-3">
                                <img src="images/panc.webp" 
                                     alt="Prakash Shetty" class="rounded-circle" width="60" height="60" referrerpolicy="no-referrer">
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Prakash Shetty</h6>
                                <small class="text-muted">Panchayat Admin, Karnataka</small>
                            </div>
                        </div>
                        <div class="testimonial-rating mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="testimonial-text text-muted mb-0">
                            "Grama Voice has improved our governance. We respond faster to citizen complaints in Udupi,
                            and the analytics help us prioritize areas that need attention."
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced CTA Section -->
    <section class="cta-section-new py-5">
        <div class="container">
            <div class="cta-card" data-aos="fade-up">
                <div class="row g-0 h-100">
                    <!-- Left Content -->
                    <div class="col-lg-6">
                        <div class="cta-content">
                            <h2 class="cta-title">
                                Be the <span class="text-warning">Voice of Change</span><br>
                                in Your Village
                            </h2>
                            <p class="cta-description">
                                Your village governance should serve you, not the other way around. 
                                We're happy to help you make a real difference in your community.
                            </p>
                            <div class="cta-buttons">
                                <a href="auth/register.php" class="btn cta-btn-primary">
                                    <span>Report Your Issue</span>
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                                <button class="btn cta-btn-secondary">
                                    <span>Call Support</span>
                                    <i class="fas fa-phone"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Graphic -->
                    <div class="col-lg-6">
                        <div class="cta-graphic">
                            <div class="concentric-arcs">
                                <div class="arc arc-1"></div>
                                <div class="arc arc-2"></div>
                                <div class="arc arc-3"></div>
                                <div class="arc arc-4"></div>
                                <div class="arc arc-5"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced Footer -->
    <footer id="contact" class="footer-section bg-dark text-white py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="footer-brand mb-4">
                        <h3 class="fw-bold text-warning mb-3">
                            <i class="fas fa-microphone-alt me-2"></i>Grama-Voice
                        </h3>
                        <p class="text-light mb-4">
                            Empowering rural communities through technology-driven governance. 
                            Making every voice count in the digital age.
                        </p>
                        <div class="social-links">
                            <a href="https://facebook.com/gramavoice" target="_blank" class="btn btn-outline-light btn-sm me-2 mb-2" title="Follow us on Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="https://twitter.com/gramavoice" target="_blank" class="btn btn-outline-light btn-sm me-2 mb-2" title="Follow us on Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="https://instagram.com/gramavoice" target="_blank" class="btn btn-outline-light btn-sm me-2 mb-2" title="Follow us on Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="https://linkedin.com/company/gramavoice" target="_blank" class="btn btn-outline-light btn-sm me-2 mb-2" title="Connect with us on LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="https://youtube.com/gramavoice" target="_blank" class="btn btn-outline-light btn-sm me-2 mb-2" title="Subscribe to our YouTube channel">
                                <i class="fab fa-youtube"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <h5 class="fw-bold text-warning mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#about" class="text-light text-decoration-none hover-link">About Us</a></li>
                        <li class="mb-2"><a href="#process" class="text-light text-decoration-none hover-link">How It Works</a></li>
                        <li class="mb-2"><a href="#testimonials" class="text-light text-decoration-none hover-link">Testimonials</a></li>
                        <li class="mb-2"><a href="auth/register.php" class="text-light text-decoration-none hover-link">Get Started</a></li>
                        <li class="mb-2"><a href="auth/login.php" class="text-light text-decoration-none hover-link">Login</a></li>
                        <li class="mb-2"><a href="auth/admin_login.php" class="text-light text-decoration-none hover-link">Admin Login</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h5 class="fw-bold text-warning mb-3">Support</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#contact" class="text-light text-decoration-none hover-link">Help Center</a></li>
                        <li class="mb-2"><a href="#contact" class="text-light text-decoration-none hover-link">Contact Us</a></li>
                        <li class="mb-2"><a href="privacy-policy.php" class="text-light text-decoration-none hover-link" onclick="showPrivacyPolicy()">Privacy Policy</a></li>
                        <li class="mb-2"><a href="terms-of-service.php" class="text-light text-decoration-none hover-link" onclick="showTermsOfService()">Terms of Service</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none hover-link" onclick="showFAQ()">FAQ</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none hover-link" onclick="showFeedback()">Feedback</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3">
                    <h5 class="fw-bold text-warning mb-3">Contact Info</h5>
                    <div class="contact-info">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-phone text-warning me-3"></i>
                            <a href="tel:+919876543210" class="text-light text-decoration-none hover-link">+91 98765 43210</a>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-envelope text-warning me-3"></i>
                            <a href="mailto:support@gramavoice.in" class="text-light text-decoration-none hover-link">support@gramavoice.in</a>
                        </div>
                        <div class="d-flex align-items-start">
                            <i class="fas fa-map-marker-alt text-warning me-3 mt-1"></i>
                            <a href="https://maps.google.com/?q=Rural+Development+Center+New+Delhi" target="_blank" class="text-light text-decoration-none hover-link">
                                Rural Development Center<br>Andhra Pradesh, India
                            </a>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-clock text-warning me-3"></i>
                            <span class="text-light">Mon-Fri: 9AM-6PM IST</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr class="my-4 border-secondary">
            
            <div class="row align-items-center">
                <div class="col-md-4">
                    <p class="text-light mb-0">
                        &copy; 2025 Grama-Voice. All rights reserved.
                    </p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="footer-links">
                        <a href="privacy-policy.php" class="text-light text-decoration-none hover-link me-3">
                            <i class="fas fa-shield-alt me-1"></i>Privacy Policy
                        </a>
                        <a href="terms-of-service.php" class="text-light text-decoration-none hover-link">
                            <i class="fas fa-file-contract me-1"></i>Terms of Service
                        </a>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <p class="text-light mb-0">
                        Made with <i class="fas fa-heart text-danger"></i> for rural India
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="script.js"></script>
    
    <!-- Footer Functions -->
    <script>
        // Footer Functions
        function showFAQ() {
            alert('FAQ: \n\nQ: How do I report an issue?\nA: Click "Get Started" to register and submit your complaint with photos.\n\nQ: How long does it take to resolve issues?\nA: Most issues are resolved within 7-14 days depending on complexity.\n\nQ: Can I track my complaint?\nA: Yes, you can track progress in real-time after logging in.');
        }
        
        function showFeedback() {
            const feedback = prompt('We value your feedback! Please share your thoughts about Grama-Voice:');
            if (feedback) {
                alert('Thank you for your feedback! We will review your comments and work to improve our services.');
            }
        }
    </script>
</body>
</html>
