<?php
/**
 * maintenance_request_management/list_requests.php
 * Reusable function: fetch all maintenance requests for a landlord's properties.
 */
function getLandlordMaintenanceRequests(PDO $pdo, int $landlordId): array
{
    $stmt = $pdo->prepare(
        "SELECT mr.request_id, mr.title, mr.description, mr.status, mr.request_date,
                u.full_name AS tenant_name, r.room_number, prop.property_name
         FROM maintenance_requests mr
         JOIN tenants t ON t.tenant_id = mr.tenant_id
         JOIN users u ON u.user_id = t.user_id
         JOIN rooms r ON r.room_id = mr.room_id
         JOIN properties prop ON prop.property_id = r.property_id
         WHERE prop.landlord_id = :lid
         ORDER BY mr.request_date DESC"
    );
    $stmt->execute([':lid' => $landlordId]);
    return $stmt->fetchAll();
}
