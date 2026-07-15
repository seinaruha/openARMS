<?php

require_once __DIR__ . '/api-helper.php';

$method = getMethod();
$id = getQuery('id');
$body = getJsonBody();

function adjustItemQuantity($item_id, $delta) {
    if (!$item_id || $delta === 0) return;
    execute('UPDATE Items SET on_hand_qty = on_hand_qty + :delta WHERE item_id = :item_id', [
        'delta' => $delta,
        'item_id' => $item_id
    ]);
}

function getQuantityDelta($transaction_type, $quantity) {
    $quantity = (float)$quantity;
    if ($transaction_type === 'IN') {
        return $quantity;
    }
    if ($transaction_type === 'OUT') {
        return -$quantity;
    }
    if ($transaction_type === 'ADJUST') {
        return $quantity;
    }
    return 0;
}

requireAuth();

if ($method === 'GET') {
    if ($id) {
        $movement = fetchOne(
            'SELECT l.transaction_id,
                    l.transaction_date,
                    l.item_id,
                    i.item_name AS item_name,
                    i.unit AS unit,
                    l.shelter_id,
                    s.shelter_name,
                    l.quantity,
                    l.transaction_type AS action,
                    l.personnel_id,
                    p.personnel_name AS performed_by,
                    l.transaction_notes AS reason,
                    l.created_at AS logged_at
             FROM InventoryLogs l
             LEFT JOIN Items i ON i.item_id = l.item_id
             LEFT JOIN Shelters s ON s.shelter_id = l.shelter_id
             LEFT JOIN Personnel p ON p.personnel_id = l.personnel_id
             WHERE l.transaction_id = :id',
            ['id' => $id]
        );

        if (!$movement) {
            respondError('Log not found', 404);
        }
        if (!isSuperadmin() && !canAccessShelter($movement['shelter_id'])) {
            respondError('Access denied', 403);
        }

        respondSuccess(['log' => $movement]);
    } else {
        $allowedShelters = getCurrentShelterIds();
        $sql = 'SELECT l.transaction_id,
                    l.transaction_date,
                    l.item_id,
                    i.item_name AS item_name,
                    i.unit AS unit,
                    l.shelter_id,
                    s.shelter_name,
                    l.quantity,
                    l.transaction_type AS action,
                    l.personnel_id,
                    p.personnel_name AS performed_by,
                    l.transaction_notes AS reason,
                    l.created_at AS logged_at
             FROM InventoryLogs l
             LEFT JOIN Items i ON i.item_id = l.item_id
             LEFT JOIN Shelters s ON s.shelter_id = l.shelter_id
             LEFT JOIN Personnel p ON p.personnel_id = l.personnel_id';
        $params = [];
        if (!isSuperadmin()) {
            if (empty($allowedShelters)) {
                respondSuccess(['logs' => []]);
            }
            $placeholders = implode(',', array_fill(0, count($allowedShelters), '?'));
            $sql .= ' WHERE l.shelter_id IN (' . $placeholders . ')';
            $params = $allowedShelters;
        }
        $sql .= ' ORDER BY l.transaction_id DESC';
        $movements = fetchAll($sql, $params);
        respondSuccess(['logs' => $movements]);
    }

} elseif ($method === 'POST') {
    ensureRoles(['superadmin','shelter_manager','staff','volunteer']);
    $body = requireJsonBody();
    validateRequired($body, ['item_id', 'quantity']);

    $item_id = (int)$body['item_id'];
    $transaction_type = sanitize($body['transaction_type'] ?? $body['action'] ?? null); 
    $quantity = (float)$body['quantity'];
    $shelter_id = !empty($body['shelter_id']) ? (int)$body['shelter_id'] : null;
    $personnel_id = null;
    if (isset($body['personnel_id']) && is_numeric($body['personnel_id'])) {
        $personnel_id = (int)$body['personnel_id'];
    } elseif (!empty($body['performed_by'])) {
        $found = fetchOne(
            'SELECT personnel_id FROM Personnel WHERE username = :name OR personnel_name = :name LIMIT 1',
            ['name' => trim($body['performed_by'])]
        );
        $personnel_id = $found['personnel_id'] ?? null;
    }
    $transaction_notes = sanitize($body['transaction_notes'] ?? $body['reason'] ?? null);

    if (!$shelter_id) {
        $itemShelter = fetchOne('SELECT shelter_id FROM Items WHERE item_id = :item_id LIMIT 1', ['item_id' => $item_id]);
        $shelter_id = $itemShelter['shelter_id'] ?? null;
    }

    if (!$personnel_id) {
        $personnel_id = getCurrentPersonnelId();
        if (!$personnel_id) {
            $fallback = fetchOne('SELECT personnel_id FROM Personnel WHERE is_active = 1 ORDER BY personnel_id LIMIT 1');
            $personnel_id = $fallback['personnel_id'] ?? null;
        }
    }

    if (!in_array($transaction_type, ['IN', 'OUT', 'TRANSFER', 'ADJUST'])) {
        respondError('Invalid transaction_type', 400);
    }

    if ($quantity <= 0) {
        respondError('Quantity must be greater than 0', 400);
    }

    if (!$shelter_id) {
        respondError('Shelter is required for inventory movement', 400);
    }

    if (!$personnel_id) {
        respondError('A valid personnel record is required for inventory movement', 400);
    }

    if (!isSuperadmin() && !canAccessShelter($shelter_id)) {
        respondError('Access denied', 403);
    }

    $result = tryTransaction(function() use ($item_id, $shelter_id, $quantity, $transaction_type, $personnel_id, $transaction_notes) {
        $insertResult = execute(
            'INSERT INTO InventoryLogs (transaction_date, item_id, shelter_id, quantity, transaction_type, personnel_id, transaction_notes)
             VALUES (CURDATE(), :item_id, :shelter_id, :quantity, :transaction_type, :personnel_id, :transaction_notes)',
            [
                'item_id' => $item_id,
                'shelter_id' => $shelter_id,
                'quantity' => $quantity,
                'transaction_type' => $transaction_type,
                'personnel_id' => $personnel_id,
                'transaction_notes' => $transaction_notes
            ]
        );

        $delta = getQuantityDelta($transaction_type, $quantity);
        if ($delta !== 0) {
            adjustItemQuantity($item_id, $delta);
        }

        return $insertResult;
    });
    respondSuccess(['success' => true, 'transaction_id' => $result['last_insert_id']], 201);

} elseif ($method === 'PUT') {
    ensureRoles(['superadmin','shelter_manager']);
    requireParam($id, 'Log ID');
    $body = requireJsonBody();

    $existingLog = fetchOne('SELECT item_id, shelter_id, quantity, transaction_type FROM InventoryLogs WHERE transaction_id = :id LIMIT 1', ['id' => $id]);
    if (!$existingLog) {
        respondError('Log not found', 404);
    }
    if (!isSuperadmin() && !canAccessShelter($existingLog['shelter_id'])) {
        respondError('Access denied', 403);
    }

    $transaction_date = sanitize($body['transaction_date'] ?? null);
    $shelter_id = isset($body['shelter_id']) ? (int)$body['shelter_id'] : $existingLog['shelter_id'];
    $item_id = isset($body['item_id']) ? (int)$body['item_id'] : (int)$existingLog['item_id'];
    $quantity = isset($body['quantity']) ? (float)$body['quantity'] : (float)$existingLog['quantity'];
    $transaction_type = sanitize($body['transaction_type'] ?? $existingLog['transaction_type']);
    $personnel_id = isset($body['personnel_id']) ? (int)$body['personnel_id'] : null;
    $transaction_notes = sanitize($body['transaction_notes'] ?? null);

    if (!in_array($transaction_type, ['IN', 'OUT', 'TRANSFER', 'ADJUST'])) {
        respondError('Invalid transaction_type', 400);
    }

    $oldDelta = getQuantityDelta($existingLog['transaction_type'], $existingLog['quantity']);
    $newDelta = getQuantityDelta($transaction_type, $quantity);

    updateTable('InventoryLogs', 'transaction_id', $id, [
        'transaction_date' => $transaction_date,
        'item_id' => $item_id,
        'shelter_id' => $shelter_id,
        'quantity' => $quantity,
        'transaction_type' => $transaction_type,
        'personnel_id' => $personnel_id,
        'transaction_notes' => $transaction_notes
    ]);

    if ($item_id !== (int)$existingLog['item_id']) {
        adjustItemQuantity($existingLog['item_id'], -$oldDelta);
        adjustItemQuantity($item_id, $newDelta);
    } else {
        adjustItemQuantity($item_id, $newDelta - $oldDelta);
    }

    respondSuccess(['success' => true]);

} elseif ($method === 'DELETE') {
    ensureRoles(['superadmin','shelter_manager']);
    if (!$id) {
        respondError('Log ID required', 400);
    }

    $existingLog = fetchOne('SELECT item_id, shelter_id, quantity, transaction_type FROM InventoryLogs WHERE transaction_id = :id LIMIT 1', ['id' => $id]);
    if (!$existingLog) {
        respondError('Log not found', 404);
    }
    if (!isSuperadmin() && !canAccessShelter($existingLog['shelter_id'])) {
        respondError('Access denied', 403);
    }

    try {
        $delta = getQuantityDelta($existingLog['transaction_type'], $existingLog['quantity']);
        if ($delta !== 0) {
            adjustItemQuantity($existingLog['item_id'], -$delta);
        }
        execute('DELETE FROM InventoryLogs WHERE transaction_id = :id', ['id' => $id]);
        respondSuccess(['success' => true]);

    } catch (Exception $e) {
        respondError('Failed to delete log: ' . $e->getMessage(), 500);
    }

} else {
    respondError('Method not allowed', 405);
}
?>
