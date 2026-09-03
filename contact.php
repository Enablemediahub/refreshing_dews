<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db-connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    if (empty($first_name) || empty($email) || empty($message)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            $table_check = $conn->query("SHOW TABLES LIKE 'contact_messages'");
            if ($table_check->num_rows == 0) {
                $conn->query("CREATE TABLE IF NOT EXISTS `contact_messages` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `first_name` varchar(100) NOT NULL,
                    `last_name` varchar(100) DEFAULT NULL,
                    `email` varchar(255) NOT NULL,
                    `subject` varchar(255) DEFAULT NULL,
                    `message` text NOT NULL,
                    `status` enum('unread','read','replied') DEFAULT 'unread',
                    `admin_notes` text DEFAULT NULL,
                    `reply_sent` tinyint(1) DEFAULT 0,
                    `replied_at` timestamp NULL DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `status` (`status`),
                    KEY `email` (`email`),
                    KEY `created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
            }

            $sql = "INSERT INTO contact_messages (first_name, last_name, email, subject, message, status) VALUES (?, ?, ?, ?, ?, 'unread')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssss', $first_name, $last_name, $email, $subject, $message);

            if ($stmt->execute()) {
                $success_message = getSetting('contact_success_message', 'Thank you for your message! I\'ll get back to you soon.');

                if (getSetting('contact_send_admin_notification', '1') == '1') {
                    $admin_email = getSetting('admin_email', getSetting('contact_email_display', 'admin@' . $_SERVER['HTTP_HOST']));
                    $full_name = trim($first_name . ' ' . $last_name);
                    $admin_subject = 'New Contact Message: ' . ($subject ?: 'No Subject');
                    $admin_message = "
                    <!DOCTYPE html>
                    <html><head><meta charset='UTF-8'><title>New Contact Message</title></head>
                    <body>
                        <h2>New Contact Message Received</h2>
                        <p><strong>From:</strong> " . htmlspecialchars($full_name) . "</p>
                        <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                        <p><strong>Subject:</strong> " . htmlspecialchars($subject ?: 'No Subject') . "</p>
                        <p><strong>Message:</strong></p>
                        <div style='background:#f9f9f9;padding:15px;border-left:3px solid #2563eb;'>" . nl2br(htmlspecialchars($message)) . "</div>
                        <p><a href='" . SITE_URL . "/admin/contact.php'>View in Admin Panel</a></p>
                    </body></html>";
                    $admin_headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
                    $admin_headers .= 'From: ' . getSetting('site_title', 'refreshing_dews') . ' <' . getSetting('contact_email_display', 'noreply@' . $_SERVER['HTTP_HOST']) . ">\r\n";
                    @mail($admin_email, $admin_subject, $admin_message, $admin_headers);
                }
            } else {
                $error_message = 'Sorry, there was an error sending your message. Please try again.';
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = 'Sorry, there was an error sending your message. Please try again.';
            error_log('Contact form error: ' . $e->getMessage());
        }
    }
}

$site_title = getSetting('site_title', 'refreshing_dews');
$site_logo = getSetting('site_logo', 'assets/logo/refreshing_dews-logo.png');
$favicon = getSetting('favicon', 'assets/logo/refreshing_dews-logo.png');

$theme_blue = getSetting('theme_blue_color', '#2563eb');
$theme_blue_dark = getSetting('theme_blue_dark_color', '#1d4ed8');
$theme_green = getSetting('theme_green_color', '#4a7c59');
$theme_green_dark = getSetting('theme_green_dark_color', '#2c4a3b');

$contact_title = getSetting('contact_title', 'Get in Touch');
$contact_subtitle = getSetting('contact_subtitle', 'I\'d love to hear from you. Whether you have a question, feedback, or just want to say hello.');
$contact_address = getSetting('contact_address', 'Worldwide');
$contact_phone = getSetting('contact_phone', '');
$contact_email_display = getSetting('contact_email_display', 'contact@refreshing_dews.com');
$contact_response_time = getSetting('contact_response_time', 'Within 24-48 hours');
$contact_map_embed = getSetting('contact_map_embed', '');
$contact_faq_title = getSetting('contact_faq_title', 'Frequently Asked Questions');

$faq_items = [];
for ($i = 1; $i <= 4; $i++) {
    $question = getSetting("faq_question_$i", '');
    $answer = getSetting("faq_answer_$i", '');
    if (!empty($question) && !empty($answer)) {
        $faq_items[] = ['question' => $question, 'answer' => $answer];
    }
}

if (empty($faq_items)) {
    $faq_items = [
        ['question' => 'Can I collaborate with you?', 'answer' => 'Absolutely! I\'m always open to collaborations that align with the values of this space. Send me a message with your ideas.'],
        ['question' => 'Do you accept guest posts?', 'answer' => 'I occasionally accept guest posts. Please send a pitch with your topic ideas, and I\'ll get back to you.'],
        ['question' => 'How can I support your work?', 'answer' => 'The best way to support is by sharing content you love, subscribing to the newsletter, and engaging with the community.'],
        ['question' => 'Can I share your content?', 'answer' => 'Yes! I encourage sharing. Please give proper credit and link back to the original post when sharing.'],
    ];
}

$social_links = [
    'facebook' => getSetting('facebook_url', '#'),
    'instagram' => getSetting('instagram_url', '#'),
    'pinterest' => getSetting('pinterest_url', '#'),
];

function getContactHeaderStyle() {
    global $theme_blue, $theme_green;
    return "background: linear-gradient(135deg, {$theme_blue} 0%, {$theme_green} 100%);";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Contact - <?php echo htmlspecialchars($site_title); ?></title>
    <meta name="description" content="Get in touch with <?php echo htmlspecialchars($site_title); ?>">
    <meta property="og:title" content="Contact - <?php echo htmlspecialchars($site_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($contact_subtitle); ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/<?php echo htmlspecialchars($site_logo); ?>">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/contact.php">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($favicon); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($site_logo); ?>">
    <link rel="manifest" href="/refreshing_dews/manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <?php echo getPageHeroStyles(); ?>
    <style>
        :root {
            --theme-blue: <?php echo $theme_blue; ?>;
            --theme-blue-dark: <?php echo $theme_blue_dark; ?>;
            --theme-green: <?php echo $theme_green; ?>;
            --theme-green-dark: <?php echo $theme_green_dark; ?>;
            --dark: #1e293b;
            --body-text: #475569;
            --light: #f8fafc;
            --border: rgba(37, 99, 235, 0.12);
            --shadow-sm: 0 4px 20px rgba(37, 99, 235, 0.08);
            --shadow-md: 0 8px 30px rgba(37, 99, 235, 0.12);
            --shadow-lg: 0 20px 50px rgba(37, 99, 235, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; color: var(--body-text); background: var(--light); line-height: 1.6; overflow-x: hidden; }
        .container { width: 100%; max-width: 1280px; margin: 0 auto; padding: 0 24px; }

        .contact-header {
            color: #fff;
            <?php echo getContactHeaderStyle(); ?>
            text-align: center;
        }
        .contact-header h1 { animation: fadeInUp 1s ease; }
        .contact-header p { animation: fadeInUp 1s ease 0.2s both; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .contact-main { padding: 70px 0 80px; }

        .contact-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 40px;
            align-items: start;
        }
        @media (min-width: 992px) {
            .contact-layout { grid-template-columns: 1fr 1.1fr; gap: 50px; }
        }

        .contact-info-panel {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .intro-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 36px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.9);
        }
        .intro-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--theme-blue);
            margin-bottom: 12px;
        }
        .intro-card p { margin-bottom: 20px; }
        .response-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(74, 124, 89, 0.1) 100%);
            color: var(--theme-blue);
            padding: 12px 18px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
        }

        .info-cards { display: grid; gap: 16px; }
        .info-card {
            display: flex;
            align-items: center;
            gap: 18px;
            background: white;
            border-radius: 18px;
            padding: 22px 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        .info-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
        .info-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .info-card h3 { font-size: 16px; color: var(--dark); margin-bottom: 4px; }
        .info-card p, .info-card a { font-size: 14px; color: var(--body-text); text-decoration: none; }
        .info-card a:hover { color: var(--theme-blue); }

        .social-row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
        .social-row a {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: white;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--theme-blue);
            text-decoration: none;
            transition: var(--transition);
        }
        .social-row a:hover {
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white;
            border-color: transparent;
            transform: translateY(-3px);
        }

        .form-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 28px;
            padding: 40px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.9);
        }
        .form-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--theme-blue);
            margin-bottom: 8px;
        }
        .form-card > p { margin-bottom: 28px; }

        .alert {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 576px) { .form-row { grid-template-columns: 1fr; } }

        .form-group { margin-bottom: 18px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: var(--dark); }
        .required::after { content: '*'; color: #dc2626; margin-left: 4px; }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border);
            border-radius: 14px;
            font-size: 15px;
            font-family: inherit;
            transition: var(--transition);
            background: #fff;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--theme-blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        textarea.form-control { min-height: 140px; resize: vertical; }

        .btn-submit {
            width: 100%;
            padding: 16px 28px;
            border: none;
            border-radius: 50px;
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.25);
        }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }

        .form-note { text-align: center; font-size: 12px; color: #94a3b8; margin-top: 16px; }

        .map-section { padding: 0 0 70px; }
        .map-container {
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            height: 420px;
            border: 1px solid var(--border);
        }
        .map-container iframe { width: 100%; height: 100%; border: 0; }

        .faq-section {
            padding: 70px 0 90px;
            background: white;
        }
        .section-title {
            text-align: center;
            font-family: 'Playfair Display', serif;
            font-size: clamp(28px, 4vw, 38px);
            color: var(--theme-blue);
            margin-bottom: 40px;
        }
        .faq-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            max-width: 900px;
            margin: 0 auto;
        }
        @media (min-width: 768px) { .faq-grid { grid-template-columns: 1fr 1fr; } }

        .faq-item {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px 24px;
            cursor: pointer;
            transition: var(--transition);
        }
        .faq-item:hover, .faq-item.active {
            border-color: rgba(37, 99, 235, 0.25);
            box-shadow: var(--shadow-sm);
        }
        .faq-question {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            font-weight: 600;
            color: var(--dark);
        }
        .faq-question i { color: var(--theme-green); transition: var(--transition); }
        .faq-item.active .faq-question i { transform: rotate(90deg); }
        .faq-answer {
            display: none;
            margin-top: 14px;
            font-size: 14px;
            line-height: 1.7;
        }
        .faq-item.active .faq-answer { display: block; }

        .floating-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
            animation: slideInRight 0.5s ease;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .form-card { padding: 28px 22px; }
            .intro-card { padding: 28px 22px; }
            .map-container { height: 300px; }
        }

        <?php echo getSetting('custom_css', ''); ?>
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <?php if (isset($_SESSION['subscribe_success'])): ?>
    <div class="alert alert-success floating-notification">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_SESSION['subscribe_success']); ?>
        <button type="button" onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;font-size:20px;cursor:pointer;color:inherit;">&times;</button>
        <?php unset($_SESSION['subscribe_success']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['subscribe_error'])): ?>
    <div class="alert alert-error floating-notification">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($_SESSION['subscribe_error']); ?>
        <button type="button" onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;font-size:20px;cursor:pointer;color:inherit;">&times;</button>
        <?php unset($_SESSION['subscribe_error']); ?>
    </div>
    <?php endif; ?>

    <header class="contact-header">
        <div class="container">
            <h1 data-aos="fade-up"><?php echo htmlspecialchars($contact_title); ?></h1>
            <p data-aos="fade-up" data-aos-delay="100"><?php echo htmlspecialchars($contact_subtitle); ?></p>
        </div>
    </header>

    <section class="contact-main">
        <div class="container">
            <div class="contact-layout">
                <div class="contact-info-panel" data-aos="fade-right">
                    <div class="intro-card">
                        <h2>Let's Connect</h2>
                        <p>Whether you have feedback, a collaboration idea, or a simple hello — your message matters. I read every note and do my best to respond thoughtfully.</p>
                        <div class="response-badge">
                            <i class="fas fa-clock"></i>
                            <?php echo htmlspecialchars($contact_response_time); ?>
                        </div>
                    </div>

                    <div class="info-cards">
                        <div class="info-card">
                            <div class="info-icon"><i class="fas fa-envelope"></i></div>
                            <div>
                                <h3>Email</h3>
                                <p><a href="mailto:<?php echo htmlspecialchars($contact_email_display); ?>"><?php echo htmlspecialchars($contact_email_display); ?></a></p>
                            </div>
                        </div>

                        <?php if (!empty($contact_phone)): ?>
                        <div class="info-card">
                            <div class="info-icon"><i class="fas fa-phone"></i></div>
                            <div>
                                <h3>Phone</h3>
                                <p><a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $contact_phone)); ?>"><?php echo htmlspecialchars($contact_phone); ?></a></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($contact_address)): ?>
                        <div class="info-card">
                            <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div>
                                <h3>Location</h3>
                                <p><?php echo htmlspecialchars($contact_address); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="social-row">
                        <?php if (!empty($social_links['facebook']) && $social_links['facebook'] !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($social_links['facebook']); ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($social_links['instagram']) && $social_links['instagram'] !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($social_links['instagram']); ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($social_links['pinterest']) && $social_links['pinterest'] !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($social_links['pinterest']); ?>" target="_blank" rel="noopener" aria-label="Pinterest"><i class="fab fa-pinterest-p"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-card" data-aos="fade-left">
                    <h2>Send a Message</h2>
                    <p>Fill out the form below and I'll get back to you as soon as I can.</p>

                    <?php if ($success_message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label required" for="first_name">First Name</label>
                                <input type="text" id="first_name" class="form-control" name="first_name" placeholder="John" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="last_name">Last Name</label>
                                <input type="text" id="last_name" class="form-control" name="last_name" placeholder="Doe" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required" for="email">Email Address</label>
                            <input type="email" id="email" class="form-control" name="email" placeholder="john@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="subject">Subject</label>
                            <input type="text" id="subject" class="form-control" name="subject" placeholder="What's this about?" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label required" for="message">Message</label>
                            <textarea id="message" class="form-control" name="message" placeholder="Your message here..." required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>

                        <button type="submit" name="contact_submit" class="btn-submit">
                            Send Message <i class="fas fa-paper-plane"></i>
                        </button>
                        <p class="form-note"><i class="fas fa-shield-alt"></i> Your information is safe and will never be shared.</p>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($contact_map_embed)): ?>
    <section class="map-section">
        <div class="container">
            <div class="map-container" data-aos="zoom-in"><?php echo $contact_map_embed; ?></div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($faq_items)): ?>
    <section class="faq-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up"><?php echo htmlspecialchars($contact_faq_title); ?></h2>
            <div class="faq-grid">
                <?php foreach ($faq_items as $index => $faq): ?>
                <div class="faq-item" data-aos="fade-up" data-aos-delay="<?php echo 80 * ($index + 1); ?>">
                    <div class="faq-question">
                        <?php echo htmlspecialchars($faq['question']); ?>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    <div class="faq-answer"><?php echo htmlspecialchars($faq['answer']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/main.js?v=20260904"></script>
    <script>
        AOS.init({ once: true, duration: 800, easing: 'ease-in-out' });

        document.querySelectorAll('.faq-item').forEach(function(item) {
            item.addEventListener('click', function() {
                this.classList.toggle('active');
            });
        });

        setTimeout(function() {
            document.querySelectorAll('.floating-notification').forEach(function(notification) {
                notification.style.transition = 'opacity 0.5s';
                notification.style.opacity = '0';
                setTimeout(function() {
                    if (notification.parentNode) notification.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
