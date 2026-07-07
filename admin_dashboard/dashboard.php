<?php
/**
 * admin_dashboard/dashboard.php
 * The Admin's main dashboard. Server-rendered with PDO.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';
require_once __DIR__ . '/../shared/middleware/auth_middleware.php';
require_once __DIR__ . '/../reports_and_analytics/admin_report.php';
require_once __DIR__ . '/../disputes_management/list_disputes.php';
require_once __DIR__ . '/../notification/list_notifications.php';

requireRole('ADMIN');
$pdo = Database::getConnection();

$view = $_GET['view'] ?? 'overview';
$allowedViews = ['overview','verifications','escrow','disputes','accounts','reports'];
if (!in_array($view, $allowedViews)) $view = 'overview';

$metrics = getSystemMetrics($pdo);
$unreadCount = unreadNotificationCount($pdo, currentUserId());

$pendingVerifications = [];
$escrowLedger = [];
$openDisputes = [];
$accounts = [];
$reportData = null;

if ($view === 'overview' || $view === 'verifications') {
    $pendingVerifications = getPendingVerifications($pdo);
}
if ($view === 'overview' || $view === 'disputes') {
    $openDisputes = getOpenDisputes($pdo);
}
if ($view === 'escrow') {
    $escrowLedger = getEscrowLedger($pdo);
}
if ($view === 'accounts') {
    $accounts = getAllAccounts($pdo);
}
if ($view === 'reports' && isset($_GET['from']) && isset($_GET['to'])) {
    $reportData = generateSystemReport($pdo, $_GET['from'], $_GET['to']);
}

function badgeClass(string $status): string
{
    $status = strtoupper($status);
    return match(true) {
        in_array($status, ['APPROVED','PAID','RELEASED','RESOLVED','ACTIVE']) => 'teal',
        in_array($status, ['PENDING','HELD','SUBMITTED','IN_PROGRESS','UNPAID','RESERVED','PENDING_REVIEW','OPEN']) => 'amber',
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
<title>Admin Dashboard &mdash; RentPay</title>
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
      <div class="side-label">Admin console</div>
      <a class="side-link <?= $view==='overview' ? 'active' : '' ?>" href="?view=overview"><i class="ti ti-layout-dashboard"></i> Overview</a>
      <a class="side-link <?= $view==='verifications' ? 'active' : '' ?>" href="?view=verifications"><i class="ti ti-file-check"></i> Verifications
        <?php if (count($pendingVerifications) > 0): ?><span class="count"><?= count($pendingVerifications) ?></span><?php endif; ?>
      </a>
      <a class="side-link <?= $view==='escrow' ? 'active' : '' ?>" href="?view=escrow"><i class="ti ti-lock"></i> Escrow monitor</a>
      <a class="side-link <?= $view==='disputes' ? 'active' : '' ?>" href="?view=disputes"><i class="ti ti-scale"></i> Disputes
        <?php if (count($openDisputes) > 0): ?><span class="count"><?= count($openDisputes) ?></span><?php endif; ?>
      </a>
      <a class="side-link <?= $view==='accounts' ? 'active' : '' ?>" href="?view=accounts"><i class="ti ti-users"></i> Accounts</a>
      <a class="side-link <?= $view==='reports' ? 'active' : '' ?>" href="?view=reports"><i class="ti ti-report"></i> Reports</a>
    </div>
    <div class="side-foot">
      <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 2)) ?></div>
      <div><p class="n"><?= sanitize($_SESSION['name'] ?? 'Admin') ?></p><p class="r">Administrator</p></div>
      <a href="../authentication/logout.php" class="icon-btn" style="margin-left:auto;" title="Log out"><i class="ti ti-logout"></i></a>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <h2><?= ucwords(str_replace(['overview','verifications','escrow','disputes','accounts','reports'],
            ['Overview','Landlord verifications','Escrow monitor','Disputes & arbitration','Accounts','Reports'], $view)) ?></h2>
      <div class="top-actions">
        <div class="icon-btn"><i class="ti ti-bell"></i><?php if ($unreadCount>0): ?><span class="dot"></span><?php endif; ?></div>
      </div>
    </div>

    <section class="content">
      <?php if (isset($_GET['success'])): ?><div class="alert success"><?= sanitize($_GET['success']) ?></div><?php endif; ?>
      <?php if (isset($_GET['error'])): ?><div class="alert error"><?= sanitize($_GET['error']) ?></div><?php endif; ?>

      <?php if ($view === 'overview'): ?>
        <div class="grid-4" style="margin-bottom:20px;">
          <div class="card"><p class="stat-label">Pending verifications</p><p class="stat-value"><?= $metrics['pending_verifications'] ?></p></div>
          <div class="card"><p class="stat-label">Open disputes</p><p class="stat-value"><?= $metrics['open_disputes'] ?></p></div>
          <div class="card"><p class="stat-label">Escrow held (total)</p><p class="stat-value" style="font-size:18px;"><?= formatMoney($metrics['escrow_held_total']) ?></p></div>
          <div class="card"><p class="stat-label">Active users</p><p class="stat-value"><?= $metrics['active_users'] ?></p></div>
        </div>

        <div class="card">
          <div class="section-head"><h3>Awaiting your decision</h3></div>
          <table>
            <tr><td><i class="ti ti-file-check" style="color:var(--amber);margin-right:6px;"></i><?= count($pendingVerifications) ?> landlord verifications</td>
                <td><a href="?view=verifications" style="color:var(--teal);font-size:13px;">Review now</a></td></tr>
            <tr><td><i class="ti ti-scale" style="color:var(--danger);margin-right:6px;"></i><?= count($openDisputes) ?> open disputes</td>
                <td><a href="?view=disputes" style="color:var(--teal);font-size:13px;">Review now</a></td></tr>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($view === 'verifications'): ?>
        <div class="card">
          <div class="section-head"><h3>Pending landlord verifications</h3></div>
          <?php if (empty($pendingVerifications)): ?><div class="empty"><i class="ti ti-file-check"></i>No pending verifications.</div><?php else: ?>
          <table>
            <tr><th>Landlord</th><th>Email</th><th>Documents</th><th>Submitted</th><th></th></tr>
            <?php foreach ($pendingVerifications as $lv): ?>
            <tr>
              <td><?= sanitize($lv['full_name']) ?></td>
              <td><?= sanitize($lv['email']) ?></td>
              <td><?= sanitize($lv['documents'] ?? 'None uploaded') ?></td>
              <td><?= date('d M Y', strtotime($lv['created_at'])) ?></td>
              <td>
                <form method="POST" action="../account_management/verify_landlord.php" style="display:flex;gap:6px;">
                  <input type="hidden" name="user_id" value="<?= $lv['user_id'] ?>">
                  <button type="submit" name="decision" value="APPROVE" class="btn btn-teal btn-sm">Approve</button>
                  <button type="submit" name="decision" value="REJECT" class="btn btn-danger-outline btn-sm">Reject</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($view === 'escrow'): ?>
        <div class="card">
          <div class="section-head"><h3>Escrow ledger</h3></div>
          <?php if (empty($escrowLedger)): ?><div class="empty"><i class="ti ti-lock"></i>No escrow records yet.</div><?php else: ?>
          <table>
            <tr><th>Reference</th><th>Tenant</th><th>Landlord</th><th>Amount</th><th>Status</th></tr>
            <?php foreach ($escrowLedger as $e): ?>
            <tr>
              <td>ESC-<?= str_pad($e['escrow_id'], 4, '0', STR_PAD_LEFT) ?></td>
              <td><?= sanitize($e['tenant_name']) ?></td>
              <td><?= sanitize($e['landlord_name']) ?></td>
              <td><?= formatMoney($e['amount']) ?></td>
              <td><span class="badge <?= badgeClass($e['status']) ?>"><?= ucfirst(strtolower($e['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($view === 'disputes'): ?>
        <div class="card">
          <div class="section-head"><h3>Open disputes</h3></div>
          <?php if (empty($openDisputes)): ?><div class="empty"><i class="ti ti-scale"></i>No open disputes.</div><?php else: ?>
          <table>
            <tr><th>Reason</th><th>Tenant</th><th>Room</th><th>Escrow amount</th><th>Evidence</th><th></th></tr>
            <?php foreach ($openDisputes as $d): ?>
            <tr>
              <td><?= sanitize($d['reason']) ?><br><span style="font-size:11.5px;color:var(--text-muted);"><?= sanitize($d['details'] ?? '') ?></span></td>
              <td><?= sanitize($d['tenant_name']) ?></td>
              <td><?= sanitize($d['room_number']) ?></td>
              <td><?= $d['escrow_amount'] ? formatMoney($d['escrow_amount']) : '&mdash;' ?></td>
              <td><?php if (!empty($d['evidence_path'])): ?><a href="../<?= sanitize($d['evidence_path']) ?>" target="_blank" style="color:var(--teal);font-size:12.5px;">View file</a><?php else: ?>&mdash;<?php endif; ?></td>
              <td>
                <?php if ($d['escrow_id']): ?>
                <form method="POST" action="../disputes_management/resolve_dispute.php" style="display:flex;gap:6px;">
                  <input type="hidden" name="dispute_id" value="<?= $d['dispute_id'] ?>">
                  <button type="submit" name="verdict" value="REFUND_TENANT" class="btn btn-danger-outline btn-sm">Refund tenant</button>
                  <button type="submit" name="verdict" value="RELEASE_LANDLORD" class="btn btn-teal btn-sm">Release landlord</button>
                </form>
                <?php else: ?><span style="font-size:12px;color:var(--text-muted);">No linked escrow</span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($view === 'accounts'): ?>
        <div class="card">
          <div class="section-head"><h3>All accounts</h3></div>
          <?php if (empty($accounts)): ?><div class="empty"><i class="ti ti-users"></i>No accounts found.</div><?php else: ?>
          <table>
            <tr><th>Name</th><th>Role</th><th>Status</th><th></th></tr>
            <?php foreach ($accounts as $acc): ?>
            <tr>
              <td><?= sanitize($acc['full_name']) ?><br><span style="font-size:11.5px;color:var(--text-muted);"><?= sanitize($acc['email']) ?></span></td>
              <td><?= ucfirst(strtolower($acc['role'])) ?></td>
              <td><span class="badge <?= badgeClass($acc['status']) ?>"><?= ucfirst(strtolower($acc['status'])) ?></span></td>
              <td>
                <form method="POST" action="../account_management/suspend_account.php" style="display:flex;gap:6px;">
                  <input type="hidden" name="user_id" value="<?= $acc['user_id'] ?>">
                  <?php if ($acc['status'] === 'SUSPENDED'): ?>
                    <button type="submit" name="action" value="REACTIVATE" class="btn btn-teal btn-sm">Reactivate</button>
                  <?php else: ?>
                    <button type="submit" name="action" value="SUSPEND" class="btn btn-danger-outline btn-sm">Suspend</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($view === 'reports'): ?>
        <div class="grid-2">
          <div class="card">
            <div class="section-head"><h3>Generate system report</h3></div>
            <form method="GET">
              <input type="hidden" name="view" value="reports">
              <div class="field"><label>From</label><input type="date" name="from" value="<?= sanitize($_GET['from'] ?? date('Y-m-01')) ?>" required></div>
              <div class="field"><label>To</label><input type="date" name="to" value="<?= sanitize($_GET['to'] ?? date('Y-m-d')) ?>" required></div>
              <button type="submit" class="btn btn-teal btn-block">Generate report</button>
            </form>
          </div>
          <div class="card">
            <div class="section-head"><h3>Report preview</h3></div>
            <?php if (!$reportData): ?><div class="empty"><i class="ti ti-report"></i>No report generated yet.</div><?php else: ?>
            <table>
              <tr><td>New users</td><td style="text-align:right;font-weight:600;"><?= $reportData['new_users'] ?></td></tr>
              <tr><td>Transactions</td><td style="text-align:right;font-weight:600;"><?= $reportData['transactions'] ?></td></tr>
              <tr><td>Total volume</td><td style="text-align:right;font-weight:600;"><?= formatMoney($reportData['volume']) ?></td></tr>
              <tr><td>Disputes resolved</td><td style="text-align:right;font-weight:600;"><?= $reportData['disputes_resolved'] ?></td></tr>
            </table>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </main>
</div>
</body>
</html>
