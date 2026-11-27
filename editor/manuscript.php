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

$articleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($articleId <= 0) {
    exit('Invalid manuscript.');
}

$manuscript = null;
$reviewers = [];
$errorMessage = '';

try {
    // Fetch manuscript
    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            authors,
            abstract,
            document_path,
            original_filename,
            file_size,
            status,
            submitted_at
        FROM articles
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$articleId]);
    $manuscript = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$manuscript) {
        http_response_code(404);
        exit('Manuscript not found.');
    }

    // Fetch reviewer assignments and submitted reviews
    $stmt = $pdo->prepare("
        SELECT
            ra.id AS assignment_id,
            ra.status AS assignment_status,
            ra.assigned_at,
            u.first_name,
            u.last_name,
            u.email,
            rr.recommendation,
            rr.comments,
            rr.confidential_comments,
            rr.reviewed_at
        FROM reviewer_assignments ra
        INNER JOIN users u ON ra.reviewer_id = u.id
        LEFT JOIN reviewer_reviews rr
            ON rr.article_id = ra.article_id
            AND rr.reviewer_id = ra.reviewer_id
        WHERE ra.article_id = ?
        ORDER BY ra.assigned_at DESC
    ");

    $stmt->execute([$articleId]);
    $reviewers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('EIC manuscript details error: ' . $e->getMessage());
    $errorMessage = 'Unable to load manuscript information.';
}

// Manuscript download
if (isset($_GET['download']) && $manuscript) {

    $storedPath = $manuscript['document_path'] ?? '';

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

    $filename = $manuscript['original_filename'] ?? basename($filePath);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($filePath));

    readfile($filePath);
    exit;
}

// Reviewer assignment handling
$availableReviewers = [];
$assignmentMessage = '';
$assignmentError = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_reviewers'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $assignmentError = 'Invalid form submission. Please refresh and try again.';
    } else {
        $selectedReviewerIds = array_values(array_unique(array_filter(array_map('intval', $_POST['reviewer_ids'] ?? []), fn($id) => $id > 0)));
        if (!$selectedReviewerIds) {
            $assignmentError = 'Please select at least one reviewer.';
        } else {
            try {
                $pdo->beginTransaction();
                $lockArticle = $pdo->prepare("SELECT status FROM articles WHERE id = ? FOR UPDATE");
                $lockArticle->execute([$articleId]);
                $articleStatus = $lockArticle->fetchColumn();
                if (!$articleStatus) {
                    throw new Exception('Manuscript not found.');
                }
                if (in_array($articleStatus, ['accepted', 'rejected', 'published'], true)) {
                    throw new Exception('Reviewers cannot be assigned to a manuscript with a final status.');
                }
                $verifyReviewer = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'reviewer'");
                $checkAssignment = $pdo->prepare("SELECT id FROM reviewer_assignments WHERE article_id = ? AND reviewer_id = ? LIMIT 1");
                $insertAssignment = $pdo->prepare("INSERT INTO reviewer_assignments (article_id, reviewer_id, assigned_by, status, assigned_at, updated_at) VALUES (?, ?, ?, 'Pending', NOW(), NOW())");
                $assignedCount = 0;
                foreach ($selectedReviewerIds as $reviewerId) {
                    $verifyReviewer->execute([$reviewerId]);
                    if (!$verifyReviewer->fetchColumn()) {
                        continue;
                    }
                    $checkAssignment->execute([$articleId, $reviewerId]);
                    if ($checkAssignment->fetchColumn()) {
                        continue;
                    }
                    $insertAssignment->execute([$articleId, $reviewerId, (int)$_SESSION['user_id']]);
                    $assignedCount++;
                }
                if ($assignedCount > 0) {
                    $updateArticle = $pdo->prepare("UPDATE articles SET status = 'under_review', updated_at = NOW() WHERE id = ? AND status = 'pending_review'");
                    $updateArticle->execute([$articleId]);
                }
                $pdo->commit();
                $assignmentMessage = $assignedCount > 0 ? $assignedCount . ' reviewer(s) assigned successfully.' : 'No new reviewers were assigned. They may already be assigned.';
                header('Location: manuscript.php?id=' . $articleId . '&assigned=1');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('EIC reviewer assignment error: ' . $e->getMessage());
                $assignmentError = $e->getMessage() === 'Manuscript not found.' || $e->getMessage() === 'Reviewers cannot be assigned to a manuscript with a final status.' ? $e->getMessage() : 'Unable to assign reviewers at this time.';
            }
        }
    }
}

try {
    $reviewerListStmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE role = 'reviewer' ORDER BY first_name, last_name");
    $reviewerListStmt->execute();
    $availableReviewers = $reviewerListStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Reviewer list fetch error: ' . $e->getMessage());
}

if (isset($_GET['assigned'])) {
    $assignmentMessage = 'Reviewer assignment(s) saved successfully.';
}

// EIC name
$eicName = trim(
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '')
);

if ($eicName === '') {
    $eicName = $_SESSION['email'] ?? 'Editor-in-Chief';
}

// Review statistics
$completedReviews = 0;

foreach ($reviewers as $reviewer) {
    if ($reviewer['assignment_status'] === 'Completed') {
        $completedReviews++;
    }
}

$totalReviewers = count($reviewers);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manuscript Details | EIC Portal</title>

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

        .back-link {
            display: inline-block;
            color: #991b1b;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            margin: 0 0 10px;
        }

        .page-header p {
            color: #6b7280;
            margin: 0;
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
            font-size: 25px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .meta {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 12px;
            line-height: 1.7;
        }

        .meta strong {
            color: #374151;
        }

        .abstract {
            margin-top: 30px;
            line-height: 1.8;
            color: #475569;
            white-space: pre-line;
        }

        .abstract h3 {
            color: #1f2937;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #fef3c7;
            color: #92400e;
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
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-draft {
            background: #e5e7eb;
            color: #374151;
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

        .review-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 18px;
        }

        .summary-box p {
            color: #64748b;
            font-size: 13px;
            margin: 0 0 10px;
        }

        .summary-box h3 {
            font-size: 24px;
            margin: 0;
        }

        .reviewer-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 22px;
            margin-bottom: 20px;
        }

        .reviewer-card:last-child {
            margin-bottom: 0;
        }

        .reviewer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .reviewer-header h3 {
            margin: 0 0 8px;
            font-size: 17px;
        }

        .reviewer-header p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }

        .review-status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            background: #fef3c7;
            color: #92400e;
        }

        .review-status.completed {
            background: #dcfce7;
            color: #166534;
        }

        .review-section {
            margin-top: 20px;
        }

        .review-section h4 {
            font-size: 15px;
            margin-bottom: 10px;
        }

        .review-section p {
            line-height: 1.8;
            font-size: 14px;
            color: #475569;
            white-space: pre-line;
            margin: 0;
        }

        .recommendation {
            display: inline-block;
            background: #eff6ff;
            color: #1e40af;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        .confidential-box {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            padding: 16px;
            border-radius: 8px;
        }

        .confidential-box h4 {
            color: #9a3412;
        }

        .empty-state {
            text-align: center;
            padding: 35px 20px;
            color: #64748b;
            background: #f8fafc;
            border-radius: 8px;
        }

        .notice {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
            background: #fee2e2;
            color: #991b1b;
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


        .assignment-form { margin-top: 25px; padding: 22px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; }
        .assignment-form select { width: 100%; min-height: 150px; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font: inherit; margin: 10px 0 15px; }
        .assignment-btn { border: none; background: #991b1b; color: white; padding: 12px 18px; border-radius: 6px; cursor: pointer; font: inherit; }
        .assignment-btn:hover { background: #7f1d1d; }
        .assignment-notice { padding: 14px; border-radius: 8px; margin: 15px 0; background: #dcfce7; color: #166534; }
        .assignment-error { padding: 14px; border-radius: 8px; margin: 15px 0; background: #fee2e2; color: #991b1b; }

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

            .review-summary {
                grid-template-columns: 1fr;
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
    background: #fff;
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

.container {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 130px clamp(24px, 5vw, 80px) 60px;
    flex: 1;
}

.back-link {
    color: #991b1b;
    display: inline-block;
    margin-bottom: 25px;
    font-size: 14px;
}

.back-link:hover {
    color: #7f1d1d;
}

.page-header {
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 28px;
    line-height: 1.3;
    color: #1e293b;
}

.page-header p {
    font-size: 15px;
    line-height: 1.7;
    color: #64748b;
}

/* CARDS */

.card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 28px;
    margin-bottom: 25px;
}

.card h2 {
    font-size: 20px;
    color: #1e293b;
}

.manuscript-title {
    font-size: 24px;
    line-height: 1.6;
    color: #1e293b;
    overflow-wrap: anywhere;
}

.meta {
    color: #64748b;
    line-height: 1.8;
}

.meta strong {
    color: #334155;
}

.abstract {
    line-height: 1.8;
    color: #475569;
}

.abstract h3 {
    color: #1e293b;
    font-size: 17px;
}

/* STATUS */

.status-badge {
    border-radius: 50px;
    font-weight: 600;
    font-size: 12px;
    padding: 7px 12px;
}

/* DOWNLOAD BUTTON */

.download-btn {
    display: inline-flex;
    align-items: center;
    background: #991b1b;
    color: #fff;
    border-radius: 8px;
    padding: 12px 18px;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s ease;
}

.download-btn:hover {
    background: #7f1d1d;
}

/* REVIEW SUMMARY */

.review-summary {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.summary-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 22px;
}

.summary-box p {
    color: #64748b;
}

.summary-box h3 {
    font-size: 28px;
    color: #1e293b;
}

/* REVIEWER CARDS */

.reviewer-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 24px;
    margin-bottom: 20px;
}

.reviewer-header {
    margin-bottom: 20px;
}

.reviewer-header h3 {
    color: #1e293b;
}

.reviewer-header p {
    color: #64748b;
}

.review-section {
    margin-top: 22px;
}

.review-section h4 {
    color: #334155;
}

.review-section p {
    line-height: 1.8;
    color: #475569;
}

.recommendation {
    border-radius: 8px;
}

.confidential-box {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 8px;
}

.empty-state {
    border-radius: 10px;
    padding: 40px 20px;
}

.notice,
.assignment-notice,
.assignment-error {
    border-radius: 10px;
    line-height: 1.7;
}

/* REVIEWER ASSIGNMENT */

.assignment-form {
    margin-top: 25px;
    padding: 24px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.reviewer-selection-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.reviewer-selection-header h3 {
    margin: 0 0 8px;
    font-size: 17px;
    color: #1e293b;
}

.reviewer-selection-header p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
}

.selected-reviewer-count {
    padding: 8px 12px;
    border-radius: 50px;
    background: #e2e8f0;
    color: #475569;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}

.selected-reviewer-count.has-selection {
    background: #fee2e2;
    color: #991b1b;
}

.reviewer-selection-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 22px;
}

.reviewer-selection-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 17px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
}

.reviewer-selection-card:hover {
    border-color: #cbd5e1;
    background: #fdfdfd;
}

.reviewer-selection-card:has(.reviewer-checkbox:checked) {
    border-color: #991b1b;
    background: #fff7f7;
    box-shadow: 0 0 0 2px rgba(153, 27, 27, 0.06);
}

.reviewer-checkbox {
    position: absolute;
    opacity: 0;
    width: 1px;
    height: 1px;
}

.reviewer-checkbox-custom {
    width: 20px;
    height: 20px;
    min-width: 20px;
    border: 1.5px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.reviewer-checkbox:checked + .reviewer-checkbox-custom {
    background: #991b1b;
    border-color: #991b1b;
}

.reviewer-checkbox:checked + .reviewer-checkbox-custom::after {
    content: "";
    width: 5px;
    height: 10px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    margin-top: -2px;
}

.reviewer-checkbox:focus-visible + .reviewer-checkbox-custom {
    outline: 3px solid rgba(153, 27, 27, 0.2);
    outline-offset: 3px;
}

.reviewer-selection-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 0;
}

.reviewer-selection-info strong {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}

.reviewer-selection-info span {
    font-size: 13px;
    color: #64748b;
    overflow-wrap: anywhere;
}

.assignment-btn {
    width: 100%;
    border: none;
    background: #991b1b;
    color: #fff;
    padding: 14px 18px;
    border-radius: 8px;
    cursor: pointer;
    font: inherit;
    font-weight: 500;
    transition: background 0.2s ease, opacity 0.2s ease;
}

.assignment-btn:hover:not(:disabled) {
    background: #7f1d1d;
}

.assignment-btn:disabled {
    background: #cbd5e1;
    color: #64748b;
    cursor: not-allowed;
}

.assignment-notice,
.assignment-error {
    padding: 14px;
    border-radius: 10px;
    margin: 15px 0;
    line-height: 1.7;
}

.assignment-notice {
    background: #dcfce7;
    color: #166534;
}

.assignment-error {
    background: #fee2e2;
    color: #991b1b;
}

@media (max-width: 600px) {
    .assignment-form {
        padding: 18px;
    }

    .reviewer-selection-card {
        padding: 15px;
    }

    .reviewer-selection-header {
        flex-direction: column;
    }
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
    .review-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
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

    .container {
        padding: 110px 24px 40px;
    }

    .card {
        padding: 22px;
    }

    .review-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* SMALL MOBILE */

@media (max-width: 600px) {
    .review-summary {
        grid-template-columns: 1fr;
    }

    .page-header h1 {
        font-size: 24px;
    }

    .manuscript-title {
        font-size: 21px;
    }

    .container {
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

    .card {
        padding: 20px;
    }

    .reviewer-card {
        padding: 18px;
    }

    .assignment-form {
        padding: 18px;
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
    <a href="manuscripts.php" class="active">Manuscripts</a>
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

    <form action="../auth/logout.php" method="POST" class="editor-logout-form">
        <button type="submit" class="editor-logout-btn">Log out</button>
    </form>
</div>
</nav>

<div class="container">

    <a href="manuscripts.php" class="back-link">
        &larr; Back to Manuscripts
    </a>

    <div class="page-header">
        <h1>Manuscript Details</h1>
        <p>Welcome, <?= e($eicName) ?>. Review the manuscript and its peer-review progress.</p>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="notice">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($manuscript): ?>

        <div class="card">

            <h2>Manuscript Information</h2>

            <div class="manuscript-title">
                <?= e($manuscript['title'] ?? 'Untitled manuscript') ?>
            </div>

            <div class="meta">
                <strong>Manuscript ID:</strong>
                #<?= (int)$manuscript['id'] ?>
            </div>

            <div class="meta">
                <strong>Authors:</strong>
                <?= e($manuscript['authors'] ?? 'Not specified') ?>
            </div>

            <div class="meta">
                <strong>Submitted:</strong>
                <?= !empty($manuscript['submitted_at'])
                    ? e(date('M d, Y', strtotime($manuscript['submitted_at'])))
                    : 'Not specified' ?>
            </div>

            <div class="meta">
                <strong>Current Status:</strong>

                <span class="status-badge status-<?= e($manuscript['status']) ?>">
                    <?= e(ucwords(str_replace('_', ' ', $manuscript['status']))) ?>
                </span>
            </div>

            <div class="abstract">
                <h3>Abstract</h3>
                <?= e($manuscript['abstract'] ?? 'No abstract available.') ?>
            </div>

            <?php if (!empty($manuscript['document_path'])): ?>

                <a href="manuscript.php?id=<?= (int)$articleId ?>&download=1"
                   class="download-btn">
                    Download Manuscript
                </a>

            <?php else: ?>

                <div class="notice" style="margin-top:20px;">
                    The manuscript document is currently unavailable.
                </div>

            <?php endif; ?>

        </div>

        <div class="card">
            <h2>Assign Reviewers</h2>
            <?php if ($assignmentMessage !== ''): ?>
                <div class="assignment-notice"><?= e($assignmentMessage) ?></div>
            <?php endif; ?>
            <?php if ($assignmentError !== ''): ?>
                <div class="assignment-error"><?= e($assignmentError) ?></div>
            <?php endif; ?>
            <?php if (in_array($manuscript['status'], ['accepted', 'rejected', 'published'], true)): ?>
                <p>Reviewer assignments are unavailable for manuscripts with a final status.</p>
            <?php else: ?>
                <form method="POST" class="assignment-form" id="reviewer-assignment-form">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

    <div class="reviewer-selection-header">
        <div>
            <h3>Select Reviewers</h3>
            <p>Choose one or more reviewers to evaluate this manuscript.</p>
        </div>

        <span class="selected-reviewer-count" id="selected-reviewer-count">
            0 selected
        </span>
    </div>

    <div class="reviewer-selection-list">
        <?php foreach ($availableReviewers as $availableReviewer): ?>
            <label class="reviewer-selection-card">
                <input
                    type="checkbox"
                    name="reviewer_ids[]"
                    value="<?= (int)$availableReviewer['id'] ?>"
                    class="reviewer-checkbox"
                >

                <span class="reviewer-checkbox-custom"></span>

                <div class="reviewer-selection-info">
                    <strong>
                        <?= e(trim($availableReviewer['first_name'] . ' ' . $availableReviewer['last_name'])) ?>
                    </strong>

                    <span>
                        <?= e($availableReviewer['email']) ?>
                    </span>
                </div>
            </label>
        <?php endforeach; ?>
    </div>

    <?php if (empty($availableReviewers)): ?>
        <div class="empty-state">
            No reviewers are currently available.
        </div>
    <?php endif; ?>

    <button
        type="submit"
        name="assign_reviewers"
        value="1"
        class="assignment-btn"
        id="assign-reviewers-btn"
        disabled
    >
        Assign Selected Reviewers
    </button>
</form>
            <?php endif; ?>
        </div>

        <div class="card">

            <h2>Peer Review Overview</h2>

            <div class="review-summary">

                <div class="summary-box">
                    <p>Total Assigned Reviewers</p>
                    <h3><?= $totalReviewers ?></h3>
                </div>

                <div class="summary-box">
                    <p>Reviews Completed</p>
                    <h3><?= $completedReviews ?></h3>
                </div>

                <div class="summary-box">
                    <p>Reviews Pending</p>
                    <h3><?= $totalReviewers - $completedReviews ?></h3>
                </div>

            </div>

            <?php if (empty($reviewers)): ?>

                <div class="empty-state">
                    No reviewers have been assigned to this manuscript yet.
                </div>

            <?php else: ?>

                <?php foreach ($reviewers as $reviewer): ?>

                    <div class="reviewer-card">

                        <div class="reviewer-header">

                            <div>
                                <h3>
                                    <?= e(trim(
                                        ($reviewer['first_name'] ?? '') . ' ' .
                                        ($reviewer['last_name'] ?? '')
                                    )) ?>
                                </h3>

                                <p><?= e($reviewer['email'] ?? '') ?></p>

                                <p>
                                    Assigned:
                                    <?= !empty($reviewer['assigned_at'])
                                        ? e(date('M d, Y', strtotime($reviewer['assigned_at'])))
                                        : 'Not specified' ?>
                                </p>
                            </div>

                            <span class="review-status <?= $reviewer['assignment_status'] === 'Completed' ? 'completed' : '' ?>">
                                <?= e($reviewer['assignment_status']) ?>
                            </span>

                        </div>

                        <?php if ($reviewer['assignment_status'] === 'Completed' && !empty($reviewer['reviewed_at'])): ?>

                            <div class="meta">
                                <strong>Review submitted:</strong>
                                <?= e(date('M d, Y', strtotime($reviewer['reviewed_at']))) ?>
                            </div>

                        <?php endif; ?>

                        <?php if (!empty($reviewer['recommendation'])): ?>

                            <div class="review-section">
                                <h4>Recommendation</h4>

                                <span class="recommendation">
                                    <?= e($reviewer['recommendation']) ?>
                                </span>
                            </div>

                        <?php endif; ?>

                        <?php if (!empty($reviewer['comments'])): ?>

                            <div class="review-section">
                                <h4>Review Comments</h4>

                                <p><?= e($reviewer['comments']) ?></p>
                            </div>

                        <?php endif; ?>

                        <?php if (!empty($reviewer['confidential_comments'])): ?>

                            <div class="review-section confidential-box">
                                <h4>Confidential Comments to the Editor</h4>

                                <p><?= e($reviewer['confidential_comments']) ?></p>
                            </div>

                        <?php endif; ?>

                        <?php if ($reviewer['assignment_status'] !== 'Completed'): ?>

                            <div class="review-section">
                                <p style="color:#92400e;">
                                    This reviewer has not submitted their evaluation yet.
                                </p>
                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

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

<script>
    const reviewerCheckboxes = document.querySelectorAll('.reviewer-checkbox');
    const reviewerCount = document.getElementById('selected-reviewer-count');
    const assignButton = document.getElementById('assign-reviewers-btn');

    function updateReviewerSelection() {
        if (!reviewerCount || !assignButton) return;

        const selectedCount = document.querySelectorAll('.reviewer-checkbox:checked').length;

        reviewerCount.textContent = selectedCount + (selectedCount === 1 ? ' selected' : ' selected');

        reviewerCount.classList.toggle('has-selection', selectedCount > 0);
        assignButton.disabled = selectedCount === 0;

        assignButton.textContent = selectedCount > 0
            ? 'Assign ' + selectedCount + ' Selected Reviewer' + (selectedCount === 1 ? '' : 's')
            : 'Assign Selected Reviewers';
    }

    reviewerCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateReviewerSelection);
    });

    updateReviewerSelection();
</script>
</body>
</html>
