<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

// ---------------------------------------------
// Load Environment Variables
// ---------------------------------------------
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$baseUrl = $_ENV['BASE_URL'];

// ---------------------------------------------
// Database Connection
// ---------------------------------------------
require __DIR__ . '/db.php';

// ---------------------------------------------
// Imports & Setup
// ---------------------------------------------
use League\OAuth2\Client\Provider\Google;

$action = $_GET['action'] ?? null;
$isApi = !empty($_SERVER['FROM_ROUTER']); // Detect if called through router

// ---------------------------------------------
// Helper: respond() handles both API and normal POST
// ---------------------------------------------
function respond($isApi, $success, $dataOrError = null, $redirect = null)
{
    // For normal browser POST, always redirect if $redirect is provided
    if ($success && $redirect && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Location: ' . $redirect);
        exit;
    }

    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(
            $success
                ? ['success' => true, 'data' => $dataOrError, 'redirect' => $redirect]
                : ['success' => false, 'error' => $dataOrError]
        );
        exit;
    }

    // Fallback redirect for normal POST
    if ($redirect) {
        header('Location: ' . $redirect);
        exit;
    }

    // If nothing else, just return JSON
    echo json_encode($dataOrError);
    exit;
}

// ---------------------------------------------
// LOCAL LOGIN
// ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'local') {

    $identifier = $_POST['identifier'] ?? '';
    $password   = $_POST['password'] ?? '';

    // Validate inputs
    if (!$identifier && !$password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute(['DemoUser@example.com', 'DemoUser']);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            respond($isApi, false, 'User not found.', $baseUrl . '/public/login.php');
        }

        $_SESSION['user'] = [
        'id'              => $user['id'],
        'email'           => $user['email'],
        'username'        => $user['username'],
        'role_level'      => $user['role_level'],
        'profile_picture' => $user['profile_picture_url'] ?? null,
        ];

        respond($isApi, true, $_SESSION['user'], $baseUrl . '/public/home.php');
    }    
    
    
    if (!$identifier || !$password) {
        respond($isApi, false, 'Please fill in all fields.', $baseUrl . '/public/login.php');
    }

    // Lookup user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        respond($isApi, false, 'User not found.', $baseUrl . '/public/login.php');
    }

    if ($user['password'] === null) {
        respond($isApi, false, "Invalid credentials — Google account or inactive.", $baseUrl . '/public/login.php');
    }

    if (!password_verify($password, $user['password'])) {
        respond($isApi, false, 'Invalid credentials.', $baseUrl . '/public/login.php');
    }

    // Success — start session
    $_SESSION['user'] = [
        'id'              => $user['id'],
        'email'           => $user['email'],
        'username'        => $user['username'],
        'role_level'      => $user['role_level'],
        'profile_picture' => $user['profile_picture_url'] ?? null,
    ];

    respond($isApi, true, $_SESSION['user'], $baseUrl . '/public/home.php');

}


// ---------------------------------------------
// DEFAULT FALLBACK
// ---------------------------------------------
else {
    respond($isApi, false, 'Invalid login request', $baseUrl . '/public/login.php');
}
