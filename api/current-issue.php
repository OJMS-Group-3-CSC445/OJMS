<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

/**
 * Reuse existing database connection.
 * Update the path below to match your project's actual PDO bootstrap file.
 */
$dbBootstrap = dirname(__DIR__) . '/config/database.php';
if (file_exists($dbBootstrap)) {
    require_once $dbBootstrap;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(503);
    echo json_encode(['error' => 'Database connection unavailable.']);
    exit;
}

try {
    // 1. Fetch the current issue (is_current = 1)
    $issueStmt = $pdo->prepare(
        "SELECT id, title, volume, issue_number, publication_date, description
         FROM journal_issues
         WHERE is_current = 1
         ORDER BY publication_date DESC, id DESC
         LIMIT 1"
    );
    $issueStmt->execute();
    $issue = $issueStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $articles = [];

    // 2. Fetch ONLY published articles belonging to the current issue
    if ($issue !== null) {
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
            ':issue_id' => (int) $issue['id'],
            ':status'   => 'published',
        ]);
        $articles = $artStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'issue'    => $issue,
        'articles' => $articles,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[OJMS Current Issue API Error] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load current issue data.']);
}