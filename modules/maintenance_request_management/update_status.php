<?php
/**
 * maintenance_request_management/update_status.php
 * Landlord action: update the status of a tenant's maintenance request.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('LANDLORD');
$pdo = Database::getConnection();

if (isPost()) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $newStatus = strtoupper(sanitize($_POST['status'] ?? '')); // IN_PROGRESS or RESOLVED

    if (!in_array($newStatus, ['IN_PROGRESS', 'RESOLVED'])) {
        redirect('../landlord_dashboard/dashboard.php?view=maintenance&error=Invalid+status');
    }

    // Confirm this request belongs to a room under this landlord.
    $stmt = $pdo->prepare(
        "SELECT mr.request_id, u.user_id AS tenant_user_id
         FROM maintenance_requests mr
         JOIN rooms r ON r.room_id = mr.room_id
         JOIN properties p ON p.property_id = r.property_id
         JOIN landlords l ON l.landlord_id = p.landlord_id
         JOIN tenants t ON t.tenant_id = mr.tenant_id
         JOIN users u ON u.user_id = t.user_id
         WHERE mr.request_id = :rid AND l.user_id = :uid"
    );
    $stmt->execute([':rid' => $requestId, ':uid' => currentUserId()]);
    $request = $stmt->fetch();

    if (!$request) {
        redirect('../landlord_dashboard/dashboard.php?view=maintenance&error=Request+not+found');
    }

    $pdo->prepare("UPDATE maintenance_requests SET status = :status WHERE request_id = :rid")
        ->execute([':status' => $newStatus, ':rid' => $requestId]);

    notify($pdo, $request['tenant_user_id'], "Maintenance update",
        "Your maintenance request status changed to: " . str_replace('_', ' ', $newStatus), "MAINTENANCE");

    redirect('../landlord_dashboard/dashboard.php?view=maintenance&success=Status+updated');
} else {
    redirect('../landlord_dashboard/dashboard.php');
}
