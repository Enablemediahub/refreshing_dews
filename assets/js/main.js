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
    initSpeechUnlock();
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

    if (!isMobile || isStandalone || getCookie('refreshing_dews_install_prompt_dismissed') === '1') return;

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

function initSpeechUnlock() {
    const unlockSpeech = function() {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.resume && window.speechSynthesis.resume();
        }
    };

    document.addEventListener('touchstart', unlockSpeech, { passive: true });
    document.addEventListener('pointerdown', unlockSpeech, { passive: true });
    document.addEventListener('click', unlockSpeech, { passive: true });
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
                    setCookie('refreshing_dews_install_prompt_dismissed', '1', 180);
                    closePopup('installAppPopup');
                });
                return;
            }

            const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            if (isIOS) {
                setCookie('refreshing_dews_install_prompt_dismissed', '1', 180);
                closePopup('installAppPopup');
                return;
            }

            setCookie('refreshing_dews_install_prompt_dismissed', '1', 180);
            closePopup('installAppPopup');
        });
    }

    if (dismissBtn) {
        dismissBtn.addEventListener('click', function() {
            setCookie('refreshing_dews_install_prompt_dismissed', '1', 180);
            closePopup('installAppPopup');
        });
    }

    window.addEventListener('beforeinstallprompt', function(event) {
        event.preventDefault();
        window.deferredInstallPrompt = event;
        if (getCookie('refreshing_dews_cookie_consent') === 'accepted' &&
            getCookie('refreshing_dews_install_prompt_dismissed') !== '1') {
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
    closePopup('newsletterPopup');
    document.body.classList.remove('modal-open');
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

const readAloudState = {
    text: '',
    sourceButton: null,
    utterance: null,
    chunks: [],
    chunkIndex: 0,
    chunkOffset: 0,
    paused: false,
    rate: 1,
    voice: null
};

function ensureReadAloudStyles() {
    if (document.getElementById('readAloudPlayerStyles')) return;
    const styles = document.createElement('style');
    styles.id = 'readAloudPlayerStyles';
    styles.textContent = `
        .read-aloud-player { position: fixed; right: 24px; bottom: 24px; z-index: 10000; width: min(520px, calc(100vw - 32px)); padding: 14px 16px; color: #fff; background: #1a2744; border: 1px solid rgba(255,255,255,.16); border-radius: 14px; box-shadow: 0 14px 36px rgba(15,24,36,.28); transform: translateY(calc(100% + 36px)); opacity: 0; pointer-events: none; transition: transform .25s ease, opacity .25s ease; }
        .read-aloud-player.is-visible { transform: translateY(0); opacity: 1; pointer-events: auto; }
        .read-aloud-player-title { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; font-weight: 700; }
        .read-aloud-close { border: 0; padding: 2px 5px; color: rgba(255,255,255,.72); background: transparent; font-size: 1rem; cursor: pointer; }
        .read-aloud-progress { width: 100%; height: 5px; margin: 0 0 12px; accent-color: #f6dfb3; cursor: pointer; }
        .read-aloud-player-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .read-aloud-control { width: 34px; height: 34px; border: 0; border-radius: 50%; color: #1a2744; background: #f6dfb3; cursor: pointer; }
        .read-aloud-player label { display: inline-flex; align-items: center; gap: 6px; font-size: .82rem; color: rgba(255,255,255,.82); }
        .read-aloud-player select { max-width: 160px; padding: 7px 8px; color: #1a2744; background: #fff; border: 0; border-radius: 6px; }
        @media (max-width: 600px) { .read-aloud-player { right: 16px; bottom: 16px; } .read-aloud-voice-label { width: 100%; } .read-aloud-voice-label select { flex: 1; max-width: none; } }
    `;
    document.head.appendChild(styles);
}

function updateReadAloudButtons(isPlaying) {
    document.querySelectorAll('.read-aloud-btn').forEach(function(button) {
        const isSource = button === readAloudState.sourceButton;
        button.dataset.playing = isSource && isPlaying ? 'true' : 'false';
        button.innerHTML = isSource && isPlaying
            ? '<i class="fas fa-stop"></i> Stop Reading'
            : '<i class="fas fa-volume-up"></i> Read Aloud';
    });
}

function createReadAloudPlayer() {
    let player = document.getElementById('readAloudPlayer');
    if (player) return player;

    ensureReadAloudStyles();

    player = document.createElement('aside');
    player.id = 'readAloudPlayer';
    player.className = 'read-aloud-player';
    player.setAttribute('aria-label', 'Read aloud player');
    player.innerHTML = `
        <div class="read-aloud-player-title"><span><i class="fas fa-volume-up"></i> Read aloud</span><button type="button" class="read-aloud-close" aria-label="Close read aloud player"><i class="fas fa-times"></i></button></div>
        <input type="range" class="read-aloud-progress" min="0" max="1000" value="0" aria-label="Reading position">
        <div class="read-aloud-player-controls">
            <button type="button" class="read-aloud-control read-aloud-play" aria-label="Play reading"><i class="fas fa-play"></i></button>
            <button type="button" class="read-aloud-control read-aloud-stop" aria-label="Stop reading"><i class="fas fa-stop"></i></button>
            <label>Speed <select class="read-aloud-rate" aria-label="Reading speed">
                <option value="0.7">Slower</option>
                <option value="1" selected>Normal</option>
                <option value="1.2">Faster</option>
            </select></label>
            <label class="read-aloud-voice-label">Voice <select class="read-aloud-voice" aria-label="Reading voice"></select></label>
        </div>`;
    document.body.appendChild(player);

    player.querySelector('.read-aloud-close').addEventListener('click', closeReadAloud);
    player.querySelector('.read-aloud-progress').addEventListener('input', function() {
        seekReadAloud(Number(this.value) / 1000);
    });

    player.querySelector('.read-aloud-play').addEventListener('click', function() {
        if (!readAloudState.text) return;
        if (readAloudState.paused) {
            readAloudState.paused = false;
            speakCurrentText();
        } else if (window.speechSynthesis.speaking) {
            readAloudState.paused = true;
            window.speechSynthesis.cancel();
            this.innerHTML = '<i class="fas fa-play"></i>';
        } else {
            speakCurrentText();
        }
    });
    player.querySelector('.read-aloud-stop').addEventListener('click', stopReadAloud);
    player.querySelector('.read-aloud-rate').addEventListener('change', function() {
        readAloudState.rate = Number(this.value);
        if (readAloudState.text) speakCurrentText();
    });
    player.querySelector('.read-aloud-voice').addEventListener('change', function() {
        readAloudState.voice = getSpeechVoices().find(voice => voice.name === this.value) || null;
        if (readAloudState.text) speakCurrentText();
    });

    populateReadAloudVoices(player);
    if ('speechSynthesis' in window && !window.readAloudVoicesBound) {
        window.speechSynthesis.addEventListener('voiceschanged', function() {
            populateReadAloudVoices(player);
        });
        window.readAloudVoicesBound = true;
    }
    return player;
}

function getSpeechVoices() {
    return 'speechSynthesis' in window ? window.speechSynthesis.getVoices() : [];
}

function populateReadAloudVoices(player) {
    const voiceSelect = player.querySelector('.read-aloud-voice');
    const voices = getSpeechVoices().filter(voice => voice.lang.toLowerCase().startsWith('en'));
    voiceSelect.innerHTML = '';
    (voices.length ? voices : getSpeechVoices()).forEach(function(voice) {
        const option = document.createElement('option');
        option.value = voice.name;
        option.textContent = `${voice.name} (${voice.lang})`;
        voiceSelect.appendChild(option);
    });
    if (readAloudState.voice) voiceSelect.value = readAloudState.voice.name;
}

function speakCurrentText() {
    if (!readAloudState.text || !('speechSynthesis' in window)) return;
    readAloudState.utterance = null;
    window.speechSynthesis.cancel();
    const fullChunk = readAloudState.chunks[readAloudState.chunkIndex] || readAloudState.text;
    const startingOffset = readAloudState.chunkOffset;
    const chunk = fullChunk.slice(startingOffset);
    const utterance = new SpeechSynthesisUtterance(chunk);
    utterance.lang = readAloudState.voice ? readAloudState.voice.lang : 'en-US';
    utterance.rate = readAloudState.rate;
    utterance.pitch = 1;
    utterance.volume = 1;
    if (readAloudState.voice) utterance.voice = readAloudState.voice;
    utterance.onstart = function() {
        updateReadAloudButtons(true);
        document.getElementById('readAloudPlayer').classList.add('is-active');
        document.querySelector('.read-aloud-play').innerHTML = '<i class="fas fa-pause"></i>';
    };
    utterance.onboundary = function(event) {
        if (typeof event.charIndex === 'number') {
            readAloudState.chunkOffset = startingOffset + event.charIndex;
            updateReadAloudProgress();
        }
    };
    utterance.onend = function() {
        if (readAloudState.utterance !== utterance) return;
        if (readAloudState.paused) return;
        readAloudState.chunkIndex += 1;
        readAloudState.chunkOffset = 0;
        if (readAloudState.chunkIndex < readAloudState.chunks.length) {
            speakCurrentText();
            return;
        }
        readAloudState.utterance = null;
        updateReadAloudButtons(false);
        document.getElementById('readAloudPlayer').classList.remove('is-active');
        document.querySelector('.read-aloud-play').innerHTML = '<i class="fas fa-play"></i>';
    };
    utterance.onerror = utterance.onend;
    readAloudState.utterance = utterance;
    window.speechSynthesis.speak(utterance);
}

function stopReadAloud() {
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    readAloudState.utterance = null;
    readAloudState.paused = false;
    updateReadAloudButtons(false);
    const player = document.getElementById('readAloudPlayer');
    if (player) {
        player.classList.remove('is-active');
        player.querySelector('.read-aloud-play').innerHTML = '<i class="fas fa-play"></i>';
    }
}

function updateReadAloudProgress() {
    const player = document.getElementById('readAloudPlayer');
    if (!player || !readAloudState.text) return;
    const completed = readAloudState.chunks.slice(0, readAloudState.chunkIndex).join('').length;
    const position = completed + readAloudState.chunkOffset;
    const progress = Math.min(1000, Math.round((position / readAloudState.text.length) * 1000));
    player.querySelector('.read-aloud-progress').value = progress;
}

function seekReadAloud(progress) {
    if (!readAloudState.text) return;
    const target = Math.min(readAloudState.text.length - 1, Math.max(0, Math.round(progress * readAloudState.text.length)));
    let offset = 0;
    readAloudState.chunkIndex = 0;
    readAloudState.chunkOffset = 0;
    for (let index = 0; index < readAloudState.chunks.length; index += 1) {
        const nextOffset = offset + readAloudState.chunks[index].length;
        if (target < nextOffset) {
            readAloudState.chunkIndex = index;
            readAloudState.chunkOffset = target - offset;
            break;
        }
        offset = nextOffset;
    }
    readAloudState.paused = false;
    speakCurrentText();
}

function closeReadAloud() {
    stopReadAloud();
    const player = document.getElementById('readAloudPlayer');
    if (player) player.classList.remove('is-visible');
    readAloudState.text = '';
    readAloudState.sourceButton = null;
    readAloudState.chunks = [];
    readAloudState.chunkIndex = 0;
    readAloudState.chunkOffset = 0;
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
    if (button === readAloudState.sourceButton && readAloudState.utterance) {
        stopReadAloud();
        return;
    }
    readAloudState.text = normalized;
    readAloudState.sourceButton = button;
    readAloudState.chunks = normalized.match(/[^.!?]+[.!?]+|[^.!?]+$/g) || [normalized];
    readAloudState.chunkIndex = 0;
    readAloudState.chunkOffset = 0;
    readAloudState.paused = false;
    const player = createReadAloudPlayer();
    player.classList.add('is-visible');
    speakCurrentText();
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
                const articleText = document.querySelector('.post-body');
                if (articleText) {
                    text = articleText.innerText || articleText.textContent || '';
                }
            }

            if (!text) {
                showNotification('Nothing to read right now.', 'error');
                return;
            }

            speakText(text, button);
        };

        button.addEventListener('click', handleRead, { passive: false });
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

