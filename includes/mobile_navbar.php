<?php
/**
 * Mobile Bottom Navigation Bar
 * Only shows on mobile devices (max-width: 768px)
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav" id="mobileBottomNav">
    <div class="mobile-nav-container">
        <a href="dashboard.php" class="mobile-nav-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            <div class="mobile-nav-icon">
                <i class="fas fa-home"></i>
            </div>
            <span class="mobile-nav-label">Home</span>
        </a>
        
        <a href="track_issue.php" class="mobile-nav-item <?php echo $current_page === 'track_issue.php' ? 'active' : ''; ?>">
            <div class="mobile-nav-icon">
                <i class="fas fa-list-check"></i>
            </div>
            <span class="mobile-nav-label">Issues</span>
        </a>
        
        <a href="submit_issue.php" class="mobile-nav-item mobile-nav-primary <?php echo $current_page === 'submit_issue.php' ? 'active' : ''; ?>">
            <div class="mobile-nav-icon">
                <i class="fas fa-plus"></i>
            </div>
            <span class="mobile-nav-label">Report</span>
        </a>
        
        <a href="profile.php" class="mobile-nav-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
            <div class="mobile-nav-icon">
                <i class="fas fa-user"></i>
            </div>
            <span class="mobile-nav-label">Profile</span>
        </a>
        
        <a href="view_all_notifications.php" class="mobile-nav-item <?php echo $current_page === 'view_all_notifications.php' ? 'active' : ''; ?>">
            <div class="mobile-nav-icon">
                <i class="fas fa-bell"></i>
            </div>
            <span class="mobile-nav-label">Alerts</span>
        </a>
        
        <a href="settings.php" class="mobile-nav-item <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
            <div class="mobile-nav-icon">
                <i class="fas fa-cog"></i>
            </div>
            <span class="mobile-nav-label">Settings</span>
        </a>
    </div>
</nav>

<style>
/* Mobile Bottom Navigation Styles */
.mobile-bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-top: 1px solid rgba(0, 0, 0, 0.1);
    z-index: 1000;
    padding: 8px 0;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
    display: none; /* Hidden by default */
}

.mobile-nav-container {
    display: flex;
    justify-content: space-around;
    align-items: center;
    max-width: 100%;
    margin: 0 auto;
    padding: 0 8px;
}

.mobile-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #6b7280;
    transition: all 0.3s ease;
    padding: 8px 12px;
    border-radius: 12px;
    position: relative;
    min-width: 60px;
}

.mobile-nav-item:hover {
    color: #6366f1;
    background: rgba(99, 102, 241, 0.1);
    transform: translateY(-2px);
}

.mobile-nav-item.active {
    color: #6366f1;
    background: rgba(99, 102, 241, 0.1);
}

.mobile-nav-item.active::before {
    content: '';
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    background: #6366f1;
    border-radius: 50%;
}

.mobile-nav-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white !important;
    border-radius: 16px;
    padding: 12px 16px;
    margin: 0 4px;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.mobile-nav-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
    color: white !important;
}

.mobile-nav-primary.active {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white !important;
}

.mobile-nav-icon {
    font-size: 1.2rem;
    margin-bottom: 4px;
    transition: all 0.3s ease;
}

.mobile-nav-primary .mobile-nav-icon {
    font-size: 1.4rem;
    margin-bottom: 2px;
}

.mobile-nav-item:hover .mobile-nav-icon {
    transform: scale(1.1);
}

.mobile-nav-label {
    font-size: 0.7rem;
    font-weight: 500;
    text-align: center;
    line-height: 1;
}

.mobile-nav-primary .mobile-nav-label {
    font-size: 0.75rem;
    font-weight: 600;
}

/* Show mobile nav only on mobile devices */
@media (max-width: 768px) {
    .mobile-bottom-nav {
        display: block;
    }
    
    /* Add bottom padding to main content to prevent overlap */
    .main-content {
        padding-bottom: 80px !important;
    }
    
    .dashboard-container {
        padding-bottom: 80px;
    }
}

/* Hide on larger screens */
@media (min-width: 769px) {
    .mobile-bottom-nav {
        display: none !important;
    }
}

/* Animation for mobile nav items */
@keyframes mobileNavPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.mobile-nav-item.active .mobile-nav-icon {
    animation: mobileNavPulse 2s infinite;
}

/* Ripple effect for mobile nav items */
.mobile-nav-item {
    position: relative;
    overflow: hidden;
}

.mobile-nav-item::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(99, 102, 241, 0.2);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.mobile-nav-item:active::after {
    width: 100px;
    height: 100px;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .mobile-bottom-nav {
        background: rgba(17, 24, 39, 0.95);
        border-top-color: rgba(255, 255, 255, 0.1);
    }
    
    .mobile-nav-item {
        color: #9ca3af;
    }
    
    .mobile-nav-item:hover,
    .mobile-nav-item.active {
        color: #6366f1;
        background: rgba(99, 102, 241, 0.2);
    }
}

/* Accessibility improvements */
.mobile-nav-item:focus {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .mobile-bottom-nav {
        border-top: 2px solid #000;
    }
    
    .mobile-nav-item {
        border: 1px solid transparent;
    }
    
    .mobile-nav-item.active {
        border-color: #6366f1;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .mobile-nav-item,
    .mobile-nav-icon {
        transition: none;
    }
    
    .mobile-nav-item.active .mobile-nav-icon {
        animation: none;
    }
}
</style>
