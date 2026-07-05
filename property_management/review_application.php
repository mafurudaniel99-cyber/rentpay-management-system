<?php
/**
 * property_management/review_application.php
 * Landlord action: approve or reject a tenancy application.
 * On approval: reserves the room and generates the first invoice.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('LANDLORD');
$pdo = Database::getConnection();

if (isPost()) {
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $decision      = strtoupper(sanitize($_POST['decision'] ?? '')); // APPROVE or REJECT

    // Confirm the application belongs to a room owned by this landlord.
    $stmt = $pdo->prepare(
        "SELECT a.*, r.rent_amount, r.property_id, t.user_id AS tenant_user_id
         FROM applications a
         JOIN rooms r ON r.room_id = a.room_id
         JOIN properties p ON p.property_id = r.property_id
         JOIN landlords l ON l.landlord_id = p.landlord_id
         JOIN tenants t ON t.tenant_id = a.tenant_id
         WHERE a.application_id = :aid AND l.user_id = :uid"
    );
    $stmt->execute([':aid' => $applicationId, ':uid' => currentUserId()]);
    $application = $stmt->fetch();

    if (!$application) {
        redirect('../landlord_dashboard/dashboard.php?view=applications&error=Application+not+found');
    }

    $pdo->beginTransaction();
    try {
        if ($decision === 'APPROVE') {
            $pdo->prepare("UPDATE applications SET status = 'APPROVED' WHERE application_id = :aid")
                ->execute([':aid' => $applicationId]);

            $pdo->prepare("UPDATE rooms SET status = 'RESERVED' WHERE room_id = :rid")
                ->execute([':rid' => $application['room_id']]);

            // Auto-generate the first invoice, due in 7 days.
            $pdo->prepare(
                "INSERT INTO invoices (tenant_id, room_id, amount_due, due_date, issue_date, status)
                 VALUES (:tid, :rid, :amount, DATE_ADD(NOW(), INTERVAL 7 DAY), NOW(), 'UNPAID')"
            )->execute([
                ':tid'    => $application['tenant_id'],
                ':rid'    => $application['room_id'],
                ':amount' => $application['rent_amount']
            ]);

            notify($pdo, $application['tenant_user_id'], "Application approved",
                "Your application has been approved. Please complete your first rent payment to proceed.", "APPLICATION");

        } else {
            $pdo->prepare("UPDATE applications SET status = 'REJECTED' WHERE application_id = :aid")
                ->execute([':aid' => $applicationId]);

            notify($pdo, $application['tenant_user_id'], "Application rejected",
                "Unfortunately your application was not approved by the landlord.", "APPLICATION");
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Application review failed: " . $e->getMessage());
        redirect('../landlord_dashboard/dashboard.php?view=applications&error=Could+not+process+decision');
    }

    redirect('../landlord_dashboard/dashboard.php?view=applications&success=Decision+recorded');
} else {
    redirect('../landlord_dashboard/dashboard.php');
}
