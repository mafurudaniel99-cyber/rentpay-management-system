<?php
/**
 * property_management/submit_application.php
 * Tenant action: submit a tenancy application for a vacant room.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('TENANT');
$pdo = Database::getConnection();

if (isPost()) {
    $roomId  = (int)($_POST['room_id'] ?? 0);
    $message = sanitize($_POST['message'] ?? '');

    $tenantStmt = $pdo->prepare("SELECT tenant_id FROM tenants WHERE user_id = :uid");
    $tenantStmt->execute([':uid' => currentUserId()]);
    $tenant = $tenantStmt->fetch();

    if (!$tenant) {
        redirect('../tenant_dashboard/dashboard.php?view=browse&error=Tenant+profile+not+found');
    }

    $roomStmt = $pdo->prepare("SELECT * FROM rooms WHERE room_id = :rid AND status = 'VACANT'");
    $roomStmt->execute([':rid' => $roomId]);
    $room = $roomStmt->fetch();

    if (!$room) {
        redirect('../tenant_dashboard/dashboard.php?view=browse&error=Room+is+no+longer+available');
    }

    // Prevent duplicate pending applications for the same room.
    $dupStmt = $pdo->prepare(
        "SELECT application_id FROM applications WHERE tenant_id = :tid AND room_id = :rid AND status = 'PENDING'"
    );
    $dupStmt->execute([':tid' => $tenant['tenant_id'], ':rid' => $roomId]);
    if ($dupStmt->fetch()) {
        redirect('../tenant_dashboard/dashboard.php?view=applications&error=You+already+applied+for+this+room');
    }

    $pdo->prepare(
        "INSERT INTO applications (tenant_id, room_id, message, status, submitted_at)
         VALUES (:tid, :rid, :msg, 'PENDING', NOW())"
    )->execute([
        ':tid' => $tenant['tenant_id'],
        ':rid' => $roomId,
        ':msg' => $message
    ]);

    // Notify the landlord who owns this room.
    $landlordUserStmt = $pdo->prepare(
        "SELECT u.user_id FROM rooms r
         JOIN properties p ON p.property_id = r.property_id
         JOIN landlords l ON l.landlord_id = p.landlord_id
         JOIN users u ON u.user_id = l.user_id
         WHERE r.room_id = :rid"
    );
    $landlordUserStmt->execute([':rid' => $roomId]);
    $landlordUserId = $landlordUserStmt->fetchColumn();
    if ($landlordUserId) {
        notify($pdo, (int)$landlordUserId, "New tenancy application",
            "You have a new tenancy application for room {$room['room_number']}.", "APPLICATION");
    }

    redirect('../tenant_dashboard/dashboard.php?view=applications&success=Application+submitted');
} else {
    redirect('../tenant_dashboard/dashboard.php?view=browse');
}
