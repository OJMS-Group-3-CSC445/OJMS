<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// ICT Admin access only
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'portal_admin'
) {
    http_response_code(403);
    exit('Access denied.');
}

$search = trim($_GET['search'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$sql = "
    SELECT
        a.id,
        a.user_id,
        a.action,
        a.description,
        a.model_type,
        a.ip_address,
        a.url,
        a.model_id,
        a.old_values,
        a.new_values,
        a.created_at,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.email,
        u.role
    FROM audit_trails a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            a.action LIKE ?
            OR a.description LIKE ?
            OR a.model_type LIKE ?
            OR u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
        )
    ";

    $term = '%' . $search . '%';

    $params = array_fill(0, 6, $term);
}

// Validate date filters
if ($dateFrom !== '') {
    $dateFromValid = DateTime::createFromFormat('Y-m-d', $dateFrom);

    if ($dateFromValid && $dateFromValid->format('Y-m-d') === $dateFrom) {
        $sql .= " AND DATE(a.created_at) >= ?";
        $params[] = $dateFrom;
    } else {
        $dateFrom = '';
    }
}

if ($dateTo !== '') {
    $dateToValid = DateTime::createFromFormat('Y-m-d', $dateTo);

    if ($dateToValid && $dateToValid->format('Y-m-d') === $dateTo) {
        $sql .= " AND DATE(a.created_at) <= ?";
        $params[] = $dateTo;
    } else {
        $dateTo = '';
    }
}

$sql .= " ORDER BY a.created_at DESC, a.id DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$logs = $stmt->fetchAll();

// Total audit log count
$totalLogs = (int) $pdo->query("
    SELECT COUNT(*) FROM audit_trails
")->fetchColumn();

// Recent logs count (last 7 days)
$recentLogs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM audit_trails
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetchColumn();

// Helper functions
function admin_log_action_label($action) {
    return ucwords(str_replace(['_', '-'], ' ', $action));
}

function admin_log_role_label($role) {
    $labels = [
        'author' => 'Author',
        'portal_admin' => 'ICT Admin',
        'editor_in_chief' => 'Editor-in-Chief',
        'managing_editor' => 'Managing Editor',
        'reviewer' => 'Reviewer'
    ];

    return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Activity Logs | OJMS</title>

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

        .admin-nav {
            display: flex;
            align-items: center;
            gap: 22px;
            flex-wrap: wrap;
        }

        .admin-nav a,
        .admin-nav button {
            font-size: 14px;
            color: #475569;
        }

        .admin-nav a.active {
            color: #991b1b;
            font-weight: 600;
        }

        .admin-nav a:hover,
        .admin-nav button:hover {
            color: #991b1b;
        }

        .admin-nav form {
            margin: 0;
        }

        .admin-nav button {
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

        .page-heading {
            margin-bottom: 30px;
        }

        .page-heading h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .page-heading p {
            margin: 0;
            color: #64748b;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 24px;
        }

        .stat-card.total {
            border-top: 3px solid #334155;
        }

        .stat-card.recent {
            border-top: 3px solid #991b1b;
        }

        .stat-label {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 700;
        }

        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .card-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h2 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .card-header p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .filters {
            padding: 22px 24px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            border-bottom: 1px solid #e2e8f0;
        }

        .filters input {
            padding: 11px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font: inherit;
        }

        .filters input[type="search"] {
            flex: 1;
            min-width: 220px;
        }

        .filter-button {
            padding: 11px 18px;
            background: #991b1b;
            color: white;
            border: none;
            border-radius: 6px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .filter-button:hover {
            background: #7f1d1d;
        }

        .reset-button {
            padding: 11px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: #475569;
            font-size: 14px;
        }

        .results-info {
            padding: 16px 24px;
            color: #64748b;
            font-size: 13px;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            vertical-align: top;
        }

        th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
        }

        td {
            color: #334155;
        }

        .actor-name {
            font-weight: 500;
        }

        .actor-email {
            display: block;
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        .action-badge {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 12px;
            white-space: nowrap;
        }

        .description {
            max-width: 350px;
            overflow-wrap: anywhere;
        }

        .log-details summary {
            cursor: pointer;
            color: #991b1b;
            font-size: 13px;
            font-weight: 500;
        }

        .log-details-content {
            padding: 12px;
            margin-top: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            max-width: 400px;
            overflow-wrap: anywhere;
        }

        .log-details-content p {
            margin: 8px 0;
        }

        .log-details-content pre {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            font-size: 12px;
            max-height: 300px;
            overflow: auto;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #64748b;
        }

        .admin-footer {
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

        .footer-inner h2 {
            margin: 0 0 5px;
        }

        .footer-inner p {
            margin: 0;
            color: #cbd5e1;
            font-size: 14px;
        }

        .footer-copy {
            max-width: 1400px;
            margin: 30px auto 0;
            color: #cbd5e1;
            font-size: 12px;
        }

        @media(max-width: 700px) {
            .admin-header {
                padding: 18px;
            }

            .admin-nav {
                width: 100%;
                gap: 18px;
            }

            .main {
                padding: 28px 18px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters {
                padding: 18px;
            }

            .filters input {
                width: 100%;
                min-width: 0;
            }

            .card-header {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<header class="admin-header">

    <a href="dashboard.php" class="admin-brand">
        <span class="admin-logo">OJ</span>
        <span>OJMS Admin</span>
    </a>

    <nav class="admin-nav" aria-label="Admin navigation">
        <a href="dashboard.php">Dashboard</a>
        <a href="reviewers.php">Manage Accounts</a>
        <a href="users.php">Users</a>
        <a class="active" href="logs.php">Activity logs</a>

        <form action="../auth/logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit">Log out</button>
        </form>
    </nav>

</header>

<main class="main">

    <section class="page-heading">
        <h1>Activity logs</h1>
        <p>Monitor recorded activities and changes across the platform.</p>
    </section>

    <section class="stats-grid">

        <div class="stat-card total">
            <div class="stat-label">Total recorded activities</div>
            <div class="stat-value"><?= $totalLogs ?></div>
        </div>

        <div class="stat-card recent">
            <div class="stat-label">Activities in the last 7 days</div>
            <div class="stat-value"><?= $recentLogs ?></div>
        </div>

    </section>

    <section class="card">

        <div class="card-header">
            <h2>Recorded activities</h2>
            <p>Search, filter and inspect activity records.</p>
        </div>

        <form method="get" class="filters">

            <input
                type="search"
                name="search"
                placeholder="Search activity, user or description..."
                value="<?= e($search) ?>">

            <input
                type="date"
                name="date_from"
                aria-label="From date"
                value="<?= e($dateFrom) ?>">

            <input
                type="date"
                name="date_to"
                aria-label="To date"
                value="<?= e($dateTo) ?>">

            <button type="submit" class="filter-button">
                Filter
            </button>

            <a href="logs.php" class="reset-button">
                Reset
            </a>

        </form>

        <div class="results-info">
            Showing <?= count($logs) ?> record(s).
            Maximum listing: 300 activities per query.
        </div>

        <?php if (empty($logs)): ?>

            <div class="empty-state">
                <p>No activity records were found.</p>
            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Record</th>
                            <th>IP address</th>
                            <th>Date</th>
                            <th>Details</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($logs as $log): ?>

                            <?php
                            $actorName = trim(
                                ($log['first_name'] ?? '') . ' ' .
                                ($log['middle_name'] ?? '') . ' ' .
                                ($log['last_name'] ?? '')
                            );
                            ?>

                            <tr>

                                <td>
                                    <div class="actor-name">
                                        <?= e($actorName ?: 'Unknown user') ?>
                                    </div>

                                    <span class="actor-email">
                                        <?= e($log['email'] ?? 'Account unavailable') ?>
                                    </span>

                                    <?php if (!empty($log['role'])): ?>
                                        <span class="actor-email">
                                            <?= e(admin_log_role_label($log['role'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="action-badge">
                                        <?= e(admin_log_action_label($log['action'])) ?>
                                    </span>
                                </td>

                                <td class="description">
                                    <?= e($log['description']) ?>
                                </td>

                                <td>
                                    <?= e($log['model_type']) ?>

                                    <?php if ($log['model_id']): ?>
                                        #<?= e((string) $log['model_id']) ?>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= e($log['ip_address'] ?: '—') ?>
                                </td>

                                <td>
                                    <?= $log['created_at']
                                        ? e(date('d M Y, H:i', strtotime($log['created_at'])))
                                        : '—' ?>
                                </td>

                                <td>

                                    <details class="log-details">

                                        <summary>View details</summary>

                                        <div class="log-details-content">

                                            <p>
                                                <strong>URL:</strong><br>
                                                <?= e($log['url'] ?: '—') ?>
                                            </p>

                                            <?php if ($log['old_values']): ?>
                                                <p><strong>Previous values</strong></p>
                                                <pre><?= e($log['old_values']) ?></pre>
                                            <?php endif; ?>

                                            <?php if ($log['new_values']): ?>
                                                <p><strong>New values</strong></p>
                                                <pre><?= e($log['new_values']) ?></pre>
                                            <?php endif; ?>

                                            <p>
                                                <strong>Record ID:</strong>
                                                <?= e((string) $log['model_id']) ?>
                                            </p>

                                            <p>
                                                <strong>Log ID:</strong>
                                                <?= e((string) $log['id']) ?>
                                            </p>

                                        </div>

                                    </details>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer class="admin-footer">

    <div class="footer-inner">

        <div>
            <h2>OJMS</h2>
            <p>Open-access scholarly publishing</p>
        </div>

        <div>
            ICT Administration
        </div>

    </div>

    <div class="footer-copy">
        &copy; <?= date('Y') ?> OJMS. All rights reserved.
    </div>

</footer>

</body>
</html>