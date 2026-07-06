<?php
/**
 * rent_payment_management/list_payments.php
 * Reusable function: returns all payments/escrow records for a landlord's tenants.
 * Included by landlord_dashboard/dashboard.php - not accessed directly.
 */

function getLandlordPayments(PDO $pdo, int $landlordId): array
{
    $stmt = $pdo->prepare(
        "SELECT pay.payment_id, pay.amount_paid, pay.payment_date, pay.status AS payment_status,
                e.status AS escrow_status, u.full_name AS tenant_name, r.room_number, prop.property_name
         FROM payments pay
         JOIN tenants t ON t.tenant_id = pay.tenant_id
         JOIN users u ON u.user_id = t.user_id
         JOIN rooms r ON r.room_id = pay.room_id
         JOIN properties prop ON prop.property_id = r.property_id
         LEFT JOIN escrow e ON e.payment_id = pay.payment_id
         WHERE prop.landlord_id = :lid
         ORDER BY pay.payment_date DESC
         LIMIT 20"
    );
    $stmt->execute([':lid' => $landlordId]);
    return $stmt->fetchAll();
}
