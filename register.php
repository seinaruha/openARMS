<?php
// register.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require __DIR__ . '/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);

$shelterId      = $input['shelter_id'] ?? null;
$personnelName  = trim($input['personnel_name'] ?? '');
$role           = trim($input['role'] ?? '');
$phone          = trim($input['phone'] ?? '');
$username       = trim($input['username'] ?? '');
$password       = $input['password'] ?? '';

if (!$shelterId || $personnelName === '' || $username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'shelter_id, personnel_name, username, and password are required.']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters.']);
    exit;
}

try {
    // Check username uniqueness up front for a clean error message
    $check = $pdo->prepare('SELECT personnel_id FROM Personnel WHERE username = :username LIMIT 1');
    $check->execute(['username' => $username]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Username already taken.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO Personnel (shelter_id, personnel_name, role, phone, username, password)
         VALUES (:shelter_id, :personnel_name, :role, :phone, :username, :password)'
    );
    $stmt->execute([
        'shelter_id'     => $shelterId,
        'personnel_name' => $personnelName,
        'role'           => $role ?: null,
        'phone'          => $phone ?: null,
        'username'       => $username,
        'password'       => $hash,
    ]);

    echo json_encode([
        'success'       => true,
        'personnel_id'  => $pdo->lastInsertId(),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again.']);
    // log $e->getMessage()
}