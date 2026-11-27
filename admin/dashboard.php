<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// ICT Admin access restriction
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'portal_admin'
) {
    http_response_code(403);
    exit('Access denied.');
}

// Dashboard statistics

// Total manuscripts
$stmt = $pdo->query("SELECT COUNT(*) FROM articles");
$totalManuscripts = (int) $stmt->fetchColumn();

// Pending manuscripts
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM articles
    WHERE status = 'pending_review'
");
$pendingManuscripts = (int) $stmt->fetchColumn();

// Manuscripts currently under review
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM articles
    WHERE status = 'under_review'
");
$underReview = (int) $stmt->fetchColumn();

// Accepted manuscripts
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM articles
    WHERE status = 'accepted'
");
$acceptedManuscripts = (int) $stmt->fetchColumn();

// Rejected manuscripts
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM articles
    WHERE status = 'rejected'
");
$rejectedManuscripts = (int) $stmt->fetchColumn();

// Total reviewers
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'reviewer'
");
$totalReviewers = (int) $stmt->fetchColumn();

// Recent manuscripts

// Manuscript table pagination
$perPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Total manuscripts for pagination
$stmt = $pdo->query("SELECT COUNT(*) FROM articles");
$manuscriptTableTotal = (int) $stmt->fetchColumn();

$totalPages = max(1, (int)ceil($manuscriptTableTotal / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

// Paginated manuscripts
$stmt = $pdo->query("
    SELECT
        a.id,
        a.title,
        a.status,
        a.submitted_at,
        a.created_at,
        u.first_name,
        u.last_name,
        u.email
    FROM articles a
    LEFT JOIN users u ON a.author_id = u.id
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
");

$recentManuscripts = $stmt->fetchAll();


// Admin identity
$adminName = trim(
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '')
);

if ($adminName === '') {
    $adminName = $_SESSION['email'] ?? 'Administrator';
}

function admin_status_label($status) {
    return ucwords(str_replace('_', ' ', $status));
}

function admin_status_class($status) {
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ICT Admin Dashboard | OJMS</title>

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

        .admin-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .admin-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .admin-logo {
            background: #991b1b;
            color: white;
            height: 36px;
            width: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .admin-nav {
            display: flex;
            align-items: center;
            gap: 28px;
            flex-wrap: wrap;
        }

        .admin-nav a {
            font-size: 14px;
            color: #475569;
        }

        .admin-nav a.active {
            color: #991b1b;
            font-weight: 600;
        }

        .admin-nav a:hover {
            color: #991b1b;
        }

        .admin-nav form {
            margin: 0;
        }

        .admin-nav button {
            background: none;
            border: none;
            font: inherit;
            color: #475569;
            cursor: pointer;
        }

        .admin-nav button:hover {
            color: #991b1b;
        }

        .admin-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .admin-heading {
            margin-bottom: 30px;
        }

        .admin-heading h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .admin-heading p {
            color: #64748b;
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 24px;
        }

        .stat-label {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 700;
        }

        .stat-card.total {
            border-top: 3px solid #334155;
        }

        .stat-card.pending {
            border-top: 3px solid #b45309;
        }

        .stat-card.review {
            border-top: 3px solid #2563eb;
        }

        .stat-card.accepted {
            border-top: 3px solid #15803d;
        }

        .stat-card.rejected {
            border-top: 3px solid #b91c1c;
        }

        .manuscript-section {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
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
            margin: 0;
            font-size: 20px;
        }

        .reviewer-count {
            background: #f1f5f9;
            padding: 9px 14px;
            border-radius: 6px;
            font-size: 14px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            text-align: left;
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
        }

        td {
            color: #334155;
        }

        .manuscript-title {
            font-weight: 500;
            max-width: 300px;
        }

        .author-cell {
            white-space: nowrap;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            white-space: nowrap;
        }

        .status-pending {
            color: #9a3412;
            background: #fff7ed;
            border: 1px solid #fed7aa;
        }

        .status-review {
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .status-accepted {
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .status-rejected {
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .admin-footer {
            background: #1e293b;
            color: #fff;
            padding: 35px 30px;
            margin-top: 50px;
        }

        .footer-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .footer-brand h2 {
            margin: 0 0 5px;
        }

        .footer-brand p {
            margin: 0;
            color: #cbd5e1;
            font-size: 14px;
        }

        .footer-copy {
            margin-top: 30px;
            font-size: 12px;
            color: #cbd5e1;
        }

        @media (max-width: 1100px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 700px) {
            .admin-header {
                padding: 18px;
                align-items: flex-start;
            }

            .admin-nav {
                width: 100%;
                gap: 18px;
            }

            .admin-main {
                padding: 28px 18px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .stat-card {
                padding: 18px;
            }

            .stat-value {
                font-size: 24px;
            }

            .section-header {
                padding: 18px;
            }
        }

        @media (max-width: 450px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

/* ================================
   ICT ADMIN NAVBAR
================================ */

.admin-header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px clamp(24px, 5vw, 80px);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.admin-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    flex-shrink: 0;
    text-decoration: none;
}

.admin-logo {
    background: #991b1b;
    color: white;
    height: 42px;
    width: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
}

.admin-brand-text {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
}

.admin-nav {
    display: flex;
    align-items: center;
    gap: 24px;
}

.admin-nav > a:not(.admin-profile-pill) {
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    text-decoration: none;
    transition: color 0.2s;
}

.admin-nav > a:not(.admin-profile-pill):hover,
.admin-nav > a:not(.admin-profile-pill).active {
    color: #991b1b;
}

.admin-profile-pill {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 9px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    max-width: 220px;
}

.admin-profile-pill span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-profile-pill svg {
    flex-shrink: 0;
}

.admin-logout-form {
    margin: 0;
}

.admin-logout-btn {
    border: none;
    background: #fef2f2;
    color: #991b1b;
    padding: 10px 16px;
    border-radius: 999px;
    cursor: pointer;
    font: 600 14px Inter, sans-serif;
    transition: background 0.2s;
}

.admin-logout-btn:hover {
    background: #fee2e2;
}

/* HAMBURGER */

.admin-menu-toggle {
    display: none;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 44px;
    height: 44px;
    padding: 11px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
}

.admin-menu-toggle span {
    display: block;
    width: 20px;
    height: 2px;
    background: #1e293b;
    border-radius: 2px;
}

/* FIXED NAVBAR SPACING */

body {
    padding-top: 78px;
}

/* ================================
   RESPONSIVE NAVBAR
================================ */

@media (max-width: 900px) {

    .admin-header {
        padding: 14px 24px;
    }

    .admin-menu-toggle {
        display: flex;
        flex-shrink: 0;
    }

    .admin-nav {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 24px;
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
    }

    .admin-nav.is-open {
        display: flex;
    }

    .admin-nav > a {
        width: 100%;
    }

    .admin-profile-pill {
        max-width: 100%;
        width: fit-content;
    }

    .admin-logout-form {
        width: 100%;
    }

    .admin-logout-btn {
        width: 100%;
    }

    .admin-main {
        padding: 28px 18px;
    }
}

@media (max-width: 480px) {

    .admin-header {
        padding: 12px 18px;
    }

    .admin-brand-text {
        font-size: 14px;
    }

    .admin-logo {
        width: 38px;
        height: 38px;
    }
}

/* SIMPLE FOOTER */

.admin-footer.admin-footer-simple {
    width: 100%;
    margin-top: 50px;
    padding: 24px clamp(24px, 5vw, 80px);
    background: #1e293b;
    color: #cbd5e1;
    border-top: none;
    text-align: center;
}

.admin-footer.admin-footer-simple p {
    margin: 0;
    font-size: 13px;
    color: #cbd5e1;
}

/* ================================
   DASHBOARD STAT CARDS
================================ */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 32px;
}

.stat-card {
    position: relative;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 24px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.stat-label {
    color: #64748b;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 18px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
    letter-spacing: -1px;
}

.stat-card.total {
    border-top: 3px solid #334155;
}

.stat-card.pending {
    border-top: 3px solid #d97706;
}

.stat-card.review {
    border-top: 3px solid #2563eb;
}

.stat-card.accepted {
    border-top: 3px solid #16a34a;
}

.stat-card.rejected {
    border-top: 3px solid #dc2626;
}

/* STAT CARDS RESPONSIVENESS */

@media (max-width: 1100px) {
    .stats-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .stat-card {
        padding: 20px;
    }

    .stat-value {
        font-size: 26px;
    }
}

@media (max-width: 450px) {
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .stat-card {
        padding: 16px;
    }

    .stat-label {
        font-size: 12px;
        margin-bottom: 14px;
    }

    .stat-value {
        font-size: 24px;
    }
}


/* ================================
   MANUSCRIPT PAGINATION
================================ */

.admin-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    padding: 20px 24px;
    border-top: 1px solid #e2e8f0;
    background: #fff;
}

.pagination-info {
    font-size: 13px;
    color: #64748b;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pagination-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #334155;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: 0.2s ease;
}

.pagination-btn:hover:not(.disabled) {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pagination-btn.disabled {
    color: #94a3b8;
    background: #f8fafc;
    cursor: not-allowed;
    opacity: 0.7;
}

.pagination-current {
    font-size: 13px;
    color: #475569;
    font-weight: 500;
}

/* MOBILE PAGINATION */

@media (max-width: 600px) {
    .admin-pagination {
        padding: 18px;
        flex-direction: column;
        align-items: flex-start;
    }

    .pagination-controls {
        width: 100%;
        justify-content: space-between;
        flex-wrap: wrap;
    }
}
.admin-nav .admin-logout-form .admin-logout-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 18px;
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
    border-radius: 999px;
    font: 600 14px Inter, sans-serif;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.2s ease, border-color 0.2s ease;
}

.admin-nav .admin-logout-form .admin-logout-btn:hover {
    background: #fee2e2;
    border-color: #fca5a5;
}

/* ADMIN PAGE CONTENT ALIGNMENT */

.admin-main,
.main {
    width: 100%;
    max-width: 1440px;
    margin-left: auto;
    margin-right: auto;
    padding-left: clamp(24px, 5vw, 80px);
    padding-right: clamp(24px, 5vw, 80px);
}

@media (max-width: 900px) {
    .admin-main,
    .main {
        padding-left: 24px;
        padding-right: 24px;
    }
}

@media (max-width: 480px) {
    .admin-main,
    .main {
        padding-left: 18px;
        padding-right: 18px;
    }
}

    </style>
</head>

<body>


<header class="admin-header">

    <a href="dashboard.php" class="admin-brand">
        <span class="admin-logo">OJ</span>
        <span class="admin-brand-text">Admin Dashboard</span>
    </a>

    <button type="button"
        class="admin-menu-toggle"
        id="admin-menu-toggle"
        aria-label="Toggle navigation"
        aria-expanded="false"
        aria-controls="admin-nav">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <nav class="admin-nav" id="admin-nav" aria-label="Admin navigation">

        <a class="active" href="dashboard.php">Dashboard</a>
        <a href="reviewers.php">Manage Accounts</a>
        <a href="users.php">Users</a>

        <a href="settings.php" class="admin-profile-pill">
            <svg xmlns="http://www.w3.org/2000/svg"
                width="18" height="18" viewBox="0 0 24 24"
                fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"/>
                <path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/>
            </svg>
            <span><?= e($adminName) ?></span>
        </a>

        <form action="../auth/logout.php" method="post" class="admin-logout-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="admin-logout-btn">Log out</button>
        </form>

    </nav>

</header>


<main class="admin-main">

    <section class="admin-heading">
        <h1>All submissions</h1>
        <p>Monitor manuscript submissions across the journal.</p>
    </section>

    <section class="stats-grid">

        <div class="stat-card total">
            <div class="stat-label">Total submissions</div>
            <div class="stat-value"><?= $totalManuscripts ?></div>
        </div>

        <div class="stat-card pending">
            <div class="stat-label">Pending processing</div>
            <div class="stat-value"><?= $pendingManuscripts ?></div>
        </div>

        <div class="stat-card review">
            <div class="stat-label">Under review</div>
            <div class="stat-value"><?= $underReview ?></div>
        </div>

        <div class="stat-card accepted">
            <div class="stat-label">Accepted</div>
            <div class="stat-value"><?= $acceptedManuscripts ?></div>
        </div>

        <div class="stat-card rejected">
            <div class="stat-label">Rejected</div>
            <div class="stat-value"><?= $rejectedManuscripts ?></div>
        </div>

    </section>

    <section class="manuscript-section">

        <div class="section-header">

            <h2>All submissions</h2>

            <a href="reviewers.php" class="reviewer-count">
                <?= $totalReviewers ?> reviewers
            </a>

        </div>

        <div class="table-wrapper">

            <?php if (empty($recentManuscripts)): ?>

                <div class="empty-state">
                    <p>No manuscripts have been submitted yet.</p>
                </div>

            <?php else: ?>

                <table>

                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Date submitted</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($recentManuscripts as $article): ?>

                            <tr>

                                <td class="manuscript-title">
                                    <?= e($article['title']) ?>
                                </td>

                                <td class="author-cell">
                                    <?= e(trim(
                                        ($article['first_name'] ?? '') . ' ' .
                                        ($article['last_name'] ?? '')
                                    ) ?: 'Unknown author') ?>
                                </td>

                                <td>
                                    <?= e(date(
                                        'd M Y',
                                        strtotime(
                                            $article['submitted_at'] ??
                                            $article['created_at']
                                        )
                                    )) ?>
                                </td>

                                <td>
                                    <span class="status <?= e(admin_status_class($article['status'])) ?>">
                                        <?= e(admin_status_label($article['status'])) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>


<?php if ($manuscriptTableTotal > 0): ?>

    <div class="admin-pagination">

        <div class="pagination-info">
            Showing
            <?= $offset + 1 ?>
            –
            <?= min($offset + $perPage, $manuscriptTableTotal) ?>
            of
            <?= $manuscriptTableTotal ?>
            manuscripts
        </div>

        <div class="pagination-controls">

            <?php if ($currentPage > 1): ?>
                <a href="?page=<?= $currentPage - 1 ?>"
                   class="pagination-btn">
                    &larr; Previous
                </a>
            <?php else: ?>
                <span class="pagination-btn disabled">
                    &larr; Previous
                </span>
            <?php endif; ?>

            <span class="pagination-current">
                Page <?= $currentPage ?> of <?= $totalPages ?>
            </span>

            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?= $currentPage + 1 ?>"
                   class="pagination-btn">
                    Next &rarr;
                </a>
            <?php else: ?>
                <span class="pagination-btn disabled">
                    Next &rarr;
                </span>
            <?php endif; ?>

        </div>

    </div>

<?php endif; ?>

    </section>

</main>


<footer class="admin-footer admin-footer-simple">
    <p>&copy; <?= date('Y') ?> OJMS. All rights reserved.</p>
</footer>


<script>
const adminToggle = document.getElementById('admin-menu-toggle');
const adminNav = document.getElementById('admin-nav');

if (adminToggle && adminNav) {
    adminToggle.addEventListener('click', function() {
        const isOpen = adminNav.classList.toggle('is-open');
        adminToggle.setAttribute('aria-expanded', isOpen);
    });

    adminNav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function() {
            adminNav.classList.remove('is-open');
            adminToggle.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('click', function(event) {
        if (!adminNav.contains(event.target) &&
            !adminToggle.contains(event.target)) {
            adminNav.classList.remove('is-open');
            adminToggle.setAttribute('aria-expanded', 'false');
        }
    });
}
</script>

</body>
</html>