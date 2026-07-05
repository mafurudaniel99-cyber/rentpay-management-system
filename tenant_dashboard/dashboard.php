<?php
/**
 * tenant_dashboard/dashboard.php
 * The Tenant's main dashboard. Server-rendered with PDO.
 * Sections switch via ?view= so every action works without JavaScript.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';
require_once __DIR__ . '/../property_management/list_properties.php';
require_once __DIR__ . '/../maintenance_request_management/list_requests.php';
require_once __DIR__ . '/../notification/list_notifications.php';
require_once __DIR__ . '/../disputes_management/list_disputes.php';

requireRole('TENANT');
$pdo = Database::getConnection();

$stmt = $pdo->prepare("SELECT * FROM tenants WHERE user_id = :uid");
$stmt->execute([':uid' => currentUserId()]);
$tenant = $stmt->fetch();
if (!$tenant) {
    die("Tenant profile not found for this account.");
}
$tenantId = (int)$tenant['tenant_id'];

$view = $_GET['view'] ?? 'overview';
$allowedViews = ['overview','browse','applications','payments','maintenance','disputes'];
if (!in_array($view, $allowedViews)) $view = 'overview';

$notifications = getUserNotifications($pdo, currentUserId(), 5);
$unreadCount = unreadNotificationCount($pdo, currentUserId());

// ---- Current active tenancy (most recent occupied room for this tenant) ----
$tenancyStmt = $pdo->prepare(
    "SELECT r.room_id, r.room_number, r.rent_amount, p.property_name, p.location, r.status AS room_status
     FROM applications a
     JOIN rooms r ON r.room_id = a.room_id
     JOIN properties p ON p.property_id = r.property_id
     WHERE a.tenant_id = :tid AND a.status = 'APPROVED'
     ORDER BY a.submitted_at DESC LIMIT 1"
);
$tenancyStmt->execute([':tid' => $tenantId]);
$currentTenancy = $tenancyStmt->fetch();

// ---- View-specific data ----
$availableRooms = [];
$applications = [];
$invoices = [];
$paymentHistory = [];
$maintenanceRequests = [];
$disputes = [];

if ($view === 'overview' || $view === 'applications') {
    $stmt = $pdo->prepare(
        "SELECT a.application_id, a.status, a.submitted_at, r.room_number, r.rent_amount, p.property_name, r.room_id
         FROM applications a
         JOIN rooms r ON r.room_id = a.room_id
         JOIN properties p ON p.property_id = r.property_id
         WHERE a.tenant_id = :tid ORDER BY a.submitted_at DESC"
    );
    $stmt->execute([':tid' => $tenantId]);
    $applications = $stmt->fetchAll();
}

if ($view === 'overview' || $view === 'maintenance') {
    $stmt = $pdo->prepare(
        "SELECT mr.* FROM maintenance_requests mr WHERE mr.tenant_id = :tid ORDER BY mr.request_date DESC"
    );
    $stmt->execute([':tid' => $tenantId]);
    $maintenanceRequests = $stmt->fetchAll();
}

if ($view === 'browse') {
    $filters = [
        'location'  => $_GET['location'] ?? '',
        'max_price' => $_GET['max_price'] ?? '',
    ];
    $availableRooms = searchAvailableRooms($pdo, $filters);
}

if ($view === 'payments') {
    $invStmt = $pdo->prepare(
        "SELECT i.* FROM invoices i WHERE i.tenant_id = :tid ORDER BY i.due_date DESC"
    );
    $invStmt->execute([':tid' => $tenantId]);
    $invoices = $invStmt->fetchAll();

    $payStmt = $pdo->prepare(
        "SELECT pay.*, e.status AS escrow_status FROM payments pay
         LEFT JOIN escrow e ON e.payment_id = pay.payment_id
         WHERE pay.tenant_id = :tid ORDER BY pay.payment_date DESC"
    );
    $payStmt->execute([':tid' => $tenantId]);
    $paymentHistory = $payStmt->fetchAll();
}

if ($view === 'disputes') {
    $disputes = getTenantDisputes($pdo, $tenantId);
}

function badgeClass(string $status): string
{
    $status = strtoupper($status);
    return match(true) {
        in_array($status, ['APPROVED','PAID','RELEASED','RESOLVED','ACTIVE']) => 'teal',
        in_array($status, ['PENDING','HELD','SUBMITTED','IN_PROGRESS','UNPAID','RESERVED','OPEN']) => 'amber',
        in_array($status, ['REJECTED','DISPUTED','OVERDUE','SUSPENDED']) => 'danger',
        default => 'gray',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tenant Dashboard &mdash; RentPay</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/2.44.0/iconfont/tabler-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/shared/assets/style.css">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><i class="ti ti-home-2"></i> RentPay</div>
    <div class="side-section">
      <div class="side-label">Tenant</div>
      <a class="side-link <?= $view==='overview' ? 'active' : '' ?>" href="?view=overview"><i class="ti ti-layout-dashboard"></i> Overview</a>
      <a class="side-link <?= $view==='browse' ? 'active' : '' ?>" href="?view=browse"><i class="ti ti-search"></i> Browse properties</a>
      <a class="side-link <?= $view==='applications' ? 'active' : '' ?>" href="?view=applications"><i class="ti ti-file-text"></i> My applications</a>
      <a class="side-link <?= $view==='payments' ? 'active' : '' ?>" href="?view=payments"><i class="ti ti-wallet"></i> Payments &amp; escrow</a>
      <a class="side-link <?= $view==='maintenance' ? 'active' : '' ?>" href="?view=maintenance"><i class="ti ti-tool"></i> Maintenance</a>
      <a class="side-link <?= $view==='disputes' ? 'active' : '' ?>" href="?view=disputes"><i class="ti ti-scale"></i> Disputes</a>
    </div>
    <div class="side-foot">
      <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'T', 0, 2)) ?></div>
      <div><p class="n"><?= sanitize($_SESSION['name'] ?? 'Tenant') ?></p><p class="r">Tenant</p></div>
      <a href="../authentication/logout.php" class="icon-btn" style="margin-left:auto;" title="Log out"><i class="ti ti-logout"></i></a>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <h2><?= ucwords(str_replace(['overview','browse','applications','payments','maintenance','disputes'],
            ['Overview','Browse properties','My applications','Payments & escrow','Maintenance','Disputes'], $view)) ?></h2>
      <div class="top-actions">
        <div class="icon-btn"><i class="ti ti-bell"></i><?php if ($unreadCount>0): ?><span class="dot"></span><?php endif; ?></div>
      </div>
    </div>

    <section class="content">
      <?php if (isset($_GET['success'])): ?><div class="alert success"><?= sanitize($_GET['success']) ?></div><?php endif; ?>
      <?php if (isset($_GET['error'])): ?><div class="alert error"><?= sanitize($_GET['error']) ?></div><?php endif; ?>

      <?php if ($view === 'overview'): ?>
        <div class="grid-4" style="margin-bottom:20px;">
          <div class="card"><p class="stat-label">Tenancy status</p>
            <p class="stat-value" style="font-size:16px;"><?= $currentTenancy ? sanitize($currentTenancy['room_status']) : 'No active tenancy' ?></p></div>
          <div class="card"><p class="stat-label">Open applications</p>
            <p class="stat-value"><?= count(array_filter($applications, fn($a) => $a['status']==='PENDING')) ?></p></div>
          <div class="card"><p class="stat-label">Open maintenance</p>
            <p class="stat-value"><?= count(array_filter($maintenanceRequests, fn($m) => $m['status']!=='RESOLVED')) ?></p></div>
          <div class="card"><p class="stat-label">Monthly rent</p>
            <p class="stat-value" style="font-size:16px;"><?= $currentTenancy ? formatMoney($currentTenancy['rent_amount']) : '&mdash;' ?></p></div>
        </div>

        <?php if ($currentTenancy): ?>
        <div class="card" style="margin-bottom:20px;">
          <div class="section-head">
            <h3><?= sanitize($currentTenancy['property_name']) ?>, Room <?= sanitize($currentTenancy['room_number']) ?></h3>
            <span class="stamp <?= $currentTenancy['room_status']==='OCCUPIED' ? '' : 'amber' ?>"><?= sanitize($currentTenancy['room_status']) ?></span>
          </div>
          <p style="font-size:13.5px;color:var(--text-secondary);"><?= sanitize($currentTenancy['location']) ?> &mdash; <?= formatMoney($currentTenancy['rent_amount']) ?>/month</p>
        </div>
        <?php endif; ?>

        <div class="grid-2">
          <div class="card">
            <div class="section-head"><h3>Recent applications</h3><a href="?view=applications" style="font-size:13px;color:var(--teal);">View all</a></div>
            <?php if (empty($applications)): ?><div class="empty"><i class="ti ti-file-text"></i>No applications yet.</div><?php else: ?>
            <table><?php foreach (array_slice($applications,0,4) as $app): ?>
              <tr><td><?= sanitize($app['property_name']) ?>, Room <?= sanitize($app['room_number']) ?></td>
              <td><span class="badge <?= badgeClass($app['status']) ?>"><?= ucfirst(strtolower($app['status'])) ?></span></td></tr>
            <?php endforeach; ?></table>
            <?php endif; ?>
          </div>
          <div class="card">
            <div class="section-head"><h3>Maintenance</h3><a href="?view=maintenance" style="font-size:13px;color:var(--teal);">View all</a></div>
            <?php if (empty($maintenanceRequests)): ?><div class="empty"><i class="ti ti-tool"></i>No requests yet.</div><?php else: ?>
            <table><?php foreach (array_slice($maintenanceRequests,0,4) as $req): ?>
              <tr><td><?= sanitize($req['title']) ?></td>
              <td><span class="badge <?= badgeClass($req['status']) ?>"><?= ucfirst(strtolower(str_replace('_',' ',$req['status']))) ?></span></td></tr>
            <?php endforeach; ?></table>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($view === 'browse'): ?>
        <div class="card" style="margin-bottom:20px;">
          <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
            <input type="hidden" name="view" value="browse">
            <input type="text" name="location" placeholder="Location" value="<?= sanitize($_GET['location'] ?? '') ?>" style="flex:2;min-width:160px;padding:9px 12px;border:1px solid var(--border);border-radius:8px;">
            <input type="number" name="max_price" placeholder="Max price (TZS)" value="<?= sanitize($_GET['max_price'] ?? '') ?>" style="flex:1;min-width:120px;padding:9px 12px;border:1px solid var(--border);border-radius:8px;">
            <button type="submit" class="btn btn-teal">Search</button>
          </form>
        </div>

        <?php if (empty($availableRooms)): ?>
          <div class="card"><div class="empty"><i class="ti ti-search"></i>No available rooms match your search.</div></div>
        <?php else: ?>
        <div class="prop-grid">
          <?php foreach ($availableRooms as $room): ?>
          <div class="prop-card"><div class="prop-img"><i class="ti ti-building-community"></i></div>
            <div class="prop-body">
              <div class="prop-top"><p>Room <?= sanitize($room['room_number']) ?></p><span class="badge teal">Available</span></div>
              <p class="prop-loc"><i class="ti ti-map-pin"></i><?= sanitize($room['property_name']) ?>, <?= sanitize($room['location']) ?></p>
              <p class="prop-price"><?= formatMoney($room['rent_amount']) ?><span> /month</span></p>
              <form method="POST" action="../property_management/submit_application.php" style="margin-top:10px;">
                <input type="hidden" name="room_id" value="<?= $room['room_id'] ?>">
                <input type="text" name="message" placeholder="Message to landlord (optional)" style="width:100%;padding:7px 9px;border:1px solid var(--border);border-radius:6px;font-size:12.5px;margin-bottom:8px;">
                <button type="submit" class="btn btn-teal btn-sm btn-block">Apply now</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($view === 'applications'): ?>
        <div class="card">
          <div class="section-head"><h3>My applications</h3></div>
          <?php if (empty($applications)): ?><div class="empty"><i class="ti ti-file-text"></i>No applications submitted yet.</div><?php else: ?>
          <table>
            <tr><th>Property</th><th>Rent</th><th>Submitted</th><th>Status</th></tr>
            <?php foreach ($applications as $app): ?>
            <tr>
              <td><?= sanitize($app['property_name']) ?>, Room <?= sanitize($app['room_number']) ?></td>
              <td><?= formatMoney($app['rent_amount']) ?></td>
              <td><?= date('d M Y', strtotime($app['submitted_at'])) ?></td>
              <td><span class="badge <?= badgeClass($app['status']) ?>"><?= ucfirst(strtolower($app['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($view === 'payments'): ?>
        <div class="card" style="margin-bottom:20px;">
          <div class="section-head"><h3>Unpaid invoices</h3></div>
          <?php $unpaid = array_filter($invoices, fn($i) => $i['status'] !== 'PAID'); ?>
          <?php if (empty($unpaid)): ?><div class="empty"><i class="ti ti-circle-check"></i>No outstanding invoices.</div><?php else: ?>
          <table>
            <tr><th>Amount due</th><th>Due date</th><th>Status</th><th></th></tr>
            <?php foreach ($unpaid as $inv): ?>
            <tr>
              <td><?= formatMoney($inv['amount_due']) ?></td>
              <td><?= date('d M Y', strtotime($inv['due_date'])) ?></td>
              <td><span class="badge <?= badgeClass($inv['status']) ?>"><?= ucfirst(strtolower($inv['status'])) ?></span></td>
              <td>
                <form method="POST" action="../rent_payment_management/initiate_payment.php" style="display:flex;gap:6px;">
                  <input type="hidden" name="invoice_id" value="<?= $inv['invoice_id'] ?>">
                  <input type="tel" name="phone" placeholder="+255 7XX XXX XXX" required style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px;">
                  <button type="submit" class="btn btn-teal btn-sm">Pay with M-Pesa</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="section-head"><h3>Payment history</h3></div>
          <?php if (empty($paymentHistory)): ?><div class="empty"><i class="ti ti-wallet"></i>No payments yet.</div><?php else: ?>
          <table>
            <tr><th>Date</th><th>Amount</th><th>Method</th><th>Escrow status</th><th></th></tr>
            <?php foreach ($paymentHistory as $pay): ?>
            <tr>
              <td><?= date('d M Y', strtotime($pay['payment_date'])) ?></td>
              <td><?= formatMoney($pay['amount_paid']) ?></td>
              <td><?= sanitize($pay['payment_method']) ?></td>
              <td><span class="badge <?= badgeClass($pay['escrow_status'] ?? 'N/A') ?>"><?= ucfirst(strtolower($pay['escrow_status'] ?? 'N/A')) ?></span></td>
              <td><a href="../invoice_and_receipt_management/generate_receipt.php?payment_id=<?= $pay['payment_id'] ?>" target="_blank" style="color:var(--teal);font-size:13px;">Receipt</a></td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($view === 'maintenance'): ?>
        <div class="grid-2">
          <div class="card">
            <div class="section-head"><h3>Submit a request</h3></div>
            <form method="POST" action="../maintenance_request_management/submit_request.php" enctype="multipart/form-data">
              <div class="field"><label>Room ID</label><input type="number" name="room_id" value="<?= $currentTenancy['room_id'] ?? '' ?>" required></div>
              <div class="field"><label>Title</label><input type="text" name="title" placeholder="e.g. Leaking kitchen tap" required></div>
              <div class="field"><label>Description</label><textarea name="description" rows="3"></textarea></div>
              <div class="field"><label>Photo (optional)</label><input type="file" name="photo"></div>
              <button type="submit" class="btn btn-teal btn-block">Submit request</button>
            </form>
          </div>
          <div class="card">
            <div class="section-head"><h3>My requests</h3></div>
            <?php if (empty($maintenanceRequests)): ?><div class="empty"><i class="ti ti-tool"></i>No requests yet.</div><?php else: ?>
            <table><?php foreach ($maintenanceRequests as $req): ?>
              <tr><td><?= sanitize($req['title']) ?></td>
              <td><span class="badge <?= badgeClass($req['status']) ?>"><?= ucfirst(strtolower(str_replace('_',' ',$req['status']))) ?></span></td></tr>
            <?php endforeach; ?></table>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($view === 'disputes'): ?>
        <div class="grid-2">
          <div class="card">
            <div class="section-head"><h3>Report an issue</h3></div>
            <p style="font-size:13px;color:var(--text-secondary);margin:0 0 14px;">If your unit does not match the listing, or you found a defect, describe it below. Admin will investigate before releasing or refunding held funds.</p>
            <form method="POST" action="../disputes_management/raise_dispute.php" enctype="multipart/form-data">
              <div class="field"><label>Room ID</label><input type="number" name="room_id" value="<?= $currentTenancy['room_id'] ?? '' ?>" required></div>
              <div class="field"><label>Reason</label>
                <select name="reason" required>
                  <option value="Structural defect">Structural defect</option>
                  <option value="Unit does not match listing">Unit does not match listing</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="field"><label>Details</label><textarea name="details" rows="3"></textarea></div>
              <div class="field"><label>Evidence (photo)</label><input type="file" name="evidence"></div>
              <button type="submit" class="btn btn-danger-outline btn-block">Submit dispute</button>
            </form>
          </div>
          <div class="card">
            <div class="section-head"><h3>My disputes</h3></div>
            <?php if (empty($disputes)): ?><div class="empty"><i class="ti ti-shield-check"></i>No disputes raised.</div><?php else: ?>
            <table><?php foreach ($disputes as $d): ?>
              <tr><td><?= sanitize($d['reason']) ?></td>
              <td><span class="badge <?= badgeClass($d['status']) ?>"><?= ucfirst(strtolower($d['status'])) ?></span></td></tr>
            <?php endforeach; ?></table>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </main>
</div>
</body>
</html>
