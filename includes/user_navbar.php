<?php
/**
 * User Navbar Component
 * Reusable navbar for all user pages
 */

// Get user data if not already available
if (!isset($user)) {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

// Get user statistics if not already available
if (!isset($total_issues)) {
    $stmt = $db->prepare("SELECT COUNT(*) as total_issues FROM issues WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_issues = $stmt->get_result()->fetch_assoc()['total_issues'] ?? 0;
}
?>

<!-- Top Navigation Bar -->
<nav class="top-navbar">
    <a href="../index.php" class="navbar-brand">
        <div class="navbar-logo">
            <i class="fas fa-microphone-alt"></i>
        </div>
        Grama Voice
    </a>
    <button class="navbar-hamburger" id="navbarHamburger">
        <div class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </button>
</nav>

<!-- User Info Section -->
<div class="user-info-section">
    <div class="user-welcome">
        <div class="user-avatar-small">
            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
        </div>
        <div class="user-details">
            <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?>!</h2>
            <p><?php echo htmlspecialchars($user['phone']); ?> • <?php echo $total_issues; ?> issues submitted</p>
        </div>
    </div>
</div>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay"></div>

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
            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
        </div>
        <div class="nav-item">
            <a href="track_issue.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'track_issue.php' ? 'active' : ''; ?>">
                <i class="fas fa-list-check"></i>
                My Issues
            </a>
        </div>
        <div class="nav-item">
            <a href="submit_issue.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'submit_issue.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                Report Issue
            </a>
        </div>
        <div class="nav-item">
            <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                Profile
            </a>
        </div>
        <div class="nav-item">
            <a href="view_all_notifications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'view_all_notifications.php' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i>
                Notifications
            </a>
        </div>
        <div class="nav-item">
            <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
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
