<?php
/**
 * property_management/list_properties.php
 * Reusable function: public/tenant search of available (vacant) rooms.
 * Only rooms belonging to an APPROVED landlord are ever returned.
 */
function searchAvailableRooms(PDO $pdo, array $filters = []): array
{
    $sql = "SELECT r.room_id, r.room_number, r.rent_amount, r.room_size, r.status,
                   p.property_name, p.location, p.property_id
            FROM rooms r
            JOIN properties p ON p.property_id = r.property_id
            JOIN landlords l ON l.landlord_id = p.landlord_id
            JOIN users u ON u.user_id = l.user_id
            WHERE r.status = 'VACANT' AND u.status = 'APPROVED'";
    $params = [];

    if (!empty($filters['location'])) {
        $sql .= " AND p.location LIKE :location";
        $params[':location'] = '%' . $filters['location'] . '%';
    }
    if (!empty($filters['max_price'])) {
        $sql .= " AND r.rent_amount <= :max_price";
        $params[':max_price'] = $filters['max_price'];
    }
    $sql .= " ORDER BY r.rent_amount ASC LIMIT 24";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
