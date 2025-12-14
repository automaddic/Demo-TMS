<?php
require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('suspensions.php', $pdo);

$id = $_POST['user_id'] ?? null;

if ($id) {
    // Fetch email before deletion
    $stmt = $pdo->prepare("SELECT email, preferred_name, first_name FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    // Send deletion email
    if ($user && $user['email']) {
        $to = $user['email'];
        $subject = "Your Sope Creek MTB Account Was Not Approved";
        $name = $user['preferred_name'] ?: $user['first_name'];
        $message = "Hello {$name},\n\nUnfortunately, your account could not be approved due to an invalid or unrecognized name. Please contact a coach if you believe this is an error.\n\nThank you.";
        $headers = "From: no-reply@sopecreekmtb.org";

        mail($to, $subject, $message, $headers);
    }
}

header("Location: $baseUrl/public/admin/suspensions.php");
exit;
