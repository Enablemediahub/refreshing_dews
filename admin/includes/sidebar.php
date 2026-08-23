<?php
/**
 * Shared admin sidebar. Requires $current_page (e.g. basename(__FILE__)).
 */
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
}

$nav = require __DIR__ . '/nav-config.php';
$active_file = $nav['active_map'][$current_page] ?? $current_page;

$site_logo = function_exists('getSetting')
    ? getSetting('site_logo', 'assets/logo/painlesslyf-logo.png')
    : 'assets/logo/painlesslyf-logo.png';
$site_title = function_exists('getSetting')
    ? getSetting('site_title', 'Painlesslyf')
    : 'Painlesslyf';

$is_active = function ($file) use ($active_file) {
    return $file === $active_file ? ' active' : '';
};
?>
        <div class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <img src="../<?php echo htmlspecialchars($site_logo); ?>" alt="Logo">
                <h3><?php echo htmlspecialchars($site_title); ?></h3>
                <p>Admin Panel</p>
            </div>

            <div class="sidebar-menu">
<?php foreach ($nav['sections'] as $section): ?>
                <div class="sidebar-menu-label"><?php echo htmlspecialchars($section['label']); ?></div>
<?php foreach ($section['items'] as $item): ?>
                <a href="<?php echo htmlspecialchars($item['file']); ?>" class="sidebar-menu-item<?php echo $is_active($item['file']); ?>">
                    <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                </a>
<?php endforeach; ?>
                <div class="sidebar-divider"></div>
<?php endforeach; ?>
<?php foreach ($nav['footer'] as $item): ?>
                <a href="<?php echo htmlspecialchars($item['file']); ?>" class="sidebar-menu-item"<?php if (!empty($item['confirm'])): ?> onclick="return confirm('<?php echo htmlspecialchars($item['confirm'], ENT_QUOTES); ?>')"<?php endif; ?>>
                    <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                </a>
<?php endforeach; ?>
            </div>
        </div>
