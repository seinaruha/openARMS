<?php

require_once __DIR__ . '/api-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'No data received']);
    exit;
}

$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT personnel_id, personnel_name, username, password_hash 
         FROM Personnel 
         WHERE username = :username 
         LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Username not found']);
        exit;
    }

    
    $passwordValid = false;
    if (!empty($user['password_hash'])) {
        $passwordValid = password_verify($password, $user['password_hash']);
    }

    if (!$passwordValid) {
        http_response_code(401);
        echo json_encode(['error' => 'Incorrect password']);
        exit;
    }

    
    global $JWT_SECRET;
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = [
        'sub' => $user['personnel_id'],
        'name' => $user['personnel_name'],
        'iat' => time(),
        'exp' => time() + 86400 * 30
    ];

    $b64h = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $b64p = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', "$b64h.$b64p", $JWT_SECRET, true);
    $b64s = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    $jwt = "$b64h.$b64p.$b64s";

    
    $roles = [];
    try {
        $rstmt = $pdo->prepare(
            'SELECT r.role_name, pr.shelter_id
             FROM PersonnelRoles pr
             JOIN Roles r ON r.role_id = pr.role_id
             WHERE pr.personnel_id = :pid'
        );
        $rstmt->execute(['pid' => $user['personnel_id']]);
        $roles = $rstmt->fetchAll();
    } catch (Exception $e) {
        
    }

    $assignedShelter = fetchOne(
        'SELECT pr.shelter_id, s.shelter_name
         FROM PersonnelRoles pr
         LEFT JOIN Shelters s ON s.shelter_id = pr.shelter_id
         WHERE pr.personnel_id = :pid AND pr.shelter_id IS NOT NULL
         LIMIT 1',
        ['pid' => $user['personnel_id']]
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user' => [
            'personnel_id' => $user['personnel_id'],
            'personnel_name' => $user['personnel_name'],
            'username' => $user['username'],
            'roles' => $roles,
            'assigned_shelter' => $assignedShelter ?: null
        ],
        'token' => $jwt
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit;
}
?>
