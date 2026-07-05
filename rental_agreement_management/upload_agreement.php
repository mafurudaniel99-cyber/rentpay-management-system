<?php
/**
 * rental_agreement_management/upload_agreement.php
 * Landlord action: upload a signed rental agreement document for a tenant.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('LANDLORD');
$pdo = Database::getConnection();

if (isPost() && isset($_FILES['agreement_file'])) {
    $tenantId      = (int)($_POST['tenant_id'] ?? 0);
    $roomId        = (int)($_POST['room_id'] ?? 0);
    $depositAmount = (float)($_POST['deposit_amount'] ?? 0);
    $startDate     = sanitize($_POST['start_date'] ?? '');
    $expiryDate    = sanitize($_POST['expiry_date'] ?? '');

    $file = $_FILES['agreement_file'];
    $allowed = ['pdf', 'doc', 'docx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        redirect('../landlord_dashboard/dashboard.php?view=agreements&error=Only+PDF+or+Word+documents+are+allowed');
    }

    $storedName = 'agreement_' . uniqid() . '.' . $ext;
    $destination = __DIR__ . '/../uploads/agreements/' . $storedName;
    move_uploaded_file($file['tmp_name'], $destination);

    $pdo->prepare(
        "INSERT INTO rental_agreements (tenant_id, room_id, file_path, deposit_amount, start_date, expiry_date, created_at)
         VALUES (:tid, :rid, :path, :deposit, :start, :expiry, NOW())"
    )->execute([
        ':tid'     => $tenantId,
        ':rid'     => $roomId,
        ':path'    => 'uploads/agreements/' . $storedName,
        ':deposit' => $depositAmount,
        ':start'   => $startDate,
        ':expiry'  => $expiryDate
    ]);

    redirect('../landlord_dashboard/dashboard.php?view=agreements&success=Agreement+uploaded');
} else {
    redirect('../landlord_dashboard/dashboard.php');
}
