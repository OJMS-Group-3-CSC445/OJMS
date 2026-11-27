<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$message = '';
$success = false;

$token = trim($_GET['token'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $message = 'Invalid verification link.';
} else {

    try {
        $stmt = $pdo->prepare("
            SELECT id, email_verified, verification_expires
            FROM users
            WHERE verification_token = ?
            LIMIT 1
        ");

        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = 'This verification link is invalid or has already been used.';
        } elseif ((int) $user['email_verified'] === 1) {
            $message = 'Your email address has already been verified.';
            $success = true;
        } elseif (
            empty($user['verification_expires']) ||
            strtotime($user['verification_expires']) < time()
        ) {
            $message = 'This verification link has expired. Please contact the journal administrator.';
        } else {

            $update = $pdo->prepare("
                UPDATE users
                SET email_verified = 1,
                    verification_token = NULL,
                    verification_expires = NULL
                WHERE id = ?
            ");

            $update->execute([$user['id']]);

            $message = 'Your email address has been successfully verified. You can now log in.';
            $success = true;
        }

    } catch (PDOException $e) {
        error_log('OJMS email verification error: ' . $e->getMessage());
        $message = 'Something went wrong while verifying your email. Please try again later.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification | OJMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@500;600;700&display=swap"
          rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="auth.css">
</head>

<body>
    <div id="site-header"></div>

    <main class="login-main">
        <section class="login-card" style="text-align:center;">

            <?php if ($success): ?>
                <h1>Email Verified</h1>
            <?php else: ?>
                <h1>Email Verification</h1>
            <?php endif; ?>

            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

            <?php if ($success): ?>
                <a href="login.php" class="login-submit" style="display:inline-block;text-decoration:none;">
                    Log In
                </a>
            <?php else: ?>
                <a href="register.php">
                    Return to Registration
                </a>
            <?php endif; ?>

        </section>
    </main>

    <div id="site-footer"></div>

    <script src="../assets/js/layout.js"></script>
</body>
</html>
