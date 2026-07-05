<?php
/**
 * contact_management/send_message.php
 * Handles the public Contact Us form submission.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/functions.php';

$pdo = Database::getConnection();

if (isPost()) {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $phone    = sanitize($_POST['phone'] ?? '');
    $type     = strtoupper(sanitize($_POST['inquirer_type'] ?? 'OTHER'));
    $subject  = sanitize($_POST['subject'] ?? 'General enquiry');
    $message  = sanitize($_POST['message'] ?? '');

    if ($fullName === '' || $email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect('../contact.php?error=Please+fill+in+all+required+fields+with+a+valid+email');
    }
    if (!in_array($type, ['TENANT','LANDLORD','PARTNER','OTHER'])) {
        $type = 'OTHER';
    }

    $pdo->prepare(
        "INSERT INTO contact_messages (full_name, email, phone, inquirer_type, subject, message, status, created_at)
         VALUES (:name, :email, :phone, :type, :subject, :message, 'NEW', NOW())"
    )->execute([
        ':name'    => $fullName,
        ':email'   => $email,
        ':phone'   => $phone,
        ':type'    => $type,
        ':subject' => $subject,
        ':message' => $message
    ]);

    redirect('../contact.php?success=Message+sent.+Our+team+will+respond+within+one+business+day.');
} else {
    redirect('../contact.php');
}
