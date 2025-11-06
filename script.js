// Enhanced JavaScript with Bootstrap Integration

// Initialize AOS (Animate On Scroll) Library
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({
        duration: 1000,
        easing: 'ease-in-out',
        once: true,
        offset: 100
    });
});

// Counter Animation for Stats Section
function animateCounters() {
    const counters = document.querySelectorAll('.stat-number');
    const speed = 200; // The lower the slower

    counters.forEach(counter => {
        const updateCount = () => {
            const target = +counter.getAttribute('data-target');
            const count = +counter.innerText;

            // Check if target is a percentage
            const isPercentage = counter.nextElementSibling.textContent.includes('%');
            
            const inc = target / speed;

            if (count < target) {
                counter.innerText = Math.ceil(count + inc);
                setTimeout(updateCount, 1);
            } else {
                counter.innerText = target + (isPercentage ? '%' : '+');
            }
        };

        updateCount();
    });
}

// Intersection Observer for Counter Animation
const observerOptions = {
    threshold: 0.5,
    rootMargin: '0px 0px -100px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounters();
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

// Enhanced Navbar Scroll Effect
function initNavbarScrollEffect() {
    const navbar = document.querySelector('.custom-navbar');
    let lastScrollTop = 0;

    window.addEventListener('scroll', () => {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 100) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        lastScrollTop = scrollTop;
    });
}

// Smooth Scrolling for Navigation Links
function initSmoothScrolling() {
    const links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const offsetTop = targetElement.offsetTop - 80; // Account for fixed navbar
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Enhanced Button Animations with Ripple Effect
function initButtonAnimations() {
    const buttons = document.querySelectorAll('.btn');
    
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Create ripple effect
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
}

// Add Ripple Effect CSS dynamically
function addRippleEffectCSS() {
    const style = document.createElement('style');
    style.textContent = `
        .btn {
            position: relative;
            overflow: hidden;
        }
        
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// Enhanced Carousel Controls
function initCarouselEnhancements() {
    const carousel = document.getElementById('testimonialCarousel');
    if (carousel) {
        // Auto-play carousel
        const carouselInstance = new bootstrap.Carousel(carousel, {
            interval: 5000,
            wrap: true,
            touch: true
        });

        // Pause on hover
        carousel.addEventListener('mouseenter', () => {
            carouselInstance.pause();
        });

        carousel.addEventListener('mouseleave', () => {
            carouselInstance.cycle();
        });

        // Add keyboard navigation
        carousel.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') {
                carouselInstance.prev();
            } else if (e.key === 'ArrowRight') {
                carouselInstance.next();
            }
        });
    }
}

// Enhanced Hover Effects
function initHoverEffects() {
    // Feature cards hover effects
    const featureCards = document.querySelectorAll('.feature-card');
    featureCards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-10px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Process steps hover effects
    const processSteps = document.querySelectorAll('.process-step');
    processSteps.forEach(step => {
        step.addEventListener('mouseenter', () => {
            step.style.transform = 'translateY(-15px) scale(1.02)';
        });
        
        step.addEventListener('mouseleave', () => {
            step.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Stat cards hover effects
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-5px) scale(1.05)';
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0) scale(1)';
        });
    });
}

// Loading Animation for Images
function initImageLoading() {
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        img.addEventListener('load', () => {
            img.style.opacity = '1';
            img.style.transition = 'opacity 0.3s ease';
        });
        
        img.addEventListener('error', () => {
            img.style.opacity = '0.5';
            img.alt = 'Image failed to load';
        });
    });
}

// Form Validation Enhancement (if forms are added later)
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

// Scroll to Top Button
function initScrollToTop() {
    // Create scroll to top button
    const scrollToTopBtn = document.createElement('button');
    scrollToTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    scrollToTopBtn.className = 'btn btn-primary btn-floating position-fixed';
    scrollToTopBtn.style.cssText = `
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    document.body.appendChild(scrollToTopBtn);
    
    // Show/hide button based on scroll position
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            scrollToTopBtn.style.display = 'block';
        } else {
            scrollToTopBtn.style.display = 'none';
        }
    });
    
    // Scroll to top functionality
    scrollToTopBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// Performance Optimization: Lazy Loading
function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Mobile Menu Enhancement
function initMobileMenuEnhancements() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    if (navbarToggler && navbarCollapse) {
        // Close mobile menu when clicking on a link
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (navbarCollapse.classList.contains('show')) {
                    navbarToggler.click();
                }
            });
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!navbarCollapse.contains(e.target) && !navbarToggler.contains(e.target)) {
                if (navbarCollapse.classList.contains('show')) {
                    navbarToggler.click();
                }
            }
        });
    }
}

// Parallax Effect for Hero Section
function initParallaxEffect() {
    const heroSection = document.querySelector('.hero-section');
    const floatingElements = document.querySelectorAll('.floating-icon');
    
    if (heroSection && floatingElements.length > 0) {
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallaxSpeed = 0.5;
            
            floatingElements.forEach((element, index) => {
                const speed = parallaxSpeed * (index + 1);
                element.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });
    }
}

// Enhanced Hero Section Interactions
function initHeroInteractions() {
    // Phone mockup interactions
    const phoneMockup = document.querySelector('.phone-mockup');
    const featureCards = document.querySelectorAll('.feature-card-app');
    
    if (phoneMockup) {
        // Add tilt effect on mouse move
        phoneMockup.addEventListener('mousemove', (e) => {
            const rect = phoneMockup.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 10;
            const rotateY = (centerX - x) / 10;
            
            phoneMockup.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        });
        
        phoneMockup.addEventListener('mouseleave', () => {
            phoneMockup.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
        });
    }
    
    // Feature cards animation on scroll
    if (featureCards.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, index * 200);
                }
            });
        }, { threshold: 0.1 });
        
        featureCards.forEach(card => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0px)';
            card.style.transition = 'all 0.5s ease';
            observer.observe(card);
        });
    }
    
    // Floating cards interaction
    const floatingCards = document.querySelectorAll('.floating-card');
    floatingCards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-10px) scale(1.05)';
            card.style.boxShadow = '0 15px 35px rgba(0, 0, 0, 0.3)';
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0) scale(1)';
            card.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.2)';
        });
    });
    
    // Hero stats counter animation
    const heroStats = document.querySelectorAll('.stat-number-hero');
    if (heroStats.length > 0) {
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateHeroStats();
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        const statsSection = document.querySelector('.hero-stats');
        if (statsSection) {
            statsObserver.observe(statsSection);
        }
    }
    
    // Scroll indicator click
    const scrollIndicator = document.querySelector('.scroll-indicator');
    if (scrollIndicator) {
        scrollIndicator.addEventListener('click', () => {
            const aboutSection = document.querySelector('#about');
            if (aboutSection) {
                aboutSection.scrollIntoView({ behavior: 'smooth' });
            }
        });
        
        // Hide scroll indicator when scrolled
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 100) {
                scrollIndicator.style.opacity = '0';
            } else {
                scrollIndicator.style.opacity = '1';
            }
        });
    }
}

// Animate hero stats
function animateHeroStats() {
    const stats = [
        { element: document.querySelector('.stat-number-hero'), target: 120, suffix: '+' },
        { element: document.querySelectorAll('.stat-number-hero')[1], target: 10, suffix: '' },
        { element: document.querySelectorAll('.stat-number-hero')[2], target: 95, suffix: '%' }
    ];
    
    stats.forEach(stat => {
        if (stat.element) {
            let current = 0;
            const increment = stat.target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= stat.target) {
                    stat.element.textContent = stat.target + stat.suffix;
                    clearInterval(timer);
                } else {
                    stat.element.textContent = Math.floor(current) + stat.suffix;
                }
            }, 30);
        }
    });
}

// Enhanced particle system for hero background
function initHeroParticles() {
    const particlesContainer = document.querySelector('.hero-particles');
    if (!particlesContainer) return;
    
    // Create dynamic particles
    for (let i = 0; i < 50; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.cssText = `
            position: absolute;
            width: 2px;
            height: 2px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            left: ${Math.random() * 100}%;
            top: ${Math.random() * 100}%;
            animation: particleFloat ${5 + Math.random() * 10}s linear infinite;
            animation-delay: ${Math.random() * 5}s;
        `;
        particlesContainer.appendChild(particle);
    }
    
    // Add particle animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes particleFloat {
            0% {
                transform: translateY(100vh) translateX(0px);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) translateX(${Math.random() * 100 - 50}px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// Hero button interactions
function initHeroButtons() {
    const heroButtons = document.querySelectorAll('.btn-hero-primary, .btn-hero-secondary');
    
    heroButtons.forEach(button => {
        // Add click animation
        button.addEventListener('click', function(e) {
            // Create ripple effect
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: rippleEffect 0.6s linear;
                pointer-events: none;
            `;
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
        
        // Add hover sound effect (visual feedback)
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Add ripple effect CSS
    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = `
        @keyframes rippleEffect {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(rippleStyle);
}

// Hero section scroll effects
function initHeroScrollEffects() {
    const heroSection = document.querySelector('.hero-section-new');
    const heroContent = document.querySelector('.hero-content-new');
    const heroDemo = document.querySelector('.hero-demo');
    
    if (!heroSection) return;
    
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.5;
        
        // Parallax effect for background
        const heroBg = document.querySelector('.hero-bg');
        if (heroBg) {
            heroBg.style.transform = `translateY(${rate}px)`;
        }
        
        // Parallax effect for floating elements
        const floatingElements = document.querySelectorAll('.floating-card');
        floatingElements.forEach((element, index) => {
            const speed = (rate * (index + 1)) * 0.3;
            element.style.transform = `translateY(${speed}px)`;
        });
        
        // Keep hero content always visible
        heroContent.style.opacity = '1';
        if (heroDemo) {
            heroDemo.style.opacity = '1';
        }
    });
}

// Initialize hero section when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize all components
    initNavbarScrollEffect();
    initSmoothScrolling();
    initButtonAnimations();
    addRippleEffectCSS();
    initCarouselEnhancements();
    initHoverEffects();
    initImageLoading();
    initFormValidation();
    initScrollToTop();
    initLazyLoading();
    initMobileMenuEnhancements();
    initParallaxEffect();
    
    // Initialize new hero section features
    initHeroInteractions();
    initHeroParticles();
    initHeroButtons();
    initHeroScrollEffects();
    
    // Initialize CTA section interactions
    initCTAInteractions();
    addCTAAnimationsCSS();
    
    // Observe stats section for counter animation
    const statsSection = document.querySelector('.stats-section');
    if (statsSection) {
        observer.observe(statsSection);
    }
    
    // Add loading class to body initially
    document.body.classList.add('loading');
    
    // Remove loading class after everything is loaded
    window.addEventListener('load', () => {
        document.body.classList.remove('loading');
    });
});

// Handle window resize for responsive adjustments
window.addEventListener('resize', () => {
    // Recalculate carousel if needed
    const carousel = document.getElementById('testimonialCarousel');
    if (carousel) {
        const carouselInstance = bootstrap.Carousel.getInstance(carousel);
        if (carouselInstance) {
            carouselInstance.update();
        }
    }
    
    // Recalculate AOS
    AOS.refresh();
});

// Add keyboard accessibility
document.addEventListener('keydown', (e) => {
    // ESC key to close mobile menu
    if (e.key === 'Escape') {
        const navbarCollapse = document.querySelector('.navbar-collapse');
        const navbarToggler = document.querySelector('.navbar-toggler');
        
        if (navbarCollapse && navbarCollapse.classList.contains('show')) {
            navbarToggler.click();
        }
    }
});

// Add focus management for better accessibility
function initFocusManagement() {
    const focusableElements = document.querySelectorAll(
        'a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select'
    );
    
    focusableElements.forEach(element => {
        element.addEventListener('focus', () => {
            element.style.outline = '2px solid var(--warm-yellow)';
            element.style.outlineOffset = '2px';
        });
        
        element.addEventListener('blur', () => {
            element.style.outline = 'none';
        });
    });
}

// Initialize focus management
document.addEventListener('DOMContentLoaded', initFocusManagement);

// Add error handling for external resources
window.addEventListener('error', (e) => {
    if (e.target.tagName === 'IMG') {
        e.target.style.display = 'none';
        console.warn('Image failed to load:', e.target.src);
    }
});

// Add performance monitoring
if ('performance' in window) {
    window.addEventListener('load', () => {
        setTimeout(() => {
            const perfData = performance.getEntriesByType('navigation')[0];
            console.log('Page load time:', perfData.loadEventEnd - perfData.loadEventStart, 'ms');
        }, 0);
    });
}

// CTA Section Interactive Features
function initCTAInteractions() {
    const ctaCard = document.querySelector('.cta-card');
    const ctaButtons = document.querySelectorAll('.cta-btn-primary, .cta-btn-secondary');
    const arcs = document.querySelectorAll('.arc');
    const ctaGraphic = document.querySelector('.cta-graphic');
    
    // CTA Card interactions
    if (ctaCard) {
        // Add tilt effect on mouse move
        ctaCard.addEventListener('mousemove', (e) => {
            const rect = ctaCard.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 20;
            const rotateY = (centerX - x) / 20;
            
            ctaCard.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px) scale(1.02)`;
        });
        
        ctaCard.addEventListener('mouseleave', () => {
            ctaCard.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0) scale(1)';
        });
        
        // Add click effect
        ctaCard.addEventListener('click', () => {
            ctaCard.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(-4px) scale(1.01)';
            setTimeout(() => {
                ctaCard.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(-8px) scale(1.02)';
            }, 150);
        });
    }
    
    // CTA Button interactions
    ctaButtons.forEach(button => {
        // Add loading state simulation
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Add loading class
            this.classList.add('loading');
            this.style.pointerEvents = 'none';
            
            // Simulate loading for 2 seconds
            setTimeout(() => {
                this.classList.remove('loading');
                this.style.pointerEvents = 'auto';
                
                // Add success animation
                this.style.background = '#28a745';
                setTimeout(() => {
                    this.style.background = '#000';
                }, 1000);
            }, 2000);
        });
        
        // Add magnetic effect
        button.addEventListener('mousemove', (e) => {
            const rect = button.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            
            button.style.transform = `translate(${x * 0.1}px, ${y * 0.1}px) translateY(-3px) scale(1.02)`;
        });
        
        button.addEventListener('mouseleave', () => {
            button.style.transform = 'translate(0px, 0px) translateY(0) scale(1)';
        });
    });
    
    // Arc interactions
    arcs.forEach((arc, index) => {
        arc.addEventListener('mouseenter', () => {
            // Speed up animation for hovered arc
            arc.style.animationDuration = '1s';
            
            // Add glow effect
            arc.style.boxShadow = '0 0 20px rgba(255, 255, 255, 0.5)';
            
            // Affect other arcs
            arcs.forEach((otherArc, otherIndex) => {
                if (otherIndex !== index) {
                    otherArc.style.animationDuration = '3s';
                    otherArc.style.opacity = '0.3';
                }
            });
        });
        
        arc.addEventListener('mouseleave', () => {
            // Reset all arcs
            arcs.forEach(otherArc => {
                otherArc.style.animationDuration = '6s';
                otherArc.style.opacity = '0.8';
                otherArc.style.boxShadow = 'none';
            });
        });
        
        // Add click effect to arcs
        arc.addEventListener('click', () => {
            // Create ripple effect
            const ripple = document.createElement('div');
            ripple.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: translate(-50%, -50%);
                animation: arcRipple 0.6s ease-out;
                pointer-events: none;
            `;
            
            arc.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // CTA Graphic interactions
    if (ctaGraphic) {
        ctaGraphic.addEventListener('click', () => {
            // Add pulse effect to all arcs
            arcs.forEach(arc => {
                arc.style.animation = 'none';
                setTimeout(() => {
                    arc.style.animation = 'arcPulse 6s ease-in-out infinite';
                }, 100);
            });
        });
    }
    
    // Scroll-triggered animations
    const ctaSection = document.querySelector('.cta-section-new');
    if (ctaSection) {
        const ctaObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    
                    // Animate arcs with delay
                    arcs.forEach((arc, index) => {
                        setTimeout(() => {
                            arc.style.opacity = '0.8';
                            arc.style.transform = 'scale(1)';
                        }, index * 200);
                    });
                }
            });
        }, { threshold: 0.3 });
        
        ctaObserver.observe(ctaSection);
    }
}

// Add CTA-specific CSS animations
function addCTAAnimationsCSS() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes arcRipple {
            0% {
                width: 0;
                height: 0;
                opacity: 1;
            }
            100% {
                width: 200px;
                height: 200px;
                opacity: 0;
            }
        }
        
        .cta-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .cta-btn-primary,
        .cta-btn-secondary {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .arc {
            transition: all 0.3s ease;
        }
        
        /* Enhanced focus states for accessibility */
        .cta-btn-primary:focus,
        .cta-btn-secondary:focus {
            outline: 3px solid var(--warm-yellow);
            outline-offset: 2px;
        }
        
        .cta-card:focus-within {
            outline: 3px solid var(--warm-yellow);
            outline-offset: 4px;
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .cta-card,
            .cta-btn-primary,
            .cta-btn-secondary,
            .arc {
                transition: none;
                animation: none;
            }
            
            .cta-card:hover {
                transform: none;
            }
        }
    `;
    document.head.appendChild(style);
}