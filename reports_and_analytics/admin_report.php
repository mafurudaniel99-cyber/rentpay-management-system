<?php
/**
 * reports_and_analytics/admin_report.php
 * Reusable functions for the Admin Dashboard: system-wide metrics,
 * escrow monitor, pending verifications, and report generation.
 */

function getSystemMetrics(PDO $pdo): array
{
    return [
        'pending_verifications' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='LANDLORD' AND status='PENDING_REVIEW'")->fetchColumn(),
        'open_disputes'         => (int)$pdo->query("SELECT COUNT(*) FROM disputes WHERE status='OPEN'")->fetchColumn(),
        'escrow_held_total'     => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM escrow WHERE status='HELD'")->fetchColumn(),
        'active_users'          => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status IN ('ACTIVE','APPROVED')")->fetchColumn(),
    ];
}

function getPendingVerifications(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT u.user_id, u.full_name, u.email, u.created_at,
                GROUP_CONCAT(vd.doc_type SEPARATOR ', ') AS documents
         FROM users u
         JOIN landlords l ON l.user_id = u.user_id
         LEFT JOIN verification_documents vd ON vd.landlord_id = l.landlord_id
         WHERE u.role = 'LANDLORD' AND u.status = 'PENDING_REVIEW'
         GROUP BY u.user_id
         ORDER BY u.created_at ASC"
    );
    return $stmt->fetchAll();
}

function getEscrowLedger(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT e.escrow_id, e.amount, e.status, tu.full_name AS tenant_name, lu.full_name AS landlord_name
         FROM escrow e
         JOIN payments pay ON pay.payment_id = e.payment_id
         JOIN tenants t ON t.tenant_id = pay.tenant_id
         JOIN users tu ON tu.user_id = t.user_id
         JOIN landlords l ON l.landlord_id = e.landlord_id
         JOIN users lu ON lu.user_id = l.user_id
         ORDER BY e.created_at DESC LIMIT 50"
    );
    return $stmt->fetchAll();
}

function getAllAccounts(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT user_id, full_name, email, role, status FROM users WHERE role != 'ADMIN' ORDER BY created_at DESC LIMIT 50"
    );
    return $stmt->fetchAll();
}

function generateSystemReport(PDO $pdo, string $from, string $to): array
{
    $newUsers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at BETWEEN :f AND :t");
    $newUsers->execute([':f' => $from, ':t' => $to]);

    $txCount = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE payment_date BETWEEN :f AND :t AND status='PAID'");
    $txCount->execute([':f' => $from, ':t' => $to]);

    $txVolume = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE payment_date BETWEEN :f AND :t AND status='PAID'");
    $txVolume->execute([':f' => $from, ':t' => $to]);

    $resolved = $pdo->prepare("SELECT COUNT(*) FROM disputes WHERE status='RESOLVED' AND resolved_at BETWEEN :f AND :t");
    $resolved->execute([':f' => $from, ':t' => $to]);

    return [
        'new_users'         => (int)$newUsers->fetchColumn(),
        'transactions'      => (int)$txCount->fetchColumn(),
        'volume'            => (float)$txVolume->fetchColumn(),
        'disputes_resolved' => (int)$resolved->fetchColumn(),
    ];
}
