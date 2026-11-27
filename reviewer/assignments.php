<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reviewer') {
    http_response_code(403);
    exit('Access denied.');
}

$reviewerName = trim(
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '')
);

if ($reviewerName === '') {
    $reviewerName = $_SESSION['email'] ?? 'Reviewer';
}
$assignments = [];
$totalAssigned = 0;
$completedCount = 0;
$pendingCount = 0;

try {
    $stmt = $pdo->prepare("
        SELECT
            ra.id AS assignment_id,
            ra.status AS assignment_status,
            ra.assigned_at,
            a.id AS article_id,
            a.title,
            a.authors,
            a.abstract,
            a.submitted_at
        FROM reviewer_assignments ra
        INNER JOIN articles a ON ra.article_id = a.id
        WHERE ra.reviewer_id = ?
        ORDER BY ra.assigned_at DESC
    ");

    $stmt->execute([$_SESSION['user_id']]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalAssigned = count($assignments);

    foreach ($assignments as $assignment) {
        if ($assignment['assignment_status'] === 'Completed') {
            $completedCount++;
        } elseif ($assignment['assignment_status'] === 'Pending') {
            $pendingCount++;
        }
    }

} catch (PDOException $e) {
    error_log("Reviewer assignments error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Manuscripts | Reviewer Portal</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .navbar {
            background: #fff;
            padding: 18px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
            flex-wrap: wrap;
            gap: 15px;
        }

        .brand {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: #374151;
            font-size: 14px;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #991b1b;
        }

        .nav-links a.active {
            font-weight: 600;
        }

        .logout-form {
            margin: 0;
        }

        .logout-btn {
            border: none;
            background: #fee2e2;
            color: #991b1b;
            padding: 9px 14px;
            border-radius: 6px;
            cursor: pointer;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 40px auto;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            margin-bottom: 8px;
            font-size: 28px;
        }

        .page-header p {
            color: #6b7280;
            margin: 0;
        }

        .summary-card {
            background: #fff;
            padding: 24px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            margin-bottom: 25px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: #fff;
            padding: 24px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            margin-bottom: 0;
        }

        .summary-card h3 {
            margin: 0 0 12px;
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
        }

        .summary-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #1e3a8a;
        }

        .summary-card.completed-card .number {
            color: #15803d;
        }

        .summary-card.pending-card .number {
            color: #b45309;
        }

        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        .assignment-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .assignment-card h2 {
            font-size: 20px;
            margin: 0 0 15px;
        }

        .meta {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .abstract {
            color: #4b5563;
            line-height: 1.7;
            margin-top: 18px;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            background: #fef3c7;
            color: #92400e;
            margin-top: 12px;
        }

        .status.completed {
            background: #dcfce7;
            color: #166534;
        }

        .status.other {
            background: #e5e7eb;
            color: #374151;
        }

        .review-btn {
            display: inline-block;
            margin-top: 20px;
            background: #1e3a8a;
            color: #fff;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 6px;
            font-size: 14px;
        }

        .review-btn:hover {
            background: #1e40af;
        }

        .empty-state {
            background: #fff;
            border: 1px solid #e5e7eb;
            padding: 50px 25px;
            text-align: center;
            border-radius: 10px;
        }

        .empty-state h2 {
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #6b7280;
        }
        .assignment-table-wrapper {
            overflow-x: auto;
        }

        .assignment-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        .assignment-table th,
        .assignment-table td {
            padding: 18px 24px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .assignment-table th {
            background: #f8fafc;
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .assignment-table td {
            font-size: 14px;
        }

        .manuscript-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .manuscript-authors {
            color: #64748b;
            font-size: 13px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.completed {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.other {
            background: #e2e8f0;
            color: #475569;
        }

        .review-action {
            display: inline-block;
            background: #991b1b;
            color: white;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
        }

        .review-action:hover {
            background: #7f1d1d;
        }

        .table-empty {
            text-align: center;
            padding: 50px 20px;
            color: #64748b;
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 18px 5%;
            }

            .nav-links {
                gap: 12px;
            }

            .container {
                width: 92%;
            }

            .assignment-card {
                padding: 18px;
            }
        }
        .footer {
    background: #1e293b;
    color: white;
    padding: 35px 30px;
    margin-top: 60px;
}

.footer-inner {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 30px;
    flex-wrap: wrap;
}

.footer-inner h2 {
    margin: 0 0 8px;
    font-size: 22px;
}

.footer-inner p {
    margin: 0;
    color: #cbd5e1;
    font-size: 14px;
}

.footer-copy {
    max-width: 1200px;
    margin: 25px auto 0;
    padding-top: 20px;
    border-top: 1px solid #334155;
    color: #cbd5e1;
    font-size: 12px;
}

.main {
    min-height: 60vh;
}

/* ========================================
   REVIEWER ASSIGNMENTS - DESIGN UPDATE
======================================== */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
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

/* REMOVE UNDERLINES */

a,
a:hover,
a:focus,
a:active {
    text-decoration: none !important;
}

/* NAVBAR */

.navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;

    padding: 20px clamp(24px, 5vw, 80px);
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(12px);

    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: nowrap;

    border-bottom: 1px solid #e2e8f0;
    gap: 24px;
}

/* BRAND */

.reviewer-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.reviewer-logo {
    width: 40px;
    height: 40px;
    background: #991b1b;
    color: white;
    border-radius: 8px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 12px;
    font-weight: 700;
}

.reviewer-brand-text {
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
}

/* NAV LINKS */

.nav-links {
    display: flex;
    align-items: center;
    gap: 25px;
    flex-wrap: nowrap;
}

.nav-links a {
    font-size: 14px;
    color: #475569;
    transition: color 0.2s ease;
}

.nav-links a:hover,
.nav-links a.active {
    color: #991b1b;
}

.nav-links a.active {
    font-weight: 600;
}

.logout-form {
    margin: 0;
}

/* PROFILE PILL */

.reviewer-profile-pill {
    display: inline-flex;
    align-items: center;
    gap: 9px;

    padding: 9px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;

    background: #f8fafc;
    color: #334155 !important;

    font-size: 13px !important;
    font-weight: 500;
    transition: background 0.2s ease;
}

.reviewer-profile-pill:hover {
    background: #f1f5f9;
}

.reviewer-profile-icon {
    flex-shrink: 0;
}

/* LOGOUT PILL */

.logout-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    border: 1px solid #991b1b;
    background: #991b1b;
    color: white;

    padding: 10px 20px;
    border-radius: 999px;

    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.logout-btn:hover {
    background: #7f1d1d;
    color: white;
}

/* HAMBURGER */

.reviewer-menu-toggle {
    display: none;
    width: 42px;
    height: 42px;
    padding: 10px;

    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: white;
    cursor: pointer;
}

.reviewer-menu-toggle span {
    display: block;
    width: 20px;
    height: 2px;
    background: #1e293b;
    border-radius: 2px;
    margin: 4px auto;
}

/* CONTENT LANE */

.container {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 130px clamp(24px, 5vw, 80px) 60px;
    flex: 1;
}

/* PAGE HEADER */

.page-header {
    margin-bottom: 35px;
}

.page-header h1 {
    font-size: clamp(25px, 3vw, 32px);
    font-weight: 700;
    letter-spacing: -0.7px;
    color: #1e293b;
    margin: 0 0 10px;
}

.page-header p {
    font-size: 15px;
    line-height: 1.7;
    color: #64748b;
}

/* SUMMARY CARDS */

.summary-cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
    margin-bottom: 40px;
}

.summary-card {
    background: white;
    padding: 30px;
    border-radius: 16px;
    border: 1px solid #e8e8e8;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.035);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.07);
}

.summary-card h3 {
    margin: 0 0 18px;
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
}

.summary-card .number {
    font-size: 36px;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -1px;
}

.summary-card.completed-card .number {
    color: #15803d;
}

.summary-card.pending-card .number {
    color: #b45309;
}

/* ASSIGNMENT TABLE */

.assignment-table-wrapper {
    background: white;
    border: 1px solid #e8e8e8;
    border-radius: 16px;
    overflow-x: auto;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.035);
}

.assignment-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

.assignment-table th,
.assignment-table td {
    padding: 22px 26px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

.assignment-table th {
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.assignment-table td {
    font-size: 14px;
}

.assignment-table tbody tr {
    transition: background 0.2s ease;
}

.assignment-table tbody tr:hover {
    background: #fafafa;
}

.assignment-table tbody tr:last-child td {
    border-bottom: none;
}

.manuscript-title {
    font-weight: 600;
    color: #1e293b;
    line-height: 1.6;
    overflow-wrap: break-word;
}

.manuscript-authors {
    color: #64748b;
    font-size: 13px;
    margin-top: 6px;
}

/* STATUS */

.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 8px 14px;
    border-radius: 999px;

    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;

    background: #fef3c7;
    color: #92400e;
}

.status-badge.completed {
    background: #dcfce7;
    color: #166534;
}

.status-badge.other {
    background: #e2e8f0;
    color: #475569;
}

/* REVIEW BUTTON */

.review-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    background: #991b1b;
    color: white;

    padding: 11px 18px;
    border-radius: 999px;

    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;

    transition: all 0.2s ease;
}

.review-action:hover {
    background: #7f1d1d;
    color: white;
    transform: translateY(-1px);
}

/* EMPTY STATE */

.table-empty,
.empty-state {
    background: white;
    border: 1px solid #e8e8e8;
    padding: 60px 30px;
    text-align: center;
    border-radius: 16px;
    color: #64748b;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.035);
}

.table-empty h3,
.table-empty h2 {
    color: #1e293b;
    margin-bottom: 10px;
}

/* FOOTER */

.footer.footer-simple {
    width: 100%;
    margin-top: auto;
    margin-bottom: 0;
    padding: 22px 24px;

    background: #1e293b;
    color: #cbd5e1;

    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;

    flex-shrink: 0;
}

.footer-simple p {
    margin: 0;
    color: #cbd5e1;
    font-size: 13px;
}

/* TABLET */

@media (max-width: 900px) {
    .summary-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .summary-card:last-child {
        grid-column: 1 / -1;
    }
}

/* MOBILE NAV */

@media (max-width: 768px) {

    .navbar {
        padding: 16px 24px;
    }

    .reviewer-menu-toggle {
        display: block;
    }

    .nav-links {
        position: absolute;
        top: calc(100% + 1px);
        left: 0;
        right: 0;

        width: 100%;
        padding: 20px 24px;

        background: white;
        border-bottom: 1px solid #e2e8f0;
        box-shadow: 0 12px 25px rgba(15, 23, 42, 0.06);

        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 18px;

        visibility: hidden;
        opacity: 0;
        pointer-events: none;
        transform: translateY(-8px);

        transition: opacity 0.2s ease,
                    transform 0.2s ease,
                    visibility 0.2s ease;
    }

    .nav-links.is-open {
        visibility: visible;
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
    }

    .nav-links > a:not(.reviewer-profile-pill) {
        padding: 5px 0;
    }

    .nav-links .reviewer-profile-pill {
        width: fit-content;
    }

    .nav-links form {
        width: 100%;
    }

    .logout-btn {
        width: fit-content;
    }

    .container {
        padding: 115px 24px 45px;
    }

    .assignment-table-wrapper {
        border-radius: 12px;
    }
}

/* SMALL MOBILE */

@media (max-width: 600px) {

    .summary-cards {
        grid-template-columns: 1fr;
    }

    .summary-card:last-child {
        grid-column: auto;
    }

    .summary-card {
        padding: 25px;
    }

    .container {
        padding-left: 18px;
        padding-right: 18px;
    }

    .page-header h1 {
        font-size: 25px;
    }
}

@media (max-width: 400px) {

    .navbar {
        padding: 15px 18px;
    }

    .reviewer-brand {
        gap: 9px;
    }

    .reviewer-brand-text {
        font-size: 13px;
    }

    .reviewer-logo {
        width: 36px;
        height: 36px;
    }

    .reviewer-menu-toggle {
        width: 40px;
        height: 40px;
    }
}


    </style>
</head>

<body>


<nav class="navbar">
    <a href="dashboard.php" class="reviewer-brand" aria-label="Reviewer dashboard">
        <span class="reviewer-logo">OJ</span>
        <span class="reviewer-brand-text">Reviewer Dashboard</span>
    </a>

    <button
        type="button"
        class="reviewer-menu-toggle"
        id="reviewer-menu-toggle"
        aria-label="Toggle navigation"
        aria-expanded="false"
        aria-controls="reviewer-nav"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>

    <div class="nav-links" id="reviewer-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="assignments.php" class="active">Assigned manuscripts</a>
        
        <a href="settings.php" class="reviewer-profile-pill">
            <svg class="reviewer-profile-icon" xmlns="http://www.w3.org/2000/svg"
                 width="18" height="18" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"></circle>
                <path d="M5 21a7 7 0 0 1 14 0"></path>
            </svg>
            <span><?= e($reviewerName) ?></span>
        </a>

        <form action="../logout.php" method="POST" class="logout-form">
            <button type="submit" class="logout-btn">Log out</button>
        </form>
    </div>
</nav>


<div class="container">

    <div class="page-header">
        <h1>Assigned Manuscripts</h1>
        <p>Welcome, <?= e($reviewerName) ?>. Manage the manuscripts assigned to you.</p>
    </div>

    <div class="summary-cards">

    <div class="summary-card">
        <h3>Total Assigned</h3>
        <div class="number"><?= $totalAssigned ?></div>
    </div>

    <div class="summary-card completed-card">
        <h3>Completed</h3>
        <div class="number"><?= $completedCount ?></div>
    </div>

    <div class="summary-card pending-card">
        <h3>Pending</h3>
        <div class="number"><?= $pendingCount ?></div>
    </div>

</div>

    <?php if (empty($assignments)): ?>

<div class="table-empty">
    <h3>No manuscripts assigned yet</h3>
    <p>Your assigned manuscripts will appear here once the editorial team assigns them to you.</p>
</div>

<?php else: ?>

<div class="assignment-table-wrapper">

    <table class="assignment-table">

        <thead>
            <tr>
                <th>Manuscript</th>
                <th>Assigned Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>

            <?php foreach ($assignments as $assignment): ?>

                <?php
                $status = strtolower($assignment['assignment_status'] ?? 'pending');

                $statusClass = $status === 'completed'
                    ? 'completed'
                    : ($status === 'pending' ? '' : 'other');
                ?>

                <tr>

                    <td>
                        <div class="manuscript-title">
                            <?= e($assignment['title']) ?>
                        </div>

                        <div class="manuscript-authors">
                            <?= e($assignment['authors'] ?? 'Not specified') ?>
                        </div>
                    </td>

                    <td>
                        <?= !empty($assignment['assigned_at'])
                            ? e(date('M d, Y', strtotime($assignment['assigned_at'])))
                            : 'Not specified' ?>
                    </td>

                    <td>
                        <span class="status-badge <?= e($statusClass) ?>">
                            <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                        </span>
                    </td>

                    <td>
                        <a href="review.php?id=<?= (int)$assignment['assignment_id'] ?>"
                           class="review-action">
                            Review manuscript
                        </a>
                    </td>

                </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

</div>

<?php endif; ?>

    
    </div>


<footer class="footer footer-simple">
    <p>&copy; <?= date('Y') ?> OJMS. All rights reserved.</p>
</footer>


<script>
    const reviewerToggle = document.getElementById('reviewer-menu-toggle');
    const reviewerNav = document.getElementById('reviewer-nav');

    if (reviewerToggle && reviewerNav) {
        reviewerToggle.addEventListener('click', function () {
            const isOpen = reviewerNav.classList.toggle('is-open');
            reviewerToggle.setAttribute('aria-expanded', isOpen);
        });

        reviewerNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function () {
                reviewerNav.classList.remove('is-open');
                reviewerToggle.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('click', function (event) {
            if (
                !reviewerNav.contains(event.target) &&
                !reviewerToggle.contains(event.target)
            ) {
                reviewerNav.classList.remove('is-open');
                reviewerToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
</script>
</body>
</html>

</body>
</html>