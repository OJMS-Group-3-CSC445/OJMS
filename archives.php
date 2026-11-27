<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbBootstrap = __DIR__ . '/config/database.php';
if (file_exists($dbBootstrap)) {
    require_once $dbBootstrap;
}

function e(?string $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatDate(?string $dateStr, string $format = 'F Y'): string {
    if (!$dateStr) return '';
    try {
        return (new DateTimeImmutable($dateStr))->format($format);
    } catch (Exception $e) {
        return '';
    }
}

function truncateText(?string $text, int $limit = 240): string {
    $clean = trim((string) ($text ?? ''));
    if ($clean === '' || mb_strlen($clean) <= $limit) return $clean;
    $cut = mb_substr($clean, 0, $limit);
    $space = mb_strrpos($cut, ' ');
    return ($space !== false ? mb_substr($cut, 0, $space) : $cut) . '…';
}

$isAuthenticated = !empty($_SESSION['user_id']);
$archives        = [];
$dbUnavailable   = false;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // 1. Fetch all journal issues, newest first
        $issuesStmt = $pdo->prepare(
            "SELECT id, title, volume, issue_number, publication_date, description, created_at
             FROM journal_issues
             ORDER BY COALESCE(publication_date, DATE(created_at)) DESC, id DESC"
        );
        $issuesStmt->execute();
        $issues = $issuesStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($issues)) {
            // 2. Fetch ONLY published articles linked to an issue
            $articlesStmt = $pdo->prepare(
                "SELECT
                    a.id,
                    a.issue_id,
                    a.title,
                    a.authors,
                    a.abstract,
                    a.document_path,
                    a.created_at,
                    u.first_name AS author_first,
                    u.last_name AS author_last
                 FROM articles a
                 LEFT JOIN users u ON u.id = a.author_id
                 WHERE a.status = :status
                   AND a.issue_id IS NOT NULL
                 ORDER BY a.created_at DESC, a.id DESC"
            );
            $articlesStmt->execute([':status' => 'published']);
            $articles = $articlesStmt->fetchAll(PDO::FETCH_ASSOC);

            $articlesByIssue = [];
            foreach ($articles as $art) {
                $articlesByIssue[(int) $art['issue_id']][] = $art;
            }

            $grouped = [];
            foreach ($issues as $issue) {
                $dateSource = $issue['publication_date'] ?: $issue['created_at'];
                $year = 'Undated';
                if ($dateSource) {
                    try {
                        $year = (new DateTimeImmutable($dateSource))->format('Y');
                    } catch (Exception $e) {
                        $year = 'Undated';
                    }
                }
                $issue['articles'] = $articlesByIssue[(int) $issue['id']] ?? [];
                $grouped[$year][] = $issue;
            }

            uksort($grouped, static function (string $a, string $b): int {
                if ($a === 'Undated') return 1;
                if ($b === 'Undated') return -1;
                return (int) $b <=> (int) $a;
            });

            $archives = $grouped;
        }
    } catch (Throwable $e) {
        error_log('[OJMS Archives DB Error] ' . $e->getMessage());
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
    <title>Journal Archives | OJMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/styles.css">
    <link rel="stylesheet" href="./assets/css/archives.css">
</head>

<body data-page="archives" data-root="./" data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>">
    <div id="site-header"></div>

    <main class="archives-page">
        <!-- Editorial Archives Hero -->
        <section class="archives-hero" aria-labelledby="archives-hero-heading">
            <div class="container archives-hero-content">
                <p class="hero-eyebrow">PUBLICATION HISTORY</p>
                <h1 id="archives-hero-heading">Journal Archives</h1>
                <p class="hero-lead">
                    Explore previously published volumes and issues of the Journal of Applied Scholarship,
                    organised by publication year.
                </p>
            </div>
        </section>

        <!-- Archives Directory -->
        <section class="archives-section" aria-label="Published journal issues by year">
            <div class="container" id="archives-container">
                <?php if ($dbUnavailable): ?>
                <div class="state-box">
                    <h3>Unable to load journal archives</h3>
                    <p>We could not retrieve archived issues right now. Please try again in a moment.</p>
                </div>
                <?php elseif (empty($archives)): ?>
                <div class="state-box">
                    <h3>No published issues available yet</h3>
                    <p>Previously published volumes and issues will appear here once they are added to the archive.</p>
                </div>
                <?php else: ?>

<!-- Archive Search & Filters -->
<div class="archives-toolbar" id="archives-toolbar">
    <div class="archives-search-wrap">
        <svg class="archives-search-icon" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="1.8"
            stroke-linecap="round" stroke-linejoin="round"
            aria-hidden="true">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>

        <input
            type="search"
            id="archives-search"
            class="archives-search"
            placeholder="Search volumes, issues or articles..."
            aria-label="Search journal archives"
        >
    </div>

    <div class="archives-filter-controls">
        <label for="archives-year-filter">Year</label>

        <select id="archives-year-filter" class="archives-year-filter">
            <option value="all">All years</option>
            <?php foreach (array_keys($archives) as $archiveYear): ?>
                    <option value="<?= e((string) $archiveYear) ?>">
                        <?= e((string) $archiveYear) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="button" id="archives-clear" class="archives-clear">
                Clear
            </button>
        </div>
    </div>

    <div class="archives-results-info" id="archives-results-info">
        Browse all published issues
    </div>

    <?php $isFirstIssue = true; ?>
    <?php foreach ($archives as $year => $yearIssues): ?>
                <?php $count = count($yearIssues); ?>
                <section class="archive-year"
                    data-archive-year="<?= e((string) $year) ?>"
                    aria-label="Issues published in <?= e((string) $year) ?>">
                    <div class="year-header" data-reveal>
                        <h2>
                            <?= e((string) $year) ?>
                        </h2>
                        <span class="year-count">
                            <?= $count === 1 ? '1 issue' : $count . ' issues' ?>
                        </span>
                    </div>

                    <div class="issues-list">
                        <?php foreach ($yearIssues as $issue): ?>
                        <?php
                                    $panelId = 'articles-issue-' . (int) $issue['id'];
                                    $volParts = [];
                                    if (!empty($issue['volume'])) $volParts[] = 'Volume ' . $issue['volume'];
                                    if (!empty($issue['issue_number'])) $volParts[] = 'Issue ' . $issue['issue_number'];
                                    $volLabel = implode(', ', $volParts);
                                    $dateLabel = formatDate($issue['publication_date'] ?? null, 'F Y');
                                    $headingText = $issue['title'] ?: ($volLabel ?: 'Journal Issue');
                                    $expanded = $isFirstIssue;
                                    $isFirstIssue = false;
                                    ?>
                        <div class="archive-issue" data-reveal>
                            <div class="issue-row">
                                <div class="issue-details">
                                    <div class="issue-meta-top">
                                        <?php if ($volLabel !== ''): ?>
                                        <span class="issue-vol-badge">
                                            <?= e($volLabel) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($dateLabel !== ''): ?>
                                        <time class="issue-pub-date" datetime="<?= e($issue['publication_date']) ?>">
                                            <?= e($dateLabel) ?>
                                        </time>
                                        <?php endif; ?>
                                    </div>
                                    <h3>
                                        <?= e($headingText) ?>
                                    </h3>
                                </div>

                                <button class="view-issue" type="button"
                                    aria-expanded="<?= $expanded ? 'true' : 'false' ?>"
                                    aria-controls="<?= e($panelId) ?>" data-target="<?= e($panelId) ?>">
                                    <span class="view-issue-label">
                                        <?= $expanded ? 'Hide Issue' : 'View Issue' ?>
                                    </span>
                                    <span class="view-issue-chevron" aria-hidden="true">&#9662;</span>
                                </button>
                            </div>

                            <div class="issue-articles-panel<?= $expanded ? ' is-open' : '' ?>" id="<?= e($panelId) ?>"
                                <?=$expanded ? '' : 'hidden' ?>
                                >
                                <div class="issue-articles-inner">
                                    <div class="issue-articles-body">
                                        <?php if (empty($issue['articles'])): ?>
                                        <p class="empty-issue-articles">
                                            No published articles are currently available for this issue.
                                        </p>
                                        <?php else: ?>
                                        <?php foreach ($issue['articles'] as $article): ?>
                                        <?php
                                                            $authors = trim((string) ($article['authors'] ?? ''));
                                                            if ($authors === '') {
                                                                $authors = trim(($article['author_first'] ?? '') . ' ' . ($article['author_last'] ?? ''));
                                                            }
                                                            $fileHref = !empty($article['document_path']) ? '../ojms_manuscripts/' . basename($article['document_path']) : '';
                                                            ?>
                                        <article class="archive-article">
                                            <div class="archive-article-info">
                                                <h4>
                                                    <?= e($article['title']) ?>
                                                </h4>
                                                <?php if ($authors !== ''): ?>
                                                <p class="article-authors">
                                                    <?= e($authors) ?>
                                                </p>
                                                <?php endif; ?>
                                                <p class="article-abstract">
                                                    <?= e(truncateText($article['abstract'] ?? '')) ?>
                                                </p>
                                            </div>
                                            <?php if ($fileHref !== ''): ?>
                                            <a href="<?= e($fileHref) ?>" class="read-article" target="_blank"
                                                rel="noopener noreferrer">
                                                Read Article <span aria-hidden="true">&rarr;</span>
                                            </a>
                                            <?php else: ?>
                                            <span class="read-article-unavailable">Document unavailable</span>
                                            <?php endif; ?>
                                        </article>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="site-footer"></div>
    <script src="./assets/js/layout.js"></script>
    <script src="./assets/js/archives.js" defer></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('archives-search');
        const yearFilter = document.getElementById('archives-year-filter');
        const clearButton = document.getElementById('archives-clear');
        const resultsInfo = document.getElementById('archives-results-info');

        if (!searchInput || !yearFilter) return;

        const yearSections = Array.from(document.querySelectorAll('.archive-year'));

        function filterArchives() {
            const query = searchInput.value.trim().toLowerCase();
            const selectedYear = yearFilter.value;

            let visibleIssues = 0;
            let visibleYears = 0;

            yearSections.forEach(section => {
                const sectionYear = section.dataset.archiveYear;
                const issueCards = Array.from(section.querySelectorAll('.archive-issue'));

                let sectionVisibleIssues = 0;

                const yearMatches = selectedYear === 'all' || selectedYear === sectionYear;

                issueCards.forEach(issue => {
                    const searchableText = issue.textContent.toLowerCase();
                    const searchMatches = searchableText.includes(query);

                    const visible = yearMatches && searchMatches;

                    issue.style.display = visible ? '' : 'none';

                    if (visible) {
                        sectionVisibleIssues++;
                        visibleIssues++;
                    }
                });

                const sectionVisible = sectionVisibleIssues > 0;

                section.style.display = sectionVisible ? '' : 'none';

                if (sectionVisible) {
                    visibleYears++;
                }
            });

            if (resultsInfo) {
                if (visibleIssues === 0) {
                    resultsInfo.textContent = 'No issues found. Try adjusting your search or filters.';
                } else {
                    resultsInfo.textContent =
                        `Showing ${visibleIssues} ${visibleIssues === 1 ? 'issue' : 'issues'} across ${visibleYears} ${visibleYears === 1 ? 'year' : 'years'}`;
                }
            }
        }

        searchInput.addEventListener('input', filterArchives);
        yearFilter.addEventListener('change', filterArchives);

        clearButton?.addEventListener('click', function () {
            searchInput.value = '';
            yearFilter.value = 'all';
            filterArchives();
            searchInput.focus();
        });

        filterArchives();
    });
    </script>
</body>

</html>