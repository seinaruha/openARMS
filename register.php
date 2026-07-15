<?php

require_once __DIR__ . '/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$shelterId      = $input['shelter_id'] ?? null;
$personnelName  = trim($input['personnel_name'] ?? '');
$role           = trim($input['role'] ?? '');
$phone          = trim($input['phone'] ?? '');
$username       = trim($input['username'] ?? '');
$password       = $input['password'] ?? '';

$allowedRoles = ['superadmin', 'shelter_manager'];
$roleStmt = $pdo->prepare(
    'SELECT r.role_name, pr.shelter_id
     FROM PersonnelRoles pr
     JOIN Roles r ON pr.role_id = r.role_id
     WHERE pr.personnel_id = :pid'
);
$roleStmt->execute(['pid' => $personnel['personnel_id']]);
$currentRoles = $roleStmt->fetchAll();
$authorized = false;
$currentShelterId = null;
foreach ($currentRoles as $r) {
    if (in_array($r['role_name'], $allowedRoles, true)) {
        $authorized = true;
        if ($r['role_name'] === 'shelter_manager' && !empty($r['shelter_id'])) {
            $currentShelterId = $r['shelter_id'];
        }
    }
}
if (!$authorized) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Only managers and above can add personnel.']);
    exit;
}

if ($personnelName === '' || $username === '' || $password === '' || $role === '') {
    http_response_code(400);
    echo json_encode(['error' => 'personnel_name, username, password, and role are required.']);
    exit;
}

if (!in_array($role, ['superadmin', 'shelter_manager', 'staff', 'volunteer', 'auditor'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role selected.']);
    exit;
}

if (in_array($role, ['staff', 'volunteer', 'shelter_manager'], true) && !$shelterId) {
    http_response_code(400);
    echo json_encode(['error' => 'Shelter is required for the selected role.']);
    exit;
}
if (in_array($role, ['superadmin'], true) && $currentShelterId !== null && !hasRole('superadmin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Only superadmin can create another superadmin.']);
    exit;
}
if (!empty($currentShelterId) && $shelterId && (int)$shelterId !== (int)$currentShelterId) {
    http_response_code(403);
    echo json_encode(['error' => 'Shelter managers may only create users for their own shelter.']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters.']);
    exit;
}

try {
    
    $check = $pdo->prepare('SELECT personnel_id FROM Personnel WHERE username = :username LIMIT 1');
    $check->execute(['username' => $username]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Username already taken.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO Personnel (personnel_name, username, password_hash, phone)
         VALUES (:personnel_name, :username, :password_hash, :phone)'
    );
    $stmt->execute([
        'personnel_name' => $personnelName,
        'username'       => $username,
        'password_hash'  => $hash,
        'phone'          => $phone ?: null,
    ]);

    $newId = $pdo->lastInsertId();

    
    if (!empty($role)) {
        try {
            $rstmt = $pdo->prepare('SELECT role_id FROM Roles WHERE role_name = :r LIMIT 1');
            $rstmt->execute(['r' => $role]);
            $r = $rstmt->fetch();
            if ($r) {
                $insertPR = $pdo->prepare('INSERT INTO PersonnelRoles (personnel_id, role_id, shelter_id) VALUES (:pid, :rid, :sid)');
                $insertPR->execute(['pid' => $newId, 'rid' => $r['role_id'], 'sid' => $shelterId ?: null]);
            }
        } catch (Exception $e) {
            
        }
    }

    echo json_encode([
        'success'       => true,
        'personnel_id'  => $newId,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again.']);
    
}