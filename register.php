<?php

require_once __DIR__ . '/api-helper.php';

$method = getMethod();
if ($method !== 'POST') {
    respondError('Method not allowed', 405);
}

requireAuth();
ensureRoles(['superadmin','shelter_manager']);

$body = requireJsonBody();
validateRequired($body, ['personnel_name', 'username', 'password', 'role']);

$shelterId      = isset($body['shelter_id']) ? (is_numeric($body['shelter_id']) ? (int)$body['shelter_id'] : null) : null;
$personnelName  = sanitize($body['personnel_name']);
$role           = sanitize($body['role']);
$phone          = sanitize($body['phone'] ?? null);
$username       = sanitize($body['username']);
$password       = $body['password'] ?? '';

$currentRoles = getCurrentRoles();
$currentShelterId = null;
foreach ($currentRoles as $r) {
    if (!empty($r['role_name']) && $r['role_name'] === 'shelter_manager' && !empty($r['shelter_id'])) {
        $currentShelterId = $r['shelter_id'];
        break;
    }
}

$validRoles = ['superadmin', 'shelter_manager', 'staff', 'volunteer', 'auditor'];
if (!in_array($role, $validRoles, true)) {
    respondError('Invalid role selected', 400);
}

if (in_array($role, ['staff', 'volunteer', 'shelter_manager'], true) && !$shelterId) {
    respondError('Shelter is required for the selected role', 400);
}

if ($role === 'superadmin' && $currentShelterId !== null && !hasRole('superadmin')) {
    respondError('Only superadmin can create another superadmin', 403);
}

if (!empty($currentShelterId) && $shelterId && (int)$shelterId !== (int)$currentShelterId) {
    respondError('Shelter managers may only create users for their own shelter', 403);
}

if (strlen($password) < 8) {
    respondError('Password must be at least 8 characters', 400);
}

try {
    $exists = fetchOne('SELECT personnel_id FROM Personnel WHERE username = :username LIMIT 1', ['username' => $username]);
    if ($exists) respondError('Username already taken', 409);

    $params = [
        'personnel_name' => $personnelName,
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => $phone ?: null
    ];
    $result = execute('INSERT INTO Personnel (personnel_name, username, password_hash, phone) VALUES (:personnel_name, :username, :password_hash, :phone)', $params);
    $newId = $result['last_insert_id'];

    if (!empty($role)) {
        $rrow = fetchOne('SELECT role_id FROM Roles WHERE role_name = :r LIMIT 1', ['r' => $role]);
        if ($rrow) {
            execute('INSERT INTO PersonnelRoles (personnel_id, role_id, shelter_id) VALUES (:pid, :rid, :sid)', ['pid' => $newId, 'rid' => $rrow['role_id'], 'sid' => $shelterId ?: null]);
        }
    }

    respondSuccess(['success' => true, 'personnel_id' => $newId], 201);

} catch (Exception $e) {
    respondError('Failed to create personnel: ' . $e->getMessage(), 500);
}
