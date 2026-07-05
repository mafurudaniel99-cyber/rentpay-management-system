<?php
/**
 * rent_payment_management/confirm_payment.php
 * Simulates the M-Pesa STK push confirmation screen.
 * GET  -> shows a "check your phone" screen for the pending payment.
 * POST -> plays the role of the M-Pesa callback: marks payment PAID,
 *         updates the invoice, and moves funds into escrow (HELD).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';

requireRole('TENANT');
$pdo = Database::getConnection();
$paymentId = (int)($_GET['payment_id'] ?? $_POST['payment_id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT pay.*, i.tenant_id FROM payments pay
     JOIN invoices i ON i.invoice_id = pay.invoice_id
     JOIN tenants t ON t.tenant_id = i.tenant_id
     WHERE pay.payment_id = :pid AND t.user_id = :uid"
);
$stmt->execute([':pid' => $paymentId, ':uid' => currentUserId()]);
$payment = $stmt->fetch();

if (!$payment) {
    redirect('../tenant_dashboard/dashboard.php?view=payments&error=Payment+not+found');
}

if (isPost() && $payment['status'] === 'PENDING') {
    // ---- This block plays the role of the real M-Pesa callback (onMpesaCallback) ----
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE payments SET status = 'PAID' WHERE payment_id = :pid")
            ->execute([':pid' => $paymentId]);

        $pdo->prepare("UPDATE invoices SET status = 'PAID' WHERE invoice_id = :iid")
            ->execute([':iid' => $payment['invoice_id']]);

        $roomStmt = $pdo->prepare(
            "SELECT p.landlord_id FROM rooms r JOIN properties p ON p.property_id = r.property_id WHERE r.room_id = :rid"
        );
        $roomStmt->execute([':rid' => $payment['room_id']]);
        $landlordId = $roomStmt->fetchColumn();

        $pdo->prepare(
            "INSERT INTO escrow (payment_id, landlord_id, amount, status, created_at)
             VALUES (:pid, :lid, :amt, 'HELD', NOW())"
        )->execute([
            ':pid' => $paymentId,
            ':lid' => $landlordId,
            ':amt' => $payment['amount_paid']
        ]);

        // Room becomes officially occupied once the first payment clears.
        $pdo->prepare("UPDATE rooms SET status = 'OCCUPIED' WHERE room_id = :rid")
            ->execute([':rid' => $payment['room_id']]);

        $pdo->prepare("UPDATE tenants SET status = 'ACTIVE' WHERE tenant_id = :tid")
            ->execute([':tid' => $payment['tenant_id']]);

        $pdo->commit();
        redirect('../tenant_dashboard/dashboard.php?view=payments&success=Payment+confirmed.+Funds+are+held+in+escrow.');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Payment confirmation failed: " . $e->getMessage());
        redirect('../tenant_dashboard/dashboard.php?view=payments&error=Payment+confirmation+failed');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Confirm payment &mdash; RentPay</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/2.44.0/iconfont/tabler-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/shared/assets/style.css">
<style>body{display:flex;align-items:center;justify-content:center;min-height:100vh;}
.box{max-width:380px;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:32px;text-align:center;}
.spin{width:34px;height:34px;border:3px solid var(--amber-bg);border-top-color:var(--amber);border-radius:50%;animation:sp .8s linear infinite;margin:0 auto 16px;}
@keyframes sp{to{transform:rotate(360deg);}}</style>
</head>
<body>
  <div class="box">
    <?php if ($payment['status'] === 'PENDING'): ?>
      <div class="spin"></div>
      <h3 style="font-size:17px;margin-bottom:8px;">Check your phone</h3>
      <p style="font-size:13.5px;color:var(--text-secondary);margin:0 0 20px;">
        An M-Pesa STK push has been sent for <b><?= formatMoney($payment['amount_paid']) ?></b>. Enter your PIN to confirm.
      </p>
      <form method="POST">
        <input type="hidden" name="payment_id" value="<?= $paymentId ?>">
        <button type="submit" class="btn btn-teal btn-block">I've entered my M-Pesa PIN</button>
      </form>
    <?php else: ?>
      <i class="ti ti-circle-check" style="font-size:36px;color:var(--success);"></i>
      <h3 style="font-size:17px;margin:10px 0 8px;">Already confirmed</h3>
      <a href="../tenant_dashboard/dashboard.php?view=payments" class="btn btn-outline btn-block">Back to dashboard</a>
    <?php endif; ?>
  </div>
</body>
</html>
