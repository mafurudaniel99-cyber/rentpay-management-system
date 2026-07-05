<?php
/**
 * invoice_and_receipt_management/generate_receipt.php
 * Outputs a simple printable HTML receipt for a given payment.
 * Usage: generate_receipt.php?payment_id=123
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireLogin();
$pdo = Database::getConnection();
$paymentId = (int)($_GET['payment_id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT pay.*, u.full_name AS tenant_name, r.room_number, prop.property_name, prop.location
     FROM payments pay
     JOIN tenants t ON t.tenant_id = pay.tenant_id
     JOIN users u ON u.user_id = t.user_id
     JOIN rooms r ON r.room_id = pay.room_id
     JOIN properties prop ON prop.property_id = r.property_id
     WHERE pay.payment_id = :pid"
);
$stmt->execute([':pid' => $paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    die("Receipt not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt #<?= $payment['payment_id'] ?></title>
<style>
  body{font-family:Arial,sans-serif;max-width:480px;margin:40px auto;color:#14293D;}
  h2{margin-bottom:0;}
  .muted{color:#5F6B66;font-size:13px;}
  table{width:100%;border-collapse:collapse;margin-top:20px;}
  td{padding:8px 0;border-bottom:1px solid #E1E4E0;font-size:14px;}
  .total{font-weight:bold;font-size:18px;}
</style>
</head>
<body>
  <h2>RentPay</h2>
  <p class="muted">Official payment receipt</p>
  <table>
    <tr><td>Receipt No.</td><td>RCPT-<?= str_pad($payment['payment_id'], 5, '0', STR_PAD_LEFT) ?></td></tr>
    <tr><td>Tenant</td><td><?= sanitize($payment['tenant_name']) ?></td></tr>
    <tr><td>Property</td><td><?= sanitize($payment['property_name']) ?>, <?= sanitize($payment['location']) ?></td></tr>
    <tr><td>Room</td><td><?= sanitize($payment['room_number']) ?></td></tr>
    <tr><td>Payment date</td><td><?= date('d M Y, H:i', strtotime($payment['payment_date'])) ?></td></tr>
    <tr><td>Method</td><td><?= sanitize($payment['payment_method']) ?></td></tr>
    <tr><td>Transaction ref.</td><td><?= sanitize($payment['transaction_code'] ?? 'N/A') ?></td></tr>
    <tr><td class="total">Amount paid</td><td class="total"><?= formatMoney($payment['amount_paid']) ?></td></tr>
  </table>
  <p class="muted" style="margin-top:24px;">This receipt was generated automatically by RentPay.</p>
  <button onclick="window.print()" style="margin-top:10px;padding:8px 16px;">Print receipt</button>
</body>
</html>
