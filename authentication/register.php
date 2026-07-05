<?php
/**
 * authentication/register.php
 * Handles registration for both Tenant and Landlord roles.
 * GET  -> shows the registration form
 * POST -> validates input and creates the account
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';

$pdo = Database::getConnection();
$errors = [];
$success = null;

if (isPost()) {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $phone    = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = strtoupper(sanitize($_POST['role'] ?? 'TENANT')); // TENANT or LANDLORD

    if ($fullName === '' || $email === '' || $phone === '' || $password === '') {
        $errors[] = "All fields are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (!in_array($role, ['TENANT', 'LANDLORD'])) {
        $errors[] = "Invalid role selected.";
    }

    if (empty($errors)) {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            $errors[] = "An account with this email already exists.";
        }
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $status = ($role === 'LANDLORD') ? 'PENDING_REVIEW' : 'ACTIVE';
            $hashed = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, email, phone, password, role, status, created_at)
                 VALUES (:name, :email, :phone, :password, :role, :status, NOW())"
            );
            $stmt->execute([
                ':name'     => $fullName,
                ':email'    => $email,
                ':phone'    => $phone,
                ':password' => $hashed,
                ':role'     => $role,
                ':status'   => $status
            ]);
            $userId = (int)$pdo->lastInsertId();

            // Create a wallet for every user (used later for escrow releases/refunds)
            $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (:uid, 0)")
                ->execute([':uid' => $userId]);

            if ($role === 'LANDLORD') {
                $pdo->prepare(
                    "INSERT INTO landlords (user_id, created_at) VALUES (:uid, NOW())"
                )->execute([':uid' => $userId]);

                $success = "Registered successfully. Please upload your BRELA and PDPC documents to complete verification.";
            } else {
                $pdo->prepare(
                    "INSERT INTO tenants (user_id, status) VALUES (:uid, 'ACTIVE')"
                )->execute([':uid' => $userId]);

                $success = "Registered successfully. You can now log in.";
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Registration failed: " . $e->getMessage());
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register &mdash; RentPay</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/2.44.0/iconfont/tabler-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/shared/assets/style.css">
<style>
  body{display:flex;align-items:center;justify-content:center;min-height:100vh;}
  .auth-wrap{max-width:420px;width:100%;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:32px;}
  .role-toggle{display:flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:20px;}
  .role-toggle label{flex:1;text-align:center;padding:10px;font-size:13.5px;font-weight:500;cursor:pointer;color:var(--text-secondary);}
  .role-toggle input{display:none;}
  .role-toggle input:checked + label{background:var(--teal);color:#fff;}
</style>
</head>
<body>
  <div class="auth-wrap">
    <h2 style="text-align:center;margin-bottom:6px;">Create your account</h2>
    <p style="text-align:center;color:var(--text-secondary);font-size:13.5px;margin:0 0 20px;">Join RentPay as a tenant or landlord.</p>

    <?php foreach ($errors as $error): ?>
      <div class="alert error"><?= sanitize($error) ?></div>
    <?php endforeach; ?>
    <?php if ($success): ?>
      <div class="alert success"><?= sanitize($success) ?> <a href="login.php" style="color:var(--teal);font-weight:600;">Log in</a></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="register.php">
      <div class="role-toggle">
        <input type="radio" name="role" value="TENANT" id="role-tenant" checked>
        <label for="role-tenant">Tenant</label>
        <input type="radio" name="role" value="LANDLORD" id="role-landlord">
        <label for="role-landlord">Landlord</label>
      </div>
      <div class="field"><label>Full name</label><input type="text" name="full_name" required></div>
      <div class="field"><label>Email</label><input type="email" name="email" required></div>
      <div class="field"><label>Phone number</label><input type="tel" name="phone" placeholder="+255 7XX XXX XXX" required></div>
      <div class="field"><label>Password</label><input type="password" name="password" minlength="8" required></div>
      <button type="submit" class="btn btn-teal btn-block">Create account</button>
    </form>
    <p style="text-align:center;font-size:13px;color:var(--text-secondary);margin-top:16px;">
      Already have an account? <a href="login.php" style="color:var(--teal);font-weight:500;">Log in</a>
    </p>
    <?php endif; ?>
  </div>
</body>
</html>
