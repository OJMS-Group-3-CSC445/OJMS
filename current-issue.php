<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Reuse the existing PHP database connection.
 * Update the path below to match your project's actual DB bootstrap file
 * (expected to expose $pdo as a PDO instance).
 */
$dbBootstrap = __DIR__ . '/config/database.php';
if (file_exists($dbBootstrap)) {
    require_once $dbBootstrap;
}

function e(?string $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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

$currentIssue  = null;
$articles      = [];
$dbUnavailable = false;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // 1. Retrieve the active current issue (is_current = 1)
        $issueStmt = $pdo->prepare(
            "SELECT id, title, volume, issue_number, publication_date, description
             FROM journal_issues
             WHERE is_current = 1
             ORDER BY publication_date DESC, id DESC
             LIMIT 1"
        );
        $issueStmt->execute();
        $currentIssue = $issueStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // 2. Retrieve ONLY published articles belonging to the current issue
        if ($currentIssue !== null) {
            $artStmt = $pdo->prepare(
                "SELECT
                    a.id,
                    a.title,
                    a.authors,
                    a.abstract,
                    a.document_path,
                    a.created_at,
                    u.first_name AS author_first,
                    u.last_name AS author_last
                 FROM articles a
                 LEFT JOIN users u ON u.id = a.author_id
                 WHERE a.issue_id = :issue_id
                   AND a.status = :status
                 ORDER BY a.created_at DESC, a.id DESC"
            );
            $artStmt->execute([
                ':issue_id' => (int) $currentIssue['id'],
                ':status'   => 'published',
            ]);
            $articles = $artStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        error_log('[OJMS Current Issue DB Error] ' . $e->getMessage());
        $dbUnavailable = true;
    }
} else {
    $dbUnavailable = true;
}

$volParts = [];
if (!empty($currentIssue['volume'])) {
    $volParts[] = 'Volume ' . $currentIssue['volume'];
}
if (!empty($currentIssue['issue_number'])) {
    $volParts[] = 'Issue ' . $currentIssue['issue_number'];
}
$volLabel     = implode(', ', $volParts);
$issueDate    = formatDate($currentIssue['publication_date'] ?? null, 'F Y');
$articleCount = count($articles);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Issue | OJMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/styles.css">
    <link rel="stylesheet" href="./assets/css/current-issue.css">
</head>
<body data-page="current-issue" data-root="./">
    <div id="site-header"></div>

    <main class="current-issue-page">
        <!-- Editorial Issue Hero -->
        <section class="issue-hero" aria-labelledby="issue-title">
            <div class="container issue-hero-content">
                <p class="hero-eyebrow">CURRENT ISSUE</p>
                <h1 id="issue-title">
                    <?php if ($dbUnavailable): ?>
                        Current Issue
                    <?php elseif ($currentIssue): ?>
                        <?= e($currentIssue['title'] ?: 'Current Issue') ?>
                    <?php else: ?>
                        No Current Issue Published
                    <?php endif; ?>
                </h1>

                <div class="issue-meta" id="issue-meta" aria-live="polite">
                    <?php if ($currentIssue && $volLabel !== ''): ?>
                        <span class="issue-badge" id="issue-number"><?= e($volLabel) ?></span>
                    <?php endif; ?>
                    <?php if ($currentIssue && $volLabel !== '' && $issueDate !== ''): ?>
                        <span class="meta-divider" id="meta-divider" aria-hidden="true">&middot;</span>
                    <?php endif; ?>
                    <?php if ($currentIssue && $issueDate !== ''): ?>
                        <time class="issue-date" id="issue-date" datetime="<?= e($currentIssue['publication_date']) ?>">
                            <?= e($issueDate) ?>
                        </time>
                    <?php endif; ?>
                </div>

                <?php if ($currentIssue && !empty($currentIssue['description'])): ?>
                    <p class="issue-description" id="issue-description"><?= e($currentIssue['description']) ?></p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Published Articles (Table of Contents) -->
        <section class="toc-section" aria-labelledby="toc-heading">
            <div class="container">
                <div class="section-header">
                    <div>
                        <p class="section-eyebrow">TABLE OF CONTENTS</p>
                        <h2 id="toc-heading">Published Articles</h2>
                    </div>
                    <?php if ($currentIssue && !$dbUnavailable): ?>
                        <p class="article-count" id="article-count" aria-live="polite">
                            <?= $articleCount === 1 ? '1 article' : $articleCount . ' articles' ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($currentIssue && !$dbUnavailable && !empty($articles)): ?>
                    <div class="article-filters" id="article-filters">
                        <div class="article-search-wrap">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round"
                                aria-hidden="true">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input
                                type="search"
                                id="article-search"
                                class="article-search"
                                placeholder="Search titles, authors or keywords..."
                                aria-label="Search published articles"
                            >
                        </div>

                        <div class="article-filter-controls">
                            <label class="sort-label" for="article-sort">Sort by</label>
                            <select id="article-sort" class="article-sort">
                                <option value="newest">Newest first</option>
                                <option value="oldest">Oldest first</option>
                            </select>

                            <button type="button" id="clear-filters" class="clear-filters">
                                Clear
                            </button>
                        </div>
                    </div>

                    <p class="filter-results-count" id="filter-results-count">
                        Showing <?= $articleCount ?> <?= $articleCount === 1 ? 'article' : 'articles' ?>
                    </p>
                <?php endif; ?>

                <div class="articles-container" id="articles-container" aria-live="polite">
                    <?php if ($dbUnavailable): ?>
                        <div class="state-box">
                            <h3>Unable to load the current issue</h3>
                            <p>We could not retrieve issue data right now. Please try again in a moment.</p>
                        </div>
                    <?php elseif (!$currentIssue): ?>
                        <div class="state-box">
                            <h3>No current issue is available yet</h3>
                            <p>The editorial office has not published a current issue. Please check back soon or browse the archives.</p>
                        </div>
                    <?php elseif (empty($articles)): ?>
                        <div class="state-box">
                            <h3>No published articles in this issue yet</h3>
                            <p>Peer-reviewed articles assigned to this issue will appear here once published.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($articles as $article): ?>
                            <?php
                            $authors = trim((string) ($article['authors'] ?? ''));
                            if ($authors === '') {
                                $authors = trim(($article['author_first'] ?? '') . ' ' . ($article['author_last'] ?? ''));
                            }
                            $pubDate  = formatDate($article['created_at'] ?? null, 'M j, Y');
                            $docPath  = basename(trim((string) ($article['document_path'] ?? '')));
                            $fileHref = $docPath !== '' ? '../ojms_manuscripts/' . $docPath : '';
                            ?>
                            <article class="article-card">
                                <div class="article-info">
                                    <?php if ($pubDate !== ''): ?>
                                        <time class="article-pub-date" datetime="<?= e($article['created_at']) ?>">
                                            <?= e($pubDate) ?>
                                        </time>
                                    <?php endif; ?>
                                    <h3><?= e($article['title']) ?></h3>
                                    <?php if ($authors !== ''): ?>
                                        <p class="article-authors"><?= e($authors) ?></p>
                                    <?php endif; ?>
                                    <p class="article-abstract"><?= e($article['abstract'] ?? '') ?></p>
                                </div>
                                <div class="article-actions">
                                    <?php if ($fileHref !== ''): ?>
                                        <a href="<?= e($fileHref) ?>" class="read-article" target="_blank" rel="noopener noreferrer">
                                            Read Article <span aria-hidden="true">&rarr;</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="read-article-unavailable">Document unavailable</span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Browse Archives CTA -->
        <section class="archives-cta-section" id="archives-cta" aria-labelledby="archives-cta-heading">
            <div class="container">
                <div class="archives-cta-card">
                    <div class="archives-cta-copy">
                        <p class="section-eyebrow">PAST VOLUMES</p>
                        <h2 id="archives-cta-heading">Explore Previous Issues</h2>
                        <p>Browse our complete archive of peer-reviewed volumes and published scholarship.</p>
                    </div>
                    <a href="archives.php" class="btn btn-primary browse-archives">
                        Browse Archives <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <div id="site-footer"></div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('article-search');
        const sortSelect = document.getElementById('article-sort');
        const clearButton = document.getElementById('clear-filters');
        const container = document.getElementById('articles-container');
        const resultsCount = document.getElementById('filter-results-count');

        if (!searchInput || !sortSelect || !container) return;

        const cards = Array.from(container.querySelectorAll('.article-card'));

        function filterArticles() {
            const query = searchInput.value.trim().toLowerCase();
            const sortOrder = sortSelect.value;

            const matchingCards = cards.filter(card => {
                const searchableText = card.textContent.toLowerCase();
                return searchableText.includes(query);
            });

            cards.forEach(card => {
                card.style.display = 'none';
            });

            matchingCards.sort((a, b) => {
                const dateA = new Date(a.querySelector('.article-pub-date')?.getAttribute('datetime') || 0);
                const dateB = new Date(b.querySelector('.article-pub-date')?.getAttribute('datetime') || 0);

                return sortOrder === 'oldest'
                    ? dateA - dateB
                    : dateB - dateA;
            });

            matchingCards.forEach(card => {
                card.style.display = '';
                container.appendChild(card);
            });

            if (resultsCount) {
                const count = matchingCards.length;
                resultsCount.textContent = `Showing ${count} ${count === 1 ? 'article' : 'articles'}`;
            }

            let noResults = document.getElementById('filter-no-results');

            if (matchingCards.length === 0) {
                if (!noResults) {
                    noResults = document.createElement('div');
                    noResults.id = 'filter-no-results';
                    noResults.className = 'state-box filter-no-results';
                    noResults.innerHTML = `
                        <h3>No articles found</h3>
                        <p>Try adjusting your search or clearing the filters.</p>
                    `;
                    container.appendChild(noResults);
                }
            } else if (noResults) {
                noResults.remove();
            }
        }

        searchInput.addEventListener('input', filterArticles);
        sortSelect.addEventListener('change', filterArticles);

        clearButton?.addEventListener('click', function () {
            searchInput.value = '';
            sortSelect.value = 'newest';
            filterArticles();
            searchInput.focus();
        });

        filterArticles();
    });
    </script>

    <script src="./assets/js/layout.js"></script></body>
</html>