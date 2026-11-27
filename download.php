<?php
declare(strict_types=1);

// Serve published article documents stored outside the public web root.
require_once __DIR__ . '/config/database.php';

function failDownload(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    failDownload(500, 'Unable to retrieve this article right now.');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    failDownload(400, 'Invalid article request.');
}

try {
    $stmt = $pdo->prepare("SELECT title, document_path FROM articles WHERE id = :id AND status = 'published' LIMIT 1");
    $stmt->execute([':id' => $id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[OJMS Download DB Error] ' . $e->getMessage());
    failDownload(500, 'Unable to retrieve this article right now.');
}

if (!$article || empty($article['document_path'])) {
    failDownload(404, 'Article document not found.');
}

// Use the absolute filesystem path already stored in the database.
$storedPath = trim((string)$article['document_path']);
$allowedDirectory = realpath('/home4/vtenergies/ojms_manuscripts');
$filePath = realpath($storedPath);

if ($allowedDirectory === false || $filePath === false) {
    error_log('[OJMS Download] Path resolution failed for article ID ' . $id . '. Storage directory or stored file path could not be resolved.');
    failDownload(404, 'Article document not found.');
}

// Restrict delivery to files within the manuscript directory.
$allowedPrefix = rtrim($allowedDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strncmp($filePath, $allowedPrefix, strlen($allowedPrefix)) !== 0) {
    error_log('[OJMS Download] Article ID ' . $id . ' resolved outside the allowed manuscript directory.');
    failDownload(403, 'Access to this document is not permitted.');
}

if (!is_file($filePath) || !is_readable($filePath)) {
    error_log('[OJMS Download] File missing or unreadable for article ID ' . $id . '.');
    failDownload(404, 'Article document not found.');
}

$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$allowedTypes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
if (!isset($allowedTypes[$extension])) {
    failDownload(415, 'This document format is not supported.');
}

$downloadName = 'article-' . $id . '.' . $extension;
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $allowedTypes[$extension]);
header('Content-Length: ' . (string)filesize($filePath));
header('Content-Disposition: ' . ($extension === 'pdf' ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"');
header('Cache-Control: private, no-store, max-age=0');
readfile($filePath);
exit;
