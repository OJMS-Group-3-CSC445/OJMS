<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 1. Reuse existing backend configuration & database connection.
 * Replace the path below with your project's actual bootstrap/DB include
 * (e.g., require_once __DIR__ . '/config/database.php';).
 * Expected variables:
 *   $pdo (PDO instance)
 *   $journalConfig (optional array with 'contact_email', 'title', etc.)
 */
$dbBootstrap = __DIR__ . '/config/database.php';
if (file_exists($dbBootstrap)) {
    require_once $dbBootstrap;
}

// Helper: safe HTML escaping
function e(?string $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Helper: truncate abstract cleanly on word boundary
function truncateText(?string $text, int $limit = 180): string {
    $clean = trim((string) ($text ?? ''));
    if ($clean === '' || mb_strlen($clean) <= $limit) {
        return $clean;
    }
    $cut = mb_substr($clean, 0, $limit);
    $lastSpace = mb_strrpos($cut, ' ');
    return ($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut) . '…';
}

// Helper: safe date formatting
function formatDate(?string $dateStr, string $format = 'F Y'): string {
    if (!$dateStr) {
        return '';
    }
    try {
        return (new DateTimeImmutable($dateStr))->format($format);
    } catch (Exception $e) {
        return '';
    }
}

// Auth state (adapt session keys to match your existing auth implementation)
$isAuthenticated = !empty($_SESSION['user_id']);
$userRole        = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
$submitUrl       = ($isAuthenticated && $userRole === 'author')
    ? './author/submit.php'
    : './auth/login.php';

$officialEmail = 'your-journal-email@example.com';

// Dynamic homepage data containers
$currentIssue      = null;
$publishedIssuesCt = 0;
$editorialBoard    = [];
$reviewers         = [];
$publishedArticles = [];
$dbUnavailable     = false;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // 1. Current Issue (is_current = 1)
        $stmt = $pdo->prepare(
            "SELECT id, title, volume, issue_number, publication_date, description
             FROM journal_issues
             WHERE is_current = 1
             ORDER BY publication_date DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute();
        $currentIssue = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // 2. Total Published Issues count for Archives
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM journal_issues");
        $stmt->execute();
        $publishedIssuesCt = (int) $stmt->fetchColumn();

        // 3. Editorial Board - Editor-in-Chief accounts
        $stmt = $pdo->prepare(
            "SELECT
                first_name,
                middle_name,
                last_name
            FROM users
            WHERE role = :role
            ORDER BY last_name ASC, first_name ASC
            LIMIT 4"
        );
        $stmt->execute([':role' => 'editor_in_chief']);
        $editorialBoard = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Reviewers (public name fields ONLY — never email, phone, password, or tokens)
        $stmt = $pdo->prepare(
            "SELECT first_name, middle_name, last_name
             FROM users
             WHERE role = :role
             ORDER BY last_name ASC, first_name ASC
             LIMIT 5"
        );
        $stmt->execute([':role' => 'reviewer']);
        $reviewers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Latest Published Articles (status = 'published' ONLY)
        $stmt = $pdo->prepare(
            "SELECT
                a.id,
                a.title,
                a.authors,
                a.abstract,
                a.created_at,
                a.issue_id,
                u.first_name AS author_first,
                u.last_name AS author_last,
                ji.volume,
                ji.issue_number
             FROM articles a
             LEFT JOIN users u ON u.id = a.author_id
             LEFT JOIN journal_issues ji ON ji.id = a.issue_id
             WHERE a.status = :status
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 4"
        );
        $stmt->execute([':status' => 'published']);
        $publishedArticles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[OJMS Homepage DB Error] ' . $e->getMessage());
        $dbUnavailable = true;
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
    <title>Journal of Applied Scholarship | OJMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/styles.css">
    <link rel="stylesheet" href="./assets/css/home.css">
</head>
<body data-page="home" data-root="./" data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>">
    <div id="site-header"></div>

    <main>
        <!-- Hero Section -->
        <section class="hero" aria-labelledby="hero-heading">
            <div class="hero-overlay"></div>
            <div class="container hero-content">
                <p class="hero-eyebrow">OPEN ACCESS JOURNAL</p>
                <h1 id="hero-heading">Journal of Applied Scholarship</h1>
                <p class="hero-lead">
                    A peer-reviewed academic publication dedicated to rigorous empirical research,
                    transparent editorial review, and scholarship that advances applied disciplines.
                </p>
                <div class="hero-actions">
                    <a class="btn btn-primary btn-lg" href="<?= e($submitUrl) ?>">Submit Manuscript</a>
                    <a class="btn btn-hero-secondary btn-lg" href="current-issue.php">Explore Current Issue</a>
                </div>
            </div>
        </section>

        <!-- Published Research Highlight -->
        <section class="published-section" aria-labelledby="published-heading">
            <div class="container">
                <div class="section-header">
                    <div>
                        <p class="section-eyebrow">LATEST SCHOLARSHIP</p>
                        <h2 id="published-heading">Recently Published Research</h2>
                    </div>
                    <a class="section-link" href="archives.php">View all archives &rarr;</a>
                </div>

                <?php if ($dbUnavailable): ?>
                    <div class="empty-state">
                        <p>Published articles are temporarily unavailable. Please check back shortly.</p>
                    </div>
                <?php elseif (empty($publishedArticles)): ?>
                    <div class="empty-state">
                        <h3>No published articles yet</h3>
                        <p>Peer-reviewed articles will appear here once they are published in an issue.</p>
                    </div>
                <?php else: ?>
                    <div class="articles-grid">
                        <?php foreach ($publishedArticles as $article): ?>
                            <?php
                            $authorDisplay = trim((string) ($article['authors'] ?? ''));
                            if ($authorDisplay === '') {
                                $authorDisplay = trim(($article['author_first'] ?? '') . ' ' . ($article['author_last'] ?? ''));
                            }
                            $pubDate = formatDate($article['created_at'] ?? null, 'M j, Y');
                            $issueMeta = [];
                            if (!empty($article['volume'])) {
                                $issueMeta[] = 'Vol. ' . $article['volume'];
                            }
                            if (!empty($article['issue_number'])) {
                                $issueMeta[] = 'No. ' . $article['issue_number'];
                            }
                            ?>
                            <article class="article-card">
                                <div class="article-meta">
                                    <?php if ($pubDate !== ''): ?>
                                        <time datetime="<?= e($article['created_at']) ?>"><?= e($pubDate) ?></time>
                                    <?php endif; ?>
                                    <?php if (!empty($issueMeta)): ?>
                                        <span class="meta-sep">&middot;</span>
                                        <span><?= e(implode(', ', $issueMeta)) ?></span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="article-title">
                                    <a href="current-issue.php?article=<?= (int) $article['id'] ?>">
                                        <?= e($article['title']) ?>
                                    </a>
                                </h3>
                                <?php if ($authorDisplay !== ''): ?>
                                    <p class="article-authors"><?= e($authorDisplay) ?></p>
                                <?php endif; ?>
                                <p class="article-abstract"><?= e(truncateText($article['abstract'] ?? '', 180)) ?></p>
                                <a class="card-link" href="current-issue.php?article=<?= (int) $article['id'] ?>">
                                    Read article &rarr;
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Explore the Journal -->
        <section class="cards" aria-labelledby="explore-heading">
            <div class="container">
                <div class="section-header">
                    <div>
                        <p class="section-eyebrow">JOURNAL DIRECTORY</p>
                        <h2 id="explore-heading">Explore the Journal</h2>
                    </div>
                </div>

                <div class="card-grid">
                    <!-- About the Journal -->
                    <article class="card">
                        <h3>About the Journal</h3>
                        <p>
                            Learn about our scholarly mission, peer-review standards, open-access policies,
                            and editorial governance.
                        </p>
                        <a class="link" href="about.php">Learn more &rarr;</a>
                    </article>

                    <!-- Current Issue -->
                    <article class="card">
                        <h3>Current Issue</h3>
                        <?php if ($currentIssue): ?>
                            <?php
                            $metaParts = [];
                            if (!empty($currentIssue['volume'])) {
                                $metaParts[] = 'Vol. ' . $currentIssue['volume'];
                            }
                            if (!empty($currentIssue['issue_number'])) {
                                $metaParts[] = 'No. ' . $currentIssue['issue_number'];
                            }
                            $issueDate = formatDate($currentIssue['publication_date'] ?? null, 'F Y');
                            if ($issueDate !== '') {
                                $metaParts[] = $issueDate;
                            }
                            ?>
                            <?php if (!empty($metaParts)): ?>
                                <p class="issue-badge"><?= e(implode(' · ', $metaParts)) ?></p>
                            <?php endif; ?>
                            <p class="issue-title"><strong><?= e($currentIssue['title']) ?></strong></p>
                            <?php if (!empty($currentIssue['description'])): ?>
                                <p><?= e(truncateText($currentIssue['description'], 120)) ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="empty-inline">No current issue has been published yet.</p>
                        <?php endif; ?>
                        <a class="link" href="current-issue.php">View Current Issue &rarr;</a>
                    </article>

                    <!-- Archives -->
                    <article class="card">
                        <h3>Archives</h3>
                        <p>Browse published research across past volumes and issues.</p>
                        <?php if (!$dbUnavailable): ?>
                            <p class="stat-note">
                                <?= $publishedIssuesCt === 1
                                    ? '1 published issue available'
                                    : $publishedIssuesCt . ' published issues available' ?>
                            </p>
                        <?php endif; ?>
                        <a class="link" href="archives.php">Browse Archives &rarr;</a>
                    </article>

                    <!-- Editorial Board -->
                    <article class="card">
                        <h3>Editorial Board</h3>

                        <?php if (!empty($editorialBoard)): ?>
                            <ul class="people">
                                <?php foreach ($editorialBoard as $member): ?>
                                    <?php
                                    $memberName = trim(implode(' ', array_filter([
                                        $member['first_name'] ?? '',
                                        $member['middle_name'] ?? '',
                                        $member['last_name'] ?? '',
                                    ])));
                                    ?>
                                    <li>
                                        <strong><?= e($memberName) ?></strong>
                                        <span>Editor-in-Chief</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="empty-inline">
                                Editorial board listings will be updated shortly.
                            </p>
                        <?php endif; ?>

                        <a class="link" href="about.php#editorial-board">
                            See Full Board &rarr;
                        </a>
                    </article>

                    <!-- Reviewers -->
                    <article class="card">
                        <h3>Reviewers</h3>
                        <?php if (!empty($reviewers)): ?>
                            <ul class="people">
                                <?php foreach ($reviewers as $rev): ?>
                                    <?php
                                    $fullName = trim(implode(' ', array_filter([
                                        $rev['first_name'] ?? '',
                                        $rev['middle_name'] ?? '',
                                        $rev['last_name'] ?? '',
                                    ])));
                                    ?>
                                    <li><strong><?= e($fullName) ?></strong></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="empty-inline">No public reviewer records are currently listed.</p>
                        <?php endif; ?>
                        <a class="link" href="about.php#reviewers">See All Reviewers &rarr;</a>
                    </article>

                    <!-- Contact Us -->
                    <article class="card">
                        <h3>Contact Us</h3>
                        <p class="label">Editorial Office</p>
                        <?php if ($officialEmail): ?>
                            <a class="email" href="mailto:<?= e($officialEmail) ?>"><?= e($officialEmail) ?></a>
                        <?php endif; ?>
                        <p>For manuscript submissions, peer-review support, and general journal enquiries.</p>
                        <a class="link" href="contact.php">View Contact Details &rarr;</a>
                    </article>
                </div>
            </div>
        </section>

        <!-- Call to Action -->
        <section class="cta" aria-labelledby="cta-heading">
            <div class="container inner-cta">
                <div class="cta-copy">
                    <h2 id="cta-heading">Have research to share with the academic community?</h2>
                    <p>
                        Submit your original manuscript for structured peer review and open-access dissemination.
                    </p>
                </div>
                <div class="cta-btn">
                    <a class="btn btn-light" href="<?= e($submitUrl) ?>">Submit Manuscript</a>
                    <?php if (!$isAuthenticated): ?>
                        <a class="btn btn-cta-outline" href="auth/register.php">Create an Account</a>
                        <a class="cta-login" href="auth/login.php">Log in &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <div id="site-footer"></div>
<script src="./assets/js/layout.js"></script>
<script src="./assets/js/home.js" defer></script>
</body>
</html>