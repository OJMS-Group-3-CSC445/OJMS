<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/smtp-config.php';

require_once __DIR__ . '/../includes/PHPMailer/Exception.php';
require_once __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Restrict access to ICT Admins
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'portal_admin'
) {
    http_response_code(403);
    exit('Access denied.');
}

$errors = [];
$success = get_flash('reviewer_success');

// Handle account creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $accountRole = $_POST['role'] ?? 'reviewer';
    $allowedRoles = ['reviewer', 'editor_in_chief', 'portal_admin'];

    if (!in_array($accountRole, $allowedRoles, true)) {
        $errors[] = 'Please select a valid account role.';
    }

    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }

    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'The password must be at least 8 characters.';
    }

    // Check for duplicate email
    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    // Create account
    if (empty($errors)) {

        try {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $verificationToken = bin2hex(random_bytes(32));
            $verificationExpires = date('Y-m-d H:i:s', time() + 86400);

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    email, password, role, first_name, middle_name, last_name, phone,
                    email_verified, verification_token, verification_expires,
                    created_at, updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $email,
                $hashedPassword,
                $accountRole,
                $firstName,
                $middleName !== '' ? $middleName : null,
                $lastName,
                $phone !== '' ? $phone : null,
                $verificationToken,
                $verificationExpires
            ]);

            $newUserId = (int) $pdo->lastInsertId();
            $verificationLink = 'https://ojms.vtenergies.com/auth/verify-email.php?token=' . urlencode($verificationToken);
            $fullName = trim($firstName . ' ' . ($middleName !== '' ? $middleName . ' ' : '') . $lastName);

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = SMTP_PORT;
                $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
                $mail->addAddress($email, $fullName);
                $mail->isHTML(true);
                $mail->Subject = 'Your OJMS Account Details and Email Verification';
                $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
                $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
                $safePassword = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
                $safeLink = htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8');
                $mail->Body = "
                    <h2>Your OJMS account has been created</h2>
                    <p>Hello {$safeName},</p>
                    <p>An account has been created for you on the Online Journal Management System.</p>
                    <p><strong>Login email:</strong> {$safeEmail}</p>
                    <p><strong>Temporary password:</strong> {$safePassword}</p>
                    <p>Please verify your email address before logging in by clicking the link below:</p>
                    <p><a href='{$safeLink}' style='display:inline-block;padding:12px 20px;background:#991b1b;color:white;text-decoration:none;border-radius:5px;'>Verify Email Address</a></p>
                    <p>This link expires in 24 hours. If you did not expect this account, contact the journal administrator.</p>
                ";
                $mail->AltBody = "OJMS account created. Email: {$email}\nTemporary password: {$password}\nVerify your account within 24 hours: {$verificationLink}";
                $mail->send();
            } catch (Exception $mailException) {
                // Remove the newly-created account if its verification email cannot be sent.
                $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND email_verified = 0');
                $deleteStmt->execute([$newUserId]);
                error_log('OJMS account email error: ' . $mail->ErrorInfo);
                error_log('OJMS SMTP exception: ' . $mailException->getMessage());
                $errors[] = 'The account could not be created because the email could not be sent. Please try again.';
            }

            if (empty($errors)) {
                set_flash('reviewer_success', ucfirst(str_replace('_', ' ', $accountRole)) . ' account created and verification email sent successfully.');
                redirect('reviewers.php');
            }

        } catch (PDOException $e) {

            error_log('Account creation error: ' . $e->getMessage());

            $errors[] = 'Unable to create the account. Please try again.';
        }
    }
}

// Fetch all accounts managed from this page
$stmt = $pdo->query("
    SELECT
        id,
        first_name,
        middle_name,
        last_name,
        email,
        role,
        phone,
        created_at
    FROM users
    WHERE role IN ('reviewer', 'editor_in_chief', 'portal_admin')
    ORDER BY created_at DESC, id DESC
");

$reviewers = $stmt->fetchAll();

// Admin identity
$adminName = trim(
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '')
);

if ($adminName === '') {
    $adminName = $_SESSION['email'] ?? 'Administrator';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Account Management | OJMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, sans-serif;
            background: #f8f7f5;
            color: #1e293b;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .admin-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .admin-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .admin-logo {
            width: 36px;
            height: 36px;
            background: #991b1b;
            color: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .admin-nav {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .admin-nav a,
        .admin-nav button {
            font-size: 14px;
            color: #475569;
        }

        .admin-nav a.active {
            color: #991b1b;
            font-weight: 600;
        }

        .admin-nav a:hover,
        .admin-nav button:hover {
            color: #991b1b;
        }

        .admin-nav form {
            margin: 0;
        }

        .admin-nav button {
            background: none;
            border: none;
            cursor: pointer;
            font: inherit;
        }

        .main {
            max-width: 1300px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .page-heading {
            margin-bottom: 30px;
        }

        .page-heading h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .page-heading p {
            color: #64748b;
            margin: 0;
        }

        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .card-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h2 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .card-header p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .card-body {
            padding: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: 2px solid #991b1b;
            outline-offset: 1px;
        }

        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
            color: #1e293b;
            background-color: #fff;
            padding-right: 48px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%2364758b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
        }

        .form-group select:hover {
            border-color: #94a3b8;
        }

        .form-group select:focus {
            border-color: #991b1b;
            box-shadow: 0 0 0 3px rgba(153, 27, 27, 0.12);
        }

        .form-group select option {
            padding: 12px;
            color: #1e293b;
            background: #fff;
        }

        .form-note {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
        }

        .primary-button {
            padding: 12px 20px;
            background: #991b1b;
            color: white;
            border: none;
            border-radius: 6px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .primary-button:hover {
            background: #7f1d1d;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
        }

        .alert.success {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert p {
            margin: 5px 0;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
        }

        td {
            color: #334155;
        }

        .reviewer-name {
            font-weight: 500;
        }

        .empty-state {
            padding: 50px 20px;
            text-align: center;
            color: #64748b;
        }

        .admin-footer {
            background: #1e293b;
            color: white;
            padding: 35px 30px;
            margin-top: 50px;
        }

        .footer-inner {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            gap: 30px;
            flex-wrap: wrap;
        }

        .footer-inner h2 {
            margin: 0 0 5px;
        }

        .footer-inner p {
            margin: 0;
            color: #cbd5e1;
            font-size: 14px;
        }

        .footer-copy {
            max-width: 1300px;
            margin: 30px auto 0;
            color: #cbd5e1;
            font-size: 12px;
        }

        @media(max-width: 700px) {
            .admin-header {
                padding: 18px;
            }

            .admin-nav {
                width: 100%;
                gap: 18px;
            }

            .main {
                padding: 28px 18px;
            }

            .card-header,
            .card-body {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

/* ================================
   ICT ADMIN NAVBAR
================================ */

.admin-header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px clamp(24px, 5vw, 80px);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.admin-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    flex-shrink: 0;
    text-decoration: none;
}

.admin-logo {
    background: #991b1b;
    color: white;
    height: 42px;
    width: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
}

.admin-brand-text {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
}

.admin-nav {
    display: flex;
    align-items: center;
    gap: 24px;
}

.admin-nav > a:not(.admin-profile-pill) {
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    text-decoration: none;
    transition: color 0.2s;
}

.admin-nav > a:not(.admin-profile-pill):hover,
.admin-nav > a:not(.admin-profile-pill).active {
    color: #991b1b;
}

.admin-profile-pill {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 9px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    max-width: 220px;
}

.admin-profile-pill span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-profile-pill svg {
    flex-shrink: 0;
}

.admin-logout-form {
    margin: 0;
}

.admin-logout-btn {
    border: none;
    background: #fef2f2;
    color: #991b1b;
    padding: 10px 16px;
    border-radius: 999px;
    cursor: pointer;
    font: 600 14px Inter, sans-serif;
    transition: background 0.2s;
}

.admin-logout-btn:hover {
    background: #fee2e2;
}

/* HAMBURGER */

.admin-menu-toggle {
    display: none;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 44px;
    height: 44px;
    padding: 11px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
}

.admin-menu-toggle span {
    display: block;
    width: 20px;
    height: 2px;
    background: #1e293b;
    border-radius: 2px;
}

/* FIXED NAVBAR SPACING */

body {
    padding-top: 78px;
}

/* RESPONSIVE NAVBAR */

@media (max-width: 900px) {

    .admin-header {
        padding: 14px 24px;
    }

    .admin-menu-toggle {
        display: flex;
        flex-shrink: 0;
    }

    .admin-nav {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 24px;
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
    }

    .admin-nav.is-open {
        display: flex;
    }

    .admin-nav > a {
        width: 100%;
    }

    .admin-profile-pill {
        max-width: 100%;
        width: fit-content;
    }

    .admin-logout-form {
        width: 100%;
    }

    .admin-logout-btn {
        width: 100%;
    }

    .main {
        padding: 28px 18px;
    }
}

@media (max-width: 480px) {

    .admin-header {
        padding: 12px 18px;
    }

    .admin-brand-text {
        font-size: 14px;
    }

    .admin-logo {
        width: 38px;
        height: 38px;
    }
}


/* SIMPLE FOOTER */

.admin-footer.admin-footer-simple {
    width: 100%;
    margin-top: 50px;
    padding: 24px clamp(24px, 5vw, 80px);
    background: #1e293b;
    color: #cbd5e1;
    border-top: none;
    text-align: center;
}

.admin-footer.admin-footer-simple p {
    margin: 0;
    font-size: 13px;
    color: #cbd5e1;
}
.admin-nav .admin-logout-form .admin-logout-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 18px;
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
    border-radius: 999px;
    font: 600 14px Inter, sans-serif;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.2s ease, border-color 0.2s ease;
}

.admin-nav .admin-logout-form .admin-logout-btn:hover {
    background: #fee2e2;
    border-color: #fca5a5;
}
/* ADMIN PAGE CONTENT ALIGNMENT */

 .admin-main,
.main {
    width: 100%;
    max-width: none;
    margin: 0;
    padding-left: clamp(24px, 5vw, 80px);
    padding-right: clamp(24px, 5vw, 80px);
}

@media (max-width: 900px) {
    .admin-main,
    .main {
        padding-left: 24px;
        padding-right: 24px;
    }
}

@media (max-width: 480px) {
    .admin-main,
    .main {
        padding-left: 18px;
        padding-right: 18px;
    }
}

    </style>
</head>

<body>


<header class="admin-header">

    <a href="dashboard.php" class="admin-brand">
        <span class="admin-logo">OJ</span>
        <span class="admin-brand-text">Admin Dashboard</span>
    </a>

    <button type="button"
        class="admin-menu-toggle"
        id="admin-menu-toggle"
        aria-label="Toggle navigation"
        aria-expanded="false"
        aria-controls="admin-nav">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <nav class="admin-nav" id="admin-nav" aria-label="Admin navigation">

        <a href="dashboard.php">Dashboard</a>
        <a class="active" href="reviewers.php">Manage Accounts</a>
        <a href="users.php">Users</a>

        <a href="settings.php" class="admin-profile-pill">
            <svg xmlns="http://www.w3.org/2000/svg"
                width="18" height="18" viewBox="0 0 24 24"
                fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"/>
                <path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/>
            </svg>
            <span><?= e($adminName) ?></span>
        </a>

        <form action="../auth/logout.php" method="post" class="admin-logout-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="admin-logout-btn">Log out</button>
        </form>

    </nav>

</header>


<main class="main">

    <section class="page-heading">
        <h1>Account management</h1>
        <p>Create reviewer, Editor-in-Chief, and ICT Admin accounts.</p>
    </section>

    <?php if ($success): ?>
        <div class="alert success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert error" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Add account form -->
    <section class="card">

        <div class="card-header">
            <h2>Create account</h2>
            <p>Set up a new account and assign its portal role.</p>
        </div>

        <div class="card-body">

            <form method="post">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>">

                <div class="form-group">
                    <label for="role">Account role</label>
                    <select id="role" name="role" required>
                        <option value="reviewer">Reviewer</option>
                        <option value="editor_in_chief">Editor-in-Chief</option>
                        <option value="portal_admin">ICT Admin</option>
                    </select>
                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label for="first_name">First name</label>
                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="middle_name">Middle name</label>
                        <input
                            type="text"
                            id="middle_name"
                            name="middle_name">
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last name</label>
                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone number</label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone">
                    </div>

                </div>

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required>
                </div>

                <div class="form-group">
                    <label for="password">Temporary password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        required>
                    <p class="form-note">
                        Minimum of 8 characters. The account holder can use this password to log in.
                    </p>
                </div>

                <button type="submit" class="primary-button">
                    Create account
                </button>

            </form>

        </div>

    </section>

    <!-- Account listing -->
    <section class="card">

        <div class="card-header">
            <h2>Registered accounts</h2>
            <p>
                <?= count($reviewers) ?>
                <?= count($reviewers) === 1 ? 'reviewer' : 'reviewers' ?>
                registered on the portal.
            </p>
        </div>

        <?php if (empty($reviewers)): ?>

            <div class="empty-state">
                <p>No accounts have been created yet.</p>
            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Date created</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($reviewers as $reviewer): ?>

                            <tr>

                                <td class="reviewer-name">
                                    <?= e(trim(
                                        ($reviewer['first_name'] ?? '') . ' ' .
                                        ($reviewer['middle_name'] ?? '') . ' ' .
                                        ($reviewer['last_name'] ?? '')
                                    )) ?>
                                </td>

                                <td>
                                    <?= e($reviewer['email']) ?>
                                </td>

                                <td>
                                    <?= e(ucwords(str_replace('_', ' ', $reviewer['role']))) ?>
                                </td>

                                <td>
                                    <?= e($reviewer['phone'] ?? '—') ?>
                                </td>

                                <td>
                                    <?= $reviewer['created_at']
                                        ? e(date('d M Y', strtotime($reviewer['created_at'])))
                                        : '—' ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer class="admin-footer admin-footer-simple">
    <p>&copy; <?= date('Y') ?> OJMS. All rights reserved.</p>
</footer>


<script>
const adminToggle = document.getElementById('admin-menu-toggle');
const adminNav = document.getElementById('admin-nav');

if (adminToggle && adminNav) {
    adminToggle.addEventListener('click', function() {
        const isOpen = adminNav.classList.toggle('is-open');
        adminToggle.setAttribute('aria-expanded', isOpen);
    });

    adminNav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function() {
            adminNav.classList.remove('is-open');
            adminToggle.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('click', function(event) {
        if (!adminNav.contains(event.target) &&
            !adminToggle.contains(event.target)) {
            adminNav.classList.remove('is-open');
            adminToggle.setAttribute('aria-expanded', 'false');
        }
    });
}
</script>


</body>
</html>