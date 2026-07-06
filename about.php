<?php
/**
 * about.php
 * Public About Us page.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/shared/helpers/functions.php';

$pageTitle = 'About us — RentPay';
$activePage = 'about';
require __DIR__ . '/shared/partials/public_header.php';
?>

<main class="public-main">
  <div class="about-hero">
    <p class="eyebrow">About RentPay</p>
    <h1 style="font-size:28px;">Renting, made accountable</h1>
  </div>

  <div class="about-block">
    <h2>Who we are</h2>
    <p>RentPay is a digital rent management platform built for the Tanzanian housing market. We connect verified landlords with tenants through a single, transparent system that manages listings, applications, rent collection, and dispute resolution end to end.</p>
  </div>

  <div class="about-block">
    <h2>The problem we solve</h2>
    <p>Renting a home in Tanzania is often informal: cash payments with no receipt trail, landlords who are difficult to verify, and disputes with no clear process for resolution. RentPay replaces this uncertainty with a structured, accountable system.</p>
  </div>

  <div class="about-block">
    <h2>How RentPay works</h2>
    <div class="steps">
      <div class="step"><div class="num">01</div><p class="t">Register and verify</p><p class="s">Landlords upload BRELA and PDPC documents before listing.</p></div>
      <div class="step"><div class="num">02</div><p class="t">Browse and apply</p><p class="s">Tenants search units by location, price, and type.</p></div>
      <div class="step"><div class="num">03</div><p class="t">Pay through escrow</p><p class="s">Rent is paid via M-Pesa and held until move-in is confirmed.</p></div>
      <div class="step"><div class="num">04</div><p class="t">Move in, or dispute</p><p class="s">Funds release on confirmation, or admin arbitrates.</p></div>
    </div>
  </div>

  <div class="about-block">
    <h2>Our commitment to trust and compliance</h2>
    <p>Every landlord is verified against BRELA business records. Every payment is held in escrow until both parties confirm the transaction is complete. Personal data is handled in line with Tanzania's Personal Data Protection Act, 2022, under the oversight of the Personal Data Protection Commission (PDPC).</p>
  </div>

  <div class="about-block">
    <span class="stamp"><i class="ti ti-certificate"></i> Our mission</span>
    <p style="margin-top:14px;font-family:'Fraunces',serif;font-size:18px;font-weight:500;color:var(--ink);">To make renting a home in Tanzania as trustworthy, transparent, and dispute-free as possible.</p>
  </div>
</main>

<?php require __DIR__ . '/shared/partials/public_footer.php'; ?>
