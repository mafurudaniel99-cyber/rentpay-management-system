<?php
/**
 * property_management/add_property.php
 * Landlord action: register a new property/building.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('LANDLORD');
$pdo = Database::getConnection();

if (isPost()) {
    $stmt = $pdo->prepare(
        "SELECT landlord_id FROM landlords WHERE user_id = :uid"
    );
    $stmt->execute([':uid' => currentUserId()]);
    $landlord = $stmt->fetch();

    if (!$landlord) {
        redirect('../landlord_dashboard/dashboard.php?error=Landlord+profile+not+found');
    }

    $name       = sanitize($_POST['property_name'] ?? '');
    $location   = sanitize($_POST['location'] ?? '');
    $totalRooms = (int)($_POST['total_rooms'] ?? 0);
    $description = sanitize($_POST['description'] ?? '');

    if ($name === '' || $location === '') {
        redirect('../landlord_dashboard/dashboard.php?view=properties&error=Property+name+and+location+are+required');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO properties (landlord_id, property_name, location, total_rooms, description, created_at)
         VALUES (:lid, :name, :location, :rooms, :desc, NOW())"
    );
    $stmt->execute([
        ':lid'      => $landlord['landlord_id'],
        ':name'     => $name,
        ':location' => $location,
        ':rooms'    => $totalRooms,
        ':desc'     => $description
    ]);

    redirect('../landlord_dashboard/dashboard.php?view=properties&success=Property+added');
} else {
    redirect('../landlord_dashboard/dashboard.php');
}
