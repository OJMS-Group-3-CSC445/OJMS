<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'author') {
    redirect('../auth/login.php');
}

$errors = [];

$userName = trim(
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['middle_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '')
);

if (empty($userName)) {
    $userName = $_SESSION['email'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }

    $title = trim($_POST['title'] ?? '');
    $abstract = trim($_POST['abstract'] ?? '');
    $authors = trim($_POST['authors'] ?? '');

    if ($title === '') {
        $errors[] = 'Manuscript title is required.';
    }

    if (strlen($title) > 255) {
        $errors[] = 'The manuscript title is too long.';
    }

    if ($abstract === '') {
        $errors[] = 'Abstract is required.';
    }

    if (!isset($_FILES['manuscript']) ||
        $_FILES['manuscript']['error'] !== UPLOAD_ERR_OK) {

        $errors[] = 'Please upload your manuscript file.';
    }

    if (empty($errors)) {

        $file = $_FILES['manuscript'];

        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = 'The manuscript file must be 10 MB or smaller.';
        }

        $originalFilename = basename($file['name']);
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        $allowedExtensions = ['pdf', 'doc', 'docx'];

        if (!in_array($extension, $allowedExtensions, true)) {
            $errors[] = 'Only PDF, DOC, and DOCX files are allowed.';
        }

        // Check actual file MIME type
        if (empty($errors)) {

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            $allowedMimeTypes = [
                'pdf' => ['application/pdf'],
                'doc' => [
                    'application/msword',
                    'application/x-msword',
                    'application/vnd.ms-word',
                    'application/octet-stream'
                ],
                'docx' => [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip',
                    'application/octet-stream'
                ]
            ];

            if (!in_array($mimeType, $allowedMimeTypes[$extension], true)) {
                $errors[] = 'The uploaded file type is not valid.';
            }
        }
    }

    if (empty($errors)) {

        // Store manuscript files outside the public website directory
        $uploadDirectory = dirname(__DIR__) . '/ojms_manuscripts';

        if (!is_dir($uploadDirectory)) {
            if (!mkdir($uploadDirectory, 0750, true)) {
                $errors[] = 'Unable to create manuscript storage directory.';
            }
        }

        if (empty($errors) && !is_writable($uploadDirectory)) {
            $errors[] = 'Manuscript storage directory is not writable.';
        }

        if (empty($errors)) {

            $storedFilename = bin2hex(random_bytes(24)) . '.' . $extension;
            $destination = $uploadDirectory . '/' . $storedFilename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $errors[] = 'Unable to save the uploaded manuscript.';
            } else {

                try {

                    $stmt = $pdo->prepare("
                        INSERT INTO articles (
                            author_id,
                            issue_id,
                            title,
                            authors,
                            abstract,
                            document_path,
                            original_filename,
                            file_size,
                            status,
                            submitted_at
                        ) VALUES (
                            ?,
                            NULL,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            'pending_review',
                            NOW()
                        )
                    ");

                    $stmt->execute([
                        $_SESSION['user_id'],
                        $title,
                        $authors,
                        $abstract,
                        $destination,
                        $originalFilename,
                        $file['size']
                    ]);

                    set_flash(
                        'success',
                        'Your manuscript was submitted successfully.'
                    );

                    redirect('dashboard.php');

                } catch (PDOException $e) {

                    // Remove uploaded file if database insertion fails
                    if (file_exists($destination)) {
                        unlink($destination);
                    }

                    error_log('Manuscript submission error: ' . $e->getMessage());

                    $errors[] = 'Your manuscript could not be submitted. Please try again.';
                }
            }
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#faf9f6">
  <title>Submit manuscript | OJMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/styles.css">
  <link rel="stylesheet" href="./dashboard.css">
  <link rel="stylesheet" href="./submit-manuscript.css">
</head>
<body class="author-dashboard submit-page">
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
        <a class="is-current" href="./submit-manuscript.php" aria-current="page">
            Submit manuscript
        </a>

        <a class="author-nav-name" href="./settings.php">
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

  <main class="author-main">
    <section class="submit-panel" aria-labelledby="submit-title">
      <a class="back-link" href="./dashboard.php">← Back to submissions</a>
      <h1 id="submit-title">Submit a manuscript</h1>
      <p class="submit-intro">Enter your manuscript details and upload the document for review.</p>
      <?php if (!empty($errors)): ?>
          <div class="dashboard-feedback" data-kind="error" role="alert">
              <?php foreach ($errors as $error): ?>
                  <p><?= e($error) ?></p>
              <?php endforeach; ?>
          </div>
      <?php endif; ?>

      <form id="submit-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="manuscript-title">Manuscript title <span aria-hidden="true">*</span></label>
        <input id="manuscript-title" name="title" type="text" maxlength="255" required autocomplete="off">

        <label for="manuscript-abstract">Abstract <span aria-hidden="true">*</span></label>
        <textarea id="manuscript-abstract" name="abstract" rows="7" required></textarea>

        <label for="manuscript-authors">Authors <span class="optional-label">(optional, separate names with commas)</span></label>
        <input id="manuscript-authors" name="authors" type="text" autocomplete="off">

        <label for="manuscript-file">Manuscript file <span aria-hidden="true">*</span></label>
        <input id="manuscript-file" name="manuscript" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
        <p class="field-hint">PDF, DOC, or DOCX. Maximum file size: 10 MB.</p>

        <div class="submit-actions">
          <button class="submit-button" type="submit">Submit manuscript</button>
          <a class="cancel-link" href="./dashboard.php">Cancel</a>
        </div>
      </form>
    </section>
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
