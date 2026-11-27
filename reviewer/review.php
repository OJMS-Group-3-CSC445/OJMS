<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    exit('You are not logged in.');
}

if (!isset($_SESSION['role'])) {
    exit('Your session role is missing.');
}

if ($_SESSION['role'] !== 'reviewer') {
    exit('Access denied. Current role: ' . e($_SESSION['role']));
}

$reviewerId = (int) $_SESSION['user_id'];
$assignmentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($assignmentId <= 0) {
    exit('Invalid assignment.');
}

$stmt = $pdo->prepare("
    SELECT
        ra.id AS assignment_id,
        ra.status AS assignment_status,
        ra.assigned_at,
        ra.assigned_by,
        a.id AS article_id,
        a.title,
        a.authors,
        a.abstract,
        a.document_path,
        a.original_filename,
        a.submitted_at
    FROM reviewer_assignments ra
    INNER JOIN articles a ON ra.article_id = a.id
    WHERE ra.id = ?
      AND ra.reviewer_id = ?
    LIMIT 1
");

$stmt->execute([$assignmentId, $reviewerId]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    http_response_code(404);
    exit('Manuscript assignment not found.');
}

$reviewerName = trim(
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '')
);

if ($reviewerName === '') {
    $reviewerName = $_SESSION['email'] ?? 'Reviewer';
}

$errorMessage = '';
$successMessage = '';

/* Manuscript download */
if (isset($_GET['download'])) {

    $storedPath = $assignment['document_path'] ?? '';

    if ($storedPath === '') {
        exit('Manuscript file not found.');
    }

    $baseDirectory = realpath(__DIR__ . '/../');
    $filePath = realpath(__DIR__ . '/../' . ltrim($storedPath, '/'));

    if (
        !$baseDirectory ||
        !$filePath ||
        strpos($filePath, $baseDirectory . DIRECTORY_SEPARATOR) !== 0 ||
        !is_file($filePath)
    ) {
        exit('Manuscript file unavailable.');
    }

    $filename = $assignment['original_filename'] ?? basename($filePath);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($filePath));

    readfile($filePath);
    exit;
}

/* Review submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($assignment['assignment_status'] === 'Completed') {
        $errorMessage = 'You have already submitted a review for this manuscript.';
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Invalid form submission. Please refresh the page and try again.';
    } else {

        $recommendation = trim($_POST['recommendation'] ?? '');
        $comments = trim($_POST['comments'] ?? '');
        $confidentialComments = trim($_POST['confidential_comments'] ?? '');

        $allowedRecommendations = [
            'Accept',
            'Minor Revision',
            'Major Revision',
            'Reject'
        ];

        if (!in_array($recommendation, $allowedRecommendations, true)) {
            $errorMessage = 'Please select a valid recommendation.';
        } elseif ($comments === '') {
            $errorMessage = 'Please provide your review comments.';
        } else {

            try {
                $pdo->beginTransaction();

                /* Check for an existing review */
                $checkReview = $pdo->prepare("
                    SELECT id
                    FROM reviewer_reviews
                    WHERE article_id = ?
                      AND reviewer_id = ?
                    LIMIT 1
                ");

                $checkReview->execute([
                    $assignment['article_id'],
                    $reviewerId
                ]);

                if ($checkReview->fetch()) {
                    $pdo->rollBack();
                    $errorMessage = 'You have already submitted a review for this manuscript.';
                } else {

                    /* Save the review */
                    $insertReview = $pdo->prepare("
                        INSERT INTO reviewer_reviews (
                            article_id,
                            reviewer_id,
                            assigned_by,
                            assigned_at,
                            comments,
                            confidential_comments,
                            recommendation,
                            reviewed_at,
                            created_at,
                            updated_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW()
                        )
                    ");

                    $insertReview->execute([
                        $assignment['article_id'],
                        $reviewerId,
                        $assignment['assigned_by'],
                        $assignment['assigned_at'],
                        $comments,
                        $confidentialComments,
                        $recommendation
                    ]);

                    /* Mark assignment as completed */
                    $updateAssignment = $pdo->prepare("
                        UPDATE reviewer_assignments
                        SET status = 'Completed',
                            updated_at = NOW()
                        WHERE id = ?
                          AND reviewer_id = ?
                          AND status = 'Pending'
                    ");

                    $updateAssignment->execute([
                        $assignmentId,
                        $reviewerId
                    ]);

                    if ($updateAssignment->rowCount() !== 1) {
                        throw new Exception('Assignment status could not be updated.');
                    }

                    $pdo->commit();

                    $successMessage = 'Your review has been submitted successfully.';
                    $assignment['assignment_status'] = 'Completed';
                }

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Reviewer submission error: ' . $e->getMessage());
                $errorMessage = 'An error occurred while submitting your review. Please try again.';
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Review Manuscript | Reviewer Portal</title>

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
            max-width: 1100px;
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

        .back-link {
            display: inline-block;
            color: #991b1b;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 28px;
            margin-bottom: 25px;
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .manuscript-title {
            font-size: 24px;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .meta {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .abstract {
            margin-top: 25px;
            line-height: 1.8;
            color: #475569;
            white-space: pre-line;
        }

        .download-btn {
            display: inline-block;
            background: #1e3a8a;
            color: white;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 14px;
        }

        .download-btn:hover {
            background: #1e40af;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.completed {
            background: #dcfce7;
            color: #166534;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
            line-height: 1.6;
        }

        .notice {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .notice.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .notice.info {
            background: #eff6ff;
            color: #1e40af;
        }

        .submit-btn {
            border: none;
            background: #991b1b;
            color: white;
            padding: 13px 22px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #7f1d1d;
        }

        .footer {
            background: #1e293b;
            color: white;
            padding: 35px 30px;
            margin-top: 60px;
        }

        .footer-inner {
            max-width: 1100px;
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
            max-width: 1100px;
            margin: 25px auto 0;
            padding-top: 20px;
            border-top: 1px solid #334155;
            color: #cbd5e1;
            font-size: 12px;
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

            .card {
                padding: 20px;
            }

            .manuscript-title {
                font-size: 21px;
            }
        }

/* ========================================
   REVIEWER REVIEW PAGE REDESIGN
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

/* REMOVE LINK UNDERLINES */

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

.nav-links a:hover {
    color: #991b1b;
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

/* MAIN CONTENT LANE */

.container {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 130px clamp(24px, 5vw, 80px) 60px;
    flex: 1;
}

/* BACK LINK */

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    color: #991b1b;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 25px;

    transition: color 0.2s ease;
}

.back-link:hover {
    color: #7f1d1d;
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

/* CONTENT CARDS */

.card {
    background: white;
    border: 1px solid #e8e8e8;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 25px;

    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.035);
}

.card h2 {
    margin: 0 0 25px;
    font-size: 20px;
    font-weight: 650;
    color: #1e293b;
}

/* MANUSCRIPT DETAILS */

.manuscript-title {
    font-size: 23px;
    line-height: 1.6;
    font-weight: 650;
    letter-spacing: -0.3px;
    color: #1e293b;
    margin-bottom: 22px;
    overflow-wrap: break-word;
}

.meta {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 14px;
    line-height: 1.7;
}

.meta strong {
    color: #334155;
    font-weight: 600;
}

/* ABSTRACT */

.abstract {
    margin-top: 28px;
    padding-top: 25px;
    border-top: 1px solid #e2e8f0;

    line-height: 1.8;
    color: #475569;
    white-space: pre-line;
    font-size: 14px;
}

.abstract strong {
    display: block;
    color: #1e293b;
    font-size: 15px;
    margin-bottom: 12px;
}

/* DOWNLOAD BUTTON */

.download-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    background: #991b1b;
    color: white;

    padding: 12px 20px;
    border-radius: 999px;
    margin-top: 25px;

    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.download-btn:hover {
    background: #7f1d1d;
    color: white;
    transform: translateY(-1px);
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

    background: #fef3c7;
    color: #92400e;
}

.status-badge.completed {
    background: #dcfce7;
    color: #166534;
}

/* FORM */

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #334155;
    margin-bottom: 10px;
    font-size: 14px;
}

.form-group textarea,
.form-group select {
    width: 100%;
    padding: 14px 15px;

    border: 1px solid #dbe1e8;
    border-radius: 10px;

    background: #fff;
    color: #1e293b;

    font-family: Inter, sans-serif;
    font-size: 14px;

    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #991b1b;
    box-shadow: 0 0 0 3px rgba(153, 27, 27, 0.08);
}

.form-group textarea {
    min-height: 180px;
    resize: vertical;
    line-height: 1.7;
}

.form-group textarea::placeholder {
    color: #94a3b8;
}

/* NOTICES */

.notice {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 22px;
    font-size: 14px;
    line-height: 1.7;
    border: 1px solid transparent;
}

.notice.error {
    background: #fef2f2;
    color: #991b1b;
    border-color: #fecaca;
}

.notice.info {
    background: #eff6ff;
    color: #1e40af;
    border-color: #bfdbfe;
}

/* SUCCESS NOTICE */

.notice[style*="dcfce7"] {
    background: #f0fdf4 !important;
    color: #166534 !important;
    border: 1px solid #bbf7d0;
}

/* SUBMIT BUTTON */

.submit-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    border: none;
    background: #991b1b;
    color: white;

    padding: 13px 22px;
    border-radius: 999px;

    font-family: Inter, sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;

    transition: all 0.2s ease;
}

.submit-btn:hover {
    background: #7f1d1d;
    transform: translateY(-1px);
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

    .manuscript-title {
        font-size: 22px;
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

    .card {
        padding: 25px;
    }
}

/* SMALL MOBILE */

@media (max-width: 600px) {

    .container {
        padding-left: 18px;
        padding-right: 18px;
    }

    .page-header h1 {
        font-size: 25px;
    }

    .card {
        padding: 22px;
        border-radius: 14px;
    }

    .manuscript-title {
        font-size: 21px;
    }

    .form-group textarea {
        min-height: 160px;
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

    .submit-btn,
    .download-btn {
        width: 100%;
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
        <a href="assignments.php">Assigned manuscripts</a>

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

    <a href="assignments.php" class="back-link">
        &larr; Back to Assigned Manuscripts
    </a>

    <div class="page-header">
        <h1>Review Manuscript</h1>
        <p>Welcome, <?= e($reviewerName) ?>. Read the manuscript and prepare your evaluation.</p>
    </div>

    <?php if ($successMessage !== ''): ?>
        <div class="notice" style="background:#dcfce7;color:#166534;">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="notice error">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <h2>Manuscript Details</h2>

        <div class="manuscript-title">
            <?= e($assignment['title'] ?? 'Untitled manuscript') ?>
        </div>

        <div class="meta">
            <strong>Authors:</strong>
            <?= e($assignment['authors'] ?? 'Not specified') ?>
        </div>

        <div class="meta">
            <strong>Assigned:</strong>
            <?= !empty($assignment['assigned_at'])
                ? e(date('M d, Y', strtotime($assignment['assigned_at'])))
                : 'Not specified' ?>
        </div>

        <div class="meta">
            <strong>Assignment Status:</strong>

            <span class="status-badge <?= strtolower($assignment['assignment_status']) === 'completed' ? 'completed' : '' ?>">
                <?= e(ucwords(str_replace('_', ' ', $assignment['assignment_status']))) ?>
            </span>
        </div>

        <div class="abstract">
            <strong>Abstract</strong>

            <?= e($assignment['abstract'] ?? 'No abstract available.') ?>
        </div>

        <?php if (!empty($assignment['document_path'])): ?>

            <a href="review.php?id=<?= (int)$assignmentId ?>&download=1"
               class="download-btn">
                Download Manuscript
            </a>

        <?php else: ?>

            <div class="notice error" style="margin-top:20px;">
                The manuscript document is currently unavailable.
            </div>

        <?php endif; ?>

    </div>

    
    <div class="card">

        <h2>Reviewer Evaluation</h2>

        <?php if ($assignment['assignment_status'] === 'Pending'): ?>

            <form method="POST" action="review.php?id=<?= (int)$assignmentId ?>">

                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="form-group">
                    <label for="recommendation">Recommendation</label>

                    <select name="recommendation" id="recommendation" required>
                        <option value="">Select recommendation</option>
                        <option value="Accept">Accept</option>
                        <option value="Minor Revision">Minor Revision</option>
                        <option value="Major Revision">Major Revision</option>
                        <option value="Reject">Reject</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="comments">Review Comments</label>

                    <textarea
                        name="comments"
                        id="comments"
                        placeholder="Write your review of the manuscript..."
                        required></textarea>
                </div>

                <div class="form-group">
                    <label for="confidential_comments">Confidential Comments to the Editor</label>

                    <textarea
                        name="confidential_comments"
                        id="confidential_comments"
                        placeholder="Comments intended only for the editorial team..."></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    Submit Review
                </button>

            </form>

        <?php else: ?>

            <div class="notice" style="background:#dcfce7;color:#166534;">
                You have submitted your review for this manuscript.
            </div>

        <?php endif; ?>

    </div>


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

