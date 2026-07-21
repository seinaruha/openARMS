<?php

require_once __DIR__ . '/api-helper.php';

$method = getMethod();
$id = getQuery('id');
$body = getJsonBody();

requireAuth();
ensureRoles(['superadmin','shelter_manager','auditor']);

if ($method === 'GET') {
    if ($id) {
        $person = fetchOne(
            'SELECT personnel_id, personnel_name, username, phone, is_active, created_at, updated_at
             FROM Personnel
             WHERE personnel_id = :id',
            ['id' => $id]
        );

        if (!$person) {
            respondError('Personnel not found', 404);
        }

        $roles = fetchAll('SELECT r.role_name, pr.shelter_id FROM PersonnelRoles pr JOIN Roles r ON r.role_id = pr.role_id WHERE pr.personnel_id = :id', ['id' => $id]);
        if (!isGlobalReader()) {
            $allowed = false;
            foreach ($roles as $role) {
                if (canAccessShelter($role['shelter_id'])) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                respondError('Access denied', 403);
            }
        }

        $person['roles'] = $roles;
        respondSuccess(['personnel' => $person]);
    } else {
        if (isGlobalReader()) {
            $personnel = fetchAll(
                'SELECT personnel_id, personnel_name, username, phone, is_active, created_at, updated_at
                 FROM Personnel
                 ORDER BY personnel_id DESC'
            );
            foreach ($personnel as &$p) {
                $roles = fetchAll('SELECT r.role_name, pr.shelter_id FROM PersonnelRoles pr JOIN Roles r ON r.role_id = pr.role_id WHERE pr.personnel_id = :id', ['id' => $p['personnel_id']]);
                $p['roles'] = $roles;
            }
            unset($p);
        } else {
            $shelterIds = getCurrentShelterIds();
            if (empty($shelterIds)) {
                respondError('Access denied', 403);
            }
            $placeholders = implode(',', array_fill(0, count($shelterIds), '?'));
            $personnel = fetchAll(
                "SELECT DISTINCT p.personnel_id, p.personnel_name, p.username, p.phone, p.is_active, p.created_at, p.updated_at
                 FROM Personnel p
                 JOIN PersonnelRoles pr ON pr.personnel_id = p.personnel_id
                 WHERE pr.shelter_id IN ($placeholders)
                 ORDER BY p.personnel_id DESC",
                $shelterIds
            );
            foreach ($personnel as &$p) {
                $roles = fetchAll('SELECT r.role_name, pr.shelter_id FROM PersonnelRoles pr JOIN Roles r ON r.role_id = pr.role_id WHERE pr.personnel_id = :id', ['id' => $p['personnel_id']]);
                $p['roles'] = $roles;
            }
            unset($p);
        }
        respondSuccess(['personnel' => $personnel]);
    }

} elseif ($method === 'POST') {
    ensureRoles(['superadmin']);
    validateRequired($body, ['personnel_name', 'username']);

    $personnel_name = sanitize($body['personnel_name']);
    $username = sanitize($body['username']);
    $password = $body['password'] ?? null;
    $phone = sanitize($body['phone'] ?? null);
    $roles = $body['roles'] ?? null; 

    try {
        $params = ['personnel_name' => $personnel_name, 'username' => $username, 'phone' => $phone];
        if ($password) {
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $result = execute('INSERT INTO Personnel (personnel_name, username, password_hash, phone) VALUES (:personnel_name, :username, :password_hash, :phone)', $params);
        } else {
            $result = execute('INSERT INTO Personnel (personnel_name, username, phone) VALUES (:personnel_name, :username, :phone)', $params);
        }

        $newId = $result['last_insert_id'];

        if (is_array($roles)) {
            foreach ($roles as $r) {
                if (empty($r['role_name'])) continue;
                $rrow = fetchOne('SELECT role_id FROM Roles WHERE role_name = :r LIMIT 1', ['r' => $r['role_name']]);
                if ($rrow) {
                    execute('INSERT INTO PersonnelRoles (personnel_id, role_id, shelter_id) VALUES (:pid, :rid, :sid)', ['pid' => $newId, 'rid' => $rrow['role_id'], 'sid' => $r['shelter_id'] ?? null]);
                }
            }
        }

        respondSuccess(['success' => true, 'personnel_id' => $newId], 201);

    } catch (Exception $e) {
        respondError('Failed to create personnel: ' . $e->getMessage(), 500);
    }

} elseif ($method === 'PUT') {
    ensureRoles(['superadmin']);
    requireParam($id, 'Personnel ID');
    $body = requireJsonBody();

    $personnel_name = sanitize($body['personnel_name'] ?? null);
    $phone = sanitize($body['phone'] ?? null);
    $is_active = isset($body['is_active']) ? (int)$body['is_active'] : null;
    $roles = $body['roles'] ?? null; 

    tryTransaction(function() use ($id, $personnel_name, $phone, $is_active, $roles) {
        
        updateTable('Personnel', 'personnel_id', $id, [
            'personnel_name' => $personnel_name,
            'phone' => $phone,
            'is_active' => $is_active
        ]);

        if (is_array($roles)) {
            execute('DELETE FROM PersonnelRoles WHERE personnel_id = :id', ['id' => $id]);
            foreach ($roles as $r) {
                if (empty($r['role_name'])) continue;
                $rrow = fetchOne('SELECT role_id FROM Roles WHERE role_name = :r LIMIT 1', ['r' => $r['role_name']]);
                if ($rrow) {
                    execute('INSERT INTO PersonnelRoles (personnel_id, role_id, shelter_id) VALUES (:pid, :rid, :sid)', ['pid' => $id, 'rid' => $rrow['role_id'], 'sid' => $r['shelter_id'] ?? null]);
                }
            }
        }
        return true;
    });
    respondSuccess(['success' => true]);

} elseif ($method === 'DELETE') {
    ensureRoles(['superadmin']);
    requireParam($id, 'Personnel ID');
    softDelete('Personnel', 'personnel_id', $id);
    respondSuccess(['success' => true]);

} else {
    respondError('Method not allowed', 405);
}
?>
