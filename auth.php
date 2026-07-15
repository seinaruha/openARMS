<?php
// auth.php
// Usage: require __DIR__ . '/auth.php';  then $personnel is available.

require_once __DIR__ . '/db.php';

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

try {
    $stmt = $pdo->prepare(
        'SELECT p.personnel_id, p.shelter_id, p.personnel_name, p.role, p.username
         FROM Sessions s
         JOIN Personnel p ON p.personnel_id = s.personnel_id
         WHERE s.token = :token AND s.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute(['token' => $token]);
    $personnel = $stmt->fetch();

    if (!$personnel) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Session expired or invalid. Please log in again.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server error validating session.']);
    exit;
}

// $personnel now holds: personnel_id, shelter_id, personnel_name, role, username