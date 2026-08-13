<?php
/**
 * Admin sidebar navigation — single source of truth.
 * Set $current_page to basename(__FILE__) before including sidebar.php.
 */

return [
    'active_map' => [
        'add-post.php'   => 'posts.php',
        'edit-post.php'  => 'posts.php',
        'add-audio.php'  => 'audio.php',
        'edit-audio.php' => 'audio.php',
    ],
    'sections' => [
        'main' => [
            'label' => 'MAIN',
            'items' => [
                ['file' => 'dashboard.php',   'label' => 'Dashboard',        'icon' => 'fa-tachometer-alt'],
                ['file' => 'posts.php',       'label' => 'Blog Posts',       'icon' => 'fa-pencil-alt'],
                ['file' => 'events.php',      'label' => 'Events',           'icon' => 'fa-calendar-alt'],
                ['file' => 'audio.php',       'label' => 'Audio Messages',   'icon' => 'fa-headphones'],
                ['file' => 'media.php',       'label' => 'Media Library',    'icon' => 'fa-images'],
                ['file' => 'contact.php',     'label' => 'Contact Messages', 'icon' => 'fa-envelope'],
                ['file' => 'subscribers.php', 'label' => 'Subscribers',      'icon' => 'fa-user-friends'],
            ],
        ],
        'management' => [
            'label' => 'MANAGEMENT',
            'items' => [
                ['file' => 'site-settings.php',   'label' => 'Site Settings',   'icon' => 'fa-cog'],
                ['file' => 'navbar-settings.php', 'label' => 'Navbar Settings', 'icon' => 'fa-bars'],
                ['file' => 'blog-settings.php',   'label' => 'Blog Settings',   'icon' => 'fa-blog'],
                ['file' => 'audio-settings.php',  'label' => 'Audio Settings',  'icon' => 'fa-headphones'],
                ['file' => 'about-settings.php',  'label' => 'About Settings',  'icon' => 'fa-user-circle'],
                ['file' => 'event-settings.php',  'label' => 'Event Settings',  'icon' => 'fa-calendar-check'],
                ['file' => 'users.php',           'label' => 'Users',             'icon' => 'fa-users-cog'],
                ['file' => 'comments.php',        'label' => 'Comments',          'icon' => 'fa-comments'],
            ],
        ],
    ],
    'footer' => [
        ['file' => 'logout.php', 'label' => 'Logout', 'icon' => 'fa-sign-out-alt', 'confirm' => 'Are you sure you want to logout?'],
    ],
];
