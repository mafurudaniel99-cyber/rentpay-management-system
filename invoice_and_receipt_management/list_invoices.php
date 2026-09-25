<?php
/**
 * invoice_and_receipt_management/list_invoices.php
 * Reusable function: fetch invoices for a landlord's tenants.
 */
function getLandlordInvoices(PDO $pdo, int $landlordId): array
{
    $stmt = $pdo->prepare(
        "SELECT i.invoice_id, i.amount_due, i.due_date, i.status, u.full_name AS tenant_name, r.room_number
         FROM invoices i
         JOIN tenants t ON t.tenant_id = i.tenant_id
         JOIN users u ON u.user_id = t.user_id
         JOIN rooms r ON r.room_id = i.room_id
         JOIN properties p ON p.property_id = r.property_id
         WHERE p.landlord_id = :lid
         ORDER BY i.due_date DESC"
    );
    $stmt->execute([':lid' => $landlordId]);
    return $stmt->fetchAll();
}
