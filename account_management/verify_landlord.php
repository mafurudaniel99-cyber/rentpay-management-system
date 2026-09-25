<?php
/**
 * account_management/verify_landlord.php
 * Admin action: approve or reject a landlord's verification (BRELA/PDPC).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('ADMIN');
$pdo = Database::getConnection();

if (isPost()) {
    $userId   = (int)($_POST['user_id'] ?? 0);
    $decision = strtoupper(sanitize($_POST['decision'] ?? ''));
    $remarks  = sanitize($_POST['remarks'] ?? '');

    if (!in_array($decision, ['APPROVE', 'REJECT'])) {
        redirect('../admin_dashboard/dashboard.php?view=verifications&error=Invalid+decision');
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id AND role = 'LANDLORD'");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect('../admin_dashboard/dashboard.php?view=verifications&error=Landlord+not+found');
    }

    if ($decision === 'APPROVE') {
        $pdo->prepare("UPDATE users SET status = 'APPROVED' WHERE user_id = :id")->execute([':id' => $userId]);
        notify($pdo, $userId, "Account approved", "Your landlord account has been approved. You can now list properties.", "SYSTEM");
    } else {
        $pdo->prepare("UPDATE users SET status = 'REJECTED', rejection_reason = :r WHERE user_id = :id")
            ->execute([':r' => $remarks ?: 'Documents could not be verified', ':id' => $userId]);
        notify($pdo, $userId, "Account rejected", "Your landlord application was rejected: " . ($remarks ?: 'Documents could not be verified'), "SYSTEM");
    }

    $pdo->prepare(
        "INSERT INTO audit_logs (admin_id, target_user_id, action, details, created_at)
         VALUES (:aid, :tuid, :action, :details, NOW())"
    )->execute([
        ':aid'     => currentUserId(),
        ':tuid'    => $userId,
        ':action'  => $decision === 'APPROVE' ? 'APPROVE_LANDLORD' : 'REJECT_LANDLORD',
        ':details' => $remarks
    ]);

    redirect('../admin_dashboard/dashboard.php?view=verifications&success=Decision+recorded');
} else {
    redirect('../admin_dashboard/dashboard.php?view=verifications');
}
