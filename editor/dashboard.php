<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Restrict access to Editor-in-Chief
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'editor_in_chief'
) {
    http_response_code(403);
    exit('Access denied.');
}

$eicName = trim(
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '')
);

if ($eicName === '') {
    $eicName = $_SESSION['email'] ?? 'Editor-in-Chief';
}

$totalManuscripts = 0;
$pendingReview = 0;
$underReview = 0;
$accepted = 0;
$rejected = 0;
$published = 0;
$recentManuscripts = [];

try {
    // Manuscript statistics
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'pending_review') AS pending_review,
            SUM(status = 'under_review') AS under_review,
            SUM(status = 'accepted') AS accepted,
            SUM(status = 'rejected') AS rejected,
            SUM(status = 'published') AS published
        FROM articles
    ");

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalManuscripts = (int) ($stats['total'] ?? 0);
    $pendingReview = (int) ($stats['pending_review'] ?? 0);
    $underReview = (int) ($stats['under_review'] ?? 0);
    $accepted = (int) ($stats['accepted'] ?? 0);
    $rejected = (int) ($stats['rejected'] ?? 0);
    $published = (int) ($stats['published'] ?? 0);

    // Recent manuscripts
    $stmt = $pdo->query("
        SELECT
            id,
            title,
            authors,
            status,
            submitted_at
        FROM articles
        ORDER BY submitted_at DESC
        LIMIT 5
    ");

    $recentManuscripts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('EIC dashboard error: ' . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor-in-Chief Dashboard | OJMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, sans-serif;
            background: #f8f7f5;
            color: #1e293b;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .logo {
            width: 38px;
            height: 38px;
            background: #991b1b;
            color: white;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .nav a,
        .nav button {
            font-size: 14px;
            color: #475569;
        }

        .nav a.active {
            color: #991b1b;
            font-weight: 600;
        }

        .nav a:hover,
        .nav button:hover {
            color: #991b1b;
        }

        .nav form {
            margin: 0;
        }

        .nav button {
            background: none;
            border: none;
            cursor: pointer;
            font: inherit;
        }

        .main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .welcome {
            margin-bottom: 35px;
        }

        .welcome h1 {
            font-size: 28px;
            margin: 0 0 10px;
        }

        .welcome p {
            margin: 0;
            color: #64748b;
            font-size: 15px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .stat-top p {
            margin: 0;
            font-size: 14px;
            color: #64748b;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 600;
        }

        .icon-burgundy {
            background: #fef2f2;
            color: #991b1b;
        }

        .icon-amber {
            background: #fffbeb;
            color: #b45309;
        }

        .icon-blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .icon-green {
            background: #f0fdf4;
            color: #15803d;
        }

        .icon-purple {
            background: #faf5ff;
            color: #7e22ce;
        }

        .icon-gray {
            background: #f1f5f9;
            color: #475569;
        }

        .stat-card h2 {
            font-size: 32px;
            margin: 0;
            font-weight: 700;
        }

        .dashboard-section {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .section-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .section-header h2 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .section-header p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .view-all {
            color: #991b1b;
            font-size: 14px;
            font-weight: 600;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th {
            background: #f8fafc;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            text-align: left;
            padding: 14px 24px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        td {
            padding: 18px 24px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #fcfcfd;
        }

        .manuscript-title {
            font-weight: 600;
            color: #1e293b;
            line-height: 1.5;
            min-width: 250px;
        }

        .authors-cell {
            color: #64748b;
            max-width: 250px;
        }

        .status-badge {
            display: inline-flex;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-pending_review {
            background: #fef3c7;
            color: #92400e;
        }

        .status-under_review {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-accepted {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-published {
            background: #d1fae5;
            color: #065f46;
        }

        .status-draft {
            background: #f1f5f9;
            color: #475569;
        }

        .action-link {
            display: inline-flex;
            padding: 8px 13px;
            background: #991b1b;
            color: white;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
        }

        .action-link:hover {
            background: #7f1d1d;
        }

        .empty-state {
            padding: 55px 25px;
            text-align: center;
            color: #64748b;
        }

        .empty-state h3 {
            color: #334155;
            margin-bottom: 10px;
        }

        .footer {
            background: #1e293b;
            color: white;
            padding: 35px 30px;
            margin-top: 50px;
        }

        .footer-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            gap: 30px;
            flex-wrap: wrap;
        }

        .footer-inner p {
            color: #cbd5e1;
            font-size: 14px;
        }

        .footer-copy {
            max-width: 1400px;
            margin: 30px auto 0;
            color: #cbd5e1;
            font-size: 12px;
        }

        @media (max-width: 1000px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .header {
                padding: 18px;
            }

            .nav {
                width: 100%;
                gap: 18px;
            }

            .main {
                padding: 28px 18px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .section-header {
                padding: 20px;
            }
        }

        /* ===== EDITOR PORTAL REDESIGN ===== */

* {
    box-sizing: border-box;
}

html, body {
    margin: 0;
    width: 100%;
    min-height: 100%;
}

body {
    font-family: Inter, sans-serif;
    background: #f8f7f5;
    color: #1e293b;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    overflow-x: clip;
}

a {
    text-decoration: none !important;
}

/* NAVBAR */

.navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px clamp(24px, 5vw, 80px);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
}

.editor-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
    color: #1e293b;
}

.editor-logo {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: #991b1b;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}

.editor-brand-text {
    font-weight: 600;
    font-size: 15px;
}

.editor-nav {
    display: flex;
    align-items: center;
    gap: 24px;
}

.editor-nav > a {
    font-size: 14px;
    color: #475569;
    white-space: nowrap;
    transition: color 0.2s ease;
}

.editor-nav > a:hover,
.editor-nav > a.active {
    color: #991b1b;
}

.editor-nav > a.active {
    font-weight: 600;
}

.editor-profile-pill {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 9px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 50px;
    color: #334155 !important;
    max-width: 220px;
}

.editor-profile-pill span {
    overflow: hidden;
    text-overflow: ellipsis;
}

.editor-profile-pill:hover {
    background: #f1f5f9;
}

.editor-logout-form {
    margin: 0;
}

.editor-logout-btn {
    border: none;
    border-radius: 50px;
    padding: 10px 18px;
    background: #991b1b;
    color: #fff;
    font-family: inherit;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease;
}

.editor-logout-btn:hover {
    background: #7f1d1d;
}

.editor-menu-toggle {
    display: none;
    width: 42px;
    height: 42px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    padding: 10px;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
}

.editor-menu-toggle span {
    display: block;
    width: 100%;
    height: 2px;
    background: #334155;
    border-radius: 2px;
}

/* MAIN CONTENT */

.main {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 130px clamp(24px, 5vw, 80px) 60px;
    flex: 1;
}

.welcome {
    margin-bottom: 35px;
}

.welcome h1 {
    font-size: 28px;
    line-height: 1.3;
}

.welcome p {
    line-height: 1.6;
}

/* STAT CARDS */

.stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    padding: 24px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    transition: border-color 0.2s ease;
}

.stat-card:hover {
    border-color: #cbd5e1;
}

.stat-top {
    margin-bottom: 12px;
}

.stat-top p {
    font-size: 14px;
    color: #64748b;
}

.stat-card h2 {
    font-size: 32px;
}

/* MANUSCRIPT SECTION */

.dashboard-section {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    margin-bottom: 30px;
}

.section-header {
    padding: 24px;
}

.section-header h2 {
    font-size: 20px;
}

.view-all {
    color: #991b1b;
}

.view-all:hover {
    color: #7f1d1d;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
}

th {
    background: #f8fafc;
}

.action-link {
    background: #991b1b;
    border-radius: 7px;
}

.action-link:hover {
    background: #7f1d1d;
}

/* FOOTER */

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
/* TABLET */

@media (max-width: 1000px) {
    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .editor-nav {
        gap: 16px;
    }
}

/* MOBILE NAV */

@media (max-width: 768px) {
    .navbar {
        padding: 14px 24px;
    }

    .editor-menu-toggle {
        display: flex;
    }

    .editor-nav {
        display: none;
        position: absolute;
        top: calc(100% + 1px);
        left: 0;
        right: 0;
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
        padding: 20px 24px;
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .editor-nav.is-open {
        display: flex;
    }

    .editor-nav > a {
        padding: 12px 0;
    }

    .editor-profile-pill {
        width: fit-content;
        max-width: 100%;
    }

    .editor-logout-form {
        width: 100%;
    }

    .editor-logout-btn {
        width: 100%;
    }

    .main {
        padding: 110px 24px 40px;
    }

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* SMALL MOBILE */

@media (max-width: 600px) {
    .stats {
        grid-template-columns: 1fr;
    }

    .welcome h1 {
        font-size: 24px;
    }

    .section-header {
        padding: 20px;
    }

    .main {
        padding-left: 18px;
        padding-right: 18px;
    }

    .navbar {
        padding-left: 18px;
        padding-right: 18px;
    }

    .editor-brand-text {
        font-size: 14px;
    }

    .footer.footer-simple {
        padding: 22px 18px;
    }
}

@media (max-width: 400px) {
    .editor-logo {
        width: 36px;
        height: 36px;
    }

    .editor-brand {
        gap: 8px;
    }
}
    </style>
</head>

<body>

<header class="navbar">
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

<nav class="editor-nav" id="editor-nav">
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="manuscripts.php">Manuscripts</a>
    <a href="decisions.php">Editorial Decisions</a>
    <a href="issues.php">Issue Management</a>

    <a href="settings.php" class="editor-profile-pill">
        <svg xmlns="http://www.w3.org/2000/svg"
            width="18" height="18" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="1.8"
            stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"></circle>
            <path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6"></path>
        </svg>
        <span><?= e($eicName) ?></span>
    </a>

    <form action="../auth/logout.php" method="post" class="editor-logout-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="editor-logout-btn">Log out</button>
    </form>
</nav>
</header>

<main class="main">

    <section class="welcome">
        <h1>Welcome, <?= e($eicName) ?></h1>
        <p>Oversee manuscripts, editorial decisions and journal publication activities.</p>
    </section>

    <section class="stats">

        <div class="stat-card">
            <div class="stat-top">
                <p>Total manuscripts</p>
            </div>
            <h2><?= $totalManuscripts ?></h2>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <p>Pending review</p>
            </div>
            <h2><?= $pendingReview ?></h2>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <p>Under review</p>
            </div>
            <h2><?= $underReview ?></h2>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <p>Accepted</p>
            </div>
            <h2><?= $accepted ?></h2>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <p>Rejected</p>
            </div>
            <h2><?= $rejected ?></h2>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <p>Published</p>
            </div>
            <h2><?= $published ?></h2>
        </div>

    </section>

    <section class="dashboard-section">

        <div class="section-header">
            <div>
                <h2>Recent manuscripts</h2>
                <p>Latest manuscripts submitted to the journal.</p>
            </div>

            <a href="manuscripts.php" class="view-all">View all manuscripts</a>
        </div>

        <?php if (empty($recentManuscripts)): ?>

            <div class="empty-state">
                <h3>No manuscripts available</h3>
                <p>Submitted manuscripts will appear here.</p>
            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>Manuscript</th>
                            <th>Authors</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($recentManuscripts as $manuscript): ?>

                        <?php
                            $status = strtolower($manuscript['status'] ?? 'draft');
                            $statusClass = 'status-' . preg_replace('/[^a-z0-9_-]/', '', $status);
                        ?>

                        <tr>

                            <td>
                                <div class="manuscript-title">
                                    <?= e($manuscript['title']) ?>
                                </div>
                            </td>

                            <td class="authors-cell">
                                <?= e($manuscript['authors'] ?? 'Not specified') ?>
                            </td>

                            <td>
                                <?= !empty($manuscript['submitted_at'])
                                    ? e(date('M d, Y', strtotime($manuscript['submitted_at'])))
                                    : 'Not specified' ?>
                            </td>

                            <td>
                                <span class="status-badge <?= e($statusClass) ?>">
                                    <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                                </span>
                            </td>

                            <td>
                                <a href="manuscript.php?id=<?= (int)$manuscript['id'] ?>" class="action-link">
                                    View
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer class="footer footer-simple">
<p>&copy; <?= date('Y') ?> OJMS. All rights reserved.</p>
</footer>

<script>
    const editorToggle = document.getElementById('editor-menu-toggle');
    const editorNav = document.getElementById('editor-nav');

    if (editorToggle && editorNav) {
        editorToggle.addEventListener('click', function () {
            const isOpen = editorNav.classList.toggle('is-open');
            editorToggle.setAttribute('aria-expanded', isOpen);
        });

        editorNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function () {
                editorNav.classList.remove('is-open');
                editorToggle.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('click', function (event) {
            if (
                !editorNav.contains(event.target) &&
                !editorToggle.contains(event.target)
            ) {
                editorNav.classList.remove('is-open');
                editorToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
</script>

</body>
</html>