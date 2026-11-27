<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reviewer') {
    redirect('../login.php');
}

$userId = (int) $_SESSION['user_id'];
$success = '';
$error = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('../login.php');
}

$reviewerName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($reviewerName === '') {
    $reviewerName = $user['email'] ?? 'Reviewer';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '') {
            $error = 'First name, last name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, middle_name = ?, phone = ?, email = ? WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $middleName, $phone, $email, $userId]);
                $success = 'Profile updated successfully.';
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $reviewerName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['email'] ?? 'Reviewer');
                $_SESSION['first_name'] = $user['first_name'] ?? '';
                $_SESSION['last_name'] = $user['last_name'] ?? '';
                $_SESSION['email'] = $user['email'] ?? '';
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    $error = 'This email address is already in use.';
                } else {
                    error_log('Reviewer profile update error: ' . $e->getMessage());
                    $error = 'Unable to update profile. Please try again.';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $error = 'Please fill in all password fields.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Your new password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                $success = 'Password changed successfully.';
            } catch (PDOException $e) {
                error_log('Reviewer password update error: ' . $e->getMessage());
                $error = 'Unable to change password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | OJMS Reviewer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, sans-serif; background: #f8f7f5; color: #1e293b; }
        a { text-decoration: none; color: inherit; }
        .header { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; gap: 24px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 600; }
        .logo { width: 36px; height: 36px; background: #991b1b; color: white; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .nav { display: flex; align-items: center; gap: 25px; flex-wrap: wrap; }
        .nav a, .nav button { font-size: 14px; color: #475569; }
        .nav a.active { color: #991b1b; font-weight: 600; }
        .nav a:hover, .nav button:hover { color: #991b1b; }
        .nav form { margin: 0; }
        .nav button { background: none; border: none; cursor: pointer; font: inherit; }
        .main { max-width: 1300px; margin: 0 auto; padding: 40px 30px; }
        .welcome { margin-bottom: 35px; }
        .welcome h1 { font-size: 28px; margin: 0 0 10px; }
        .welcome p { color: #64748b; margin: 0; }
        .section-card { background: white; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 25px; }
        .section-header { padding: 24px; border-bottom: 1px solid #e2e8f0; }
        .section-header h2 { margin: 0 0 8px; font-size: 20px; }
        .section-header p { margin: 0; color: #64748b; font-size: 14px; }
        .section-body { padding: 24px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #334155; }
        .form-group input { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; background: #fff; color: #1e293b; }
        .form-group input:focus { outline: none; border-color: #991b1b; box-shadow: 0 0 0 3px rgba(153,27,27,.08); }
        .btn { background: #991b1b; color: #fff; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 600; }
        .btn:hover { background: #7f1d1d; }
        .alert { padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .account-info { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #e2e8f0; gap: 20px; }
        .account-info:last-child { border-bottom: none; }
        .account-info span:first-child { color: #64748b; font-size: 14px; }
        .account-info span:last-child { font-weight: 600; font-size: 14px; text-align: right; }
        .footer { background: #1e293b; color: white; padding: 35px 30px; margin-top: 50px; }
        .footer-inner { max-width: 1300px; margin: 0 auto; display: flex; justify-content: space-between; gap: 30px; flex-wrap: wrap; }
        .footer-inner p { color: #cbd5e1; font-size: 14px; }
        .footer-copy { max-width: 1300px; margin: 30px auto 0; color: #cbd5e1; font-size: 12px; }
        @media(max-width: 800px) { .form-grid { grid-template-columns: 1fr; gap: 0; } }
        @media(max-width: 700px) { .header { padding: 18px; } .nav { width: 100%; gap: 18px; } .main { padding: 28px 18px; } .section-body { padding: 20px; } }
        /* ========================================
   REVIEWER SETTINGS PAGE REDESIGN
======================================== */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
}

body {
    font-family: Inter, sans-serif;
    background: #f8f7f5;
    color: #1e293b;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    overflow-x: clip;
}

a,
a:hover,
a:focus,
a:active {
    text-decoration: none !important;
}

/* NAVBAR */

.navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    padding: 20px clamp(24px, 5vw, 80px);
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(12px);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: nowrap;
    border-bottom: 1px solid #e2e8f0;
    gap: 24px;
}

/* BRAND */

.reviewer-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.reviewer-logo {
    width: 40px;
    height: 40px;
    background: #991b1b;
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}

.reviewer-brand-text {
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
}

/* NAV LINKS */

.nav-links {
    display: flex;
    align-items: center;
    gap: 25px;
    flex-wrap: nowrap;
}

.nav-links a {
    font-size: 14px;
    color: #475569;
    transition: color 0.2s ease;
}

.nav-links a:hover {
    color: #991b1b;
}

.logout-form {
    margin: 0;
}

.logout-form input[type="hidden"] {
    display: none;
}

/* PROFILE PILL */

.reviewer-profile-pill {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 9px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155 !important;
    font-size: 13px !important;
    font-weight: 500;
    transition: background 0.2s ease;
}

.reviewer-profile-pill:hover {
    background: #f1f5f9;
}

.reviewer-profile-icon {
    flex-shrink: 0;
}

/* LOGOUT PILL */

.logout-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #991b1b !important;
    background: #991b1b !important;
    color: white !important;
    padding: 10px 20px;
    border-radius: 999px;
    font-size: 13px !important;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.logout-btn:hover {
    background: #7f1d1d !important;
    color: white !important;
}

/* HAMBURGER */

.reviewer-menu-toggle {
    display: none;
    width: 42px;
    height: 42px;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: white;
    cursor: pointer;
}

.reviewer-menu-toggle span {
    display: block;
    width: 20px;
    height: 2px;
    background: #1e293b;
    border-radius: 2px;
    margin: 4px auto;
}

/* MAIN CONTENT */

.main {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 130px clamp(24px, 5vw, 80px) 60px;
    flex: 1;
}

/* PAGE HEADER */

.welcome {
    margin-bottom: 35px;
}

.welcome h1 {
    font-size: clamp(25px, 3vw, 32px);
    font-weight: 700;
    letter-spacing: -0.7px;
    color: #1e293b;
    margin: 0 0 10px;
}

.welcome p {
    font-size: 15px;
    line-height: 1.7;
    color: #64748b;
    margin: 0;
}

/* SETTINGS CARDS */

.section-card {
    background: white;
    border: 1px solid #e8e8e8;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.035);
}

.section-header {
    padding: 28px 30px;
    border-bottom: 1px solid #e2e8f0;
}

.section-header h2 {
    margin: 0 0 8px;
    font-size: 20px;
    font-weight: 650;
    color: #1e293b;
}

.section-header p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
    line-height: 1.7;
}

.section-body {
    padding: 30px;
}

/* FORMS */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.form-group {
    margin-bottom: 22px;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #334155;
}

.form-group input {
    width: 100%;
    padding: 14px 15px;
    border: 1px solid #dbe1e8;
    border-radius: 10px;
    font-family: Inter, sans-serif;
    font-size: 14px;
    background: #fff;
    color: #1e293b;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-group input:focus {
    outline: none;
    border-color: #991b1b;
    box-shadow: 0 0 0 3px rgba(153, 27, 27, 0.08);
}

/* BUTTONS */

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #991b1b;
    color: #fff;
    border: none;
    padding: 13px 22px;
    border-radius: 999px;
    cursor: pointer;
    font-family: Inter, sans-serif;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn:hover {
    background: #7f1d1d;
    transform: translateY(-1px);
}

/* ALERTS */

.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 22px;
    font-size: 14px;
    line-height: 1.7;
}

.alert-success {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* ACCOUNT INFORMATION */

.account-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 0;
    border-bottom: 1px solid #e2e8f0;
    gap: 20px;
}

.account-info:first-child {
    padding-top: 0;
}

.account-info:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.account-info span:first-child {
    color: #64748b;
    font-size: 14px;
}

.account-info span:last-child {
    font-weight: 600;
    font-size: 14px;
    color: #334155;
    text-align: right;
    overflow-wrap: anywhere;
}

/* FOOTER */

.footer.footer-simple {
    width: 100%;
    margin-top: auto;
    margin-bottom: 0;
    padding: 22px 24px;
    background: #1e293b;
    color: #cbd5e1;
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
    flex-shrink: 0;
}

.footer-simple p {
    margin: 0;
    color: #cbd5e1;
    font-size: 13px;
}

/* TABLET */

@media (max-width: 900px) {
    .form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }
}

/* MOBILE NAV */

@media (max-width: 768px) {
    .navbar {
        padding: 16px 24px;
    }

    .reviewer-menu-toggle {
        display: block;
    }

    .nav-links {
        position: absolute;
        top: calc(100% + 1px);
        left: 0;
        right: 0;
        width: 100%;
        padding: 20px 24px;
        background: white;
        border-bottom: 1px solid #e2e8f0;
        box-shadow: 0 12px 25px rgba(15, 23, 42, 0.06);
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 18px;
        visibility: hidden;
        opacity: 0;
        pointer-events: none;
        transform: translateY(-8px);
        transition: opacity 0.2s ease,
                    transform 0.2s ease,
                    visibility 0.2s ease;
    }

    .nav-links.is-open {
        visibility: visible;
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
    }

    .nav-links > a:not(.reviewer-profile-pill) {
        padding: 5px 0;
    }

    .nav-links .reviewer-profile-pill {
        width: fit-content;
    }

    .nav-links form {
        width: 100%;
    }

    .logout-btn {
        width: fit-content;
    }

    .main {
        padding: 115px 24px 45px;
    }

    .section-header {
        padding: 24px;
    }

    .section-body {
        padding: 25px;
    }
}

/* SMALL MOBILE */

@media (max-width: 600px) {
    .form-grid {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .main {
        padding-left: 18px;
        padding-right: 18px;
    }

    .welcome h1 {
        font-size: 25px;
    }

    .section-card {
        border-radius: 14px;
    }

    .section-header {
        padding: 22px;
    }

    .section-body {
        padding: 22px;
    }

    .account-info {
        align-items: flex-start;
        flex-direction: column;
        gap: 8px;
    }

    .account-info span:last-child {
        text-align: left;
    }
}

/* VERY SMALL MOBILE */

@media (max-width: 400px) {
    .navbar {
        padding: 15px 18px;
    }

    .reviewer-brand {
        gap: 9px;
    }

    .reviewer-brand-text {
        font-size: 13px;
    }

    .reviewer-logo {
        width: 36px;
        height: 36px;
    }

    .reviewer-menu-toggle {
        width: 40px;
        height: 40px;
    }

    .btn {
        width: 100%;
    }
}
    </style>
</head>
<body>
<header class="navbar">
<a href="dashboard.php" class="reviewer-brand" aria-label="Reviewer dashboard">
    <span class="reviewer-logo">OJ</span>
    <span class="reviewer-brand-text">Reviewer Dashboard</span>
</a>

<button
    type="button"
    class="reviewer-menu-toggle"
    id="reviewer-menu-toggle"
    aria-label="Toggle navigation"
    aria-expanded="false"
    aria-controls="reviewer-nav">
    <span></span>
    <span></span>
    <span></span>
</button>

<div class="nav-links" id="reviewer-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="assignments.php">Assigned manuscripts</a>

    <a href="settings.php" class="reviewer-profile-pill">
        <svg class="reviewer-profile-icon"
             xmlns="http://www.w3.org/2000/svg"
             width="18" height="18" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"></circle>
            <path d="M5 21a7 7 0 0 1 14 0"></path>
        </svg>
        <span><?= e($reviewerName) ?></span>
    </a>

    <form action="../auth/logout.php" method="post" class="logout-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="logout-btn">Log out</button>
    </form>
</div>
</header>
<main class="main">
    <section class="welcome">
        <h1>Settings</h1>
        <p>Manage your reviewer account and personal information.</p>
    </section>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <section class="section-card">
        <div class="section-header"><h2>Profile Information</h2><p>Update your personal information and contact details.</p></div>
        <div class="section-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-grid">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" value="<?= e($user['first_name'] ?? '') ?>" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" value="<?= e($user['last_name'] ?? '') ?>" required></div>
                    <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" value="<?= e($user['middle_name'] ?? '') ?>"></div>
                    <div class="form-group"><label>Phone Number</label><input type="text" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
                    <div class="form-group"><label>Email Address *</label><input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required></div>
                </div>
                <button type="submit" name="update_profile" class="btn">Save Changes</button>
            </form>
        </div>
    </section>

    <section class="section-card">
        <div class="section-header"><h2>Change Password</h2><p>Choose a strong password to keep your account secure.</p></div>
        <div class="section-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required autocomplete="current-password"></div>
                <div class="form-grid">
                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>
                    <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
                </div>
                <button type="submit" name="change_password" class="btn">Change Password</button>
            </form>
        </div>
    </section>

    <section class="section-card">
        <div class="section-header"><h2>Account Information</h2><p>Information about your reviewer account.</p></div>
        <div class="section-body">
            <div class="account-info"><span>Account Role</span><span>Reviewer</span></div>
            <div class="account-info"><span>Account Email</span><span><?= e($user['email']) ?></span></div>
            <div class="account-info"><span>Account Created</span><span><?= !empty($user['created_at']) ? e(date('F j, Y', strtotime($user['created_at']))) : 'Not available' ?></span></div>
        </div>
    </section>
</main>
<footer class="footer footer-simple">
<p>&copy; <?= date('Y') ?> OJMS. All rights reserved.</p>
</footer>
<script>
    const reviewerToggle = document.getElementById('reviewer-menu-toggle');
    const reviewerNav = document.getElementById('reviewer-nav');

    if (reviewerToggle && reviewerNav) {
        reviewerToggle.addEventListener('click', function () {
            const isOpen = reviewerNav.classList.toggle('is-open');
            reviewerToggle.setAttribute('aria-expanded', isOpen);
        });

        reviewerNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function () {
                reviewerNav.classList.remove('is-open');
                reviewerToggle.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('click', function (event) {
            if (
                !reviewerNav.contains(event.target) &&
                !reviewerToggle.contains(event.target)
            ) {
                reviewerNav.classList.remove('is-open');
                reviewerToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
</script>
</body>
</html>
