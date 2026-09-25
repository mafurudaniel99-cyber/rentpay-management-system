<?php
/**
 * landlord_dashboard/dashboard.php
 * The Landlord's main dashboard. Server-rendered with PDO, no JS required.
 * Sections are switched via ?view= so every link and form works even
 * without JavaScript. Forms POST to their owning module's action file.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';
require_once __DIR__ . '/../rent_payment_management/list_payments.php';
require_once __DIR__ . '/../maintenance_request_management/list_requests.php';
require_once __DIR__ . '/../invoice_and_receipt_management/list_invoices.php';
require_once __DIR__ . '/../reports_and_analytics/income_report.php';
require_once __DIR__ . '/../notification/list_notifications.php';

requireRole('LANDLORD');
$pdo = Database::getConnection();

// Resolve the landlord_id for the logged-in user.
$stmt = $pdo->prepare("SELECT * FROM landlords WHERE user_id = :uid");
$stmt->execute([':uid' => currentUserId()]);
$landlord = $stmt->fetch();
if (!$landlord) {
    die("Landlord profile not found for this account.");
}
$landlordId = (int)$landlord['landlord_id'];

$view = $_GET['view'] ?? 'overview';
$allowedViews = ['overview','properties','applications','payments','maintenance','expenses','agreements'];
if (!in_array($view, $allowedViews)) {
    $view = 'overview';
}

// ---- Data needed on every view (cheap) ----
$stats = getLandlordStats($pdo, $landlordId);
$notifications = getUserNotifications($pdo, currentUserId(), 5);
$unreadCount = unreadNotificationCount($pdo, currentUserId());

// ---- Pending applications count, always shown as a sidebar badge ----
$pendingAppsStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM applications a
     JOIN rooms r ON r.room_id = a.room_id
     JOIN properties p ON p.property_id = r.property_id
     WHERE p.landlord_id = :lid AND a.status = 'PENDING'"
);
$pendingAppsStmt->execute([':lid' => $landlordId]);
$pendingApplications = (int)$pendingAppsStmt->fetchColumn();

// ---- View-specific data ----
$properties = [];
$applications = [];
$payments = [];
$maintenanceRequests = [];
$expenses = [];
$agreements = [];

if ($view === 'overview') {
    // Recent applications + recent maintenance for the summary cards
    $applications = array_slice(getApplicationsForLandlord($pdo, $landlordId), 0, 4);
    $maintenanceRequests = array_slice(getLandlordMaintenanceRequests($pdo, $landlordId), 0, 4);
}

if ($view === 'properties') {
    $stmt = $pdo->prepare(
        "SELECT property_id, property_name, location, total_rooms, description
         FROM properties WHERE landlord_id = :lid ORDER BY created_at DESC"
    );
    $stmt->execute([':lid' => $landlordId]);
    $properties = $stmt->fetchAll();

    foreach ($properties as &$prop) {
        $roomStmt = $pdo->prepare("SELECT * FROM rooms WHERE property_id = :pid ORDER BY room_number");
        $roomStmt->execute([':pid' => $prop['property_id']]);
        $prop['rooms'] = $roomStmt->fetchAll();
    }
    unset($prop);
}

if ($view === 'applications') {
    $applications = getApplicationsForLandlord($pdo, $landlordId);
}

if ($view === 'payments') {
    $payments = getLandlordPayments($pdo, $landlordId);
    $invoices = getLandlordInvoices($pdo, $landlordId);
}

if ($view === 'maintenance') {
    $maintenanceRequests = getLandlordMaintenanceRequests($pdo, $landlordId);
}

if ($view === 'expenses') {
    $stmt = $pdo->prepare(
        "SELECT e.*, p.property_name FROM expenses e
         JOIN properties p ON p.property_id = e.property_id
         WHERE p.landlord_id = :lid ORDER BY expense_date DESC"
    );
    $stmt->execute([':lid' => $landlordId]);
    $expenses = $stmt->fetchAll();
}

if ($view === 'agreements') {
    $stmt = $pdo->prepare(
        "SELECT ra.*, u.full_name AS tenant_name, r.room_number FROM rental_agreements ra
         JOIN tenants t ON t.tenant_id = ra.tenant_id
         JOIN users u ON u.user_id = t.user_id
         JOIN rooms r ON r.room_id = ra.room_id
         JOIN properties p ON p.property_id = r.property_id
         WHERE p.landlord_id = :lid ORDER BY ra.created_at DESC"
    );
    $stmt->execute([':lid' => $landlordId]);
    $agreements = $stmt->fetchAll();
}

// Helper used above - kept local to this file since it is dashboard-specific.
function getApplicationsForLandlord(PDO $pdo, int $landlordId): array
{
    $stmt = $pdo->prepare(
        "SELECT a.application_id, a.status, a.submitted_at, u.full_name AS tenant_name,
                r.room_number, r.rent_amount, prop.property_name
         FROM applications a
         JOIN tenants t ON t.tenant_id = a.tenant_id
         JOIN users u ON u.user_id = t.user_id
         JOIN rooms r ON r.room_id = a.room_id
         JOIN properties prop ON prop.property_id = r.property_id
         WHERE prop.landlord_id = :lid
         ORDER BY a.submitted_at DESC"
    );
    $stmt->execute([':lid' => $landlordId]);
    return $stmt->fetchAll();
}

function badgeClass(string $status): string
{
    $status = strtoupper($status);
    return match(true) {
        in_array($status, ['APPROVED','PAID','RELEASED','RESOLVED','ACTIVE']) => 'teal',
        in_array($status, ['PENDING','HELD','SUBMITTED','IN_PROGRESS','UNPAID','RESERVED']) => 'amber',
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
<title>Landlord Dashboard &mdash; RentPay</title>
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
      <div class="side-label">Landlord</div>
      <a class="side-link <?= $view==='overview' ? 'active' : '' ?>" href="?view=overview"><i class="ti ti-layout-dashboard"></i> Overview</a>
      <a class="side-link <?= $view==='properties' ? 'active' : '' ?>" href="?view=properties"><i class="ti ti-building-community"></i> Properties &amp; rooms</a>
      <a class="side-link <?= $view==='applications' ? 'active' : '' ?>" href="?view=applications"><i class="ti ti-file-text"></i> Applications
        <?php if ($pendingApplications > 0): ?><span class="count"><?= $pendingApplications ?></span><?php endif; ?>
      </a>
      <a class="side-link <?= $view==='payments' ? 'active' : '' ?>" href="?view=payments"><i class="ti ti-wallet"></i> Payments &amp; escrow</a>
      <a class="side-link <?= $view==='maintenance' ? 'active' : '' ?>" href="?view=maintenance"><i class="ti ti-tool"></i> Maintenance</a>
      <a class="side-link <?= $view==='expenses' ? 'active' : '' ?>" href="?view=expenses"><i class="ti ti-receipt"></i> Expenses</a>
      <a class="side-link <?= $view==='agreements' ? 'active' : '' ?>" href="?view=agreements"><i class="ti ti-file-certificate"></i> Agreements</a>
    </div>
    <div class="side-foot">
      <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'L', 0, 2)) ?></div>
      <div>
        <p class="n"><?= sanitize($_SESSION['name'] ?? 'Landlord') ?></p>
        <p class="r">Landlord</p>
      </div>
      <a href="../authentication/logout.php" class="icon-btn" style="margin-left:auto;" title="Log out"><i class="ti ti-logout"></i></a>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <h2><?= ucwords(str_replace(['overview','properties','applications','payments','maintenance','expenses','agreements'],
            ['Overview','Properties & rooms','Applications','Payments & escrow','Maintenance','Expenses','Agreements'], $view)) ?></h2>
      <div class="top-actions">
        <div class="icon-btn" title="<?= $unreadCount ?> unread notifications">
          <i class="ti ti-bell"></i>
          <?php if ($unreadCount > 0): ?><span class="dot"></span><?php endif; ?>
        </div>
        <div class="icon-btn"><i class="ti ti-settings"></i></div>
      </div>
    </div>

    <section class="content">

      <?php if (isset($_GET['success'])): ?>
        <div class="alert success"><i class="ti ti-check" style="vertical-align:-2px;margin-right:4px;"></i><?= sanitize($_GET['success']) ?></div>
      <?php endif; ?>
      <?php if (isset($_GET['error'])): ?>
        <div class="alert error"><i class="ti ti-alert-triangle" style="vertical-align:-2px;margin-right:4px;"></i><?= sanitize($_GET['error']) ?></div>
      <?php endif; ?>

      <?php if ($view === 'overview'): ?>
        <div class="grid-4" style="margin-bottom:20px;">
          <div class="card"><p class="stat-label">Total properties</p><p class="stat-value"><?= $stats['total_properties'] ?></p></div>
          <div class="card"><p class="stat-label">Monthly income</p><p class="stat-value"><?= formatMoney($stats['monthly_income']) ?></p></div>
          <div class="card"><p class="stat-label">Outstanding balance</p><p class="stat-value"><?= formatMoney($stats['outstanding']) ?></p></div>
          <div class="card"><p class="stat-label">Vacant rooms</p><p class="stat-value"><?= $stats['vacant_rooms'] ?></p></div>
        </div>

        <div class="card" style="margin-bottom:20px;">
          <div class="section-head">
            <h3>Wallet balance</h3>
            <span class="stamp"><i class="ti ti-wallet"></i><?= formatMoney($stats['wallet_balance']) ?></span>
          </div>
          <p style="font-size:13.5px;color:var(--text-secondary);margin:0;">This is the total escrow funds released to you so far. Funds are released automatically once a tenant confirms move-in.</p>
        </div>

        <div class="grid-2">
          <div class="card">
            <div class="section-head"><h3>Recent applications</h3><a href="?view=applications" style="font-size:13px;color:var(--teal);">View all</a></div>
            <?php if (empty($applications)): ?>
              <div class="empty"><i class="ti ti-file-text"></i>No applications yet.</div>
            <?php else: ?>
              <table>
                <?php foreach ($applications as $app): ?>
                <tr>
                  <td><?= sanitize($app['tenant_name']) ?><br><span style="font-size:11.5px;color:var(--text-muted);"><?= sanitize($app['property_name']) ?>, Room <?= sanitize($app['room_number']) ?></span></td>
                  <td><span class="badge <?= badgeClass($app['status']) ?>"><?= ucfirst(strtolower($app['status'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </div>
          <div class="card">
            <div class="section-head"><h3>Maintenance requests</h3><a href="?view=maintenance" style="font-size:13px;color:var(--teal);">View all</a></div>
            <?php if (empty($maintenanceRequests)): ?>
              <div class="empty"><i class="ti ti-tool"></i>No open requests.</div>
            <?php else: ?>
              <table>
                <?php foreach ($maintenanceRequests as $req): ?>
                <tr>
                  <td><?= sanitize($req['title']) ?><br><span style="font-size:11.5px;color:var(--text-muted);"><?= sanitize($req['tenant_name']) ?>, Room <?= sanitize($req['room_number']) ?></span></td>
                  <td><span class="badge <?= badgeClass($req['status']) ?>"><?= ucfirst(strtolower(str_replace('_',' ',$req['status']))) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($view === 'properties'): ?>
        <div class="card" style="margin-bottom:20px;">
          <div class="section-head"><h3>Add a property</h3></div>
          <form method="POST" action="../property_management/add_property.php">
            <div class="grid-2">
              <div class="field"><label>Property name</label><input type="text" name="property_name" required></div>
              <div class="field"><label>Location</label><input type="text" name="location" required></div>
            </div>
            <div class="grid-2">
              <div class="field"><label>Total rooms</label><input type="number" name="total_rooms" min="0"></div>
              <div class="field"><label>Description</label><input type="text" name="description"></div>
            </div>
            <button type="submit" class="btn btn-teal">Add property</button>
          </form>
        </div>

        <?php if (empty($properties)): ?>
          <div class="card"><div class="empty"><i class="ti ti-building-community"></i>No properties added yet.</div></div>
        <?php else: ?>
          <?php foreach ($properties as $prop): ?>
          <div class="card" style="margin-bottom:16px;">
            <div class="section-head">
              <h3><?= sanitize($prop['property_name']) ?></h3>
              <span style="font-size:13px;color:var(--text-secondary);"><i class="ti ti-map-pin" style="vertical-align:-2px;"></i> <?= sanitize($prop['location']) ?></span>
            </div>
            <table style="margin-bottom:14px;">
              <tr><th>Room</th><th>Rent</th><th>Size</th><th>Status</th></tr>
              <?php foreach ($prop['rooms'] as $room): ?>
              <tr>
                <td><?= sanitize($room['room_number']) ?></td>
                <td><?= formatMoney($room['rent_amount']) ?></td>
                <td><?= sanitize($room['room_size'] ?? '&mdash;') ?></td>
                <td><span class="badge <?= badgeClass($room['status']) ?>"><?= ucfirst(strtolower($room['status'])) ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($prop['rooms'])): ?>
              <tr><td colspan="4" style="color:var(--text-muted);">No rooms added yet.</td></tr>
              <?php endif; ?>
            </table>
            <details>
              <summary style="cursor:pointer;font-size:13px;color:var(--teal);font-weight:500;">+ Add a room</summary>
              <form method="POST" action="../property_management/add_room.php" style="margin-top:12px;">
                <input type="hidden" name="property_id" value="<?= $prop['property_id'] ?>">
                <div class="grid-2">
                  <div class="field"><label>Room number</label><input type="text" name="room_number" required></div>
                  <div class="field"><label>Rent amount (TZS)</label><input type="number" name="rent_amount" min="1" required></div>
                </div>
                <div class="grid-2">
                  <div class="field"><label>Room size</label><input type="text" name="room_size" placeholder="e.g. 3x4m"></div>
                  <div class="field"><label>Description</label><input type="text" name="description"></div>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Add room</button>
              </form>
            </details>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($view === 'applications'): ?>
        <div class="card">
          <div class="section-head"><h3>Tenancy applications</h3></div>
          <?php if (empty($applications)): ?>
            <div class="empty"><i class="ti ti-file-text"></i>No applications received yet.</div>
          <?php else: ?>
          <table>
            <tr><th>Tenant</th><th>Property / room</th><th>Submitted</th><th>Status</th><th></th></tr>
            <?php foreach ($applications as $app): ?>
            <tr>
              <td><?= sanitize($app['tenant_name']) ?></td>
              <td><?= sanitize($app['property_name']) ?>, Room <?= sanitize($app['room_number']) ?><br>
                  <span style="font-size:11.5px;color:var(--text-muted);"><?= formatMoney($app['rent_amount']) ?>/month</span></td>
              <td><?= date('d M Y', strtotime($app['submitted_at'])) ?></td>
              <td><span class="badge <?= badgeClass($app['status']) ?>"><?= ucfirst(strtolower($app['status'])) ?></span></td>
              <td>
                <?php if ($app['status'] === 'PENDING'): ?>
                <form method="POST" action="../property_management/review_application.php" style="display:flex;gap:6px;">
                  <input type="hidden" name="application_id" value="<?= $app['application_id'] ?>">
                  <button type="submit" name="decision" value="APPROVE" class="btn btn-teal btn-sm">Approve</button>
                  <button type="submit" name="decision" value="REJECT" class="btn btn-danger-outline btn-sm">Reject</button>
                </form>
                <?php else: ?>&mdash;<?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($view === 'payments'): ?>
        <div class="card" style="margin-bottom:20px;">
          <div class="section-head"><h3>Recent payments</h3></div>
          <?php if (empty($payments)): ?>
            <div class="empty"><i class="ti ti-wallet"></i>No payments recorded yet.</div>
          <?php else: ?>
          <table>
            <tr><th>Tenant</th><th>Room</th><th>Amount</th><th>Date</th><th>Escrow status</th><th></th></tr>
            <?php foreach ($payments as $pay): ?>
            <tr>
              <td><?= sanitize($pay['tenant_name']) ?></td>
              <td><?= sanitize($pay['property_name']) ?>, <?= sanitize($pay['room_number']) ?></td>
              <td><?= formatMoney($pay['amount_paid']) ?></td>
              <td><?= date('d M Y', strtotime($pay['payment_date'])) ?></td>
              <td><span class="badge <?= badgeClass($pay['escrow_status'] ?? 'N/A') ?>"><?= ucfirst(strtolower($pay['escrow_status'] ?? 'N/A')) ?></span></td>
              <td><a href="../invoice_and_receipt_management/generate_receipt.php?payment_id=<?= $pay['payment_id'] ?>" target="_blank" style="color:var(--teal);font-size:13px;">Receipt</a></td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="section-head"><h3>Invoices issued</h3></div>
          <?php if (empty($invoices)): ?>
            <div class="empty"><i class="ti ti-file-invoice"></i>No invoices yet.</div>
          <?php else: ?>
          <table>
            <tr><th>Tenant</th><th>Room</th><th>Amount due</th><th>Due date</th><th>Status</th></tr>
            <?php foreach ($invoices as $inv): ?>
            <tr>
              <td><?= sanitize($inv['tenant_name']) ?></td>
              <td><?= sanitize($inv['room_number']) ?></td>
              <td><?= formatMoney($inv['amount_due']) ?></td>
              <td><?= date('d M Y', strtotime($inv['due_date'])) ?></td>
              <td><span class="badge <?= badgeClass($inv['status']) ?>"><?= ucfirst(strtolower($inv['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($view === 'maintenance'): ?>
        <div class="card">
          <div class="section-head"><h3>Maintenance requests</h3></div>
          <?php if (empty($maintenanceRequests)): ?>
            <div class="empty"><i class="ti ti-tool"></i>No maintenance requests submitted yet.</div>
          <?php else: ?>
          <table>
            <tr><th>Issue</th><th>Tenant / room</th><th>Submitted</th><th>Status</th><th></th></tr>
            <?php foreach ($maintenanceRequests as $req): ?>
            <tr>
              <td><?= sanitize($req['title']) ?><br><span style="font-size:11.5px;color:var(--text-muted);"><?= sanitize($req['description']) ?></span></td>
              <td><?= sanitize($req['tenant_name']) ?><br><span style="font-size:11.5px;color:var(--text-muted);">Room <?= sanitize($req['room_number']) ?></span></td>
              <td><?= date('d M Y', strtotime($req['request_date'])) ?></td>
              <td><span class="badge <?= badgeClass($req['status']) ?>"><?= ucfirst(strtolower(str_replace('_',' ',$req['status']))) ?></span></td>
              <td>
                <?php if ($req['status'] !== 'RESOLVED'): ?>
                <form method="POST" action="../maintenance_request_management/update_status.php" style="display:flex;gap:6px;">
                  <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                  <?php if ($req['status'] === 'SUBMITTED'): ?>
                  <button type="submit" name="status" value="IN_PROGRESS" class="btn btn-outline btn-sm">Start work</button>
                  <?php endif; ?>
                  <button type="submit" name="status" value="RESOLVED" class="btn btn-teal btn-sm">Mark resolved</button>
                </form>
                <?php else: ?>&mdash;<?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($view === 'expenses'): ?>
        <div class="card" style="margin-bottom:20px;">
          <div class="section-head"><h3>Record an expense</h3></div>
          <form method="POST" action="../expense_management/add_expense.php">
            <div class="grid-2">
              <div class="field"><label>Property</label>
                <select name="property_id" required>
                  <?php
                    $propStmt = $pdo->prepare("SELECT property_id, property_name FROM properties WHERE landlord_id = :lid");
                    $propStmt->execute([':lid' => $landlordId]);
                    foreach ($propStmt->fetchAll() as $p) {
                        echo '<option value="' . $p['property_id'] . '">' . sanitize($p['property_name']) . '</option>';
                    }
                  ?>
                </select>
              </div>
              <div class="field"><label>Expense type</label><input type="text" name="expense_type" placeholder="e.g. Plumbing repair" required></div>
            </div>
            <div class="grid-2">
              <div class="field"><label>Amount (TZS)</label><input type="number" name="amount" min="1" required></div>
              <div class="field"><label>Date</label><input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required></div>
            </div>
            <div class="field"><label>Description</label><input type="text" name="description"></div>
            <button type="submit" class="btn btn-teal">Record expense</button>
          </form>
        </div>

        <div class="card">
          <div class="section-head"><h3>Expense history</h3></div>
          <?php if (empty($expenses)): ?>
            <div class="empty"><i class="ti ti-receipt"></i>No expenses recorded yet.</div>
          <?php else: ?>
          <table>
            <tr><th>Property</th><th>Type</th><th>Amount</th><th>Date</th></tr>
            <?php foreach ($expenses as $exp): ?>
            <tr>
              <td><?= sanitize($exp['property_name']) ?></td>
              <td><?= sanitize($exp['expense_type']) ?></td>
              <td><?= formatMoney($exp['amount']) ?></td>
              <td><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($view === 'agreements'): ?>
        <div class="card" style="margin-bottom:20px;">
          <div class="section-head"><h3>Upload a rental agreement</h3></div>
          <form method="POST" action="../rental_agreement_management/upload_agreement.php" enctype="multipart/form-data">
            <div class="grid-2">
              <div class="field"><label>Tenant ID</label><input type="number" name="tenant_id" required></div>
              <div class="field"><label>Room ID</label><input type="number" name="room_id" required></div>
            </div>
            <div class="grid-2">
              <div class="field"><label>Deposit amount (TZS)</label><input type="number" name="deposit_amount"></div>
              <div class="field"><label>Agreement file (PDF/Word)</label><input type="file" name="agreement_file" required></div>
            </div>
            <div class="grid-2">
              <div class="field"><label>Start date</label><input type="date" name="start_date" required></div>
              <div class="field"><label>Expiry date</label><input type="date" name="expiry_date" required></div>
            </div>
            <button type="submit" class="btn btn-teal">Upload agreement</button>
          </form>
        </div>

        <div class="card">
          <div class="section-head"><h3>Uploaded agreements</h3></div>
          <?php if (empty($agreements)): ?>
            <div class="empty"><i class="ti ti-file-certificate"></i>No agreements uploaded yet.</div>
          <?php else: ?>
          <table>
            <tr><th>Tenant</th><th>Room</th><th>Deposit</th><th>Period</th><th></th></tr>
            <?php foreach ($agreements as $agr): ?>
            <tr>
              <td><?= sanitize($agr['tenant_name']) ?></td>
              <td><?= sanitize($agr['room_number']) ?></td>
              <td><?= formatMoney($agr['deposit_amount'] ?? 0) ?></td>
              <td><?= date('d M Y', strtotime($agr['start_date'])) ?> &ndash; <?= date('d M Y', strtotime($agr['expiry_date'])) ?></td>
              <td><a href="../<?= sanitize($agr['file_path']) ?>" target="_blank" style="color:var(--teal);font-size:13px;">View file</a></td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </section>
  </main>
</div>
</body>
</html>
