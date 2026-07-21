<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api-helper.php';

function getBearerToken() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return $matches[1];
    }
    return null;
}

$token = getBearerToken();

if (!$token) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing or invalid Authorization header.']);
    exit;
}

$personnel = null;
try {
    if (substr_count($token, '.') === 2) {
        list($b64h, $b64p, $b64s) = explode('.', $token);
        $header = json_decode(base64_decode(strtr($b64h, '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($b64p, '-_', '+/')), true);
        $sig = base64_decode(strtr($b64s, '-_', '+/'));

        global $JWT_SECRET;
        $expected = hash_hmac('sha256', "$b64h.$b64p", $JWT_SECRET, true);
        if (hash_equals($expected, $sig) && (empty($payload['exp']) || $payload['exp'] > time())) {
            $stmt = $pdo->prepare('SELECT personnel_id, personnel_name, username, is_active FROM Personnel WHERE personnel_id = :id LIMIT 1');
            $stmt->execute(['id' => $payload['sub']]);
            $personnel = $stmt->fetch();
        }
    }
} catch (Exception $e) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

if (!$personnel) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Session expired or invalid. Please log in again.']);
    exit;
}

if (isset($personnel['is_active']) && !$personnel['is_active']) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Account inactive. Contact administrator.']);
    exit;
}

if (!empty($personnel['personnel_id'])) {
    setCurrentPersonnel($personnel['personnel_id']);
}

