<?php
/**
 * maintenance_request_management/submit_request.php
 * Tenant action: submit a maintenance/repair request for their room.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('TENANT');
$pdo = Database::getConnection();

if (isPost()) {
    $roomId      = (int)($_POST['room_id'] ?? 0);
    $title       = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    $tenantStmt = $pdo->prepare("SELECT tenant_id FROM tenants WHERE user_id = :uid");
    $tenantStmt->execute([':uid' => currentUserId()]);
    $tenant = $tenantStmt->fetch();

    if (!$tenant || $title === '') {
        redirect('../tenant_dashboard/dashboard.php?view=maintenance&error=Please+describe+the+issue');
    }

    $photoPath = null;
    if (!empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $storedName = 'maint_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/maintenance_photos/' . $storedName);
            $photoPath = 'uploads/maintenance_photos/' . $storedName;
        }
    }

    $pdo->prepare(
        "INSERT INTO maintenance_requests (tenant_id, room_id, title, description, photo, request_date, status)
         VALUES (:tid, :rid, :title, :desc, :photo, NOW(), 'SUBMITTED')"
    )->execute([
        ':tid'   => $tenant['tenant_id'],
        ':rid'   => $roomId,
        ':title' => $title,
        ':desc'  => $description,
        ':photo' => $photoPath
    ]);

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
        notify($pdo, (int)$landlordUserId, "New maintenance request", "A tenant submitted: \"$title\"", "MAINTENANCE");
    }

    redirect('../tenant_dashboard/dashboard.php?view=maintenance&success=Request+submitted');
} else {
    redirect('../tenant_dashboard/dashboard.php?view=maintenance');
}
