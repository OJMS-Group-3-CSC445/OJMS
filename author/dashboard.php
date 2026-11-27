<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Protect the author portal
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'author') {
    redirect('../auth/login.php');
}

$userName = trim(
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['middle_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '')
);

if (empty($userName)) {
    $userName = $_SESSION['email'];
}

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM articles 
    WHERE author_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$totalArticles = (int) $stmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalArticles / $perPage));

$stmt = $pdo->prepare("
    SELECT
    id,
    title,
    abstract,
    original_filename,
    status,
    submitted_at,
    created_at,
    decided_at,
    decision_remarks
    FROM articles
    WHERE author_id = ?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");

$stmt->bindValue(1, $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->bindValue(2, $perPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();

$articles = $stmt->fetchAll();

function status_label($status) {
    return ucwords(str_replace('_', ' ', $status));
}

function status_class($status) {
    switch ($status) {
        case 'pending_review':
            return 'status-pending';

        case 'under_review':
            return 'status-review';

        case 'accepted':
        case 'published':
            return 'status-accepted';

        case 'rejected':
            return 'status-rejected';

        default:
            return 'status-review';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#faf9f6">
  <title>My submissions | OJMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/styles.css">
  <link rel="stylesheet" href="./dashboard.css">
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
        <a class="is-current" href="./dashboard.php" aria-current="page">Dashboard</a>
        <a href="./submit-manuscript.php">Submit manuscript</a>
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


  <main id="dashboard" class="author-main">
    <section class="submissions" aria-labelledby="submissions-title">
      <div class="submissions-heading">
        <div>
          <h1 id="submissions-title">My submissions</h1>
          <p>Track the status of your manuscripts and access submission details.</p>
        </div>
        <a class="submit-button" href="./submit-manuscript.php">Submit Manuscript</a>
      </div>

      <p class="dashboard-feedback" id="dashboard-feedback" role="status" hidden></p>

      <div class="table-scroll" tabindex="0" aria-label="Submission list, scroll horizontally on smaller screens" <?= empty($articles) ? 'hidden' : '' ?>>
        <table class="submission-table">
          <thead>
            <tr>
              <th scope="col">Title</th>
              <th scope="col">Date submitted</th>
              <th scope="col">Status</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($articles as $article): ?>
                <tr>
                    <th scope="row">
                        <?= e($article['title']) ?>
                    </th>

                    <td>
                        <?= e(date('d M Y', strtotime($article['submitted_at'] ?? $article['created_at']))) ?>
                    </td>

                    <td>
                        <span class="status <?= e(status_class($article['status'])) ?>">
                            <?= e(status_label($article['status'])) ?>
                        </span>
                    </td>

                    <td>
                    <button
                        class="details-button"
                        type="button"
                        data-title="<?= e($article['title']) ?>"
                        data-abstract="<?= e($article['abstract'] ?? '') ?>"
                        data-file="<?= e($article['original_filename'] ?? '') ?>"
                        data-date="<?= e($article['submitted_at'] ?? $article['created_at']) ?>"
                        data-status="<?= e($article['status']) ?>"
                        data-decision-date="<?= e($article['decided_at'] ?? '') ?>"
                        data-remarks="<?= e($article['decision_remarks'] ?? '') ?>">
                        View Details
                    </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
          <nav class="submission-pagination" aria-label="Submissions pages">

              <?php if ($page > 1): ?>
                  <a href="?page=<?= $page - 1 ?>">Previous</a>
              <?php endif; ?>

              <span>Page <?= $page ?> of <?= $totalPages ?></span>

              <?php if ($page < $totalPages): ?>
                  <a href="?page=<?= $page + 1 ?>">Next</a>
              <?php endif; ?>

          </nav>
      <?php endif; ?>

      <section class="empty-state" aria-labelledby="empty-state-title" <?= empty($articles) ? '' : 'hidden' ?>>
        <h2 id="empty-state-title">You haven't submitted a manuscript yet</h2>
        <p>Submit your first manuscript to begin tracking its review status.</p>
        <a class="empty-submit-button" href="./submit-manuscript.php">Submit Manuscript</a>
      </section>
    </section>
  </main>

  <footer class="author-footer author-footer-simple">
    <p>&copy; 2026 OJMS. All rights reserved.</p>
</footer>

  <dialog class="submission-dialog" aria-labelledby="dialog-heading">
    <header class="dialog-header">
      <h2 id="dialog-heading">Submission details</h2>
      <button class="dialog-close" type="button" aria-label="Close details">×</button>
    </header>
    <div class="dialog-content">
      <h3 id="dialog-title"></h3>
      <section class="dialog-field">
        <h4>Abstract</h4>
        <p id="dialog-abstract"></p>
      </section>
      <section class="dialog-field">
        <h4>Manuscript file</h4>
        <p id="dialog-file" class="dialog-file"></p>
      </section>
      <section class="dialog-field">
        <h4>Date submitted</h4>
        <p id="dialog-date"></p>
      </section>
      <section class="dialog-field dialog-status-field">
        <h4>Status</h4>
        <p><span id="dialog-status" class="status"></span></p>
        <p id="dialog-status-note" class="dialog-note"></p>
      </section>
      <section class="dialog-field">
        <h4>Decision date</h4>
        <p id="dialog-decision-date" class="dialog-note"></p>
      </section>
      <section class="dialog-field dialog-review-field" id="dialog-review-section" hidden>
        <h4>Reviewer feedback</h4>
        <ul id="dialog-reviews"></ul>
      </section>
      <a class="download-link" id="file-download" href="#" target="_blank" rel="noopener" hidden>Download File</a>
    </div>
    <button class="dialog-bottom-close" type="button">Close</button>
  </dialog>

  <script>
document.addEventListener('DOMContentLoaded', function () {

    const dialog = document.querySelector('.submission-dialog');

    if (!dialog) return;

    const title = document.getElementById('dialog-title');
    const abstractText = document.getElementById('dialog-abstract');
    const file = document.getElementById('dialog-file');
    const date = document.getElementById('dialog-date');
    const status = document.getElementById('dialog-status');
    const statusNote = document.getElementById('dialog-status-note');
    const decisionDate = document.getElementById('dialog-decision-date');
    const downloadLink = document.getElementById('file-download');

    function formatDate(value) {
        if (!value) return 'Not available';

        const parsed = new Date(value.replace(' ', 'T'));

        if (Number.isNaN(parsed.getTime())) {
            return value;
        }

        return parsed.toLocaleDateString(undefined, {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    document.querySelectorAll('.details-button').forEach(function (button) {

        button.addEventListener('click', function () {

            const manuscriptTitle = button.dataset.title || 'Untitled manuscript';
            const manuscriptAbstract = button.dataset.abstract || 'No abstract available.';
            const manuscriptFile = button.dataset.file || 'No file information available.';
            const manuscriptDate = button.dataset.date || '';
            const manuscriptStatus = button.dataset.status || 'unknown';
            const manuscriptDecisionDate = button.dataset.decisionDate || '';
            const manuscriptRemarks = button.dataset.remarks || '';

            title.textContent = manuscriptTitle;
            abstractText.textContent = manuscriptAbstract;
            file.textContent = manuscriptFile;
            date.textContent = formatDate(manuscriptDate);

            status.textContent = manuscriptStatus
                .replaceAll('_', ' ')
                .replace(/\b\w/g, letter => letter.toUpperCase());

            status.className = 'status';

            if (manuscriptStatus === 'pending_review') {
                status.classList.add('status-pending');
            } else if (manuscriptStatus === 'under_review') {
                status.classList.add('status-review');
            } else if (
                manuscriptStatus === 'accepted' ||
                manuscriptStatus === 'published'
            ) {
                status.classList.add('status-accepted');
            } else if (manuscriptStatus === 'rejected') {
                status.classList.add('status-rejected');
            }

            statusNote.textContent = manuscriptRemarks || '';

            decisionDate.textContent = manuscriptDecisionDate
                ? formatDate(manuscriptDecisionDate)
                : 'No decision has been recorded yet.';

            // Until we build secure manuscript download handling,
            // do not expose the stored file path.
            downloadLink.hidden = true;

            dialog.showModal();
        });

    });

    document.querySelectorAll('.dialog-close, .dialog-bottom-close')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                dialog.close();
            });
        });

    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
            dialog.close();
        }
    });

});
</script>


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
            menuToggle.setAttribute('aria-label', 'Toggle navigation');
        });
    });

    document.addEventListener('click', function (event) {
        if (
            !navigation.contains(event.target) &&
            !menuToggle.contains(event.target)
        ) {
            navigation.classList.remove('is-open');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Toggle navigation');
        }
    });
});
</script>

</body>
</html>
