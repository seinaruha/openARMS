<?php

require_once __DIR__ . '/api-helper.php';

$method = getMethod();
$id = getQuery('id');
$body = getJsonBody();

if ($method === 'GET') {
    if ($id) {
        $supplier = fetchOne(
            'SELECT supplier_id, supplier_name, contact, email, address, supplier_type, is_active, deleted_at, created_at, updated_at
             FROM Suppliers
             WHERE supplier_id = :id',
            ['id' => $id]
        );

        if (!$supplier) {
            respondError('Supplier not found', 404);
        }

        respondSuccess(['supplier' => $supplier]);
    } else {
        $suppliers = fetchAll(
            'SELECT supplier_id, supplier_name, contact, email, address, supplier_type, is_active, deleted_at, created_at, updated_at
             FROM Suppliers
             WHERE is_active = 1
             ORDER BY supplier_id DESC'
        );
        respondSuccess(['suppliers' => $suppliers]);
    }

} elseif ($method === 'POST') {
    validateRequired($body, ['supplier_name']);

    $supplier_name = sanitize($body['supplier_name']);
    $contact = sanitize($body['contact'] ?? null);
    $email = sanitize($body['email'] ?? null);
    $address = sanitize($body['address'] ?? null);
    $supplier_type = sanitize($body['supplier_type'] ?? 'General');

    try {
        $result = execute(
            'INSERT INTO Suppliers (supplier_name, contact, email, address, supplier_type)
             VALUES (:supplier_name, :contact, :email, :address, :supplier_type)',
            [
                'supplier_name' => $supplier_name,
                'contact' => $contact,
                'email' => $email,
                'address' => $address,
                'supplier_type' => $supplier_type
            ]
        );

        respondSuccess(['success' => true, 'supplier_id' => $result['last_insert_id']], 201);

    } catch (Exception $e) {
        respondError('Failed to create supplier: ' . $e->getMessage(), 500);
    }

} elseif ($method === 'PUT') {
    requireParam($id, 'Supplier ID');
    $body = requireJsonBody();
    $supplier_name = sanitize($body['supplier_name'] ?? null);
    $contact = sanitize($body['contact'] ?? null);
    $email = sanitize($body['email'] ?? null);
    $address = sanitize($body['address'] ?? null);
    $supplier_type = sanitize($body['supplier_type'] ?? 'General');

    updateTable('Suppliers', 'supplier_id', $id, [
        'supplier_name' => $supplier_name,
        'contact' => $contact,
        'email' => $email,
        'address' => $address,
        'supplier_type' => $supplier_type
    ]);
    respondSuccess(['success' => true]);

} elseif ($method === 'DELETE') {
    requireParam($id, 'Supplier ID');
    softDelete('Suppliers', 'supplier_id', $id);
    respondSuccess(['success' => true]);

} else {
    respondError('Method not allowed', 405);
}
?>
