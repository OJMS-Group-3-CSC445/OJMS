<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['contact_csrf'])) {
    $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
}

function e(?string $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$isAuthenticated = !empty($_SESSION['user_id']);
$formStatus      = null; // 'success' | 'error'
$formMessage     = '';
$old             = [
    'name'    => '',
    'email'   => '',
    'subject' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $old['name']    = trim((string) ($_POST['name'] ?? ''));
    $old['email']   = trim((string) ($_POST['email'] ?? ''));
    $old['subject'] = trim((string) ($_POST['subject'] ?? ''));
    $old['message'] = trim((string) ($_POST['message'] ?? ''));

    if (!hash_equals($_SESSION['contact_csrf'], $token)) {
        $formStatus  = 'error';
        $formMessage = 'Invalid form submission. Please refresh the page and try again.';
    } elseif ($old['name'] === '' || $old['email'] === '' || $old['subject'] === '' || $old['message'] === '') {
        $formStatus  = 'error';
        $formMessage = 'Please fill in all required fields.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $formStatus  = 'error';
        $formMessage = 'Please enter a valid email address.';
    } else {
        // TODO: Connect PHPMailer / SMTP delivery here when ready
        $formStatus  = 'success';
        $formMessage = 'Thank you for reaching out. Your message has been received by the Editorial Office.';
        $old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
        $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | OJMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/styles.css">
    <link rel="stylesheet" href="./assets/css/contact.css">
</head>
<body data-page="contact-us" data-root="./" data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>">
    <div id="site-header"></div>

    <main class="contact-page">
        <!-- Compact Editorial Hero -->
        <section class="contact-hero" aria-labelledby="contact-hero-heading">
            <div class="container contact-hero-content">
                <p class="hero-eyebrow">GET IN TOUCH</p>
                <h1 id="contact-hero-heading">Contact the Editorial Office</h1>
                <p class="hero-lead">
                    Have a question about manuscript submissions, peer review, or journal policies?
                    Reach out using the details below or send us a message directly.
                </p>
            </div>
        </section>

        <!-- Two-Column Contact Section -->
        <section class="contact-section">
            <div class="container">
                <div class="contact-layout">
                    <!-- Left Column: Hardcoded Editorial Office Details -->
                    <aside class="contact-info-card" aria-labelledby="office-heading">
                        <p class="section-eyebrow">EDITORIAL OFFICE</p>
                        <h2 id="office-heading">Journal of Applied Scholarship</h2>
                        <p class="office-note">
                            For manuscript enquiries, reviewer support, and general correspondence.
                        </p>

                        <div class="contact-list">
                            <div class="contact-item">
                                <div class="contact-icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect width="20" height="16" x="2" y="4" rx="2"/>
                                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                                    </svg>
                                </div>
                                <div class="contact-body">
                                    <span class="contact-label">Email</span>
                                    <p class="contact-value">
                                        <a href="mailto:bennadeyemi@ojms.org">bennadeyemi@ojms.org</a>
                                    </p>
                                </div>
                            </div>

                            <div class="contact-item">
                                <div class="contact-icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    </svg>
                                </div>
                                <div class="contact-body">
                                    <span class="contact-label">Phone</span>
                                    <p class="contact-value">
                                        <a href="tel:+2348001234567">+234 (0) 800 123 4567</a>
                                    </p>
                                </div>
                            </div>

                            <div class="contact-item">
                                <div class="contact-icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                </div>
                                <div class="contact-body">
                                    <span class="contact-label">Postal Address</span>
                                    <address class="contact-address">
                                        P.M.B 2240, Abeokuta, Ogun State, Alabata<br>
                                        Road, Abeokuta, Ogun State, 110111, Nigeria
                                    </address>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- Right Column: Contact Form -->
                    <div class="contact-form-card" aria-labelledby="form-heading">
                        <h2 id="form-heading">Send a Message</h2>
                        <p class="form-intro">Fill out the form below and our editorial team will respond as soon as possible.</p>

                        <?php if ($formStatus !== null): ?>
                            <div class="form-alert form-alert-<?= e($formStatus) ?>" role="alert">
                                <?= e($formMessage) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="contact.php" class="contact-form" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['contact_csrf']) ?>">

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="contact-name">Full Name <span class="req">*</span></label>
                                    <input
                                        type="text"
                                        id="contact-name"
                                        name="name"
                                        value="<?= e($old['name']) ?>"
                                        placeholder="Dr. Jane Doe"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="contact-email">Email Address <span class="req">*</span></label>
                                    <input
                                        type="email"
                                        id="contact-email"
                                        name="email"
                                        value="<?= e($old['email']) ?>"
                                        placeholder="you@institution.edu"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="contact-subject">Subject <span class="req">*</span></label>
                                <input
                                    type="text"
                                    id="contact-subject"
                                    name="subject"
                                    value="<?= e($old['subject']) ?>"
                                    placeholder="Manuscript enquiry, reviewer support, etc."
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="contact-message">Message <span class="req">*</span></label>
                                <textarea
                                    id="contact-message"
                                    name="message"
                                    rows="5"
                                    placeholder="Write your message here…"
                                    required
                                ><?= e($old['message']) ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-submit">
                                Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div id="site-footer"></div>
    <script src="./assets/js/layout.js"></script>
</body>
</html>