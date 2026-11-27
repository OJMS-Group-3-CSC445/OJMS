<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$allowedStatuses = [
    'draft',
    'pending_review',
    'under_review',
    'accepted',
    'rejected',
    'published'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$manuscripts = [];
$errorMessage = '';

try {
    $sql = "
        SELECT
            a.id,
            a.title,
            a.authors,
            a.status,
            a.submitted_at,
            u.first_name,
            u.last_name,
            u.email
        FROM articles a
        INNER JOIN users u ON a.author_id = u.id
        WHERE 1=1
    ";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (
            a.title LIKE ?
            OR a.authors LIKE ?
            OR u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
        )";

        $searchTerm = '%' . $search . '%';

        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($statusFilter !== '') {
        $sql .= " AND a.status = ?";
        $params[] = $statusFilter;
    }

    $sql .= " ORDER BY a.submitted_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $manuscripts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('EIC manuscripts listing error: ' . $e->getMessage());
    $errorMessage = 'Unable to load manuscripts at this time.';
}

// Pagination
$totalManuscripts = count($manuscripts);
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

    <title>Manuscripts | EIC Portal</title>

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
            transition: background 0.2s ease;
        }

        .logout-btn:hover {
            background: #fee2e2;
        }

        /* Main content */
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
            font-weight: 700;
            margin: 0 0 12px;
            color: #1e293b;
        }

        .page-header p {
            color: #64748b;
            margin: 0;
            line-height: 1.7;
            font-size: 15px;
        }

        /* Cards */
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.02);
        }

        /* Filters */
        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .filters input,
        .filters select {
            padding: 13px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
            font-family: inherit;
            color: #1e293b;
            outline: none;
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }

        .filters input:focus,
        .filters select:focus {
            border-color: #991b1b;
            box-shadow: 0 0 0 3px rgba(153, 27, 27, 0.08);
        }

        .filters input {
            flex: 1;
            min-width: 250px;
        }

        .filters select {
            min-width: 200px;
        }

        .filter-btn {
            background: #991b1b;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            cursor: pointer;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s ease;
        }

        .filter-btn:hover {
            background: #7f1d1d;
        }

        .reset-btn {
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease;
        }

        .reset-btn:hover {
            background: #e2e8f0;
        }

        /* Results */
        .results-count {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .table-wrapper {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th,
        td {
            text-align: left;
            padding: 17px 15px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
        }

        td {
            font-size: 14px;
            color: #475569;
        }

        tbody tr:hover {
            background: #fafafa;
        }

        .title-cell {
            font-weight: 600;
            color: #1e293b;
            min-width: 280px;
            line-height: 1.7;
        }

        .author-cell {
            min-width: 200px;
            line-height: 1.7;
        }

        .author-cell small {
            color: #94a3b8;
            font-size: 12px;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 7px 12px;
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
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-draft {
            background: #e5e7eb;
            color: #374151;
        }

        /* View button */
        .view-btn {
            display: inline-block;
            background: #fef2f2;
            color: #991b1b;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            transition: background 0.2s ease;
        }

        .view-btn:hover {
            background: #fee2e2;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state h3 {
            color: #1e293b;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 14px;
            line-height: 1.7;
        }

        /* Error notice */
        .notice {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
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
            font-weight: 700;
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

            .page-header h1 {
                font-size: 25px;
            }

            .card {
                padding: 18px;
            }

            .filters {
                flex-direction: column;
            }

            .filters input,
            .filters select,
            .filter-btn,
            .reset-btn {
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

/* CARD */

.card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.02);
}

/* FILTERS */

.filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.filters input,
.filters select {
    padding: 13px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
    font-family: inherit;
    color: #1e293b;
    outline: none;
    transition: border 0.2s ease, box-shadow 0.2s ease;
}

.filters input:focus,
.filters select:focus {
    border-color: #991b1b;
    box-shadow: 0 0 0 3px rgba(153, 27, 27, 0.08);
}

.filters input {
    flex: 1;
    min-width: 250px;
}

.filters select {
    min-width: 200px;
}

.filter-btn {
    background: #991b1b;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px 20px;
    cursor: pointer;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.2s ease;
}

.filter-btn:hover {
    background: #7f1d1d;
}

.reset-btn {
    background: #f1f5f9;
    color: #475569;
    padding: 12px 18px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease;
}

.reset-btn:hover {
    background: #e2e8f0;
}

/* RESULTS */

.results-count {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 20px;
}

.table-wrapper {
    overflow-x: auto;
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th,
td {
    text-align: left;
    padding: 17px 15px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

th {
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 600;
}

td {
    font-size: 14px;
    color: #475569;
}

tbody tr {
    transition: background 0.2s ease;
}

tbody tr:hover {
    background: #fafafa;
}

tbody tr:last-child td {
    border-bottom: none;
}

.title-cell {
    font-weight: 600;
    color: #1e293b;
    min-width: 280px;
    line-height: 1.7;
}

.author-cell {
    min-width: 200px;
    line-height: 1.7;
}

.author-cell small {
    color: #94a3b8;
    font-size: 12px;
}

/* STATUS */

.status-badge {
    display: inline-block;
    padding: 7px 12px;
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
    background: #e0e7ff;
    color: #3730a3;
}

.status-draft {
    background: #e5e7eb;
    color: #374151;
}

/* VIEW BUTTON */

.view-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fef2f2;
    color: #991b1b;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    transition: background 0.2s ease;
}

.view-btn:hover {
    background: #fee2e2;
}

/* PAGINATION */

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.pagination-pages {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
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
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.pagination-btn:hover:not(.disabled),
.pagination-number:hover {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
}

.pagination-number.active {
    background: #991b1b;
    color: #fff;
    border-color: #991b1b;
}

.pagination-btn.disabled {
    color: #94a3b8;
    background: #f8fafc;
    cursor: not-allowed;
}

/* EMPTY STATE */

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}

.empty-state h3 {
    color: #1e293b;
    font-size: 18px;
    margin-bottom: 10px;
}

.empty-state p {
    font-size: 14px;
    line-height: 1.7;
}

/* ERROR */

.notice {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.6;
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
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
    .pagination {
        justify-content: center;
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

    .filters {
        flex-direction: column;
    }

    .filters input,
    .filters select,
    .filter-btn,
    .reset-btn {
        width: 100%;
    }

    .pagination {
        flex-direction: column;
    }
}

/* SMALL MOBILE */

@media (max-width: 600px) {
    .page-header h1 {
        font-size: 24px;
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

    .pagination-pages {
        justify-content: center;
    }

    .pagination-btn,
    .pagination-number {
        min-width: 36px;
        height: 36px;
        padding: 0 9px;
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

    <div class="page-header">
        <h1>Manuscripts</h1>
        <p>
            Welcome, <?= e($eicName) ?>.
            Manage submissions and monitor their editorial status.
        </p>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="notice">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <form method="GET" action="manuscripts.php" class="filters">

            <input
                type="text"
                name="search"
                placeholder="Search title, author or email..."
                value="<?= e($search) ?>"
            >

            <select name="status">
                <option value="">All Statuses</option>

                <?php foreach ($allowedStatuses as $status): ?>
                    <option
                        value="<?= e($status) ?>"
                        <?= $statusFilter === $status ? 'selected' : '' ?>
                    >
                        <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="filter-btn">
                Search
            </button>

            <a href="manuscripts.php" class="reset-btn">
                Reset
            </a>

        </form>

        <div class="results-count">
    <span>
        Showing <?= $totalManuscripts > 0 ? $offset + 1 : 0 ?>
        - <?= min($offset + $perPage, $totalManuscripts) ?>
        of <?= $totalManuscripts ?> manuscripts
    </span>
</div>

        <?php if (empty($manuscripts)): ?>

            <div class="empty-state">
                <h3>No manuscripts found</h3>
                <p>
                    There are no manuscripts matching your search or filter.
                </p>
            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Manuscript</th>
                            <th>Author</th>
                            <th>Submission Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($manuscripts as $row): ?>

                            <tr>

                                <td class="title-cell">
                                    <?= e($row['title'] ?? 'Untitled manuscript') ?>
                                </td>

                                <td class="author-cell">
                                    <?= e(trim(
                                        ($row['first_name'] ?? '') . ' ' .
                                        ($row['last_name'] ?? '')
                                    )) ?>

                                    <br>

                                    <small>
                                        <?= e($row['email'] ?? '') ?>
                                    </small>
                                </td>

                                <td>
                                    <?= !empty($row['submitted_at'])
                                        ? e(date('M d, Y', strtotime($row['submitted_at'])))
                                        : 'Not specified' ?>
                                </td>

                                <td>
                                    <span class="status-badge status-<?= e($row['status']) ?>">
                                        <?= e(ucwords(str_replace('_', ' ', $row['status']))) ?>
                                    </span>
                                </td>

                                <td>
                                    <a
                                        href="manuscript.php?id=<?= (int)$row['id'] ?>"
                                        class="view-btn"
                                    >
                                        View
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>
            <?php if ($totalPages > 1): ?>
    <div class="pagination">

        <?php
        $paginationBase = 'manuscripts.php?';
        $paginationParams = [];

        if ($search !== '') {
            $paginationParams['search'] = $search;
        }

        if ($statusFilter !== '') {
            $paginationParams['status'] = $statusFilter;
        }

        $paginationBase .= http_build_query($paginationParams);
        $paginationBase .= $paginationBase !== 'manuscripts.php?' ? '&' : '';
        ?>

        <?php if ($currentPage > 1): ?>
            <a class="pagination-btn"
               href="<?= e($paginationBase) ?>page=<?= $currentPage - 1 ?>">
                &larr; Previous
            </a>
        <?php else: ?>
            <span class="pagination-btn disabled">&larr; Previous</span>
        <?php endif; ?>

        <div class="pagination-pages">
            <?php for ($pageNum = 1; $pageNum <= $totalPages; $pageNum++): ?>
                <a
                    href="<?= e($paginationBase) ?>page=<?= $pageNum ?>"
                    class="pagination-number <?= $pageNum === $currentPage ? 'active' : '' ?>"
                >
                    <?= $pageNum ?>
                </a>
            <?php endfor; ?>
        </div>

        <?php if ($currentPage < $totalPages): ?>
            <a class="pagination-btn"
               href="<?= e($paginationBase) ?>page=<?= $currentPage + 1 ?>">
                Next &rarr;
            </a>
        <?php else: ?>
            <span class="pagination-btn disabled">Next &rarr;</span>
        <?php endif; ?>

    </div>
<?php endif; ?>

        <?php endif; ?>

    </div>

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

</body>
</html>
