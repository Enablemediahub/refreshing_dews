<?php
/**
 * Shared site footer - consistent across all public pages
 */
if (!function_exists('getSetting')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/db-connection.php';
    require_once __DIR__ . '/functions.php';
}

$footer_site_title = getSetting('site_title', 'refreshing_dews');
$footer_site_logo = getSetting('site_logo', 'assets/logo/refreshing_dews-logo.png');
$footer_description = getSetting('footer_description', 'A space for the brave. No sugarcoating. No fluff. Just truth, grace, and the roadmap back to God\'s heart for your life and your marriage.');
$footer_copyright = getSetting('footer_copyright', '© 2026 refreshing_dews. All rights reserved.');
$footer_contact_email = getSetting('contact_email_display', 'contact@refreshing_dews.com');
$footer_contact_address = getSetting('contact_address', 'Worldwide');
$footer_response_time = getSetting('contact_response_time', 'Always open');

$footer_social_links = [
    'facebook' => getSetting('facebook_url', '#'),
    'instagram' => getSetting('instagram_url', '#'),
    'twitter' => getSetting('twitter_url', '#'),
    'youtube' => getSetting('youtube_url', '#'),
    'spotify' => getSetting('spotify_url', '#'),
    'pinterest' => getSetting('pinterest_url', '#'),
];

$footer_recent_posts = getRecentPosts(3);

if (!defined('FOOTER_STYLES_LOADED')) {
    define('FOOTER_STYLES_LOADED', true);
    echo getFooterStyles();
}
?>
<footer class="footer">
    <div class="container">
        <div class="footer-top">
            <div>
                <span class="footer-kicker">Stay connected</span>
                <h3>Let truth, grace, and purpose find you.</h3>
            </div>
            <button type="button" class="footer-cta newsletter-trigger">Join the newsletter <i class="fas fa-arrow-right"></i></button>
        </div>

        <div class="footer-grid">
            <div class="footer-col">
                <div class="footer-logo">
                    <img src="<?php echo htmlspecialchars($footer_site_logo); ?>" alt="<?php echo htmlspecialchars($footer_site_title); ?> Logo">
                </div>
                <p class="footer-description"><?php echo htmlspecialchars($footer_description); ?></p>
                <div class="footer-social">
                    <?php foreach ($footer_social_links as $platform => $url): ?>
                        <?php if (!empty($url) && $url !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo ucfirst($platform); ?>">
                            <i class="fab fa-<?php echo $platform === 'facebook' ? 'facebook-f' : ($platform === 'pinterest' ? 'pinterest-p' : $platform); ?>"></i>
                        </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="footer-col">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                    <li><a href="blog.php"><i class="fas fa-chevron-right"></i> Blog</a></li>
                    <li><a href="audio.php"><i class="fas fa-chevron-right"></i> Audio</a></li>
                    <li><a href="about.php"><i class="fas fa-chevron-right"></i> About</a></li>
                    <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Latest Posts</h3>
                <ul class="footer-links">
                    <?php if (!empty($footer_recent_posts)): ?>
                        <?php foreach ($footer_recent_posts as $post): ?>
                        <li><a href="blog-post.php?slug=<?php echo urlencode($post['slug']); ?>"><i class="fas fa-chevron-right"></i> <?php echo htmlspecialchars(truncateText($post['title'], 30)); ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="blog.php"><i class="fas fa-chevron-right"></i> View all posts</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Contact</h3>
                <ul class="footer-links">
                    <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($footer_contact_email); ?></li>
                    <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($footer_contact_address); ?></li>
                    <li><i class="fas fa-clock"></i> <?php echo htmlspecialchars($footer_response_time); ?></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p><?php echo htmlspecialchars($footer_copyright); ?></p>
            <p class="footer-credit">Designed and Developed by <strong>DALE QUIST</strong> [Enable Technologies]</p>
        </div>
    </div>
</footer>

<div id="cookieConsentPopup" class="site-popup cookie-popup" role="dialog" aria-modal="true" aria-labelledby="cookieConsentTitle">
    <div class="site-popup-card">
        <h3 id="cookieConsentTitle">We use cookies</h3>
        <p>We use cookies to improve your experience, remember your choices, and help us keep the site working smoothly.</p>
        <div class="site-popup-actions">
            <button type="button" class="btn btn-secondary cookie-decline">Decline</button>
            <button type="button" class="btn btn-primary cookie-accept">Accept</button>
        </div>
    </div>
</div>

<div id="installAppPopup" class="site-popup install-popup" role="dialog" aria-modal="true" aria-labelledby="installAppTitle">
    <div class="site-popup-card">
        <h3 id="installAppTitle">Install this app</h3>
        <p id="installAppMessage">Get a faster, app-like experience on your device.</p>
        <div class="site-popup-actions install-actions">
            <button type="button" class="btn btn-secondary install-dismiss">Not now</button>
            <button type="button" class="btn btn-primary install-button">Install now</button>
        </div>
    </div>
</div>

<div id="newsletterPopup" class="site-popup newsletter-popup" role="dialog" aria-modal="true" aria-labelledby="newsletterPopupTitle">
    <div class="site-popup-card">
        <button type="button" class="newsletter-close" aria-label="Close newsletter popup">×</button>
        <h3 id="newsletterPopupTitle">Subscribe for updates</h3>
        <p>Get encouragement, new blog posts, and thoughtful updates right in your inbox.</p>
        <form id="newsletterPopupForm" class="newsletter-form" method="post" action="subscribe">
            <input type="email" name="email" placeholder="Enter your full email address" required>
            <button type="submit" class="btn btn-primary">Subscribe</button>
        </form>
    </div>
</div>
