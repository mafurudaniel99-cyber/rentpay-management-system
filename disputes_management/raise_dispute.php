<?php
/**
 * disputes_management/raise_dispute.php
 * Tenant action: raise a dispute (e.g. move-in defect, unit mismatch).
 * If an escrow record is HELD for this room, it is linked and flagged DISPUTED
 * so it cannot be released until an admin arbitrates.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('TENANT');
$pdo = Database::getConnection();

if (isPost()) {
    $roomId  = (int)($_POST['room_id'] ?? 0);
    $reason  = sanitize($_POST['reason'] ?? '');
    $details = sanitize($_POST['details'] ?? '');

    $tenantStmt = $pdo->prepare("SELECT tenant_id FROM tenants WHERE user_id = :uid");
    $tenantStmt->execute([':uid' => currentUserId()]);
    $tenant = $tenantStmt->fetch();

    if (!$tenant || $reason === '') {
        redirect('../tenant_dashboard/dashboard.php?view=disputes&error=Please+select+a+reason');
    }

    $evidencePath = null;
    if (!empty($_FILES['evidence']['name'])) {
        $ext = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $storedName = 'evidence_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['evidence']['tmp_name'], __DIR__ . '/../uploads/maintenance_photos/' . $storedName);
            $evidencePath = 'uploads/maintenance_photos/' . $storedName;
        }
    }

    // Link to the HELD escrow for this tenant's room, if one exists, and flag it DISPUTED.
    $escrowStmt = $pdo->prepare(
        "SELECT e.escrow_id FROM escrow e
         JOIN payments pay ON pay.payment_id = e.payment_id
         WHERE pay.room_id = :rid AND pay.tenant_id = :tid AND e.status = 'HELD'
         ORDER BY e.created_at DESC LIMIT 1"
    );
    $escrowStmt->execute([':rid' => $roomId, ':tid' => $tenant['tenant_id']]);
    $escrowId = $escrowStmt->fetchColumn() ?: null;

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "INSERT INTO disputes (tenant_id, room_id, escrow_id, reason, details, evidence_path, status, created_at)
             VALUES (:tid, :rid, :eid, :reason, :details, :evidence, 'OPEN', NOW())"
        )->execute([
            ':tid'      => $tenant['tenant_id'],
            ':rid'      => $roomId,
            ':eid'      => $escrowId,
            ':reason'   => $reason,
            ':details'  => $details,
            ':evidence' => $evidencePath
        ]);

        if ($escrowId) {
            $pdo->prepare("UPDATE escrow SET status = 'DISPUTED' WHERE escrow_id = :eid")
                ->execute([':eid' => $escrowId]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Dispute submission failed: " . $e->getMessage());
        redirect('../tenant_dashboard/dashboard.php?view=disputes&error=Could+not+submit+dispute');
    }

    redirect('../tenant_dashboard/dashboard.php?view=disputes&success=Dispute+submitted.+Admin+will+review+your+evidence.');
} else {
    redirect('../tenant_dashboard/dashboard.php?view=disputes');
}
