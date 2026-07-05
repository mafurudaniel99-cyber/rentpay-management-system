<?php
/**
 * reports_and_analytics/income_report.php
 * Reusable function: aggregate income statistics for a landlord's dashboard.
 */
function getLandlordStats(PDO $pdo, int $landlordId): array
{
    $totalProperties = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE landlord_id = :lid");
    $totalProperties->execute([':lid' => $landlordId]);

    $vacantRooms = $pdo->prepare(
        "SELECT COUNT(*) FROM rooms r JOIN properties p ON p.property_id = r.property_id
         WHERE p.landlord_id = :lid AND r.status = 'VACANT'"
    );
    $vacantRooms->execute([':lid' => $landlordId]);

    $monthlyIncome = $pdo->prepare(
        "SELECT COALESCE(SUM(pay.amount_paid), 0) FROM payments pay
         JOIN rooms r ON r.room_id = pay.room_id
         JOIN properties p ON p.property_id = r.property_id
         WHERE p.landlord_id = :lid
         AND pay.status = 'PAID'
         AND MONTH(pay.payment_date) = MONTH(CURDATE())
         AND YEAR(pay.payment_date) = YEAR(CURDATE())"
    );
    $monthlyIncome->execute([':lid' => $landlordId]);

    $outstanding = $pdo->prepare(
        "SELECT COALESCE(SUM(i.amount_due), 0) FROM invoices i
         JOIN rooms r ON r.room_id = i.room_id
         JOIN properties p ON p.property_id = r.property_id
         WHERE p.landlord_id = :lid AND i.status IN ('UNPAID','OVERDUE')"
    );
    $outstanding->execute([':lid' => $landlordId]);

    $walletBalance = $pdo->prepare(
        "SELECT w.balance FROM wallets w
         JOIN landlords l ON l.user_id = w.user_id
         WHERE l.landlord_id = :lid"
    );
    $walletBalance->execute([':lid' => $landlordId]);

    return [
        'total_properties' => (int)$totalProperties->fetchColumn(),
        'vacant_rooms'      => (int)$vacantRooms->fetchColumn(),
        'monthly_income'    => (float)$monthlyIncome->fetchColumn(),
        'outstanding'       => (float)$outstanding->fetchColumn(),
        'wallet_balance'    => (float)($walletBalance->fetchColumn() ?: 0),
    ];
}

/**
 * Monthly income trend for the last 6 months (used for simple analytics/reporting).
 */
function getMonthlyIncomeTrend(PDO $pdo, int $landlordId): array
{
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(pay.payment_date, '%Y-%m') AS month, SUM(pay.amount_paid) AS total
         FROM payments pay
         JOIN rooms r ON r.room_id = pay.room_id
         JOIN properties p ON p.property_id = r.property_id
         WHERE p.landlord_id = :lid AND pay.status = 'PAID'
         AND pay.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY month ORDER BY month ASC"
    );
    $stmt->execute([':lid' => $landlordId]);
    return $stmt->fetchAll();
}
