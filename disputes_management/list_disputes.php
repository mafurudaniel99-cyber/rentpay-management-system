<?php
/**
 * disputes_management/list_disputes.php
 * Reusable functions to fetch disputes for a tenant or system-wide (admin).
 */
function getTenantDisputes(PDO $pdo, int $tenantId): array
{
    $stmt = $pdo->prepare(
        "SELECT d.*, r.room_number FROM disputes d
         JOIN rooms r ON r.room_id = d.room_id
         WHERE d.tenant_id = :tid ORDER BY d.created_at DESC"
    );
    $stmt->execute([':tid' => $tenantId]);
    return $stmt->fetchAll();
}

function getOpenDisputes(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT d.*, u.full_name AS tenant_name, r.room_number, e.amount AS escrow_amount, e.escrow_id
         FROM disputes d
         JOIN tenants t ON t.tenant_id = d.tenant_id
         JOIN users u ON u.user_id = t.user_id
         JOIN rooms r ON r.room_id = d.room_id
         LEFT JOIN escrow e ON e.escrow_id = d.escrow_id
         WHERE d.status = 'OPEN'
         ORDER BY d.created_at ASC"
    );
    return $stmt->fetchAll();
}
