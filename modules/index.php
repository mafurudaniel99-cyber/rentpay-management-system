<?php
/**
 * index.php
 * The main entry point of the RentPay application (public homepage).
 * Shows the hero, trust signals, and a live list of available rooms
 * pulled directly from the database.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/shared/helpers/functions.php';
require_once __DIR__ . '/property_management/list_properties.php';

$pdo = Database::getConnection();

$filters = [
    'location'  => $_GET['location'] ?? '',
    'max_price' => $_GET['max_price'] ?? '',
];
$availableRooms = searchAvailableRooms($pdo, $filters);

$pageTitle = 'RentPay — Secure Rent Management';
$activePage = 'home';
require __DIR__ . '/shared/partials/public_header.php';
?>

<main class="public-main">
  <section class="hero">
    <p class="eyebrow">Rent management, secured</p>
    <h1>Find a home. Pay through escrow.<br>Move in with confidence.</h1>
    <p>Every rent payment is held safely until move-in is confirmed, so tenants and landlords are both protected.</p>
    <form method="GET" action="index.php" class="search-bar">
      <input type="text" name="location" placeholder="Location, e.g. Mikocheni" value="<?= sanitize($filters['location']) ?>">
      <input type="number" name="max_price" placeholder="Max price (TZS)" value="<?= sanitize((string)$filters['max_price']) ?>">
      <button type="submit" class="btn btn-teal">Search</button>
    </form>
  </section>

  <div class="trust-row">
    <div class="trust-card"><i class="ti ti-shield-check"></i><p class="t">Verified landlords</p><p class="s">Checked against BRELA and PDPC records before listing.</p></div>
    <div class="trust-card"><i class="ti ti-lock"></i><p class="t">Escrow-held payments</p><p class="s">Paid via M-Pesa, released only after move-in.</p></div>
    <div class="trust-card"><i class="ti ti-scale"></i><p class="t">Dispute protection</p><p class="s">Admin reviews evidence before any refund or release.</p></div>
  </div>

  <h2 style="font-size:20px;margin-bottom:6px;">Available properties</h2>
  <p style="color:var(--text-secondary);font-size:13.5px;margin:0 0 4px;"><?= count($availableRooms) ?> room(s) found</p>

  <?php if (empty($availableRooms)): ?>
    <div class="card" style="margin:20px 0 48px;"><div class="empty"><i class="ti ti-search"></i>No available rooms match your search right now.</div></div>
  <?php else: ?>
  <div class="prop-grid">
    <?php foreach ($availableRooms as $room): ?>
    <div class="prop-card">
      <div class="prop-img"><i class="ti ti-building-community"></i></div>
      <div class="prop-body">
        <div class="prop-top"><p>Room <?= sanitize($room['room_number']) ?></p><span class="badge teal">Available</span></div>
        <p class="prop-loc"><i class="ti ti-map-pin"></i><?= sanitize($room['property_name']) ?>, <?= sanitize($room['location']) ?></p>
        <p class="prop-price"><?= formatMoney($room['rent_amount']) ?><span> /month</span></p>
        <a href="authentication/register.php" class="btn btn-teal btn-sm btn-block" style="margin-top:8px;">Register to apply</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/shared/partials/public_footer.php'; ?>
