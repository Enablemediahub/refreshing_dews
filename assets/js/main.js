// Main JavaScript file

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any components
    initMobileMenu();
    initScrollEffects();
    initLazyLoading();
    initConsentPopup();
    initInstallPrompt();
    initNewsletterModal();
    bindReadAloudButtons();
});

// Mobile Menu
function initMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileMenuBtn && navLinks) {
        mobileMenuBtn.addEventListener('click', function() {
            navLinks.classList.toggle('show');
            
            // Change icon
            const icon = this.querySelector('i');
            if (navLinks.classList.contains('show')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!navLinks.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
                navLinks.classList.remove('show');
                mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
            }
        });
        
        // Close menu when clicking a link
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                navLinks.classList.remove('show');
                mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
            });
        });
    }
}

// Scroll Effects
function initScrollEffects() {
    const navbar = document.getElementById('navbar');
    
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
    
    // Scroll to top button
    const scrollTopBtn = document.getElementById('scrollTop');
    if (scrollTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 500) {
                scrollTopBtn.classList.add('show');
            } else {
                scrollTopBtn.classList.remove('show');
            }
        });
        
        scrollTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
}

// Lazy Loading Images
function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for older browsers
        images.forEach(img => {
            img.src = img.dataset.src;
        });
    }
}

// Audio Player Functions
function playAudio(audioId) {
    const audio = document.getElementById('audio-' + audioId);
    const btn = event.currentTarget;
    
    if (!audio) return;
    
    // Pause all other audio players
    document.querySelectorAll('audio').forEach(a => {
        if (a.id !== 'audio-' + audioId && !a.paused) {
            a.pause();
            const otherBtn = document.querySelector(`[onclick="playAudio('${a.id.replace('audio-', '')}')"]`);
            if (otherBtn) {
                otherBtn.innerHTML = '<i class="fas fa-play"></i>';
            }
        }
    });
    
    if (audio.paused) {
        audio.play();
        btn.innerHTML = '<i class="fas fa-pause"></i>';
    } else {
        audio.pause();
        btn.innerHTML = '<i class="fas fa-play"></i>';
    }
    
    audio.addEventListener('ended', function() {
        btn.innerHTML = '<i class="fas fa-play"></i>';
    });
}

// Theme Toggle
function initThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    
    if (themeToggle) {
        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-theme');
            themeToggle.querySelector('i').className = 'fas fa-sun';
        }
        
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-theme');
            const icon = this.querySelector('i');
            
            if (document.body.classList.contains('dark-theme')) {
                icon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            } else {
                icon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            }
        });
    }
}

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const inputs = form.querySelectorAll('input[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('error');
            isValid = false;
            
            // Show error message
            const errorDiv = input.nextElementSibling;
            if (errorDiv && errorDiv.classList.contains('error-message')) {
                errorDiv.textContent = 'This field is required';
            }
        } else {
            input.classList.remove('error');
            
            // Remove error message
            const errorDiv = input.nextElementSibling;
            if (errorDiv && errorDiv.classList.contains('error-message')) {
                errorDiv.textContent = '';
            }
        }
        
        // Email validation
        if (input.type === 'email' && input.value.trim()) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value.trim())) {
                input.classList.add('error');
                isValid = false;
                
                const errorDiv = input.nextElementSibling;
                if (errorDiv && errorDiv.classList.contains('error-message')) {
                    errorDiv.textContent = 'Please enter a valid email address';
                }
            }
        }
    });
    
    return isValid;
}

// Cookie Consent
function getCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

function setCookie(name, value, days = 365) {
    const expires = new Date();
    expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
}

function openPopup(popupId) {
    const popup = document.getElementById(popupId);
    if (!popup) return;
    popup.classList.add('show');
    document.body.classList.add('modal-open');
}

function closePopup(popupId) {
    const popup = document.getElementById(popupId);
    if (!popup) return;
    popup.classList.remove('show');
    if (!document.querySelector('.site-popup.show')) {
        document.body.classList.remove('modal-open');
    }
}

function initConsentPopup() {
    const popup = document.getElementById('cookieConsentPopup');
    if (!popup) return;

    if (getCookie('refreshing_dews_cookie_consent') === 'accepted') {
        closePopup('cookieConsentPopup');
        return;
    }

    openPopup('cookieConsentPopup');

    const acceptBtn = popup.querySelector('.cookie-accept');
    const declineBtn = popup.querySelector('.cookie-decline');

    acceptBtn && acceptBtn.addEventListener('click', function() {
        setCookie('refreshing_dews_cookie_consent', 'accepted', 365);
        closePopup('cookieConsentPopup');
        setTimeout(() => {
            maybeShowInstallPrompt();
        }, 300);
    });

    declineBtn && declineBtn.addEventListener('click', function() {
        setCookie('refreshing_dews_cookie_consent', 'declined', 30);
        closePopup('cookieConsentPopup');
    });
}

function maybeShowInstallPrompt() {
    const installPopup = document.getElementById('installAppPopup');
    if (!installPopup) return;

    const consentAccepted = getCookie('refreshing_dews_cookie_consent') === 'accepted';
    if (!consentAccepted) return;

    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    const isStandalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || !!window.navigator.standalone;

    if (!isMobile || isStandalone) {
        setTimeout(() => openNewsletterPopup(), 700);
        return;
    }

    const iOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
    const message = installPopup.querySelector('#installAppMessage');
    if (iOS) {
        if (message) {
            message.textContent = 'On iPhone or iPad, tap the Share button and choose “Add to Home Screen” to install this app.';
        }
        installPopup.querySelector('.install-button').textContent = 'Got it';
    } else if (message) {
        message.textContent = 'Install this app for a faster, cleaner mobile experience.';
    }

    setTimeout(() => {
        openPopup('installAppPopup');
    }, 700);
}

function initInstallPrompt() {
    const installPopup = document.getElementById('installAppPopup');
    const installButton = installPopup ? installPopup.querySelector('.install-button') : null;
    const dismissBtn = installPopup ? installPopup.querySelector('.install-dismiss') : null;

    if (!installPopup) return;

    if (installButton) {
        installButton.addEventListener('click', function() {
            const deferredEvent = window.deferredInstallPrompt;
            if (deferredEvent) {
                deferredEvent.prompt();
                deferredEvent.userChoice.then(function(choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        showNotification('App installed successfully!', 'success');
                    }
                    window.deferredInstallPrompt = null;
                    closePopup('installAppPopup');
                    setTimeout(() => openNewsletterPopup(), 500);
                });
                return;
            }

            closePopup('installAppPopup');
            setTimeout(() => openNewsletterPopup(), 500);
        });
    }

    if (dismissBtn) {
        dismissBtn.addEventListener('click', function() {
            closePopup('installAppPopup');
            setTimeout(() => openNewsletterPopup(), 500);
        });
    }

    window.addEventListener('beforeinstallprompt', function(event) {
        event.preventDefault();
        window.deferredInstallPrompt = event;
        if (getCookie('refreshing_dews_cookie_consent') === 'accepted') {
            setTimeout(() => {
                openPopup('installAppPopup');
            }, 700);
        }
    });
}

function openNewsletterPopup() {
    const popup = document.getElementById('newsletterPopup');
    if (!popup) return;
    openPopup('newsletterPopup');
}

function closeNewsletterPopup() {
    const popup = document.getElementById('newsletterPopup');
    if (popup) {
        popup.classList.remove('show');
    }
}

function initNewsletterModal() {
    const triggerButton = document.querySelector('.newsletter-trigger');
    const closeButton = document.querySelector('.newsletter-close');
    const form = document.getElementById('newsletterPopupForm');

    triggerButton && triggerButton.addEventListener('click', function() {
        openNewsletterPopup();
    });

    closeButton && closeButton.addEventListener('click', function() {
        closeNewsletterPopup();
    });

    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            submitNewsletterForm(this);
        });
    }
}

function submitNewsletterForm(form) {
    const emailInput = form.querySelector('input[name="email"], input[type="email"]');
    const email = emailInput ? emailInput.value.trim() : '';

    if (!email) {
        showNotification('Please enter your email address.', 'error');
        return false;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Subscribing...';
    }

    const formData = new URLSearchParams();
    formData.append('email', email);
    formData.append('ajax', '1');

    fetch(form.action || 'subscribe.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: formData.toString()
    })
    .then(async response => {
        const data = await response.json().catch(() => ({ success: false, message: 'Unable to process your request.' }));
        if (data.success) {
            showNotification(data.message || 'Successfully subscribed!', 'success');
            form.reset();
            closeNewsletterPopup();
        } else {
            showNotification(data.message || 'Subscription failed. Please try again.', 'error');
        }
    })
    .catch(() => {
        showNotification('An error occurred while subscribing. Please try again.', 'error');
    })
    .finally(() => {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Subscribe';
        }
    });

    return false;
}

function speakText(text, button) {
    if (!('speechSynthesis' in window)) {
        showNotification('Text-to-speech is not supported on this device.', 'error');
        return;
    }

    const normalized = String(text || '').replace(/\s+/g, ' ').trim();
    if (!normalized) {
        showNotification('There is no article text to read.', 'error');
        return;
    }

    const isPlaying = button.dataset.playing === 'true';
    if (isPlaying) {
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
        button.dataset.playing = 'false';
        button.innerHTML = '<i class="fas fa-volume-up"></i> Read Aloud';
        return;
    }

    const syncSpeech = function() {
        if (!window.speechSynthesis) return;
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(normalized);
        utterance.lang = 'en-US';
        utterance.rate = 1;
        utterance.pitch = 1;
        utterance.volume = 1;
        utterance.onstart = function() {
            button.dataset.playing = 'true';
            button.innerHTML = '<i class="fas fa-stop"></i> Stop Reading';
        };
        utterance.onend = function() {
            button.dataset.playing = 'false';
            button.innerHTML = '<i class="fas fa-volume-up"></i> Read Aloud';
        };
        utterance.onerror = function() {
            button.dataset.playing = 'false';
            button.innerHTML = '<i class="fas fa-volume-up"></i> Read Aloud';
        };

        if (window.speechSynthesis.paused) {
            window.speechSynthesis.resume();
        }

        window.speechSynthesis.speak(utterance);
    };

    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        setTimeout(syncSpeech, 160);
        return;
    }

    syncSpeech();
}

function bindReadAloudButtons() {
    document.querySelectorAll('.read-aloud-btn').forEach(function(button) {
        if (button.dataset.bound === 'true') return;
        button.dataset.bound = 'true';

        const handleRead = function(event) {
            if (event && event.preventDefault) {
                event.preventDefault();
            }

            let text = button.dataset.readText || '';
            const targetSelector = button.dataset.target || '';

            if (targetSelector) {
                const target = document.querySelector(targetSelector);
                if (target) {
                    text = target.innerText || text;
                }
            }

            if (!text) {
                showNotification('Nothing to read right now.', 'error');
                return;
            }

            speakText(text, button);
        };

        button.addEventListener('click', handleRead);
        button.addEventListener('touchstart', handleRead, { passive: false });
    });
}

// Notification System
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close"><i class="fas fa-times"></i></button>
    `;
    
    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? 'var(--tea-green)' : type === 'error' ? '#FEE2E2' : '#E5E7EB'};
        color: ${type === 'error' ? '#991B1B' : 'var(--text-dark)'};
        border-radius: 10px;
        box-shadow: var(--shadow-lg);
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 15px;
        animation: slideIn 0.3s ease;
        border-left: 4px solid ${type === 'success' ? 'var(--tea-green-dark)' : type === 'error' ? '#DC2626' : '#6B7280'};
    `;
    
    document.body.appendChild(notification);
    
    // Close button
    notification.querySelector('.notification-close').addEventListener('click', function() {
        notification.remove();
    });
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

// Add CSS animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .notification {
        font-family: 'Inter', sans-serif;
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .notification-close {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1rem;
        color: inherit;
        opacity: 0.7;
        transition: opacity 0.3s;
    }
    
    .notification-close:hover {
        opacity: 1;
    }

    body.modal-open {
        overflow: hidden;
    }
    
    .nav-links.show {
        display: flex !important;
        flex-direction: column;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--white);
        padding: 20px;
        box-shadow: var(--shadow-lg);
        border-top: 1px solid var(--tea-green-light);
    }
    
    .nav-links.show li {
        margin: 10px 0;
    }
    
    @media (max-width: 768px) {
        .nav-links {
            display: none;
        }
    }
    
    input.error, textarea.error {
        border-color: #DC2626 !important;
    }
    
    .error-message {
        color: #DC2626;
        font-size: 0.875rem;
        margin-top: 5px;
    }
`;

document.head.appendChild(style);

// Initialize everything
initThemeToggle();

