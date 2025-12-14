<?php
require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('suspensions.php', $pdo);

$id = $_POST['user_id'] ?? null;

if ($id) {
    // Fetch user email
    $stmt = $pdo->prepare("SELECT email, preferred_name, first_name FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Update suspension status
    $pdo->prepare("UPDATE users SET is_suspended = 0 WHERE id = ?")->execute([$id]);

    // Send approval email
    if ($user && $user['email']) {
        $to = $user['email'];
        $subject = "Your Sope Creek MTB Account is Approved";
        $name = $user['preferred_name'] ?: $user['first_name'];
        $message = "Hello {$name},\n\nYour account has been reviewed and approved by a coach. You can now access all features of the Sope Creek MTB dashboard.\n\nThank you!";
        $headers = "From: no-reply@sopecreekmtb.org";

        mail($to, $subject, $message, $headers);
    }
}

header('Location:' . $baseUrl . '/public/admin/suspensions.php');
exit;
