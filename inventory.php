<?php

require_once __DIR__ . '/api-helper.php';

$method = getMethod();
$id = getQuery('id');
$body = getJsonBody();

requireAuth();

if ($method === 'GET') {
    if ($id) {
        $item = fetchOne(
            'SELECT i.item_id,
                    i.shelter_id,
                    i.item_name AS name,
                    i.item_type AS category,
                    i.unit,
                    i.active,
                    i.received_date,
                    i.expiry_date,
                    i.notes,
                    i.item_properties,
                    i.on_hand_qty AS quantity,
                    i.initial_qty,
                    COALESCE(si.low_stock_threshold, i.initial_qty, 0) AS min_stock,
                    i.is_active,
                    i.created_at,
                    i.updated_at,
                    s.shelter_name
             FROM Items i
             LEFT JOIN Shelters s ON i.shelter_id = s.shelter_id
             LEFT JOIN ShelterInventory si ON si.item_id = i.item_id AND si.shelter_id = i.shelter_id
             WHERE i.item_id = :id AND i.is_active = 1',
            ['id' => $id]
        );

        if (!$item) {
            respondError('Item not found', 404);
        }
        if (!isSuperadmin() && !canAccessShelter($item['shelter_id'])) {
            respondError('Access denied', 403);
        }

        respondSuccess(['item' => $item]);
    } else {
        $shelter_id = getQuery('shelter_id');
        $params = [];
        $filter = 'WHERE i.is_active = 1';
        $allowedShelters = getCurrentShelterIds();
        if ($shelter_id) {
            if (!isSuperadmin() && !canAccessShelter($shelter_id)) {
                respondError('Access denied', 403);
            }
            $filter .= ' AND i.shelter_id = :shelter_id';
            $params['shelter_id'] = $shelter_id;
        } elseif (!isSuperadmin()) {
            if (is_array($allowedShelters) && !empty($allowedShelters)) {
                $placeholders = implode(',', array_fill(0, count($allowedShelters), '?'));
                $filter .= " AND i.shelter_id IN ($placeholders)";
                $params = array_merge($params, $allowedShelters);
            } else {
                $filter .= ' AND 0 = 1';
            }
        }

        $items = fetchAll(
            'SELECT i.item_id,
                    i.shelter_id,
                    i.item_name AS name,
                    i.item_type AS category,
                    i.unit,
                    i.active,
                    i.received_date,
                    i.expiry_date,
                    i.notes,
                    i.item_properties,
                    i.on_hand_qty AS quantity,
                    i.initial_qty,
                    COALESCE(si.low_stock_threshold, i.initial_qty, 0) AS min_stock,
                    i.is_active,
                    i.created_at,
                    i.updated_at,
                    s.shelter_name
             FROM Items i
             LEFT JOIN Shelters s ON i.shelter_id = s.shelter_id
             LEFT JOIN ShelterInventory si ON si.item_id = i.item_id AND si.shelter_id = i.shelter_id
             ' . $filter . '
             ORDER BY i.item_id DESC',
            $params
        );
        respondSuccess(['items' => $items]);
    }

} elseif ($method === 'POST') {
    ensureRoles(['superadmin','shelter_manager','staff']);
    $itemNameInput = $body['item_name'] ?? $body['name'] ?? null;
    validateRequired(array_merge($body, ['item_name' => $itemNameInput]), ['item_name', 'unit', 'shelter_id']);
    $item_name = sanitize($itemNameInput);
    $unit = sanitize($body['unit'] ?? null);
    if (!$item_name || !$unit) {
        respondError('Field name and unit are required', 400);
    }

    $shelter_id = !empty($body['shelter_id']) ? (int)$body['shelter_id'] : null;
    if (!isSuperadmin() && !canAccessShelter($shelter_id)) {
        respondError('Access denied', 403);
    }
    $item_type = sanitize($body['item_type'] ?? $body['category'] ?? null);
    $item_properties = isset($body['item_properties']) ? sanitize($body['item_properties']) : null;
    $on_hand_qty = isset($body['on_hand_qty']) ? (float)$body['on_hand_qty'] : (isset($body['quantity']) ? (float)$body['quantity'] : 0);
    $min_stock = isset($body['min_stock']) ? (float)$body['min_stock'] : null;
    $initial_qty = isset($body['initial_qty']) ? (float)$body['initial_qty'] : ($min_stock !== null ? $min_stock : $on_hand_qty);
    $received_date = sanitize($body['received_date'] ?? date('Y-m-d'));
    $expiry_date = sanitize($body['expiry_date'] ?? null);
    $notes = sanitize($body['notes'] ?? null);

    try {
        $result = execute(
            'INSERT INTO Items (shelter_id, item_name, item_type, unit, item_properties, on_hand_qty, initial_qty, received_date, expiry_date, notes)
             VALUES (:shelter_id, :item_name, :item_type, :unit, :item_properties, :on_hand_qty, :initial_qty, :received_date, :expiry_date, :notes)',
            [
                'shelter_id' => $shelter_id,
                'item_name' => $item_name,
                'item_type' => $item_type,
                'unit' => $unit,
                'item_properties' => $item_properties,
                'on_hand_qty' => $on_hand_qty,
                'initial_qty' => $initial_qty,
                'received_date' => $received_date,
                'expiry_date' => $expiry_date,
                'notes' => $notes
            ]
        );

        $newItemId = $result['last_insert_id'];
        if ($min_stock !== null && $shelter_id) {
            execute(
                'INSERT INTO ShelterInventory (shelter_id, item_id, low_stock_threshold)
                 VALUES (:shelter_id, :item_id, :low_stock_threshold)',
                ['shelter_id' => $shelter_id, 'item_id' => $newItemId, 'low_stock_threshold' => $min_stock]
            );
        }

        respondSuccess(['success' => true, 'item_id' => $newItemId], 201);

    } catch (Exception $e) {
        respondError('Failed to create item: ' . $e->getMessage(), 500);
    }

} elseif ($method === 'PUT') {
    ensureRoles(['superadmin','shelter_manager','staff']);
    requireParam($id, 'Item ID');
    $body = requireJsonBody();

    $existingItem = fetchOne('SELECT shelter_id FROM Items WHERE item_id = :id LIMIT 1', ['id' => $id]);
    if (!$existingItem) {
        respondError('Item not found', 404);
    }
    if (!isSuperadmin() && !canAccessShelter($existingItem['shelter_id'])) {
        respondError('Access denied', 403);
    }

    $shelter_id = isset($body['shelter_id']) ? (int)$body['shelter_id'] : $existingItem['shelter_id'];
    if (!isSuperadmin() && !canAccessShelter($shelter_id)) {
        respondError('Access denied', 403);
    }
    $item_name = sanitize($body['item_name'] ?? $body['name'] ?? null);
    $item_type = sanitize($body['item_type'] ?? $body['category'] ?? null);
    $unit = sanitize($body['unit'] ?? null);
    $item_properties = isset($body['item_properties']) ? sanitize($body['item_properties']) : null;
    $on_hand_qty = isset($body['on_hand_qty']) ? (float)$body['on_hand_qty'] : (isset($body['quantity']) ? (float)$body['quantity'] : null);
    $min_stock = isset($body['min_stock']) ? (float)$body['min_stock'] : null;
    $initial_qty = isset($body['initial_qty']) ? (float)$body['initial_qty'] : ($min_stock !== null ? $min_stock : null);
    $received_date = sanitize($body['received_date'] ?? null);
    $expiry_date = sanitize($body['expiry_date'] ?? null);
    $notes = sanitize($body['notes'] ?? null);
    $is_active = isset($body['is_active']) ? (int)$body['is_active'] : null;

    updateTable('Items', 'item_id', $id, [
        'shelter_id' => $shelter_id,
        'item_name' => $item_name,
        'item_type' => $item_type,
        'unit' => $unit,
        'item_properties' => $item_properties,
        'on_hand_qty' => $on_hand_qty,
        'initial_qty' => $initial_qty,
        'received_date' => $received_date,
        'expiry_date' => $expiry_date,
        'notes' => $notes,
        'is_active' => $is_active
    ]);

    if ($min_stock !== null && $shelter_id) {
        $existingInventory = fetchOne(
            'SELECT shelter_inventory_id FROM ShelterInventory WHERE item_id = :item_id AND shelter_id = :shelter_id LIMIT 1',
            ['item_id' => $id, 'shelter_id' => $shelter_id]
        );
        if ($existingInventory) {
            execute(
                'UPDATE ShelterInventory SET low_stock_threshold = :low_stock_threshold WHERE shelter_inventory_id = :id',
                ['low_stock_threshold' => $min_stock, 'id' => $existingInventory['shelter_inventory_id']]
            );
        } else {
            execute(
                'INSERT INTO ShelterInventory (shelter_id, item_id, low_stock_threshold)
                 VALUES (:shelter_id, :item_id, :low_stock_threshold)',
                ['shelter_id' => $shelter_id, 'item_id' => $id, 'low_stock_threshold' => $min_stock]
            );
        }
    }

    respondSuccess(['success' => true]);

} elseif ($method === 'DELETE') {
    ensureRoles(['superadmin','shelter_manager']);
    requireParam($id, 'Item ID');
    $existingItem = fetchOne('SELECT shelter_id FROM Items WHERE item_id = :id LIMIT 1', ['id' => $id]);
    if (!$existingItem) {
        respondError('Item not found', 404);
    }
    if (!isSuperadmin() && !canAccessShelter($existingItem['shelter_id'])) {
        respondError('Access denied', 403);
    }
    softDelete('Items', 'item_id', $id);
    respondSuccess(['success' => true]);

} else {
    respondError('Method not allowed', 405);
}
?>
