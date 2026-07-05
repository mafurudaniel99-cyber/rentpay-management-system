<?php
/**
 * expense_management/add_expense.php
 * Landlord action: record an operating expense against a property.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('LANDLORD');
$pdo = Database::getConnection();

if (isPost()) {
    $propertyId  = (int)($_POST['property_id'] ?? 0);
    $expenseType = sanitize($_POST['expense_type'] ?? '');
    $amount      = (float)($_POST['amount'] ?? 0);
    $expenseDate = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
    $description = sanitize($_POST['description'] ?? '');

    $check = $pdo->prepare(
        "SELECT p.property_id FROM properties p JOIN landlords l ON l.landlord_id = p.landlord_id
         WHERE p.property_id = :pid AND l.user_id = :uid"
    );
    $check->execute([':pid' => $propertyId, ':uid' => currentUserId()]);

    if (!$check->fetch() || $amount <= 0) {
        redirect('../landlord_dashboard/dashboard.php?view=expenses&error=Invalid+expense+details');
    }

    $pdo->prepare(
        "INSERT INTO expenses (property_id, expense_type, amount, expense_date, description)
         VALUES (:pid, :type, :amount, :date, :desc)"
    )->execute([
        ':pid'   => $propertyId,
        ':type'  => $expenseType,
        ':amount'=> $amount,
        ':date'  => $expenseDate,
        ':desc'  => $description
    ]);

    redirect('../landlord_dashboard/dashboard.php?view=expenses&success=Expense+recorded');
} else {
    redirect('../landlord_dashboard/dashboard.php');
}
