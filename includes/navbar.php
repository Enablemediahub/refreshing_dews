<?php
// Get settings from database - correct paths for includes folder
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db-connection.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Fetch all navbar-related settings directly from database
$all_settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $all_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Helper function to get setting with default
function getNavbarSetting($key, $default = '') {
    global $all_settings;
    return isset($all_settings[$key]) ? $all_settings[$key] : $default;
}

// Get settings from database
$site_logo = getNavbarSetting('site_logo', 'assets/logo/painlesslyf-logo.png');
$navbar_bg = getNavbarSetting('navbar_background', 'rgba(250, 247, 240, 0.92)');
$navbar_text = getNavbarSetting('navbar_text_color', '#2c3e2f');
$navbar_hover_color = getNavbarSetting('navbar_hover_color', '#4a7c59');
$primary_color = getNavbarSetting('primary_color', '#baa68e');
$navbar_accent = getNavbarSetting('navbar_accent_color', '#baa68e');
$navbar_background_type = getNavbarSetting('navbar_background_type', 'solid');
$navbar_blur = getNavbarSetting('navbar_blur', '10');

// Dropdown styling settings
$dropdown_bg = getNavbarSetting('navbar_dropdown_bg', '#ffffff');
$dropdown_text = getNavbarSetting('navbar_dropdown_text', '#333333');
$dropdown_hover_bg = getNavbarSetting('navbar_dropdown_hover_bg', '#4a7c59');
$dropdown_hover_text = getNavbarSetting('navbar_dropdown_hover_text', '#ffffff');

// Navbar sizing
$navbar_padding = getNavbarSetting('navbar_padding', '20');
$navbar_logo_height = getNavbarSetting('navbar_logo_height', '45');

// Get menu text and links
$menu_home_text = getNavbarSetting('navbar_menu_home', 'Home');
$menu_blog_text = getNavbarSetting('navbar_menu_blog', 'Blog');
$menu_audio_text = getNavbarSetting('navbar_menu_audio', 'Audio');
$menu_about_text = getNavbarSetting('navbar_menu_about', 'About');
$menu_contact_text = getNavbarSetting('navbar_menu_contact', 'Contact');

// Get menu links
$menu_home_link = getNavbarSetting('navbar_link_home', 'index.php');
$menu_blog_link = getNavbarSetting('navbar_link_blog', 'blog.php');
$menu_audio_link = getNavbarSetting('navbar_link_audio', 'audio.php');
$menu_about_link = getNavbarSetting('navbar_link_about', 'about.php');
$menu_contact_link = getNavbarSetting('navbar_link_contact', 'contact.php');

// Get menu icons
$menu_home_icon = getNavbarSetting('navbar_icon_home', 'fas fa-home');
$menu_blog_icon = getNavbarSetting('navbar_icon_blog', 'fas fa-pencil-alt');
$menu_audio_icon = getNavbarSetting('navbar_icon_audio', 'fas fa-headphones');
$menu_about_icon = getNavbarSetting('navbar_icon_about', 'fas fa-user');
$menu_contact_icon = getNavbarSetting('navbar_icon_contact', 'fas fa-envelope');

// Get mobile settings
$mobile_breakpoint = getNavbarSetting('navbar_mobile_breakpoint', '992');
$show_theme_toggle = getNavbarSetting('navbar_show_theme_toggle', '1');
$hide_on_scroll = getNavbarSetting('navbar_hide_on_scroll', '1');
$scroll_threshold = getNavbarSetting('navbar_scroll_threshold', '100');

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Pages with a hero/header band — same transparent white nav as the index slideshow
$hero_overlay_pages = [
    'index.php',
    '',
    'about.php',
    'blog.php',
    'blog-post.php',
    'audio.php',
    'audio-player.php',
    'contact.php',
];
$use_hero_overlay = in_array($current_page, $hero_overlay_pages, true);

// Build dynamic styles
$navbar_style = '';
if ($navbar_background_type == 'frosted') {
    $navbar_style .= 'backdrop-filter: blur(' . $navbar_blur . 'px);';
    $navbar_style .= '-webkit-backdrop-filter: blur(' . $navbar_blur . 'px);';
}
?>
<style>
/* Modern Professional Navbar — logo colors: taupe #baa68e, forest #4a7c59, cream #faf7f0 */
:root {
    --nav-primary: <?php echo $primary_color; ?>;
    --nav-accent: <?php echo $navbar_accent; ?>;
    --nav-green: <?php echo $navbar_hover_color; ?>;
    --nav-blue: #1a2744;
    --nav-text: <?php echo $navbar_text; ?>;
    --nav-bg: <?php echo $navbar_bg; ?>;
    --nav-cream: #faf7f0;
    --nav-dark: #2c3e2f;
}

.modern-navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 10000;
    padding: 16px 0;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.modern-navbar.scrolled {
    padding: 10px 0;
}

.navbar-inner {
    max-width: 1320px;
    margin: 0 auto;
    padding: 0 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}

.navbar-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 10px 12px 10px 20px;
    border-radius: 60px;
    background: transparent;
    border: 1px solid transparent;
    transition: all 0.35s ease;
}

.modern-navbar.scrolled .navbar-bar {
    background: var(--nav-bg);
    <?php echo $navbar_style; ?>
    border-color: rgba(186, 166, 142, 0.25);
    box-shadow: 0 4px 30px rgba(44, 62, 47, 0.08), 0 1px 0 rgba(255, 255, 255, 0.6) inset;
}

.modern-navbar .logo {
    display: flex;
    align-items: center;
    text-decoration: none;
    flex-shrink: 0;
}

.modern-navbar .logo img {
    height: <?php echo intval($navbar_logo_height); ?>px;
    width: <?php echo intval($navbar_logo_height); ?>px;
    border-radius: 50%;
    object-fit: cover;
    transition: transform 0.3s ease;
    background: #ffffff;
    padding: 2px;
    box-shadow: 0 2px 10px rgba(44, 62, 47, 0.12);
    border: 2px solid rgba(255, 255, 255, 0.85);
}

.modern-navbar .logo:hover img {
    transform: scale(1.03);
}

/* Desktop navigation links */
.nav-links {
    display: flex;
    align-items: center;
    gap: 6px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-links li a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 50px;
    text-decoration: none;
    color: var(--nav-text);
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0.2px;
    transition: all 0.25s ease;
    white-space: nowrap;
}

.nav-links li a i {
    font-size: 14px;
    color: var(--nav-green);
    transition: color 0.25s ease;
}

.nav-links li a:hover {
    background: rgba(186, 166, 142, 0.15);
    color: var(--nav-dark);
}

.nav-links li a:hover i {
    color: var(--nav-primary);
}

.nav-links li a.active {
    background: linear-gradient(135deg, var(--nav-blue) 0%, #2d3f60 100%);
    color: #ffffff;
    box-shadow: 0 6px 18px rgba(26, 39, 68, 0.28);
}

.nav-links li a.active i {
    color: rgba(255, 255, 255, 0.9);
}

.nav-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.nav-cta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: 50px;
    background: linear-gradient(135deg, var(--nav-primary) 0%, #9b8a6b 100%);
    color: #ffffff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.3px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(186, 166, 142, 0.35);
    white-space: nowrap;
}

.nav-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(186, 166, 142, 0.45);
    color: #ffffff;
}

.nav-cta i {
    font-size: 13px;
}

/* Mobile toggle */
.mobile-toggle {
    display: none;
    background: rgba(186, 166, 142, 0.12);
    border: 1px solid rgba(186, 166, 142, 0.25);
    border-radius: 12px;
    cursor: pointer;
    padding: 10px 12px;
    z-index: 10002;
    transition: all 0.3s ease;
}

.mobile-toggle:hover {
    background: rgba(186, 166, 142, 0.22);
}

.mobile-toggle-box {
    width: 22px;
    height: 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
}

.mobile-toggle-inner {
    width: 100%;
    height: 2px;
    background: var(--nav-text);
    border-radius: 2px;
    transition: all 0.3s ease;
    position: relative;
}

.mobile-toggle-inner::before,
.mobile-toggle-inner::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 2px;
    background: var(--nav-text);
    border-radius: 2px;
    transition: all 0.3s ease;
    left: 0;
}

.mobile-toggle-inner::before { top: -7px; }
.mobile-toggle-inner::after { bottom: -7px; }

.mobile-toggle.active .mobile-toggle-inner {
    background: transparent;
}

.mobile-toggle.active .mobile-toggle-inner::before {
    transform: rotate(45deg);
    top: 0;
    background: var(--nav-green);
}

.mobile-toggle.active .mobile-toggle-inner::after {
    transform: rotate(-45deg);
    bottom: 0;
    background: var(--nav-green);
}

/* Hero overlay: light nav text on dark hero */
.modern-navbar.nav-on-hero .nav-links li a,
.modern-navbar.nav-on-hero .mobile-toggle-inner,
.modern-navbar.nav-on-hero .mobile-toggle-inner::before,
.modern-navbar.nav-on-hero .mobile-toggle-inner::after {
    color: #ffffff;
}

.modern-navbar.nav-on-hero:not(.scrolled) .nav-links li a {
    color: rgba(255, 255, 255, 0.92);
}

.modern-navbar.nav-on-hero:not(.scrolled) .nav-links li a i {
    color: rgba(255, 255, 255, 0.75);
}

.modern-navbar.nav-on-hero:not(.scrolled) .nav-links li a:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
}

.modern-navbar.nav-on-hero:not(.scrolled) .mobile-toggle-inner,
.modern-navbar.nav-on-hero:not(.scrolled) .mobile-toggle-inner::before,
.modern-navbar.nav-on-hero:not(.scrolled) .mobile-toggle-inner::after {
    background: #ffffff;
}

.modern-navbar.nav-on-hero:not(.scrolled) .mobile-toggle {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
}

/* Mobile drawer */
.mobile-drawer {
    position: fixed;
    top: 0;
    right: 0;
    width: min(360px, 88vw);
    height: 100vh;
    height: 100dvh;
    background: var(--nav-bg);
    <?php echo $navbar_style; ?>
    z-index: 10001;
    transform: translateX(100%);
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: -8px 0 40px rgba(44, 62, 47, 0.12);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.mobile-drawer.active {
    transform: translateX(0);
}

.mobile-drawer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(44, 62, 47, 0.4);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.35s ease;
}

.mobile-drawer-overlay.active {
    opacity: 1;
    visibility: visible;
}

.mobile-drawer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 24px 16px;
    border-bottom: 1px solid rgba(186, 166, 142, 0.2);
}

.mobile-drawer-header img {
    height: <?php echo max(32, intval($navbar_logo_height) - 8); ?>px;
    width: <?php echo max(32, intval($navbar_logo_height) - 8); ?>px;
    border-radius: 50%;
    object-fit: cover;
    background: #ffffff;
    padding: 2px;
    border: 2px solid rgba(186, 166, 142, 0.25);
}

.mobile-drawer-close {
    background: rgba(186, 166, 142, 0.12);
    border: 1px solid rgba(186, 166, 142, 0.25);
    width: 40px;
    height: 40px;
    border-radius: 12px;
    cursor: pointer;
    color: var(--nav-text);
    font-size: 18px;
    transition: all 0.25s ease;
}

.mobile-drawer-close:hover {
    background: var(--nav-blue);
    color: #ffffff;
}

.mobile-drawer-links {
    list-style: none;
    padding: 16px 16px 0;
    margin: 0;
    flex: 1;
}

.mobile-drawer-links li {
    margin-bottom: 4px;
}

.mobile-drawer-links a {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    border-radius: 50px;
    text-decoration: none;
    color: var(--nav-text);
    font-weight: 500;
    font-size: 15px;
    transition: all 0.25s ease;
}

.mobile-drawer-links a i {
    width: 22px;
    text-align: center;
    font-size: 18px;
    color: var(--nav-green);
}

.mobile-drawer-links a:hover,
.mobile-drawer-links a.active {
    background: linear-gradient(135deg, var(--nav-blue) 0%, #2d3f60 100%);
    color: #ffffff;
    box-shadow: 0 6px 18px rgba(26, 39, 68, 0.22);
}

.mobile-drawer-links a:hover i,
.mobile-drawer-links a.active i {
    color: rgba(255, 255, 255, 0.9);
}

.mobile-drawer-footer {
    padding: 20px 24px 28px;
    border-top: 1px solid rgba(186, 166, 142, 0.2);
}

.mobile-drawer-social {
    display: flex;
    gap: 16px;
    justify-content: center;
    margin-bottom: 16px;
}

.mobile-drawer-social a {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(186, 166, 142, 0.12);
    color: var(--nav-text);
    font-size: 18px;
    transition: all 0.25s ease;
    text-decoration: none;
}

.mobile-drawer-social a:hover {
    background: var(--nav-primary);
    color: #ffffff;
    transform: translateY(-2px);
}

.mobile-drawer-meta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    font-size: 13px;
    color: var(--nav-text);
    opacity: 0.65;
}

.drawer-theme-toggle {
    background: rgba(186, 166, 142, 0.15);
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    cursor: pointer;
    color: var(--nav-text);
    font-size: 16px;
    transition: all 0.25s ease;
}

.drawer-theme-toggle:hover {
    background: var(--nav-blue);
    color: #ffffff;
}

/* Dark theme */
body.dark-theme .modern-navbar.scrolled .navbar-bar {
    background: rgba(26, 26, 26, 0.95);
    border-color: rgba(255, 255, 255, 0.08);
}

body.dark-theme .nav-links li a,
body.dark-theme .mobile-drawer-links a {
    color: #e0e0e0;
}

body.dark-theme .mobile-drawer {
    background: rgba(26, 26, 26, 0.95);
}

body.dark-theme .mobile-drawer-header,
body.dark-theme .mobile-drawer-footer {
    border-color: rgba(255, 255, 255, 0.08);
}

/* Responsive */
@media (max-width: <?php echo intval($mobile_breakpoint); ?>px) {
    .nav-links {
        display: none;
    }
    
    .nav-cta {
        display: none;
    }
    
    .mobile-toggle {
        display: block;
    }
    
    .navbar-inner {
        padding: 0 16px;
    }
    
    .navbar-bar {
        padding: 8px 10px 8px 16px;
    }
}

@media (min-width: 769px) and (max-width: <?php echo intval($mobile_breakpoint); ?>px) {
    .navbar-inner {
        padding: 0 20px;
    }

    .navbar-bar {
        padding: 9px 12px 9px 18px;
    }
}

@media (max-width: 768px) {
    .modern-navbar {
        padding: 10px 0;
    }

    .modern-navbar.scrolled {
        padding: 8px 0;
    }

    .navbar-inner {
        padding: 0 10px;
    }

    .navbar-bar {
        padding: 7px 8px 7px 12px;
        border-radius: 18px;
    }

    .mobile-toggle {
        padding: 9px 10px;
    }

    .modern-navbar .logo img {
        height: <?php echo max(30, intval($navbar_logo_height) - 10); ?>px;
        width: <?php echo max(30, intval($navbar_logo_height) - 10); ?>px;
    }
}

@media (max-width: 480px) {
    .modern-navbar .logo img {
        height: <?php echo max(28, intval($navbar_logo_height) - 15); ?>px;
        width: <?php echo max(28, intval($navbar_logo_height) - 15); ?>px;
    }
}
</style>

<nav class="modern-navbar<?php echo $use_hero_overlay ? ' nav-on-hero' : ''; ?>" id="modernNavbar">
    <div class="navbar-inner">
        <div class="navbar-bar">
            <a href="<?php echo htmlspecialchars($menu_home_link); ?>" class="logo">
                <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo">
            </a>
            
            <ul class="nav-links">
                <li>
                    <a href="<?php echo htmlspecialchars($menu_home_link); ?>" class="<?php echo ($current_page == 'index.php' || $current_page == '') ? 'active' : ''; ?>">
                        <i class="<?php echo htmlspecialchars($menu_home_icon); ?>"></i>
                        <span><?php echo htmlspecialchars($menu_home_text); ?></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars($menu_blog_link); ?>" class="<?php echo ($current_page == 'blog.php') ? 'active' : ''; ?>">
                        <i class="<?php echo htmlspecialchars($menu_blog_icon); ?>"></i>
                        <span><?php echo htmlspecialchars($menu_blog_text); ?></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars($menu_audio_link); ?>" class="<?php echo ($current_page == 'audio.php') ? 'active' : ''; ?>">
                        <i class="<?php echo htmlspecialchars($menu_audio_icon); ?>"></i>
                        <span><?php echo htmlspecialchars($menu_audio_text); ?></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars($menu_about_link); ?>" class="<?php echo ($current_page == 'about.php') ? 'active' : ''; ?>">
                        <i class="<?php echo htmlspecialchars($menu_about_icon); ?>"></i>
                        <span><?php echo htmlspecialchars($menu_about_text); ?></span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars($menu_contact_link); ?>" class="<?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>">
                        <i class="<?php echo htmlspecialchars($menu_contact_icon); ?>"></i>
                        <span><?php echo htmlspecialchars($menu_contact_text); ?></span>
                    </a>
                </li>
            </ul>
            
            <div class="nav-actions">
                <a href="<?php echo htmlspecialchars($menu_audio_link); ?>" class="nav-cta">
                    <i class="fas fa-headphones"></i> Listen
                </a>
                <button class="mobile-toggle" id="mobileToggle" type="button" aria-label="Open menu">
                    <div class="mobile-toggle-box">
                        <div class="mobile-toggle-inner"></div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</nav>

<div class="mobile-drawer-overlay" id="drawerOverlay"></div>

<div class="mobile-drawer" id="mobileDrawer">
    <div class="mobile-drawer-header">
        <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo">
        <button class="mobile-drawer-close" id="drawerClose" type="button" aria-label="Close menu">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <ul class="mobile-drawer-links">
        <li>
            <a href="<?php echo htmlspecialchars($menu_home_link); ?>" class="<?php echo ($current_page == 'index.php' || $current_page == '') ? 'active' : ''; ?>">
                <i class="<?php echo htmlspecialchars($menu_home_icon); ?>"></i>
                <span><?php echo htmlspecialchars($menu_home_text); ?></span>
            </a>
        </li>
        <li>
            <a href="<?php echo htmlspecialchars($menu_blog_link); ?>" class="<?php echo ($current_page == 'blog.php') ? 'active' : ''; ?>">
                <i class="<?php echo htmlspecialchars($menu_blog_icon); ?>"></i>
                <span><?php echo htmlspecialchars($menu_blog_text); ?></span>
            </a>
        </li>
        <li>
            <a href="<?php echo htmlspecialchars($menu_audio_link); ?>" class="<?php echo ($current_page == 'audio.php') ? 'active' : ''; ?>">
                <i class="<?php echo htmlspecialchars($menu_audio_icon); ?>"></i>
                <span><?php echo htmlspecialchars($menu_audio_text); ?></span>
            </a>
        </li>
        <li>
            <a href="<?php echo htmlspecialchars($menu_about_link); ?>" class="<?php echo ($current_page == 'about.php') ? 'active' : ''; ?>">
                <i class="<?php echo htmlspecialchars($menu_about_icon); ?>"></i>
                <span><?php echo htmlspecialchars($menu_about_text); ?></span>
            </a>
        </li>
        <li>
            <a href="<?php echo htmlspecialchars($menu_contact_link); ?>" class="<?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>">
                <i class="<?php echo htmlspecialchars($menu_contact_icon); ?>"></i>
                <span><?php echo htmlspecialchars($menu_contact_text); ?></span>
            </a>
        </li>
    </ul>
    
    <div class="mobile-drawer-footer">
        <div class="mobile-drawer-social">
            <?php
            $instagram_url = getNavbarSetting('instagram_url', '#');
            $twitter_url = getNavbarSetting('twitter_url', '#');
            $youtube_url = getNavbarSetting('youtube_url', '#');
            $spotify_url = getNavbarSetting('spotify_url', '#');
            ?>
            <?php if ($instagram_url && $instagram_url != '#'): ?>
            <a href="<?php echo htmlspecialchars($instagram_url); ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram"></i></a>
            <?php endif; ?>
            <?php if ($twitter_url && $twitter_url != '#'): ?>
            <a href="<?php echo htmlspecialchars($twitter_url); ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-twitter"></i></a>
            <?php endif; ?>
            <?php if ($youtube_url && $youtube_url != '#'): ?>
            <a href="<?php echo htmlspecialchars($youtube_url); ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-youtube"></i></a>
            <?php endif; ?>
            <?php if ($spotify_url && $spotify_url != '#'): ?>
            <a href="<?php echo htmlspecialchars($spotify_url); ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-spotify"></i></a>
            <?php endif; ?>
        </div>
        <div class="mobile-drawer-meta">
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getNavbarSetting('site_title', 'Painlesslyf')); ?></span>
            <?php if ($show_theme_toggle == '1'): ?>
            <button class="drawer-theme-toggle" id="drawerThemeToggle" type="button" aria-label="Toggle theme">
                <i class="fas fa-moon"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        const toggle = document.getElementById('mobileToggle');
        const drawer = document.getElementById('mobileDrawer');
        const overlay = document.getElementById('drawerOverlay');
        const closeBtn = document.getElementById('drawerClose');
        const body = document.body;
        
        if (!toggle || !drawer) return;
        
        function openDrawer() {
            toggle.classList.add('active');
            drawer.classList.add('active');
            if (overlay) overlay.classList.add('active');
            body.style.overflow = 'hidden';
        }
        
        function closeDrawer() {
            toggle.classList.remove('active');
            drawer.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
            body.style.overflow = '';
        }
        
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (drawer.classList.contains('active')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
        
        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
        if (overlay) overlay.addEventListener('click', closeDrawer);
        
        drawer.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', closeDrawer);
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && drawer.classList.contains('active')) {
                closeDrawer();
            }
        });
        
        // Theme toggle
        const themeToggle = document.getElementById('drawerThemeToggle');
        if (themeToggle) {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                body.classList.add('dark-theme');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            }
            
            themeToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                body.classList.toggle('dark-theme');
                const icon = this.querySelector('i');
                if (body.classList.contains('dark-theme')) {
                    icon.className = 'fas fa-sun';
                    localStorage.setItem('theme', 'dark');
                } else {
                    icon.className = 'fas fa-moon';
                    localStorage.setItem('theme', 'light');
                }
            });
        }
        
        // Scroll effect
        const navbar = document.getElementById('modernNavbar');
        const hideOnScroll = <?php echo intval($hide_on_scroll); ?>;
        const scrollThreshold = <?php echo intval($scroll_threshold); ?>;
        let lastScroll = 0;
        let scrollTimeout;
        
        window.addEventListener('scroll', function() {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            
            if (hideOnScroll && !drawer.classList.contains('active')) {
                if (currentScroll > lastScroll && currentScroll > scrollThreshold) {
                    navbar.style.transform = 'translateY(-100%)';
                } else {
                    navbar.style.transform = 'translateY(0)';
                }
            }
            
            lastScroll = currentScroll;
            
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                if (hideOnScroll && !drawer.classList.contains('active')) {
                    navbar.style.transform = 'translateY(0)';
                }
            }, 150);
        });
        
        if (window.pageYOffset > 50) {
            navbar.classList.add('scrolled');
        }
    }
})();
</script>