<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Reuse existing backend database connection & configuration.
 * Update the path below to match your project's actual DB bootstrap file.
 */
$dbBootstrap = __DIR__ . '/config/database.php';

if (!file_exists($dbBootstrap)) {
    die('Database configuration file not found: ' . $dbBootstrap);
}

require_once $dbBootstrap;

if (!isset($pdo)) {
    die('Database file loaded, but $pdo was not created.');
}

if (!$pdo instanceof PDO) {
    die('$pdo exists but is not a PDO connection.');
}

function e(?string $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$editorialBoard = [];
$reviewers = [];
$dbUnavailable = false;

if (isset($pdo) && $pdo instanceof PDO) {

    // Editorial leadership from the users table
    try {
        $stmt = $pdo->prepare(
            "SELECT first_name, last_name, role
             FROM users
             WHERE role IN ('editor_in_chief', 'managing_editor')
             ORDER BY role ASC, last_name ASC"
        );

        $stmt->execute();
        $editorialBoard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        error_log('[OJMS About Editorial Board Error] ' . $e->getMessage());
    }

    // Reviewers
    try {
        $stmt = $pdo->prepare(
            "SELECT first_name, last_name
             FROM users
             WHERE role = :role
             ORDER BY last_name ASC, first_name ASC"
        );

        $stmt->execute([':role' => 'reviewer']);
        $reviewers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        error_log('[OJMS About Reviewers Error] ' . $e->getMessage());
    }

} else {
    $dbUnavailable = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About the Journal | OJMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@500;600;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="../assets/css/about.css">
</head>

<body data-page="about" data-root="../" data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>">
  <div id="site-header"></div>

  <main>
    <!-- About Hero -->
    <section class="about-hero" aria-labelledby="about-hero-heading">
      <div class="container about-hero-content">
        <p class="hero-eyebrow">ABOUT THE JOURNAL</p>
        <h1 id="about-hero-heading">Advancing Knowledge Through Research</h1>
        <p class="hero-lead">
          Learn about our scholarly mission, aims and scope, editorial leadership,
          and the peer reviewers who uphold the integrity of every publication.
        </p>
      </div>
    </section>

    <!-- On This Page Anchor Bar -->
    <nav class="section-nav" aria-label="On this page">
      <div class="container section-nav-inner">
        <span class="section-nav-label">On this page</span>
        <div class="section-nav-links">
          <a href="#scope">Aims and Scope</a>
          <a href="#editorial-board">Editorial Board</a>
          <a href="#reviewers">Reviewers</a>
        </div>
      </div>
    </nav>

    <!-- Journal Introduction -->
    <section class="about-intro-section" aria-labelledby="intro-heading">
      <div class="container">
        <div class="intro-layout" data-reveal>
          <div class="intro-label-col">
            <p class="section-eyebrow">OVERVIEW</p>
            <h2 id="intro-heading">Journal of Applied Scholarship</h2>
          </div>
          <div class="intro-copy-col">
            <blockquote class="editorial-statement">
              The Journal of Applied Scholarship is an open-access publication for
              rigorous, original research and thoughtful exchange across disciplines.
              We support a transparent editorial process and make high-quality
              scholarship available to a broad global audience.
            </blockquote>
          </div>
        </div>
      </div>
    </section>

    <!-- Aims and Scope -->
    <section id="scope" class="about-section about-section-cream" aria-labelledby="scope-heading">
      <div class="container">
        <div class="section-header" data-reveal>
          <p class="section-eyebrow">MISSION &amp; FOCUS</p>
          <h2 id="scope-heading">Aims and Scope</h2>
        </div>

        <div class="scope-grid">
          <article class="scope-card" data-reveal>
            <div class="scope-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
              </svg>
            </div>
            <h3>Knowledge &amp; Practice</h3>
            <p>Publish research that advances knowledge and practice.</p>
          </article>

          <article class="scope-card" data-reveal>
            <div class="scope-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                <path d="m9 12 2 2 4-4" />
              </svg>
            </div>
            <h3>Transparent Scholarship</h3>
            <p>Encourage responsible, inclusive, and transparent scholarship.</p>
          </article>

          <article class="scope-card" data-reveal>
            <div class="scope-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="2" y1="12" x2="22" y2="12" />
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
              </svg>
            </div>
            <h3>Cross-Disciplinary Exchange</h3>
            <p>Connect researchers, practitioners, and communities across disciplines.</p>
          </article>

          <article class="scope-card" data-reveal>
            <div class="scope-icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>
            <h3>Author Guidance</h3>
            <p>Support early-career authors through constructive editorial guidance.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- Editorial Board -->
    <section id="editorial-board" class="about-section" aria-labelledby="board-heading">
      <div class="container">
        <div class="section-header" data-reveal>
          <p class="section-eyebrow">LEADERSHIP</p>
          <h2 id="board-heading">Editorial Board</h2>
        </div>

        <?php if ($dbUnavailable): ?>

<div class="empty-state" data-reveal>
    <p>Editorial board information is temporarily unavailable.</p>
</div>

<?php elseif (empty($editorialBoard)): ?>

<div class="empty-state" data-reveal>
    <h3>Editorial board coming soon</h3>
    <p>Editorial leadership information will appear here.</p>
</div>

<?php else: ?>

<div class="board-grid">

    <?php foreach ($editorialBoard as $member): ?>

        <?php
            $fullName = trim(
                ($member['first_name'] ?? '') . ' ' .
                ($member['last_name'] ?? '')
            );

            $position = match ($member['role']) {
                'editor_in_chief' => 'Editor-in-Chief',
                'managing_editor' => 'Managing Editor',
                default => 'Editorial Board Member'
            };
        ?>

        <article class="board-card" data-reveal>

            <p class="board-position">
                <?= e($position) ?>
            </p>

            <h3 class="board-name">
                <?= e($fullName) ?>
            </h3>

        </article>

    <?php endforeach; ?>

</div>

<?php endif; ?>
      </div>
    </section>

    <!-- Reviewers -->
    <section id="reviewers" class="about-section about-section-cream" aria-labelledby="reviewers-heading">
      <div class="container">
        <div class="section-header" data-reveal>
          <p class="section-eyebrow">PEER REVIEW</p>
          <h2 id="reviewers-heading">Reviewers</h2>
        </div>

        <?php if ($dbUnavailable): ?>

<div class="empty-state" data-reveal>
    <p>Reviewer listings are temporarily unavailable.</p>
</div>

<?php elseif (empty($reviewers)): ?>

<div class="empty-state" data-reveal>
    <h3>No reviewers listed</h3>
    <p>Registered peer reviewers will appear here.</p>
</div>

<?php else: ?>

<ul class="reviewers-grid">

    <?php foreach ($reviewers as $rev): ?>

        <?php
            $fullName = trim(
                ($rev['first_name'] ?? '') . ' ' .
                ($rev['last_name'] ?? '')
            );
        ?>

        <?php if ($fullName !== ''): ?>

            <li class="reviewer-item" data-reveal>

                <span class="reviewer-dot" aria-hidden="true"></span>

                <span><?= e($fullName) ?></span>

            </li>

        <?php endif; ?>

    <?php endforeach; ?>

</ul>

<?php endif; ?>
      </div>
    </section>
  </main>

  <div id="site-footer"></div>
  <script src="../assets/js/layout.js"></script>
  <script src="../assets/js/home.js" defer></script>
</body>

</html>