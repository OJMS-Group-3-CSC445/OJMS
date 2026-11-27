<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'author') {
    redirect('../auth/login.php');
}

$userId = $_SESSION['user_id'];
$errors = [];
$success = get_flash('settings_success');

// Fetch current account information
$stmt = $pdo->prepare("
    SELECT id, first_name, middle_name, last_name, email, phone, password
    FROM users
    WHERE id = ? AND role = 'author'
    LIMIT 1
");

$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('../auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }

    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    }

    // Check whether another account uses this email
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            SELECT id FROM users
            WHERE email = ? AND id != ?
            LIMIT 1
        ");

        $stmt->execute([$email, $userId]);

        if ($stmt->fetch()) {
            $errors[] = 'This email address is already in use.';
        }
    }

    $changingPassword = (
        $newPassword !== '' ||
        $confirmPassword !== '' ||
        $currentPassword !== ''
    );

    if ($changingPassword) {

        if ($currentPassword === '') {
            $errors[] = 'Enter your current password to change your password.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Your current password is incorrect.';
        }

        if (strlen($newPassword) < 8) {
            $errors[] = 'Your new password must be at least 8 characters.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Your new passwords do not match.';
        }
    }

    if (empty($errors)) {

        try {

            if ($changingPassword) {

                $hashedPassword = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET first_name = ?,
                        middle_name = ?,
                        last_name = ?,
                        email = ?,
                        phone = ?,
                        password = ?
                    WHERE id = ? AND role = 'author'
                ");

                $stmt->execute([
                    $firstName,
                    $middleName,
                    $lastName,
                    $email,
                    $phone,
                    $hashedPassword,
                    $userId
                ]);

            } else {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET first_name = ?,
                        middle_name = ?,
                        last_name = ?,
                        email = ?,
                        phone = ?
                    WHERE id = ? AND role = 'author'
                ");

                $stmt->execute([
                    $firstName,
                    $middleName,
                    $lastName,
                    $email,
                    $phone,
                    $userId
                ]);
            }

            // Refresh session information
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            $_SESSION['email'] = $email;

            session_regenerate_id(true);

            set_flash('settings_success', 'Your account settings have been updated.');

            redirect('settings.php');

        } catch (PDOException $e) {

            error_log('Author settings error: ' . $e->getMessage());

            $errors[] = 'Unable to update your account. Please try again.';
        }
    }

    // Preserve submitted form values after validation errors
    $user['first_name'] = $firstName;
    $user['middle_name'] = $middleName;
    $user['last_name'] = $lastName;
    $user['email'] = $email;
    $user['phone'] = $phone;
}

$userName = trim(
    ($user['first_name'] ?? '') . ' ' .
    ($user['middle_name'] ?? '') . ' ' .
    ($user['last_name'] ?? '')
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#faf9f6">

    <title>Account Settings | OJMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/styles.css">
    <link rel="stylesheet" href="./dashboard.css">

    <style>
        .settings-container {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 130px clamp(24px, 5vw, 80px) 60px;
        }

        .settings-heading {
            margin-bottom: 30px;
        }

        .settings-card {
            background: #fff;
            border: 1px solid #dce3ec;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 24px;
        }

        .settings-card h2 {
            margin-top: 0;
            font-size: 20px;
        }

        .settings-description {
            color: #64748b;
            margin-bottom: 24px;
        }

        .settings-form-group {
            margin-bottom: 20px;
        }

        .settings-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .settings-form-group input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font: inherit;
        }

        .settings-form-group input:focus {
            outline: 2px solid #991b1b;
            outline-offset: 1px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .settings-submit {
            background: #991b1b;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 12px 22px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .settings-submit:hover {
            background: #7f1d1d;
        }

        .settings-alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 24px;
        }

        .settings-alert.success {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .settings-alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .settings-alert p {
            margin: 5px 0;
        }

        .settings-back {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
        }

        @media(max-width: 600px) {
            .settings-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .settings-card {
                padding: 20px;
            }

            .settings-container {
                margin: 30px auto;
                padding: 0 16px;
            }
        }
        /* =========================================
   SETTINGS PAGE LAYOUT
========================================= */

.settings-panels {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}

.settings-card {
    min-width: 0;
    height: 100%;
    margin-bottom: 0;
    background: #fff;
    border: 1px solid #dce3ec;
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
}

.settings-card h2 {
    font-size: 21px;
    font-weight: 600;
    margin-bottom: 10px;
}

.settings-description {
    font-size: 14px;
    line-height: 1.7;
    color: #64748b;
}

.settings-form-group input {
    min-height: 46px;
    background: #faf9f6;
    transition: border-color .2s ease, background .2s ease;
}

.settings-form-group input:focus {
    background: #fff;
}

.settings-submit {
    min-height: 46px;
    border-radius: 6px;
    transition: background .2s ease;
}

.settings-back {
    color: #64748b;
    font-size: 14px;
}

.settings-back:hover {
    color: #991b1b;
}

/* Responsive */

@media (max-width: 900px) {
    .settings-panels {
        grid-template-columns: 1fr;
        gap: 24px;
    }

    .settings-card {
        height: auto;
    }
}

@media (max-width: 760px) {
    .settings-container {
        padding: 110px 20px 48px;
        margin: 0 auto;
    }
}

@media (max-width: 600px) {
    .settings-container {
        padding: 110px 16px 40px;
    }

    .settings-panels {
        gap: 18px;
    }

    .settings-card {
        padding: 20px;
    }

    .settings-grid {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .settings-heading h1 {
        font-size: 28px;
    }
}
    </style>
</head>

<body class="author-dashboard">

<header class="author-header">
    <a class="author-identity" href="./dashboard.php" aria-label="Author dashboard">
        <span class="author-mark" aria-hidden="true">OJ</span>
        <span class="author-brand-text">Author Dashboard</span>
    </a>

    <button
        class="author-menu-toggle"
        type="button"
        aria-label="Toggle navigation"
        aria-expanded="false"
        aria-controls="author-navigation"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>

    <nav class="author-nav" id="author-navigation" aria-label="Author navigation">
        <a href="./dashboard.php">Dashboard</a>
        <a href="./submit-manuscript.php">Submit manuscript</a>

        <a class="author-nav-name is-current" href="./settings.php">
            <svg
                class="author-user-icon"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <circle cx="12" cy="8" r="4"></circle>
                <path d="M5 20a7 7 0 0 1 14 0"></path>
            </svg>
            <span><?= e($userName) ?></span>
        </a>

        <form action="../auth/logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="author-logout">Log out</button>
        </form>
    </nav>
</header>
<main class="settings-container">

    <a href="./dashboard.php" class="settings-back">
        &larr; Back to dashboard
    </a>

    <div class="settings-heading">
        <h1>Account settings</h1>
        <p>Manage your personal information and account security.</p>
    </div>

    <?php if ($success): ?>
        <div class="settings-alert success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="settings-alert error" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form method="post">

        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="settings-panels">

        <section class="settings-card">

            <h2>Personal information</h2>

            <p class="settings-description">
                Update the personal information associated with your author account.
            </p>

            <div class="settings-grid">

                <div class="settings-form-group">
                    <label for="first_name">First name</label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        value="<?= e($user['first_name']) ?>"
                        required>
                </div>

                <div class="settings-form-group">
                    <label for="middle_name">Middle name</label>
                    <input
                        type="text"
                        id="middle_name"
                        name="middle_name"
                        value="<?= e($user['middle_name'] ?? '') ?>">
                </div>

                <div class="settings-form-group">
                    <label for="last_name">Last name</label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        value="<?= e($user['last_name']) ?>"
                        required>
                </div>

                <div class="settings-form-group">
                    <label for="phone">Phone number</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="<?= e($user['phone'] ?? '') ?>"
                        required>
                </div>

            </div>

            <div class="settings-form-group">
                <label for="email">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($user['email']) ?>"
                    required>
            </div>

            <button class="settings-submit" type="submit">
                Save changes
            </button>

        </section>

        <section class="settings-card">

            <h2>Change password</h2>

            <p class="settings-description">
                Leave these fields blank if you do not want to change your password.
            </p>

            <div class="settings-form-group">
                <label for="current_password">Current password</label>
                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    autocomplete="current-password">
            </div>

            <div class="settings-form-group">
                <label for="new_password">New password</label>
                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    autocomplete="new-password">
            </div>

            <div class="settings-form-group">
                <label for="confirm_password">Confirm new password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    autocomplete="new-password">
            </div>

            <button class="settings-submit" type="submit">
                Update password
            </button>

        </section>
        </div>

    </form>

</main>
<footer class="author-footer author-footer-simple">
    <p>&copy; 2026 OJMS. All rights reserved.</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.querySelector('.author-menu-toggle');
    const navigation = document.querySelector('.author-nav');

    if (!menuToggle || !navigation) return;

    menuToggle.addEventListener('click', function () {
        const isOpen = navigation.classList.toggle('is-open');

        menuToggle.setAttribute('aria-expanded', String(isOpen));
        menuToggle.setAttribute(
            'aria-label',
            isOpen ? 'Close navigation' : 'Toggle navigation'
        );
    });

    navigation.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            navigation.classList.remove('is-open');
            menuToggle.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('click', function (event) {
        if (
            !navigation.contains(event.target) &&
            !menuToggle.contains(event.target)
        ) {
            navigation.classList.remove('is-open');
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    });
});
</script>
</body>
</html>