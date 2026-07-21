<?php

require_once __DIR__ . '/api-helper.php';

$method = getMethod();
$id = getQuery('id');
$body = getJsonBody();

if ($method === 'GET') {
    if ($id) {
        $shelter = fetchOne(
            'SELECT shelter_id, shelter_name, address, contact_person, contact_number, capacity, shelter_type, is_active, deleted_at, created_at, updated_at
             FROM Shelters
             WHERE shelter_id = :id',
            ['id' => $id]
        );

        if (!$shelter) {
            respondError('Shelter not found', 404);
        }

        respondSuccess(['shelter' => $shelter]);
    } else {
        $shelters = fetchAll(
            'SELECT shelter_id, shelter_name, address, contact_person, contact_number, capacity, shelter_type, is_active, deleted_at, created_at, updated_at
             FROM Shelters
             WHERE is_active = 1
             ORDER BY shelter_id DESC'
        );
        respondSuccess(['shelters' => $shelters]);
    }

} elseif ($method === 'POST') {
    requireAuth();
    ensureRoles(['superadmin']);
    validateRequired($body, ['shelter_name']);

    $shelter_name = sanitize($body['shelter_name']);
    $address = sanitize($body['address'] ?? null);
    $contact_person = sanitize($body['contact_person'] ?? null);
    $contact_number = sanitize($body['contact_number'] ?? null);
    $capacity = isset($body['capacity']) ? (int)$body['capacity'] : null;
    $shelter_type = sanitize($body['shelter_type'] ?? null);

    try {
        $result = execute(
            'INSERT INTO Shelters (shelter_name, address, contact_person, contact_number, capacity, shelter_type)
             VALUES (:shelter_name, :address, :contact_person, :contact_number, :capacity, :shelter_type)',
            [
                'shelter_name' => $shelter_name,
                'address' => $address,
                'contact_person' => $contact_person,
                'contact_number' => $contact_number,
                'capacity' => $capacity,
                'shelter_type' => $shelter_type
            ]
        );

        respondSuccess(['success' => true, 'shelter_id' => $result['last_insert_id']], 201);

    } catch (Exception $e) {
        respondError('Failed to create shelter: ' . $e->getMessage(), 500);
    }

} elseif ($method === 'PUT') {
    requireAuth();
    ensureRoles(['superadmin']);
    requireParam($id, 'Shelter ID');
    $body = requireJsonBody();

    $shelter_name = sanitize($body['shelter_name'] ?? null);
    $address = sanitize($body['address'] ?? null);
    $contact_person = sanitize($body['contact_person'] ?? null);
    $contact_number = sanitize($body['contact_number'] ?? null);
    $capacity = isset($body['capacity']) ? (int)$body['capacity'] : null;
    $shelter_type = sanitize($body['shelter_type'] ?? null);

    updateTable('Shelters', 'shelter_id', $id, [
        'shelter_name' => $shelter_name,
        'address' => $address,
        'contact_person' => $contact_person,
        'contact_number' => $contact_number,
        'capacity' => $capacity,
        'shelter_type' => $shelter_type
    ]);
    respondSuccess(['success' => true]);

} elseif ($method === 'DELETE') {
    requireAuth();
    ensureRoles(['superadmin']);
    requireParam($id, 'Shelter ID');
    softDelete('Shelters', 'shelter_id', $id);
    respondSuccess(['success' => true]);

} else {
    respondError('Method not allowed', 405);
}
?>
