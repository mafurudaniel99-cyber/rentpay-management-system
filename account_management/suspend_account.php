<?php
/**
 * account_management/suspend_account.php
 * Admin action: suspend or reactivate any user account.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('ADMIN');
$pdo = Database::getConnection();

if (isPost()) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = strtoupper(sanitize($_POST['action'] ?? 'SUSPEND'));
    $reason = sanitize($_POST['reason'] ?? 'Policy violation');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect('../admin_dashboard/dashboard.php?view=accounts&error=User+not+found');
    }

    if ($action === 'REACTIVATE') {
        $pdo->prepare("UPDATE users SET status = 'ACTIVE', suspension_reason = NULL WHERE user_id = :id")
            ->execute([':id' => $userId]);
        notify($pdo, $userId, "Account reactivated", "Your account has been reactivated.", "SYSTEM");
    } else {
        $pdo->prepare("UPDATE users SET status = 'SUSPENDED', suspension_reason = :r WHERE user_id = :id")
            ->execute([':r' => $reason, ':id' => $userId]);
        notify($pdo, $userId, "Account suspended", "Your account has been suspended: $reason", "SYSTEM");
    }

    $pdo->prepare(
        "INSERT INTO audit_logs (admin_id, target_user_id, action, details, created_at)
         VALUES (:aid, :tuid, :action, :details, NOW())"
    )->execute([
        ':aid'     => currentUserId(),
        ':tuid'    => $userId,
        ':action'  => $action,
        ':details' => $reason
    ]);

    redirect('../admin_dashboard/dashboard.php?view=accounts&success=Account+updated');
} else {
    redirect('../admin_dashboard/dashboard.php?view=accounts');
}
