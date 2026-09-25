<?php
/**
 * shared/partials/public_header.php
 * Shared top navigation for public (visitor-facing) pages: index, about, contact.
 * Expects an optional $activePage variable ('home' | 'about' | 'contact').
 */
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'RentPay' ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/2.44.0/iconfont/tabler-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/shared/assets/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/shared/assets/public.css">
</head>
<body>
<nav class="public-nav">
  <a class="brand" href="<?= BASE_URL ?>/index.php"><i class="ti ti-home-2"></i> RentPay</a>
  <div class="nav-links">
    <a href="<?= BASE_URL ?>/index.php" class="<?= $activePage==='home' ? 'active' : '' ?>">Home</a>
    <a href="<?= BASE_URL ?>/about.php" class="<?= $activePage==='about' ? 'active' : '' ?>">About us</a>
    <a href="<?= BASE_URL ?>/contact.php" class="<?= $activePage==='contact' ? 'active' : '' ?>">Contact</a>
  </div>
  <div class="nav-actions">
    <a href="<?= BASE_URL ?>/authentication/login.php" class="btn btn-ghost">Log in</a>
    <a href="<?= BASE_URL ?>/authentication/register.php" class="btn btn-brass">Register</a>
  </div>
</nav>
