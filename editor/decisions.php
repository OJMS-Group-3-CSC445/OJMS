<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$eicId = (int)$_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle editorial decision submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errorMessage = 'Invalid security token. Please try again.';
    } else {

        $articleId = (int)($_POST['article_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');

        if ($articleId <= 0) {
            $errorMessage = 'Invalid manuscript.';
        } elseif (!in_array($decision, ['accepted', 'rejected'], true)) {
            $errorMessage = 'Invalid editorial decision.';
        } else {

            try {
                $pdo->beginTransaction();

                // Confirm manuscript is currently under review
                $articleStmt = $pdo->prepare("
                    SELECT id, status
                    FROM articles
                    WHERE id = ?
                    FOR UPDATE
                ");
                $articleStmt->execute([$articleId]);
                $article = $articleStmt->fetch(PDO::FETCH_ASSOC);

                if (!$article || $article['status'] !== 'under_review') {
                    throw new Exception(
                        'This manuscript is no longer awaiting an editorial decision.'
                    );
                }

                // Check reviewer assignments
                $assignmentStmt = $pdo->prepare("
                    SELECT
                        COUNT(*) AS total_assignments,
                        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_assignments
                    FROM reviewer_assignments
                    WHERE article_id = ?
                ");
                $assignmentStmt->execute([$articleId]);

                $assignmentData = $assignmentStmt->fetch(PDO::FETCH_ASSOC);

                $totalAssignments = (int)($assignmentData['total_assignments'] ?? 0);
                $completedAssignments = (int)($assignmentData['completed_assignments'] ?? 0);

                if (
                    $totalAssignments === 0 ||
                    $completedAssignments !== $totalAssignments
                ) {
                    throw new Exception(
                        'All assigned reviewers must complete their reviews before a final decision can be made.'
                    );
                }

                // Save the final decision
                $updateStmt = $pdo->prepare("
                    UPDATE articles
                    SET
                        status = ?,
                        decided_by = ?,
                        decision_remarks = ?,
                        decided_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $updateStmt->execute([
                    $decision,
                    $eicId,
                    $remarks,
                    $articleId
                ]);

                $pdo->commit();

                $successMessage = 'Editorial decision recorded successfully.';

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($e instanceof PDOException) {
                    error_log('Editorial decision error: ' . $e->getMessage());
                    $errorMessage = 'Unable to save the decision at this time.';
                } else {
                    $errorMessage = $e->getMessage();
                }
            }
        }
    }
}

// Fetch manuscripts awaiting final decisions
$manuscripts = [];
$loadError = '';

try {

    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.title,
            a.authors,
            a.abstract,
            a.submitted_at,
            u.first_name,
            u.last_name,
            u.email
        FROM articles a
        INNER JOIN users u ON a.author_id = u.id
        WHERE a.status = 'under_review'
        ORDER BY a.submitted_at ASC
    ");

    $stmt->execute();
    $manuscripts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Editorial decisions listing error: ' . $e->getMessage());
    $loadError = 'Unable to load manuscripts at this time.';
}

// Fetch completed reviews for each manuscript
foreach ($manuscripts as &$manuscript) {

    $reviewStmt = $pdo->prepare("
        SELECT
            rr.recommendation,
            rr.comments,
            rr.confidential_comments,
            rr.reviewed_at,
            r.first_name,
            r.last_name,
            r.email
        FROM reviewer_reviews rr
        INNER JOIN users r ON rr.reviewer_id = r.id
        WHERE rr.article_id = ?
        ORDER BY rr.reviewed_at ASC
    ");

    $reviewStmt->execute([$manuscript['id']]);
    $manuscript['reviews'] = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);

    $assignmentStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed
        FROM reviewer_assignments
        WHERE article_id = ?
    ");

    $assignmentStmt->execute([$manuscript['id']]);
    $assignmentData = $assignmentStmt->fetch(PDO::FETCH_ASSOC);

    $manuscript['total_assignments'] = (int)($assignmentData['total'] ?? 0);
    $manuscript['completed_assignments'] = (int)($assignmentData['completed'] ?? 0);

    $manuscript['ready_for_decision'] =
        $manuscript['total_assignments'] > 0 &&
        $manuscript['total_assignments'] === $manuscript['completed_assignments'];

}
unset($manuscript);

// Overall statistics (before pagination)
$totalManuscripts = count($manuscripts);
$readyCount = 0;

foreach ($manuscripts as $m) {
    if ($m['ready_for_decision']) {
        $readyCount++;
    }
}

$waitingCount = $totalManuscripts - $readyCount;

// Pagination
$perPage = 10;
$totalPages = max(1, (int)ceil($totalManuscripts / $perPage));

$currentPage = max(1, (int)($_GET['page'] ?? 1));
$currentPage = min($currentPage, $totalPages);

$offset = ($currentPage - 1) * $perPage;
$manuscripts = array_slice($manuscripts, $offset, $perPage);

// EIC name
$eicName = trim(
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '')
);

if ($eicName === '') {
    $eicName = $_SESSION['email'] ?? 'Editor-in-Chief';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Editorial Decisions | OJMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #f8f7f5;
            color: #1e293b;
        }

        /* Header */
        .navbar {
            background: #fff;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
            flex-wrap: wrap;
            gap: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #1e293b;
            font-size: 17px;
            font-weight: 700;
        }

        .logo {
            width: 42px;
            height: 42px;
            background: #991b1b;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
        }

        .nav-links {
            display: flex;
            gap: 22px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
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
            background: #fef2f2;
            color: #991b1b;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
        }

        .logout-btn:hover {
            background: #fee2e2;
        }

        /* Main */
        .container {
            width: 90%;
            max-width: 1400px;
            margin: 40px auto;
            min-height: 60vh;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 30px;
            margin: 0 0 12px;
            font-weight: 700;
        }

        .page-header p {
            color: #64748b;
            margin: 0;
            line-height: 1.7;
            font-size: 15px;
        }

        /* Messages */
        .notice {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .notice-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .notice-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .notice-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        /* Manuscript cards */
        .manuscript-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.02);
        }

        .manuscript-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .manuscript-title {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.6;
            color: #1e293b;
            margin: 0 0 10px;
        }

        .manuscript-meta {
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
        }

        .submission-date {
            color: #64748b;
            font-size: 13px;
            white-space: nowrap;
        }

        .section-heading {
            font-size: 16px;
            font-weight: 700;
            margin: 25px 0 15px;
            color: #1e293b;
        }

        .abstract-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 18px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.8;
            color: #475569;
        }

        /* Review cards */
        .review-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            background: #fafafa;
        }

        .reviewer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .reviewer-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .reviewer-email {
            font-size: 12px;
            color: #94a3b8;
        }

        .recommendation {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .recommendation-accept {
            background: #dcfce7;
            color: #166534;
        }

        .recommendation-minor {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .recommendation-major {
            background: #fef3c7;
            color: #92400e;
        }

        .recommendation-reject {
            background: #fee2e2;
            color: #991b1b;
        }

        .review-label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin: 15px 0 8px;
        }

        .review-text {
            font-size: 14px;
            line-height: 1.8;
            color: #475569;
            white-space: pre-wrap;
        }

        .confidential-box {
            margin-top: 15px;
            padding: 15px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
        }

        .confidential-box .review-label {
            color: #9a3412;
            margin-top: 0;
        }

        .confidential-box .review-text {
            color: #7c2d12;
        }

        .review-date {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 15px;
        }

        /* Decision section */
        .decision-box {
            margin-top: 30px;
            padding: 25px;
            background: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .decision-box h3 {
            margin: 0 0 10px;
            font-size: 17px;
        }

        .decision-box > p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .decision-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .decision-form label {
            font-size: 14px;
            font-weight: 600;
            color: #475569;
        }

        .decision-form select,
        .decision-form textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            background: #fff;
            color: #1e293b;
            outline: none;
        }

        .decision-form select:focus,
        .decision-form textarea:focus {
            border-color: #991b1b;
            box-shadow: 0 0 0 3px rgba(153, 27, 27, 0.08);
        }

        .decision-form textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.7;
        }

        .decision-btn {
            background: #991b1b;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 13px 20px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            align-self: flex-start;
        }

        .decision-btn:hover {
            background: #7f1d1d;
        }

        .decision-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }

        /* Empty state */
        .empty-state {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            text-align: center;
            padding: 60px 25px;
            color: #64748b;
        }

        .empty-state h3 {
            color: #1e293b;
            margin: 0 0 10px;
        }

        .empty-state p {
            font-size: 14px;
            line-height: 1.7;
        }

        /* Footer */
        .footer {
            background: #1e293b;
            color: white;
            padding: 35px 5%;
            margin-top: 60px;
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

        .footer-inner h2 {
            margin: 0 0 8px;
            font-size: 22px;
        }

        .footer-inner p {
            margin: 0;
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.6;
        }

        .footer-copy {
            max-width: 1400px;
            margin: 25px auto 0;
            padding-top: 20px;
            border-top: 1px solid #334155;
            color: #94a3b8;
            font-size: 12px;
        }


        /* Decision dashboard table */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px; margin:25px 0; }
        .stat-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; }
        .stat-label { color:#64748b; font-size:13px; margin-bottom:8px; }
        .stat-number { font-size:28px; font-weight:700; color:#1e293b; }
        .table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow-x:auto; }
        .toolbar { display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap; margin:20px 0; }
        .search-input { padding:12px 14px; border:1px solid #d1d5db; border-radius:8px; font:inherit; width:min(100%,360px); }
        table { width:100%; border-collapse:collapse; min-width:760px; }
        th,td { text-align:left; padding:15px 16px; border-bottom:1px solid #e5e7eb; font-size:14px; }
        th { background:#f8fafc; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
        tr:last-child td { border-bottom:0; }
        .progress-pill { display:inline-block; padding:6px 10px; border-radius:20px; font-size:12px; font-weight:600; white-space:nowrap; }
        .progress-ready { background:#dcfce7; color:#166534; }
        .progress-wait { background:#fef3c7; color:#92400e; }
        .view-btn { border:1px solid #991b1b; color:#991b1b; background:#fff; padding:9px 12px; border-radius:8px; font:inherit; font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap; }
        .view-btn:hover { background:#fef2f2; }
        .manuscript-card { display:none; }
        .manuscript-card.open { display:block; margin-top:22px; }
        .details-top { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:18px; }
        .close-details { border:0; background:#f1f5f9; padding:9px 12px; border-radius:8px; cursor:pointer; font:inherit; }
        @media(max-width:600px) { .toolbar { align-items:stretch; } .search-input { width:100%; } }

        /* Responsive */
        @media (max-width: 1024px) {
            .navbar {
                padding: 18px 4%;
            }

            .nav-links {
                gap: 15px;
            }

            .container {
                width: 92%;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 18px 5%;
                flex-direction: column;
                align-items: flex-start;
            }

            .nav-links {
                gap: 15px;
                width: 100%;
            }

            .container {
                width: 92%;
                margin: 30px auto;
            }

            .manuscript-card {
                padding: 20px;
            }

            .manuscript-title {
                font-size: 18px;
            }

            .decision-box {
                padding: 20px;
            }

            .decision-btn {
                width: 100%;
            }

            .footer-inner {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .nav-links {
                gap: 12px;
            }

            .nav-links a {
                font-size: 13px;
            }

            .brand {
                font-size: 15px;
            }

            .page-header h1 {
                font-size: 23px;
            }
        }

        /* =========================
   EDITOR-IN-CHIEF REDESIGN
========================= */

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background: #f8f7f5;
    color: #1e293b;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    overflow-x: clip;
}

/* Navbar */

.navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: #fff;
    padding: 14px clamp(24px, 5vw, 80px);
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
}

.editor-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: #1e293b;
    flex-shrink: 0;
}

.editor-logo {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #991b1b;
    color: #fff;
    border-radius: 10px;
    font-weight: 700;
    font-size: 14px;
}

.editor-brand-text {
    font-size: 16px;
    font-weight: 700;
}

.editor-nav {
    display: flex;
    align-items: center;
    gap: 24px;
}

.editor-nav > a:not(.editor-profile-pill) {
    text-decoration: none;
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
    transition: color .2s;
}

.editor-nav > a:not(.editor-profile-pill):hover,
.editor-nav > a.active {
    color: #991b1b;
}

.editor-nav > a.active {
    font-weight: 600;
}

.editor-profile-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #334155;
    text-decoration: none;
    border-radius: 999px;
    padding: 9px 14px;
    font-size: 13px;
    font-weight: 600;
    max-width: 220px;
}

.editor-profile-pill span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.editor-logout-form {
    margin: 0;
}

.editor-logout-btn {
    border: none;
    background: #fef2f2;
    color: #991b1b;
    padding: 10px 15px;
    border-radius: 999px;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
}

.editor-logout-btn:hover {
    background: #fee2e2;
}

.editor-menu-toggle {
    display: none;
    width: 42px;
    height: 42px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
    padding: 10px;
}

.editor-menu-toggle span {
    display: block;
    height: 2px;
    width: 100%;
    background: #1e293b;
    margin: 5px 0;
    border-radius: 2px;
}

/* Main */

.container {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 130px clamp(24px, 5vw, 80px) 60px;
    flex: 1;
    min-height: 60vh;
}

.page-header {
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: clamp(24px, 3vw, 32px);
    font-weight: 700;
    margin: 0 0 12px;
    color: #1e293b;
}

.page-header p {
    color: #64748b;
    margin: 0;
    line-height: 1.7;
    font-size: 14px;
}

/* Notices */

.notice {
    padding: 16px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.7;
}

.notice-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.notice-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.notice-warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

/* Statistics */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 18px;
    margin: 25px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .02);
}

.stat-label {
    color: #64748b;
    font-size: 13px;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #1e293b;
}

/* Toolbar */

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin: 30px 0 20px;
}

.toolbar h2 {
    color: #1e293b;
    font-size: 18px;
    font-weight: 700;
}

.search-input {
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font: inherit;
    font-size: 14px;
    width: min(100%, 360px);
    background: #fff;
    outline: none;
}

.search-input:focus {
    border-color: #991b1b;
    box-shadow: 0 0 0 3px rgba(153, 27, 27, .08);
}

/* Table */

.table-wrap {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow-x: auto;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .02);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}

th,
td {
    text-align: left;
    padding: 17px 18px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}

th {
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .04em;
    font-weight: 600;
}

td {
    color: #475569;
}

td strong {
    color: #1e293b;
    line-height: 1.6;
}

tr:last-child td {
    border-bottom: 0;
}

.decision-row:hover {
    background: #fafafa;
}

/* Progress */

.progress-pill {
    display: inline-block;
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.progress-ready {
    background: #dcfce7;
    color: #166534;
}

.progress-wait {
    background: #fef3c7;
    color: #92400e;
}

/* View reviews button */

.view-btn {
    border: 1px solid #991b1b;
    color: #991b1b;
    background: #fff;
    padding: 9px 13px;
    border-radius: 8px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: .2s;
}

.view-btn:hover {
    background: #fef2f2;
}

/* Pagination */

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 28px 0 35px;
}

.pagination-pages {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: center;
}

.pagination-btn,
.pagination-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #475569;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: .2s;
}

.pagination-btn:hover,
.pagination-number:hover {
    border-color: #991b1b;
    color: #991b1b;
}

.pagination-number.active {
    background: #991b1b;
    border-color: #991b1b;
    color: #fff;
}

.pagination-btn.disabled {
    color: #94a3b8;
    background: #f8fafc;
    cursor: not-allowed;
}

/* EDITORIAL DECISION MODAL - FIXED */

.review-modal-backdrop {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 99999 !important;

    display: none;
    background: rgba(15, 23, 42, 0.65);

    overflow-y: auto;
    overflow-x: hidden;

    padding: 20px;
    align-items: flex-start;
    justify-content: center;
}

.review-modal-backdrop.open {
    display: flex !important;
}

.review-modal {
    position: relative !important;

    width: 100% !important;
    max-width: 900px !important;
    min-width: 0 !important;

    margin: auto !important;
    max-height: none !important;

    overflow: visible !important;
    overflow-x: hidden !important;

    background: #ffffff;
    border-radius: 16px;
    padding: 30px;

    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);

    box-sizing: border-box !important;
}

@media (max-width: 768px) {
    .review-modal-backdrop {
        padding: 10px !important;
        align-items: flex-start !important;
    }

    .review-modal {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;

        margin: 10px auto !important;
        padding: 18px !important;

        border-radius: 12px !important;
    }

    .manuscript-header {
        flex-direction: column !important;
        width: 100% !important;
    }

    .manuscript-header > div {
        width: 100%;
        min-width: 0;
    }

    .abstract-box,
    .review-card,
    .decision-box {
        width: 100% !important;
        min-width: 0 !important;
    }

    .decision-box {
        padding: 18px !important;
    }

    .decision-form {
        width: 100%;
        min-width: 0;
    }

    .decision-form select,
    .decision-form button {
        width: 100%;
        max-width: 100%;
    }
}
/* Manuscript details */

.manuscript-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.manuscript-title {
    font-size: 21px;
    font-weight: 700;
    line-height: 1.6;
    color: #1e293b;
    margin: 0 0 10px;
}

.manuscript-meta {
    color: #64748b;
    font-size: 14px;
    line-height: 1.8;
}

.submission-date {
    color: #64748b;
    font-size: 13px;
}

.section-heading {
    font-size: 16px;
    font-weight: 700;
    margin: 28px 0 15px;
    color: #1e293b;
}

.abstract-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 20px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.8;
    color: #475569;
}

/* Review cards */

.review-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 22px;
    margin-bottom: 15px;
    background: #fafafa;
}

.reviewer-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.reviewer-name {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 5px;
}

.reviewer-email {
    font-size: 12px;
    color: #94a3b8;
}

.recommendation {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.recommendation-accept {
    background: #dcfce7;
    color: #166534;
}

.recommendation-minor {
    background: #dbeafe;
    color: #1d4ed8;
}

.recommendation-major {
    background: #fef3c7;
    color: #92400e;
}

.recommendation-reject {
    background: #fee2e2;
    color: #991b1b;
}

.review-label {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin: 15px 0 8px;
}

.review-text {
    font-size: 14px;
    line-height: 1.8;
    color: #475569;
    white-space: pre-wrap;
}

.confidential-box {
    margin-top: 15px;
    padding: 16px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 8px;
}

.confidential-box .review-label {
    color: #9a3412;
    margin-top: 0;
}

.confidential-box .review-text {
    color: #7c2d12;
}

.review-date {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 15px;
}

/* Decision form */

.decision-box {
    margin-top: 30px;
    padding: 26px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.decision-box h3 {
    margin: 0 0 10px;
    font-size: 17px;
}

.decision-box > p {
    color: #64748b;
    font-size: 14px;
    line-height: 1.7;
    margin-bottom: 20px;
}

.decision-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.decision-form label {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
}

.decision-form select,
.decision-form textarea {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-family: inherit;
    font-size: 14px;
    background: #fff;
    color: #1e293b;
    outline: none;
}

.decision-form select:focus,
.decision-form textarea:focus {
    border-color: #991b1b;
    box-shadow: 0 0 0 3px rgba(153, 27, 27, .08);
}

.decision-form textarea {
    min-height: 130px;
    resize: vertical;
    line-height: 1.7;
}

.decision-btn {
    background: #991b1b;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 13px 20px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    align-self: flex-start;
    transition: .2s;
}

.decision-btn:hover {
    background: #7f1d1d;
}

.decision-btn:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}

/* Empty state */

.empty-state {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    text-align: center;
    padding: 60px 25px;
    color: #64748b;
}

.empty-state h3 {
    color: #1e293b;
    margin: 0 0 10px;
    font-size: 18px;
}

.empty-state p {
    font-size: 14px;
    line-height: 1.7;
}

/* Footer */

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

/* Responsive */

@media (max-width: 1000px) {
    .editor-nav {
        gap: 16px;
    }
}

@media (max-width: 768px) {
    .navbar {
        padding: 14px 5%;
    }

    .editor-menu-toggle {
        display: block;
    }

    .editor-nav {
        position: absolute;
        top: calc(100% + 1px);
        left: 0;
        width: 100%;
        background: #fff;
        padding: 20px 5%;
        border-bottom: 1px solid #e5e7eb;
        display: none;
        flex-direction: column;
        align-items: stretch;
        gap: 18px;
        box-shadow: 0 10px 25px rgba(15, 23, 42, .05);
    }

    .editor-nav.is-open {
        display: flex;
    }

    .editor-nav > a:not(.editor-profile-pill) {
        padding: 5px 0;
    }

    .editor-profile-pill {
        width: fit-content;
        max-width: 100%;
    }

    .editor-logout-form,
    .editor-logout-btn {
        width: 100%;
    }

    .container {
        padding: 110px 5% 45px;
    }

    .toolbar {
        align-items: stretch;
    }

    .search-input {
        width: 100%;
    }

    .review-modal-backdrop {
        padding: 12px;
    }

    .review-modal {
        max-height: 94vh;
        padding: 20px;
        border-radius: 12px;
    }

    .manuscript-title {
        font-size: 18px;
    }

    .decision-box {
        padding: 20px;
    }

    .decision-btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .editor-brand-text {
        font-size: 14px;
    }

    .page-header h1 {
        font-size: 24px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .pagination {
        gap: 8px;
    }

    .pagination-btn,
    .pagination-number {
        min-width: 36px;
        height: 36px;
        padding: 0 9px;
    }

    .review-card {
        padding: 16px;
    }
}
/* MOBILE NAVBAR FIX */
@media (max-width: 768px) {
    .navbar {
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: nowrap !important;
        padding: 14px 20px !important;
        gap: 12px !important;
    }

    .editor-brand {
        min-width: 0;
        flex: 1;
    }

    .editor-brand-text {
        font-size: 14px;
    }

    .editor-menu-toggle {
        display: flex !important;
        flex-shrink: 0;
    }

    .editor-nav {
        display: none !important;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 20px;
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 18px !important;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
    }

    .editor-nav.is-open {
        display: flex !important;
    }

    .editor-nav > a {
        width: 100%;
    }

    .editor-logout-form {
        width: 100%;
    }

    .editor-logout-btn {
        width: 100%;
    }
}
.review-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(15, 23, 42, .62);
    backdrop-filter: blur(3px);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    overflow-y: auto;
}

.review-modal-backdrop.open {
    display: flex;
}

.review-modal {
    width: 100%;
    max-width: 1000px;
    max-height: calc(100vh - 32px);
    overflow-y: auto;
    overflow-x: hidden;
    box-sizing: border-box;
    background: #fff;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, .2);
    animation: modalFadeIn .2s ease;
}

@media (max-width: 768px) {
    .review-modal-backdrop {
        padding: 10px;
        align-items: center;
    }

    .review-modal {
        width: 100%;
        max-width: 100%;
        max-height: calc(100vh - 20px);
        padding: 20px;
        border-radius: 12px;
    }

    .review-modal * {
        max-width: 100%;
        box-sizing: border-box;
    }
}
    </style>
</head>

<body>

<nav class="navbar">
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

<div class="editor-nav" id="editor-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="manuscripts.php">Manuscripts</a>
    <a href="decisions.php" class="active">Editorial Decisions</a>
    <a href="issues.php">Issue Management</a>

    <a href="settings.php" class="editor-profile-pill">
        <svg xmlns="http://www.w3.org/2000/svg"
            width="18" height="18" viewBox="0 0 24 24"
            fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/>
            <path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/>
        </svg>
        <span><?= e($eicName) ?></span>
    </a>

    <form action="../auth/logout.php" method="POST" class="editor-logout-form">
        <button type="submit" class="editor-logout-btn">Log out</button>
    </form>
</div>
</nav>

<div class="container">

    <div class="page-header">
        <h1>Editorial Decisions</h1>
        <p>
            Welcome, <?= e($eicName) ?>.
            Review completed assessments and make final editorial decisions.
        </p>
    </div>

    <?php if ($successMessage !== ''): ?>
        <div class="notice notice-success">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="notice notice-error">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($loadError !== ''): ?>
        <div class="notice notice-error">
            <?= e($loadError) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($manuscripts)): ?>

        <div class="empty-state">
            <h3>No manuscripts awaiting decisions</h3>
            <p>
                Manuscripts awaiting final editorial decisions will appear here
                after they enter the review process.
            </p>
        </div>

    <?php else: ?>
        <div class="stats-grid">
            <div class="stat-card">
            <div class="stat-label">Under Review</div>
            <div class="stat-number"><?= $totalManuscripts ?></div>
            </div>
            <div class="stat-card"><div class="stat-label">Ready for Decision</div><div class="stat-number"><?= $readyCount ?></div></div>
            <div class="stat-card"><div class="stat-label">Awaiting Reviews</div><div class="stat-number"><?= $waitingCount ?></div></div>
        </div>

        <div class="toolbar">
            <h2 style="margin:0;font-size:18px;">Manuscripts Awaiting Decision</h2>
            <input type="search" id="manuscriptSearch" class="search-input" placeholder="Search title or author..." aria-label="Search manuscripts">
        </div>
        <div class="table-wrap">
            <table id="decisionTable">
                <thead><tr><th>Manuscript</th><th>Author</th><th>Review Progress</th><th>Submitted</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($manuscripts as $m): ?>
                    <tr class="decision-row" data-search="<?= e(strtolower($m['title'].' '.$m['first_name'].' '.$m['last_name'].' '.$m['email'])) ?>">
                        <td><strong><?= e($m['title']) ?></strong></td>
                        <td><?= e(trim(($m['first_name'] ?? '').' '.($m['last_name'] ?? ''))) ?></td>
                        <td><span class="progress-pill <?= $m['ready_for_decision'] ? 'progress-ready' : 'progress-wait' ?>"><?= $m['completed_assignments'] ?>/<?= $m['total_assignments'] ?> completed</span></td>
                        <td><?= !empty($m['submitted_at']) ? e(date('M d, Y',strtotime($m['submitted_at']))) : 'Not specified' ?></td>
                        <td>
                            <button class="view-btn" type="button"
                                onclick="openManuscriptModal(<?= (int)$m['id'] ?>)">
                                View Reviews
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($totalPages > 1): ?>
    <div class="pagination">

        <?php if ($currentPage > 1): ?>
            <a class="pagination-btn"
               href="decisions.php?page=<?= $currentPage - 1 ?>">
                &larr; Previous
            </a>
        <?php else: ?>
            <span class="pagination-btn disabled">&larr; Previous</span>
        <?php endif; ?>

        <div class="pagination-pages">
            <?php for ($pageNum = 1; $pageNum <= $totalPages; $pageNum++): ?>
                <a
                    href="decisions.php?page=<?= $pageNum ?>"
                    class="pagination-number <?= $pageNum === $currentPage ? 'active' : '' ?>"
                >
                    <?= $pageNum ?>
                </a>
            <?php endfor; ?>
        </div>

        <?php if ($currentPage < $totalPages): ?>
            <a class="pagination-btn"
               href="decisions.php?page=<?= $currentPage + 1 ?>">
                Next &rarr;
            </a>
        <?php else: ?>
            <span class="pagination-btn disabled">Next &rarr;</span>
        <?php endif; ?>

    </div>
<?php endif; ?>
        </div>

        <?php foreach ($manuscripts as $manuscript): ?>

<div class="review-modal-backdrop" id="manuscript-<?= (int)$manuscript['id'] ?>" onclick="closeModalFromBackdrop(event, <?= (int)$manuscript['id'] ?>)">
    <div class="review-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title-<?= (int)$manuscript['id'] ?>" onclick="event.stopPropagation()">

                <div class="details-top">
                <strong id="modal-title-<?= (int)$manuscript['id'] ?>">Manuscript Review & Decision</strong>
                <button type="button" class="modal-close-btn"
                    onclick="closeManuscriptModal(<?= (int)$manuscript['id'] ?>)"
                    aria-label="Close modal">
                    &times;
                </button>
                </div>
                <div class="manuscript-header">

                    <div>
                        <h2 class="manuscript-title">
                            <?= e($manuscript['title']) ?>
                        </h2>

                        <div class="manuscript-meta">
                            <strong>Author:</strong>
                            <?= e(trim(
                                ($manuscript['first_name'] ?? '') . ' ' .
                                ($manuscript['last_name'] ?? '')
                            )) ?>
                            <br>

                            <strong>Email:</strong>
                            <?= e($manuscript['email'] ?? '') ?>
                        </div>
                    </div>

                    <div class="submission-date">
                        Submitted:
                        <?= !empty($manuscript['submitted_at'])
                            ? e(date('M d, Y', strtotime($manuscript['submitted_at'])))
                            : 'Not specified' ?>
                    </div>

                </div>

                <?php if (!empty($manuscript['abstract'])): ?>

                    <h3 class="section-heading">Abstract</h3>

                    <div class="abstract-box">
                        <?= nl2br(e($manuscript['abstract'])) ?>
                    </div>

                <?php endif; ?>

                <h3 class="section-heading">
                    Reviewer Assessments
                </h3>

                <?php if (empty($manuscript['reviews'])): ?>

                    <div class="notice notice-warning">
                        No reviewer assessments have been recorded for this manuscript.
                    </div>

                <?php else: ?>

                    <?php foreach ($manuscript['reviews'] as $review): ?>

                        <?php
                            $recommendation = strtolower(
                                trim($review['recommendation'] ?? '')
                            );

                            $recommendationClass = 'recommendation-accept';

                            if (strpos($recommendation, 'minor') !== false) {
                                $recommendationClass = 'recommendation-minor';
                            } elseif (strpos($recommendation, 'major') !== false) {
                                $recommendationClass = 'recommendation-major';
                            } elseif (strpos($recommendation, 'reject') !== false) {
                                $recommendationClass = 'recommendation-reject';
                            }
                        ?>

                        <div class="review-card">

                            <div class="reviewer-header">

                                <div>
                                    <div class="reviewer-name">
                                        <?= e(trim(
                                            ($review['first_name'] ?? '') . ' ' .
                                            ($review['last_name'] ?? '')
                                        )) ?>
                                    </div>

                                    <div class="reviewer-email">
                                        <?= e($review['email'] ?? '') ?>
                                    </div>
                                </div>

                                <span class="recommendation <?= e($recommendationClass) ?>">
                                    <?= e(ucwords(str_replace('_', ' ', $review['recommendation'] ?? 'Not specified'))) ?>
                                </span>

                            </div>

                            <div class="review-label">
                                Reviewer Comments
                            </div>

                            <div class="review-text">
                                <?= !empty($review['comments'])
                                    ? e($review['comments'])
                                    : 'No comments provided.' ?>
                            </div>

                            <?php if (!empty($review['confidential_comments'])): ?>

                                <div class="confidential-box">

                                    <div class="review-label">
                                        Confidential Comments to the Editor
                                    </div>

                                    <div class="review-text">
                                        <?= e($review['confidential_comments']) ?>
                                    </div>

                                </div>

                            <?php endif; ?>

                            <div class="review-date">
                                Reviewed:
                                <?= !empty($review['reviewed_at'])
                                    ? e(date('M d, Y', strtotime($review['reviewed_at'])))
                                    : 'Not specified' ?>
                            </div>

                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

                <div class="decision-box">

                    <h3>Final Editorial Decision</h3>

                    <?php if ($manuscript['ready_for_decision']): ?>

                        <p>
                            All assigned reviewers have completed their assessments.
                            You may now record your final editorial decision.
                        </p>

                        <form
                            method="POST"
                            action="decisions.php"
                            class="decision-form"
                            onsubmit="return confirm('Are you sure you want to record this final editorial decision?');"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_token']) ?>"
                            >

                            <input
                                type="hidden"
                                name="article_id"
                                value="<?= (int)$manuscript['id'] ?>"
                            >

                            <label for="decision-<?= (int)$manuscript['id'] ?>">
                                Decision
                            </label>

                            <select
                                name="decision"
                                id="decision-<?= (int)$manuscript['id'] ?>"
                                required
                            >
                                <option value="">Select a decision</option>
                                <option value="accepted">Accept</option>
                                <option value="rejected">Reject</option>
                            </select>

                            <label for="remarks-<?= (int)$manuscript['id'] ?>">
                                Decision Remarks
                            </label>

                            <textarea
                                name="remarks"
                                id="remarks-<?= (int)$manuscript['id'] ?>"
                                placeholder="Enter the editorial decision remarks..."
                            ></textarea>

                            <button type="submit" class="decision-btn">
                                Record Decision
                            </button>

                        </form>

                    <?php else: ?>

                        <div class="notice notice-warning">
                            <strong>
                                <?= $manuscript['completed_assignments'] ?>
                                of
                                <?= $manuscript['total_assignments'] ?>
                                reviewer assignments completed.
                            </strong>

                            <br><br>

                            A final decision cannot be recorded until all assigned
                            reviewers have completed their assessments.
                        </div>

                    <?php endif; ?>

                </div>

            </div>
</div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

<footer class="footer footer-simple">
<p>&copy; <?= date('Y') ?> OJMS. All rights reserved.</p>
</footer>



<script>
    function openManuscriptModal(id) {
        const modal = document.getElementById('manuscript-' + id);
        if (!modal) return;

        modal.classList.add('open');
        document.body.style.overflow = 'hidden';

        const closeButton = modal.querySelector('.modal-close-btn');
        if (closeButton) closeButton.focus();
    }

    function closeManuscriptModal(id) {
        const modal = document.getElementById('manuscript-' + id);
        if (!modal) return;

        modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    function closeModalFromBackdrop(event, id) {
        if (event.target === event.currentTarget) {
            closeManuscriptModal(id);
        }
    }

    // Close modal with Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.review-modal-backdrop.open').forEach(modal => {
                modal.classList.remove('open');
            });

            document.body.style.overflow = '';
        }
    });

    // Manuscript search
    const manuscriptSearchBox = document.getElementById('manuscriptSearch');

    if (manuscriptSearchBox) {
        manuscriptSearchBox.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();

            document.querySelectorAll('.decision-row').forEach(row => {
                row.style.display = row.dataset.search.includes(q) ? '' : 'none';
            });
        });
    }

    // Mobile navigation
    const editorToggle = document.getElementById('editor-menu-toggle');
    const editorNav = document.getElementById('editor-nav');

    if (editorToggle && editorNav) {
        editorToggle.addEventListener('click', function() {
            const isOpen = editorNav.classList.toggle('is-open');
            editorToggle.setAttribute('aria-expanded', isOpen);
        });

        editorNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                editorNav.classList.remove('is-open');
                editorToggle.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('click', function(event) {
            if (!editorNav.contains(event.target) &&
                !editorToggle.contains(event.target)) {
                editorNav.classList.remove('is-open');
                editorToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
</script>


</body>
</html>