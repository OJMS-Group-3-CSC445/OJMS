<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Restrict access to reviewers
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'reviewer'
) {
    http_response_code(403);
    exit('Access denied.');
}

$reviewerId = (int) $_SESSION['user_id'];


$reviewerName = trim(
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '')
);

if ($reviewerName === '') {
    $reviewerName = $_SESSION['email'] ?? 'Reviewer';
}

// Dashboard statistics
$totalAssignments = 0;
$pendingReviews = 0;
$completedReviews = 0;
$recentAssignments = [];


try {
    $stmt = $pdo->prepare("
        SELECT
            ra.id AS assignment_id,
            ra.status AS assignment_status,
            ra.assigned_at,
            a.title,
            a.submitted_at
        FROM reviewer_assignments ra
        INNER JOIN articles a ON ra.article_id = a.id
        WHERE ra.reviewer_id = ?
        ORDER BY ra.assigned_at DESC
        LIMIT 5
    ");

    $stmt->execute([$reviewerId]);
    $recentAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Reviewer dashboard assignments error: ' . $e->getMessage());
}

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM reviewer_assignments
        WHERE reviewer_id = ?
    ");

    $stmt->execute([$reviewerId]);
    $totalAssignments = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log('Reviewer assignment count error: ' . $e->getMessage());
}

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM reviewer_assignments
        WHERE reviewer_id = ?
        AND status = 'pending'
    ");

    $stmt->execute([$reviewerId]);
    $pendingReviews = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log('Pending review count error: ' . $e->getMessage());
}

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM reviewer_assignments
        WHERE reviewer_id = ?
        AND status = 'completed'
    ");

    $stmt->execute([$reviewerId]);
    $completedReviews = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log('Completed review count error: ' . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reviewer Dashboard | OJMS</title>

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
            width: 36px;
            height: 36px;
            background: #991b1b;
            color: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 25px;
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
            max-width: 1300px;
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
            color: #64748b;
            margin: 0;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 25px;
        }

        .stat-card p {
            color: #64748b;
            font-size: 14px;
            margin: 0 0 15px;
        }

        .stat-card h2 {
            font-size: 32px;
            margin: 0;
        }

        .section-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .section-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
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

        .empty-state {
            padding: 60px 25px;
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
            max-width: 1300px;
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
            max-width: 1300px;
            margin: 30px auto 0;
            color: #cbd5e1;
            font-size: 12px;
        }

        @media(max-width: 800px) {

            .stats {
                grid-template-columns: 1fr;
            }

        }

        @media(max-width: 700px) {

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

        }

        .view-all-link {
    color: #991b1b;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}

.view-all-link:hover {
    text-decoration: underline;
}

.recent-assignments {
    display: flex;
    flex-direction: column;
}

.recent-assignment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 30px;
    padding: 24px 30px;
    border-bottom: 1px solid #e5e7eb;
}

.recent-assignment-item:last-child {
    border-bottom: none;
}

.assignment-info {
    flex: 1;
    min-width: 0;
}

.assignment-info h3 {
    margin: 0 0 10px;
    font-size: 15px;
    line-height: 1.6;
    font-weight: 600;
    color: #1e293b;
    overflow-wrap: break-word;
}

.assignment-info p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.assignment-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    flex-shrink: 0;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 14px;
    border-radius: 20px;
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

.review-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    background: #991b1b;
    color: white;
    padding: 11px 16px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    transition: background 0.2s ease;
}

.review-link:hover {
    background: #7f1d1d;
}

@media (max-width: 768px) {
    .recent-assignment-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 18px;
        padding: 22px;
    }

    .assignment-info {
        width: 100%;
    }

    .assignment-actions {
        width: 100%;
        justify-content: space-between;
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .recent-assignment-item {
        padding: 18px;
    }

    .assignment-actions {
        align-items: flex-start;
        gap: 10px;
    }

    .review-link {
        padding: 10px 12px;
    }
}


/* ==============================
   REVIEWER DASHBOARD REDESIGN
============================== */

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
}

body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    overflow-x: clip;
}

/* HEADER */

.header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;

    display: flex;
    justify-content: space-between;
    align-items: center;

    padding: 20px clamp(24px, 5vw, 80px);
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(12px);

    border-bottom: 1px solid #e2e8f0;
    flex-wrap: nowrap;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.logo {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    font-weight: 700;
    flex-shrink: 0;
}

.brand-text {
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
}

/* NAVIGATION */

.nav {
    display: flex;
    align-items: center;
    gap: 25px;
    flex-wrap: nowrap;
}

.nav a {
    font-size: 14px;
    color: #475569;
    transition: color 0.2s ease;
}

.nav a:hover {
    color: #991b1b;
}

.nav a.active {
    color: #991b1b;
    font-weight: 600;
}

.nav form {
    margin: 0;
}

.nav button {
    font: inherit;
    cursor: pointer;
}

/* PROFILE PILL */

.profile-pill {
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

.profile-pill:hover {
    background: #f1f5f9;
}

.profile-icon {
    flex-shrink: 0;
}

/* LOGOUT PILL */

.logout-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 10px 20px;
    border: 1px solid #991b1b !important;
    border-radius: 999px;

    background: #991b1b !important;
    color: #fff !important;

    font-size: 13px !important;
    font-weight: 500;
    transition: all 0.2s ease;
}

.logout-pill:hover {
    background: #7f1d1d !important;
    border-color: #7f1d1d !important;
    color: #fff !important;
}

/* HAMBURGER */

.menu-toggle {
    display: none;
    width: 42px;
    height: 42px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    padding: 10px;
    cursor: pointer;
}

.menu-toggle span {
    display: block;
    width: 20px;
    height: 2px;
    margin: 4px auto;
    background: #1e293b;
    border-radius: 2px;
}

/* MAIN CONTENT LANE */

.main {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 130px clamp(24px, 5vw, 80px) 60px;
    flex: 1;
}

/* WELCOME */

.welcome {
    margin-bottom: 35px;
}

.welcome h1 {
    font-size: clamp(25px, 3vw, 32px);
    font-weight: 700;
    letter-spacing: -0.7px;
    color: #1e293b;
}

.welcome p {
    font-size: 15px;
    line-height: 1.7;
}

/* STAT CARDS */

.stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
    margin-bottom: 40px;
}

.stat-card {
    position: relative;
    padding: 30px;
    border-radius: 16px;
    border: 1px solid #e8e8e8;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.035);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.07);
}

.stat-card p {
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 18px;
}

.stat-card h2 {
    font-size: 36px;
    font-weight: 700;
    letter-spacing: -1px;
    color: #1e293b;
}

/* ASSIGNMENTS PANEL */

.section-card {
    border-radius: 16px;
    border: 1px solid #e8e8e8;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.035);
    background: #fff;
}

.section-header {
    padding: 28px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.section-header h2 {
    font-size: 20px;
    font-weight: 650;
}

.section-header p {
    line-height: 1.6;
}

.recent-assignment-item {
    padding: 26px 30px;
    transition: background 0.2s ease;
}

.recent-assignment-item:hover {
    background: #fafafa;
}

.assignment-info h3 {
    font-size: 15px;
    font-weight: 600;
}

.assignment-info p {
    line-height: 1.6;
}

.status-badge {
    padding: 8px 15px;
}

.review-link {
    border-radius: 999px;
    padding: 11px 18px;
    transition: all 0.2s ease;
}

.review-link:hover {
    transform: translateY(-1px);
}

.view-all-link {
    white-space: nowrap;
}

/* EMPTY STATE */

.empty-state {
    padding: 70px 30px;
}

.empty-state h3 {
    font-size: 18px;
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
    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .stats .stat-card:last-child {
        grid-column: 1 / -1;
    }
}

/* MOBILE NAVIGATION */

@media (max-width: 768px) {

    .header {
        padding: 16px 24px;
    }

    .brand-text {
        font-size: 14px;
    }

    .menu-toggle {
        display: block;
    }

    .nav {
        position: absolute;
        top: calc(100% + 1px);
        left: 0;
        right: 0;

        width: 100%;
        padding: 20px 24px;

        background: #fff;
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

        transition: opacity 0.2s ease, transform 0.2s ease,
                    visibility 0.2s ease;
    }

    .nav.is-open {
        visibility: visible;
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
    }

    .nav > a:not(.profile-pill) {
        padding: 5px 0;
    }

    .nav .profile-pill {
        width: fit-content;
    }

    .nav form {
        width: 100%;
    }

    .logout-pill {
        width: fit-content;
    }

    .main {
        padding: 115px 24px 45px;
    }

    .section-header {
        padding: 24px;
    }
}

/* SMALL MOBILE */

@media (max-width: 600px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .stats .stat-card:last-child {
        grid-column: auto;
    }

    .stat-card {
        padding: 25px;
    }

    .main {
        padding-left: 18px;
        padding-right: 18px;
    }

    .recent-assignment-item {
        padding: 22px;
    }

    .assignment-actions {
        align-items: center;
    }

    .section-header {
        padding: 22px;
    }

    .welcome h1 {
        font-size: 25px;
    }
}

@media (max-width: 400px) {

    .header {
        padding: 15px 18px;
    }

    .brand {
        gap: 9px;
    }

    .brand-text {
        font-size: 13px;
    }

    .logo {
        width: 36px;
        height: 36px;
    }

    .menu-toggle {
        width: 40px;
        height: 40px;
    }

    .assignment-actions {
        flex-direction: column;
        align-items: flex-start;
    }
}


    </style>

</head>

<body>


<header class="header">
    <a href="dashboard.php" class="brand" aria-label="Reviewer dashboard">
        <span class="logo">OJ</span>
        <span class="brand-text">Reviewer Dashboard</span>
    </a>

    <button
        type="button"
        class="menu-toggle"
        id="reviewer-menu-toggle"
        aria-label="Toggle navigation"
        aria-expanded="false"
        aria-controls="reviewer-navigation"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>

    <nav class="nav" id="reviewer-navigation" aria-label="Reviewer navigation">
        <a class="active" href="dashboard.php">Dashboard</a>
        <a href="assignments.php">Assigned manuscripts</a>

        <a href="settings.php" class="profile-pill">
            <svg class="profile-icon" xmlns="http://www.w3.org/2000/svg"
                 width="18" height="18" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"></circle>
                <path d="M5 21a7 7 0 0 1 14 0"></path>
            </svg>
            <span><?= e($reviewerName) ?></span>
        </a>

        <form action="../auth/logout.php" method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >
            <button type="submit" class="logout-pill">Log out</button>
        </form>
    </nav>
</header>


<main class="main">

    <section class="welcome">

        <h1>
            Welcome, <?= e($reviewerName) ?>
        </h1>

        <p>
            Manage your manuscript assignments and peer reviews.
        </p>

    </section>

    <section class="stats">

        <div class="stat-card">
            <p>Total assignments</p>
            <h2><?= $totalAssignments ?></h2>
        </div>

        <div class="stat-card">
            <p>Pending reviews</p>
            <h2><?= $pendingReviews ?></h2>
        </div>

        <div class="stat-card">
            <p>Completed reviews</p>
            <h2><?= $completedReviews ?></h2>
        </div>

    </section>

    <section class="section-card">
    <div class="section-header">
        <div>
            <h2>My assignments</h2>
            <p>Recently assigned manuscripts for peer review.</p>
        </div>
        <a href="assignments.php" class="view-all-link">View all</a>
    </div>

    <?php if (empty($recentAssignments)): ?>

        <div class="empty-state">
            <h3>No manuscripts assigned yet</h3>
            <p>Once the editorial team assigns manuscripts to you, they will appear here.</p>
        </div>

    <?php else: ?>

        <div class="recent-assignments">

            <?php foreach ($recentAssignments as $assignment): ?>

                <?php
                    $status = strtolower($assignment['assignment_status'] ?? 'pending');
                    $statusClass = $status === 'completed' ? 'completed' : 'pending';
                ?>

                <div class="recent-assignment-item">

                    <div class="assignment-info">
                        <h3><?= e($assignment['title']) ?></h3>

                        <p>
                            Assigned:
                            <?= !empty($assignment['assigned_at'])
                                ? e(date('M d, Y', strtotime($assignment['assigned_at'])))
                                : 'Not specified' ?>
                        </p>
                    </div>

                    <div class="assignment-actions">
                        <span class="status-badge <?= e($statusClass) ?>">
                            <?= e(ucwords($status)) ?>
                        </span>

                        <a href="review.php?id=<?= (int)$assignment['assignment_id'] ?>"
                           class="review-link">
                            <?= $status === 'completed' ? 'View review' : 'Review manuscript' ?>
                        </a>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>
</section>

</main>


<footer class="footer footer-simple">
    <p>&copy; <?= date('Y') ?> OJMS. All rights reserved.</p>
</footer>


<script>
    const reviewerMenuToggle = document.getElementById('reviewer-menu-toggle');
    const reviewerNavigation = document.getElementById('reviewer-navigation');

    if (reviewerMenuToggle && reviewerNavigation) {
        reviewerMenuToggle.addEventListener('click', function () {
            const isOpen = reviewerNavigation.classList.toggle('is-open');

            reviewerMenuToggle.setAttribute('aria-expanded', isOpen);
        });

        reviewerNavigation.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                reviewerNavigation.classList.remove('is-open');
                reviewerMenuToggle.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('click', function (event) {
            if (
                !reviewerNavigation.contains(event.target) &&
                !reviewerMenuToggle.contains(event.target)
            ) {
                reviewerNavigation.classList.remove('is-open');
                reviewerMenuToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
</script>
</body>
</html>


</body>
</html>