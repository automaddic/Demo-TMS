<?php
// router-api.php — adjust $backendRoot to your actual path
$backendRoot = '/home/automaddic/mtb/server';

header('Content-Type: application/json; charset=utf-8');

// get path param (strip any accidental ? parts)
$path = $_GET['path'] ?? '';
$path = explode('?', $path)[0];
if (!$path) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing path parameter']);
    exit;
}

$cleanPath = str_replace('..', '', $path);
$targetFile = realpath($backendRoot . '/' . ltrim($cleanPath, '/'));

if (!$targetFile || !file_exists($targetFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'API endpoint not found', 'path' => $cleanPath]);
    exit;
}

if (strpos($targetFile, realpath($backendRoot)) !== 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// ====== Important: forward query string into $_GET ======
parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $queryFromUri);

// $_GET already contains at least ['path' => '...'] — merge so action, etc. are present:
$_GET = array_merge($_GET, $queryFromUri);

// rebuild $_REQUEST so backend sees all GET + POST:
$_REQUEST = array_merge($_GET, $_POST);

// mark router origin
$_SERVER['FROM_ROUTER'] = true;

// optional debug logging (comment out in production)
// error_log("Router calling: $targetFile");
// error_log("Router _GET: " . print_r($_GET, true));
// error_log("Router _POST: " . print_r($_POST, true));

ob_start();
try {
    include $targetFile;
    $out = ob_get_clean();
    echo $out;
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}
