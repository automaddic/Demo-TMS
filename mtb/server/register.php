<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../'); // Path to root containing `.env`
$dotenv->load();

$baseUrl = $_ENV['BASE_URL'] ?? 'http://localhost/mtb-login-php';

require __DIR__ . '/db.php'; // database connection

$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (!$username || !$email || !$password || !$confirmPassword) {
    $_SESSION['error'] = 'All fields are required';
    $_SESSION['from_register'] = true;
    header('Location: ' . $baseUrl . '/public/register.php');
    exit;
}

if ($password !== $confirmPassword) {
    $_SESSION['error'] = 'Passwords do not match';
    $_SESSION['from_register'] = true;
    header('Location: ' . $baseUrl . '/public/register.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Email already in use';
        $_SESSION['from_register'] = true;
        header('Location: ' . $baseUrl . '/public/register.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);

    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Username already taken';
        $_SESSION['from_register'] = true;
        header('Location: ' . $baseUrl . '/public/register.php');
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $hashedPassword]);

    $_SESSION['success'] = 'Registration successful!';
    $_SESSION['from_register'] = true;
    $_SESSION['just_registered'] = true;

    header('Location: ' . $baseUrl . '/public/register.php');
    exit;

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    $_SESSION['error'] = 'Internal server error';
    $_SESSION['from_register'] = true;
    header('Location: ' . $baseUrl . '/public/register.php');
    exit;
}
