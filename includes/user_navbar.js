/**
 * User Navbar JavaScript
 * Handles mobile menu functionality for all user pages
 */

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const navbarHamburger = document.getElementById('navbarHamburger');
    const sidebar = document.getElementById('sidebar');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const hamburger = navbarHamburger?.querySelector('.hamburger');
    
    if (navbarHamburger && sidebar && mobileOverlay) {
        navbarHamburger.addEventListener('click', function() {
            const isOpen = sidebar.classList.contains('open');
            
            if (isOpen) {
                // Close menu
                sidebar.classList.remove('open');
                mobileOverlay.classList.remove('active');
                navbarHamburger.classList.remove('active');
                hamburger?.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                // Open menu
                sidebar.classList.add('open');
                mobileOverlay.classList.add('active');
                navbarHamburger.classList.add('active');
                hamburger?.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
        
        // Close menu when clicking overlay
        mobileOverlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            mobileOverlay.classList.remove('active');
            navbarHamburger.classList.remove('active');
            hamburger?.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        // Close menu when clicking nav links
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                sidebar.classList.remove('open');
                mobileOverlay.classList.remove('active');
                navbarHamburger.classList.remove('active');
                hamburger?.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
        
        // Close menu on window resize if screen becomes large
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                sidebar.classList.remove('open');
                mobileOverlay.classList.remove('active');
                navbarHamburger.classList.remove('active');
                hamburger?.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
});
