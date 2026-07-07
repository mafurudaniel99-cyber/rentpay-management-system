<?php
/**
 * property_management/add_room.php
 * Landlord action: add a room to one of their properties.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('LANDLORD');
$pdo = Database::getConnection();

if (isPost()) {
    $propertyId = (int)($_POST['property_id'] ?? 0);
    $roomNumber = sanitize($_POST['room_number'] ?? '');
    $rentAmount = (float)($_POST['rent_amount'] ?? 0);
    $roomSize   = sanitize($_POST['room_size'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    // Verify this property belongs to the logged-in landlord before writing.
    $check = $pdo->prepare(
        "SELECT p.property_id FROM properties p
         JOIN landlords l ON l.landlord_id = p.landlord_id
         WHERE p.property_id = :pid AND l.user_id = :uid"
    );
    $check->execute([':pid' => $propertyId, ':uid' => currentUserId()]);

    if (!$check->fetch()) {
        redirect('../landlord_dashboard/dashboard.php?view=properties&error=Invalid+property');
    }

    if ($roomNumber === '' || $rentAmount <= 0) {
        redirect('../landlord_dashboard/dashboard.php?view=properties&error=Room+number+and+rent+amount+are+required');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO rooms (property_id, room_number, rent_amount, room_size, status, description)
         VALUES (:pid, :room, :rent, :size, 'VACANT', :desc)"
    );
    $stmt->execute([
        ':pid'  => $propertyId,
        ':room' => $roomNumber,
        ':rent' => $rentAmount,
        ':size' => $roomSize,
        ':desc' => $description
    ]);

    redirect('../landlord_dashboard/dashboard.php?view=properties&success=Room+added');
} else {
    redirect('../landlord_dashboard/dashboard.php');
}
