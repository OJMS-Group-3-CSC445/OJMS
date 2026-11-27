<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'editor_in_chief') {
    http_response_code(403);
    exit('Access denied.');
}

$userId = (int)$_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

// Ensure CSRF token exists
$token = csrf_token();

// Fetch current user
try {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, password
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        exit('User not found.');
    }
} catch (PDOException $e) {
    error_log('EIC settings user fetch error: ' . $e->getMessage());
    exit('Unable to load settings.');
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Invalid security token. Please refresh and try again.';
    } else {

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (
            $currentPassword === '' ||
            $newPassword === '' ||
            $confirmPassword === ''
        ) {
            $errorMessage = 'Please fill in all password fields.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errorMessage = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $errorMessage = 'Your new password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'New passwords do not match.';
        } elseif ($currentPassword === $newPassword) {
            $errorMessage = 'Your new password must be different from your current password.';
        } else {

            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET password = ?
                    WHERE id = ?
                ");

                $stmt->execute([$hashedPassword, $userId]);

                $successMessage = 'Password changed successfully.';

            } catch (PDOException $e) {
                error_log('EIC password update error: ' . $e->getMessage());
                $errorMessage = 'Unable to change password. Please try again.';
            }
        }
    }
}

$eicName = trim(
    ($user['first_name'] ?? '') . ' ' .
    ($user['last_name'] ?? '')
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Settings | OJMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #f8f7f5;
            color: #1e293b;
        }

        .navbar {
            background: #fff;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
            flex-wrap: wrap;
            gap: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #1e293b;
            font-size: 17px;
            font-weight: 700;
        }

        .logo {
            width: 42px;
            height: 42px;
            background: #991b1b;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
        }

        .nav-links {
            display: flex;
            gap: 22px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #991b1b;
        }

        .nav-links a.active {
            font-weight: 600;
        }

        .logout-form {
            margin: 0;
        }

        .logout-btn {
            border: none;
            background: #fef2f2;
            color: #991b1b;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
        }

        .logout-btn:hover {
            background: #fee2e2;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 40px auto;
            min-height: 60vh;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 30px;
            margin: 0 0 12px;
            font-weight: 700;
        }

        .page-header p {
            color: #64748b;
            margin: 0;
            line-height: 1.7;
            font-size: 15px;
        }

        .notice {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .notice-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .notice-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .settings-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.02);
        }

        .card-header {
            margin-bottom: 25px;
        }

        .card-header h2 {
            font-size: 20px;
            margin: 0 0 10px;
        }

        .card-header p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
            margin: 0;
        }

        .profile-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .detail-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 18px;
        }

        .detail-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .detail-value {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            word-break: break-word;
        }

        .password-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: #475569;
        }

        .form-group input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            background: #fff;
            color: #1e293b;
            outline: none;
        }

        .form-group input:focus {
            border-color: #991b1b;
            box-shadow: 0 0 0 3px rgba(153, 27, 27, 0.08);
        }

        .form-group small {
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
        }

        .submit-btn {
            background: #991b1b;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 13px 20px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            align-self: flex-start;
        }

        .submit-btn:hover {
            background: #7f1d1d;
        }

        .footer {
            background: #1e293b;
            color: white;
            padding: 35px 5%;
            margin-top: 60px;
        }

        .footer-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .footer-inner h2 {
            margin: 0 0 8px;
            font-size: 22px;
        }

        .footer-inner p {
            margin: 0;
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.6;
        }

        .footer-copy {
            max-width: 1200px;
            margin: 25px auto 0;
            padding-top: 20px;
            border-top: 1px solid #334155;
            color: #94a3b8;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 18px 5%;
                flex-direction: column;
                align-items: flex-start;
            }

            .nav-links {
                gap: 15px;
                width: 100%;
            }

            .container {
                width: 92%;
                margin: 30px auto;
            }

            .settings-card {
                padding: 20px;
            }

            .profile-details {
                grid-template-columns: 1fr;
            }

            .submit-btn {
                width: 100%;
            }

            .footer-inner {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .nav-links {
                gap: 12px;
            }

            .nav-links a {
                font-size: 13px;
            }

            .brand {
                font-size: 15px;
            }

            .page-header h1 {
                font-size: 23px;
            }
        }
        /* EDITOR-IN-CHIEF NAVBAR */

.navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: #fff;
    padding: 16px clamp(24px, 5vw, 80px);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e5e7eb;
    gap: 20px;
}

.editor-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    flex-shrink: 0;
}

.editor-logo {
    width: 42px;
    height: 42px;
    background: #991b1b;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
}

.editor-brand-text {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
}

.editor-nav {
    display: flex;
    align-items: center;
    gap: 24px;
}

.editor-nav > a:not(.editor-profile-pill) {
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    text-decoration: none;
    transition: color 0.2s;
}

.editor-nav > a:not(.editor-profile-pill):hover,
.editor-nav > a:not(.editor-profile-pill).active {
    color: #991b1b;
}

.editor-profile-pill {
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

.editor-profile-pill span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.editor-profile-pill svg {
    flex-shrink: 0;
}

.editor-profile-pill.active {
    border-color: #e2e8f0;
    background: #f8fafc;
    color: #334155;
}

.editor-logout-form {
    margin: 0;
}

.editor-logout-btn {
    border: none;
    background: #fef2f2;
    color: #991b1b;
    padding: 10px 16px;
    border-radius: 999px;
    cursor: pointer;
    font: 600 14px Inter, sans-serif;
    transition: background 0.2s;
}

.editor-logout-btn:hover {
    background: #fee2e2;
}

.editor-menu-toggle {
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

.editor-menu-toggle span {
    display: block;
    width: 20px;
    height: 2px;
    background: #1e293b;
    border-radius: 2px;
}

/* ACCOUNT FOR FIXED NAVBAR */

body {
    padding-top: 78px;
}

/* RESPONSIVE NAVBAR */

@media (max-width: 900px) {
    .navbar {
        padding: 14px 24px;
    }

    .editor-menu-toggle {
        display: flex;
        flex-shrink: 0;
    }

    .editor-nav {
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

    .editor-nav.is-open {
        display: flex;
    }

    .editor-nav > a {
        width: 100%;
    }

    .editor-profile-pill {
        max-width: 100%;
        width: fit-content;
    }

    .editor-logout-form {
        width: 100%;
    }

    .editor-logout-btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .navbar {
        padding: 12px 18px;
    }

    .editor-brand-text {
        font-size: 14px;
    }

    .editor-logo {
        width: 38px;
        height: 38px;
    }
}
/* SIMPLE FOOTER */

.footer.footer-simple {
    width: 100%;
    margin-top: auto;
    padding: 24px clamp(24px, 5vw, 80px);
    background: #1e293b;
    color: #cbd5e1;
    border-top: none;
    text-align: center;
}

.footer.footer-simple p {
    margin: 0;
    font-size: 13px;
    color: #cbd5e1;
}
    </style>
</head>

<body>

<nav class="navbar">
    <a href="dashboard.php" class="editor-brand">
        <span class="editor-logo">OJ</span>
        <span class="editor-brand-text">Editor Dashboard</span>
    </a>

    <button type="button"
        class="editor-menu-toggle"
        id="editor-menu-toggle"
        aria-label="Toggle navigation"
        aria-expanded="false"
        aria-controls="editor-nav">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <div class="editor-nav" id="editor-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="manuscripts.php">Manuscripts</a>
        <a href="decisions.php">Editorial Decisions</a>
        <a href="issues.php">Issue Management</a>

        <a href="settings.php" class="editor-profile-pill active">
            <svg xmlns="http://www.w3.org/2000/svg"
                width="18" height="18" viewBox="0 0 24 24"
                fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"/>
                <path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/>
            </svg>
            <span><?= e($eicName) ?></span>
        </a>

        <form action="../auth/logout.php" method="POST" class="editor-logout-form">
            <button type="submit" class="editor-logout-btn">Log out</button>
        </form>
    </div>
</nav>
<div class="container">

    <div class="page-header">
        <h1>Settings</h1>
        <p>
            Manage your account information and security.
        </p>
    </div>

    <?php if ($successMessage !== ''): ?>
        <div class="notice notice-success">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="notice notice-error">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Account Information -->
    <div class="settings-card">

        <div class="card-header">
            <h2>Account Information</h2>
            <p>
                Your account details as registered in the journal management system.
            </p>
        </div>

        <div class="profile-details">

            <div class="detail-item">
                <div class="detail-label">Full Name</div>
                <div class="detail-value">
                    <?= e($eicName ?: 'Not provided') ?>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Email Address</div>
                <div class="detail-value">
                    <?= e($user['email']) ?>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Account Role</div>
                <div class="detail-value">
                    Editor-in-Chief
                </div>
            </div>

        </div>

    </div>

    <!-- Change Password -->
    <div class="settings-card">

        <div class="card-header">
            <h2>Change Password</h2>
            <p>
                Update your password to keep your account secure.
                Your new password must contain at least 8 characters.
            </p>
        </div>

        <form method="POST" action="settings.php" class="password-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($token) ?>"
            >

            <div class="form-group">
                <label for="current_password">
                    Current Password
                </label>

                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="form-group">
                <label for="new_password">
                    New Password
                </label>

                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >

                <small>
                    Your password must be at least 8 characters long.
                </small>
            </div>

            <div class="form-group">
                <label for="confirm_password">
                    Confirm New Password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
            </div>

            <button type="submit" class="submit-btn">
                Update Password
            </button>

        </form>

    </div>

</div>

<footer class="footer footer-simple">
<p>&copy; <?= date('Y') ?> OJMS. All rights reserved.</p>
</footer>

<script>
const editorToggle = document.getElementById('editor-menu-toggle');
const editorNav = document.getElementById('editor-nav');

if (editorToggle && editorNav) {
    editorToggle.addEventListener('click', function() {
        const isOpen = editorNav.classList.toggle('is-open');
        editorToggle.setAttribute('aria-expanded', isOpen);
    });

    editorNav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function() {
            editorNav.classList.remove('is-open');
            editorToggle.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('click', function(event) {
        if (!editorNav.contains(event.target) &&
            !editorToggle.contains(event.target)) {
            editorNav.classList.remove('is-open');
            editorToggle.setAttribute('aria-expanded', 'false');
        }
    });
}
</script>

</body>
</html>
