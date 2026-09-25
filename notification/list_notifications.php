<?php
/**
 * notification/list_notifications.php
 * Reusable function: fetch recent notifications for the logged-in user.
 */
function getUserNotifications(PDO $pdo, int $userId, int $limit = 8): array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM notifications WHERE user_id = :uid ORDER BY sent_at DESC LIMIT :lim"
    );
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function unreadNotificationCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND status = 'UNREAD'");
    $stmt->execute([':uid' => $userId]);
    return (int)$stmt->fetchColumn();
}
