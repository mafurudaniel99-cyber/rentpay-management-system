<?php
/**
 * contact.php
 * Public Contact Us page with a working form (international-standard fields).
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/shared/helpers/functions.php';

$pageTitle = 'Contact us — RentPay';
$activePage = 'contact';
require __DIR__ . '/shared/partials/public_header.php';
?>

<main class="public-main">
  <p class="eyebrow" style="margin-top:32px;">Contact us</p>
  <h1 style="font-size:26px;margin-bottom:6px;">Get in touch with RentPay</h1>
  <p style="color:var(--text-secondary);margin-bottom:10px;">Our team responds within one business day.</p>

  <?php if (isset($_GET['success'])): ?><div class="alert success"><?= sanitize($_GET['success']) ?></div><?php endif; ?>
  <?php if (isset($_GET['error'])): ?><div class="alert error"><?= sanitize($_GET['error']) ?></div><?php endif; ?>

  <div class="contact-grid">
    <div class="card">
      <form method="POST" action="contact_management/send_message.php">
        <div class="grid-2">
          <div class="field"><label>Full name</label><input type="text" name="full_name" required></div>
          <div class="field"><label>Email</label><input type="email" name="email" required></div>
        </div>
        <div class="grid-2">
          <div class="field"><label>Phone number</label><input type="tel" name="phone" placeholder="+255 7XX XXX XXX"></div>
          <div class="field"><label>I am a</label>
            <select name="inquirer_type">
              <option value="TENANT">Tenant</option>
              <option value="LANDLORD">Landlord</option>
              <option value="PARTNER">Prospective partner</option>
              <option value="OTHER">Other</option>
            </select>
          </div>
        </div>
        <div class="field"><label>Subject</label>
          <select name="subject">
            <option>General enquiry</option>
            <option>Landlord verification</option>
            <option>Payment or escrow issue</option>
            <option>Dispute or complaint</option>
            <option>Data protection request</option>
          </select>
        </div>
        <div class="field"><label>Message</label><textarea name="message" rows="4" required></textarea></div>
        <label style="display:flex;gap:8px;align-items:flex-start;font-size:12.5px;color:var(--text-secondary);margin-bottom:16px;">
          <input type="checkbox" required style="margin-top:2px;">
          I agree to the processing of my data as described in the Privacy and Data Protection Policy.
        </label>
        <button type="submit" class="btn btn-teal">Send message</button>
      </form>
    </div>

    <div class="card info-card">
      <p style="font-weight:600;margin:0 0 16px;">Contact information</p>
      <div class="info-item"><i class="ti ti-map-pin"></i><div><p class="t">Office</p><p class="s">Mikocheni, Dar es Salaam, Tanzania</p></div></div>
      <div class="info-item"><i class="ti ti-mail"></i><div><p class="t">Email</p><p class="s">support@rentpay.co.tz</p></div></div>
      <div class="info-item"><i class="ti ti-phone"></i><div><p class="t">Phone</p><p class="s">+255 22 000 0000</p></div></div>
      <div class="info-item"><i class="ti ti-clock"></i><div><p class="t">Working hours</p><p class="s">Mon &ndash; Fri, 8:00 &ndash; 17:00 EAT</p></div></div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/shared/partials/public_footer.php'; ?>
