<?php
require_once __DIR__ . '/config/bootstrap.php'; // only loads .env

// Clear all session data
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ' . $baseUrl . '/public/login.php');
exit;
