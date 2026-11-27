<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/smtp-config.php';

require_once __DIR__ . '/../includes/PHPMailer/Exception.php';
require_once __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$registrationSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm-password'] ?? '';

    $nameParts = preg_split('/\s+/', $name);

    if (count($nameParts) < 2) {
        $errors[] = 'Please enter your full name.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {

        try {

            $stmt = $pdo->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );

            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {

                $firstName = array_shift($nameParts);
                $lastName = array_pop($nameParts);
                $middleName = implode(' ', $nameParts);

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $role = 'author';

                $verificationToken = bin2hex(random_bytes(32));
                $verificationExpires = date('Y-m-d H:i:s', time() + 86400);

                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        email,
                        password,
                        role,
                        first_name,
                        last_name,
                        middle_name,
                        phone,
                        email_verified,
                        verification_token,
                        verification_expires
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
                ");

                $stmt->execute([
                    $email,
                    $hashedPassword,
                    $role,
                    $firstName,
                    $lastName,
                    $middleName,
                    $phone,
                    $verificationToken,
                    $verificationExpires
                ]);

                $verificationLink =
                    'https://ojms.vtenergies.com/auth/verify-email.php?token='
                    . urlencode($verificationToken);

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
                    $mail->addAddress($email, $name);

                    $mail->isHTML(true);
                    $mail->Subject = 'Verify Your OJMS Account';

                    $mail->Body = "
                        <h2>Welcome to OJMS</h2>
                        <p>Hello " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ",</p>
                        <p>Thank you for registering with the Online Journal Management System.</p>
                        <p>Please click the button below to verify your email address:</p>
                        <p>
                            <a href='" . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . "'
                               style='display:inline-block;padding:12px 20px;background:#2c5e4f;color:white;text-decoration:none;border-radius:5px;'>
                                Verify Email Address
                            </a>
                        </p>
                        <p>This verification link expires in 24 hours.</p>
                        <p>If you did not create this account, please ignore this email.</p>
                    ";

                    $mail->AltBody =
                        "Verify your OJMS account using this link: "
                        . $verificationLink;

                    $mail->send();

                    $registrationSuccess = true;

                } catch (Exception $mailException) {

                      error_log('OJMS verification email error: ' . $mail->ErrorInfo);
                      error_log('OJMS SMTP exception: ' . $mailException->getMessage());
                  
                      $errors[] = 'Your account was created, but we could not send the verification email. Please contact the journal administrator.';
                  }
            }

        } catch (PDOException $e) {

            error_log("Registration error: " . $e->getMessage());

            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | OJMS</title>
  <!-- Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@500;600;700&display=swap"
    rel="stylesheet">
  <!-- Styles -->
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="auth.css">
  <style>
    .register-main { display: flex; justify-content: center; align-items: center; width: 100%; box-sizing: border-box; padding: 48px 20px; }
    .register-section { width: 100%; display: flex; justify-content: center; box-sizing: border-box; }
    .register-card, .verified-UI { width: 100%; max-width: 520px; margin: 0 auto; box-sizing: border-box; }
    .verified-UI { text-align: center; }
    @media (max-width: 600px) {
      .register-main { padding: 28px 16px; align-items: flex-start; }
      .register-section, .register-card, .verified-UI { width: 100%; max-width: 100%; }
      .register-card, .verified-UI { padding: 24px 20px; }
      .register-card h1, .verified-UI h1 { font-size: clamp(1.7rem, 7vw, 2.2rem); }
    }
  </style>
</head>

<body class="register-page" data-page="register" data-root="../">
  <div id="site-header"></div>

  <main class="login-main register-main">
    <?php if ($registrationSuccess): ?>
      <section class="success-section verified-UI" aria-labelledby="verified-title">
        <div class="verified-icon" aria-hidden="true">
          <svg viewBox="0 0 32 32" focusable="false">
            <path d="m8 16 5 5 11-11" />
          </svg>
        </div>
        <h1 id="verified-title">Check your email</h1>
        <p class="verified-message">
          We sent a verification link to your email address. Verify your account before signing in.
        </p>
        <a href="login.php" class="verified-login-link">Log in</a>
      </section>
    <?php else: ?>
      <section class="register-section">
        <section class="login-card register-card" aria-labelledby="register-title">
          <header class="login-card-header">
            <p class="auth-eyebrow">AUTHOR REGISTRATION</p>
            <h1 id="register-title">Create your account</h1>
            <p>Register as an author to submit manuscripts and track peer review in OJMS.</p>
          </header>

          <?php if (!empty($errors)): ?>
            <div class="auth-feedback" role="alert">
              <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form class="login-form register-form" action="register.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-field">
              <label for="name">Full name</label>
              <input id="name" name="name" type="text" autocomplete="name" placeholder="First and last name" required>
            </div>
            <div class="form-field">
              <label for="email">Email address</label>
              <input id="email" name="email" type="email" autocomplete="email" placeholder="you@institution.edu" required>
            </div>
            <div class="form-field">
              <label for="phone">Phone number</label>
              <input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="+234 800 000 0000" required>
            </div>
            <div class="form-field">
              <label for="password">Password</label>
              <div class="password-field">
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" aria-describedby="password-hint" required>
                <button class="password-toggle" type="button" data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                  <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                  <svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" /><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7s-3.5 7-10 7S2 12 2 12s3.5-7 10-7" /><line x1="2" y1="2" x2="22" y2="22" /></svg>
                </button>
              </div>
              <span class="field-hint" id="password-hint">Must be at least 8 characters.</span>
            </div>
            <div class="form-field">
              <label for="confirm-password">Confirm password</label>
              <div class="password-field">
                <input id="confirm-password" name="confirm-password" type="password" autocomplete="new-password" minlength="8" required>
                <button class="password-toggle" type="button" data-toggle-password="confirm-password" aria-label="Show confirm password" aria-pressed="false">
                  <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                  <svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" /><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7s-3.5 7-10 7S2 12 2 12s3.5-7 10-7" /><line x1="2" y1="2" x2="22" y2="22" /></svg>
                </button>
              </div>
            </div>
            <button class="login-submit" type="submit">Create account</button>
            <div class="Register-link">
              <p>Already have an account?</p>
              <a href="login.php">Log in</a>
            </div>
          </form>
        </section>
      </section>
    <?php endif; ?>
  </main>

  <div id="site-footer"></div>
  <script src="../assets/js/layout.js"></script>
  <script>
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
      button.addEventListener('click', function () {
        const input = document.getElementById(button.getAttribute('data-toggle-password'));
        if (!input) return;

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');

        const eye = button.querySelector('.icon-eye');
        const eyeOff = button.querySelector('.icon-eye-off');
        if (eye) eye.hidden = isPassword;
        if (eyeOff) eyeOff.hidden = !isPassword;
      });
    });
  </script>
</body>
</html>