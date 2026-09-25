<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/auth.php';

header('Content-Type: application/json');

if (!IsAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

// Writing to the session refreshes its modification time, preventing PHP's session garbage collection from expiring it while the tab is idle.
$_SESSION['LastKeepAlive'] = time();
echo json_encode(['ok' => true]);
