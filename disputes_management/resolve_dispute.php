<?php
/**
 * disputes_management/resolve_dispute.php
 * Admin action: arbitration verdict on a dispute.
 * REFUND_TENANT   -> escrow funds return to the tenant's wallet
 * RELEASE_LANDLORD -> escrow funds proceed to the landlord's wallet
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('ADMIN');
$pdo = Database::getConnection();

if (isPost()) {
    $disputeId = (int)($_POST['dispute_id'] ?? 0);
    $verdict   = strtoupper(sanitize($_POST['verdict'] ?? ''));

    if (!in_array($verdict, ['REFUND_TENANT', 'RELEASE_LANDLORD'])) {
        redirect('../admin_dashboard/dashboard.php?view=disputes&error=Invalid+verdict');
    }

    $stmt = $pdo->prepare("SELECT * FROM disputes WHERE dispute_id = :id AND status = 'OPEN'");
    $stmt->execute([':id' => $disputeId]);
    $dispute = $stmt->fetch();

    if (!$dispute || !$dispute['escrow_id']) {
        redirect('../admin_dashboard/dashboard.php?view=disputes&error=Dispute+or+escrow+record+not+found');
    }

    $escrowStmt = $pdo->prepare("SELECT * FROM escrow WHERE escrow_id = :id");
    $escrowStmt->execute([':id' => $dispute['escrow_id']]);
    $escrow = $escrowStmt->fetch();

    $tenantUserStmt = $pdo->prepare(
        "SELECT u.user_id FROM tenants t JOIN users u ON u.user_id = t.user_id WHERE t.tenant_id = :tid"
    );
    $tenantUserStmt->execute([':tid' => $dispute['tenant_id']]);
    $tenantUserId = $tenantUserStmt->fetchColumn();

    $landlordUserStmt = $pdo->prepare(
        "SELECT u.user_id FROM landlords l JOIN users u ON u.user_id = l.user_id WHERE l.landlord_id = :lid"
    );
    $landlordUserStmt->execute([':lid' => $escrow['landlord_id']]);
    $landlordUserId = $landlordUserStmt->fetchColumn();

    $pdo->beginTransaction();
    try {
        if ($verdict === 'REFUND_TENANT') {
            $pdo->prepare("UPDATE wallets SET balance = balance + :amt WHERE user_id = :uid")
                ->execute([':amt' => $escrow['amount'], ':uid' => $tenantUserId]);
            $pdo->prepare("UPDATE escrow SET status = 'REFUNDED', resolved_at = NOW() WHERE escrow_id = :id")
                ->execute([':id' => $escrow['escrow_id']]);
            notify($pdo, (int)$tenantUserId, "Dispute resolved", "Your escrow payment has been refunded to your wallet.", "DISPUTE");
        } else {
            $pdo->prepare("UPDATE wallets SET balance = balance + :amt WHERE user_id = :uid")
                ->execute([':amt' => $escrow['amount'], ':uid' => $landlordUserId]);
            $pdo->prepare("UPDATE escrow SET status = 'RELEASED', resolved_at = NOW() WHERE escrow_id = :id")
                ->execute([':id' => $escrow['escrow_id']]);
            notify($pdo, (int)$landlordUserId, "Dispute resolved", "Escrow funds have been released to your wallet.", "DISPUTE");
        }

        $pdo->prepare(
            "UPDATE disputes SET status = 'RESOLVED', verdict = :verdict, resolved_at = NOW() WHERE dispute_id = :id"
        )->execute([':verdict' => $verdict, ':id' => $disputeId]);

        $pdo->prepare(
            "INSERT INTO audit_logs (admin_id, target_user_id, action, details, created_at)
             VALUES (:aid, :tuid, 'RESOLVE_DISPUTE', :details, NOW())"
        )->execute([
            ':aid'     => currentUserId(),
            ':tuid'    => $tenantUserId,
            ':details' => "Dispute #$disputeId resolved with verdict $verdict"
        ]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Dispute resolution failed: " . $e->getMessage());
        redirect('../admin_dashboard/dashboard.php?view=disputes&error=Could+not+resolve+dispute');
    }

    redirect('../admin_dashboard/dashboard.php?view=disputes&success=Verdict+recorded');
} else {
    redirect('../admin_dashboard/dashboard.php?view=disputes');
}
