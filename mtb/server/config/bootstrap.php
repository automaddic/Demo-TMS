<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../'); // Path to root containing `.env`
$dotenv->load();

$baseUrl = $_ENV['BASE_URL'] ?? 'http://localhost/mtb-login-php';

date_default_timezone_set('America/New_York');

require __DIR__ . '/../db.php'; // database connection
require_once __DIR__ . '/../auth/require-role.php';
require_once __DIR__ . '/../api/data/user.php'; // helper to get current user
require_once __DIR__ . '/../api/data/ride-groups.php'; // returns ride group data'

$user = getCurrentUser($pdo); // Fetch user from session or DB
$rideGroups = getRideGroups($pdo); // Fetch ride groups