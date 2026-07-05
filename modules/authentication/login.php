<?php
/**
 * authentication/login.php
 * Authenticates a user and redirects them to the correct dashboard by role.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = Database::getConnection();
$errors = [];

if (isPost()) {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $errors[] = "Invalid email or password.";
    } elseif ($user['role'] === 'LANDLORD' && $user['status'] !== 'APPROVED' && $user['status'] !== 'ACTIVE') {
        $errors[] = "Your landlord account is still pending admin approval.";
    } elseif ($user['status'] === 'SUSPENDED') {
        $errors[] = "This account has been suspended. Contact support for assistance.";
    } elseif ($user['status'] === 'REJECTED') {
        $errors[] = "Your account application was rejected. Reason: " . ($user['rejection_reason'] ?? 'Not specified.');
    } else {
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['full_name'];

        switch ($user['role']) {
            case 'ADMIN':
                redirect('../admin_dashboard/dashboard.php');
                break;
            case 'LANDLORD':
                redirect('../landlord_dashboard/dashboard.php');
                break;
            default:
                redirect('../tenant_dashboard/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Log in &mdash; RentPay</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/shared/assets/style.css">
<style>
  body{display:flex;align-items:center;justify-content:center;min-height:100vh;}
  .auth-wrap{max-width:380px;width:100%;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:32px;}
</style>
</head>
<body>
  <div class="auth-wrap">
    <h2 style="text-align:center;margin-bottom:6px;">Log in to RentPay</h2>
    <p style="text-align:center;color:var(--text-secondary);font-size:13.5px;margin:0 0 20px;">Enter your details to access your dashboard.</p>

    <?php foreach ($errors as $error): ?>
      <div class="alert error"><?= sanitize($error) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="login.php">
      <div class="field"><label>Email</label><input type="email" name="email" required></div>
      <div class="field"><label>Password</label><input type="password" name="password" required></div>
      <button type="submit" class="btn btn-teal btn-block">Log in</button>
    </form>
    <p style="text-align:center;font-size:13px;color:var(--text-secondary);margin-top:16px;">
      Don't have an account? <a href="register.php" style="color:var(--teal);font-weight:500;">Register</a>
    </p>
  </div>
</body>
</html>
