<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape output before displaying it in HTML.
 */
function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate or retrieve CSRF token.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token.
 */
function verify_csrf_token($token): bool {
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirect helper.
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * Flash message helper.
 */
function set_flash(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

/**
 * Retrieve and remove flash message.
 */
function get_flash(string $key): ?string {
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $message;
}

/**
 * Record an activity in the audit trail.
 *
 * @param PDO    $pdo
 * @param string $action
 * @param string $description
 * @param string|null $modelType
 * @param int|string|null $modelId
 * @param array|null $oldValues
 * @param array|null $newValues
 */
function log_activity(
    PDO $pdo,
    string $action,
    string $description,
    ?string $modelType = null,
    $modelId = null,
    ?array $oldValues = null,
    ?array $newValues = null
): bool {

    try {
        $userId = $_SESSION['user_id'] ?? null;

        $oldJson = $oldValues !== null
            ? json_encode($oldValues, JSON_UNESCAPED_UNICODE)
            : null;

        $newJson = $newValues !== null
            ? json_encode($newValues, JSON_UNESCAPED_UNICODE)
            : null;

        $stmt = $pdo->prepare("
            INSERT INTO audit_trails (
                user_id,
                action,
                description,
                model_type,
                model_id,
                old_values,
                new_values,
                ip_address,
                url,
                created_at
            ) VALUES (
                :user_id,
                :action,
                :description,
                :model_type,
                :model_id,
                :old_values,
                :new_values,
                :ip_address,
                :url,
                NOW()
            )
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':description' => $description,
            ':model_type' => $modelType,
            ':model_id' => $modelId,
            ':old_values' => $oldJson,
            ':new_values' => $newJson,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':url' => $_SERVER['REQUEST_URI'] ?? null
        ]);

        return true;

    } catch (Throwable $e) {
        error_log("Audit logging failed: " . $e->getMessage());
        return false;
    }
}