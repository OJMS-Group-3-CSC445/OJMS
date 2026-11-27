<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {

        try {

            $stmt = $pdo->prepare("
                SELECT id, email, password, role, first_name, last_name, email_verified
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {

                if ((int)($user['email_verified'] ?? 0) !== 1) {
                    $errors[] = 'Please verify your email address before logging in. Check your inbox for the verification link.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];

                    // Redirect users according to their role
                    switch ($user['role']) {

                        case 'author':
                            redirect('../author/dashboard.php');
                            break;

                        case 'portal_admin':
                            redirect('../admin/dashboard.php');
                            break;

                        case 'editor_in_chief':
                            redirect('../editor/dashboard.php');
                            break;

                        case 'reviewer':
                            redirect('../reviewer/dashboard.php');
                            break;

                        default:
                            session_unset();
                            session_destroy();
                            $errors[] = 'Your account role is not recognized. Please contact the administrator.';
                            break;
                    }
                }

            } else {
                $errors[] = 'Invalid email address or password.';
            }

        } catch (PDOException $e) {

            error_log("Login error: " . $e->getMessage());

            $errors[] = 'Login failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log in | OJMS</title>
  <!-- Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@500;600;700&display=swap"
    rel="stylesheet">
  <!-- Styles -->
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="auth.css">
</head>

<body class="login-page" data-page="login" data-root="../">
  <div id="site-header"></div>

  <main class="login-main">
    <section class="login-card" aria-labelledby="login-title">
      <header class="login-card-header">
        <p class="auth-eyebrow">OJMS PORTAL</p>
        <h1 id="login-title">Log in</h1>
        <p>Sign in with your account to continue to your portal.</p>
      </header>

      <?php if (!empty($errors)): ?>
      <div class="auth-feedback" role="alert">
        <?php foreach ($errors as $error): ?>
        <p>
          <?= e($error) ?>
        </p>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form class="login-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-field">
          <label for="email">Email address</label>
          <input id="email" name="email" type="email" autocomplete="email" placeholder="you@institution.edu" required>
        </div>

        <div class="form-field">
          <label for="password">Password</label>
          <div class="password-field">
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
              <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
              <svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>
                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                <path d="M6.61 6.61A13.53 13.53 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                <line x1="2" y1="2" x2="22" y2="22" />
              </svg>
            </button>   
           </div>

        </div>

        <button class="login-submit" type="submit">Log in</button>

        <div class="Register-link">
          <p>New author?</p>
          <a href="register.php">Register</a>
        </div>
      </form>
    </section>
  </main>

  <div id="site-footer"></div>
  <script src="../assets/js/layout.js"></script>
<script>
document.querySelectorAll('.password-toggle').forEach(function(button) {
    button.addEventListener('click', function() {
        const input = button.parentElement.querySelector('input');
        const eye = button.querySelector('.icon-eye');
        const eyeOff = button.querySelector('.icon-eye-off');
        const isShowing = input.type === 'password';

        input.type = isShowing ? 'text' : 'password';
        if (eye) eye.hidden = isShowing;
        if (eyeOff) eyeOff.hidden = !isShowing;
        button.setAttribute('aria-pressed', String(isShowing));
        button.setAttribute('aria-label', isShowing ? 'Hide password' : 'Show password');
    });
});
</script>
</body>

</html>