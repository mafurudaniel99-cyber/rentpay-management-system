<?php
/**
 * rent_payment_management/initiate_payment.php
 * Tenant action: pay an invoice. Since this is a demo environment with no live
 * M-Pesa credentials, the STK push is simulated: a PENDING payment is created
 * here, then confirm_payment.php (called by the dashboard's "confirm" step)
 * plays the role of the M-Pesa callback and moves funds into escrow.
 *
 * In production, replace the simulated confirmation with a real STK push call
 * and a public callback endpoint that Safaricom invokes asynchronously.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('TENANT');
$pdo = Database::getConnection();

if (isPost()) {
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    $phone     = sanitize($_POST['phone'] ?? '');

    $tenantStmt = $pdo->prepare("SELECT tenant_id FROM tenants WHERE user_id = :uid");
    $tenantStmt->execute([':uid' => currentUserId()]);
    $tenant = $tenantStmt->fetch();

    $invoiceStmt = $pdo->prepare(
        "SELECT * FROM invoices WHERE invoice_id = :iid AND tenant_id = :tid AND status = 'UNPAID'"
    );
    $invoiceStmt->execute([':iid' => $invoiceId, ':tid' => $tenant['tenant_id'] ?? 0]);
    $invoice = $invoiceStmt->fetch();

    if (!$invoice) {
        redirect('../tenant_dashboard/dashboard.php?view=payments&error=Invoice+not+found+or+already+paid');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO payments (invoice_id, tenant_id, room_id, amount_paid, payment_date, payment_method, transaction_code, balance, status)
         VALUES (:iid, :tid, :rid, :amount, NOW(), 'MPESA', :txn, 0, 'PENDING')"
    );
    $stmt->execute([
        ':iid'    => $invoiceId,
        ':tid'    => $invoice['tenant_id'],
        ':rid'    => $invoice['room_id'],
        ':amount' => $invoice['amount_due'],
        ':txn'    => 'STK-' . strtoupper(uniqid())
    ]);
    $paymentId = $pdo->lastInsertId();

    // In production: call the M-Pesa Daraja STK Push API here with $phone and $invoice['amount_due'].
    redirect('../rent_payment_management/confirm_payment.php?payment_id=' . $paymentId);
} else {
    redirect('../tenant_dashboard/dashboard.php?view=payments');
}
