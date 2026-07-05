<?php
/**
 * shared/helpers/functions.php
 * Small utility functions reused across every module.
 */

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header("Location: $path");
    exit;
}

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function formatMoney(float $amount): string
{
    return "TZS " . number_format($amount, 0);
}

function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Insert a notification for a given user.
 * Used by every module whenever a status changes (payment, maintenance, application, dispute).
 */
function notify(PDO $pdo, int $userId, string $title, string $message, string $type = 'SYSTEM'): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, title, message, type, status, sent_at)
         VALUES (:uid, :title, :message, :type, 'UNREAD', NOW())"
    );
    $stmt->execute([
        ':uid'     => $userId,
        ':title'   => $title,
        ':message' => $message,
        ':type'    => $type
    ]);
}
