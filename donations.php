<?php

require_once __DIR__ . '/api-helper.php';

$method = getMethod();
$id = getQuery('id');
$body = getJsonBody();

function buildDonationLineRows($body, $donation_id = null) {
    $lines = $body['lines'] ?? [];
    if (!empty($body['item_id']) && isset($body['item_quantity'])) {
        $lines = [[
            'item_id' => $body['item_id'],
            'item_quantity' => $body['item_quantity'],
            'line_notes' => $body['line_notes'] ?? null
        ]];
    }
    $rows = [];
    foreach ($lines as $ln) {
        if (empty($ln['item_id']) || !isset($ln['item_quantity'])) continue;
        $quantity = (float)$ln['item_quantity'];
        if ($quantity <= 0) continue;
        $row = [
            'item_id' => (int)$ln['item_id'],
            'item_quantity' => $quantity,
            'line_notes' => $ln['line_notes'] ?? null
        ];
        if ($donation_id !== null) {
            $row['donation_id'] = $donation_id;
        }
        $rows[] = $row;
    }
    return $rows;
}

function validateDonationLineRows($rows) {
    if (empty($rows)) {
        respondError('Donation lines are required', 400);
    }
    foreach ($rows as $row) {
        if (empty($row['item_id']) || $row['item_quantity'] <= 0) {
            respondError('Donation item and quantity are required', 400);
        }
        $item = fetchOne('SELECT item_id FROM Items WHERE item_id = :id AND is_active = 1', ['id' => $row['item_id']]);
        if (!$item) {
            respondError('Invalid item selected', 400);
        }
    }
}

try {
    requireAuth();
    if ($method === 'GET') {
        if ($id) {
            $donation = fetchOne(
                'SELECT d.*, s.shelter_name, su.supplier_name
                 FROM Donations d
                 LEFT JOIN Shelters s ON d.shelter_id = s.shelter_id
                 LEFT JOIN Suppliers su ON d.supplier_id = su.supplier_id
                 WHERE d.donation_id = :id',
                ['id' => $id]
            );

            if (!$donation) return respondError('Donation not found', 404);
            if (!isGlobalReader() && !canAccessShelter($donation['shelter_id'])) {
                return respondError('Access denied', 403);
            }

            $lines = fetchAll(
                'SELECT donation_line_id, item_id, item_quantity, line_notes FROM DonationLines WHERE donation_id = :id',
                ['id' => $id]
            );
            $donation['lines'] = $lines;
            return respondSuccess(['donation' => $donation]);
        }

        $sql = 'SELECT d.donation_id,
                    d.donor_name,
                    d.description,
                    d.shelter_id,
                    d.supplier_id,
                    d.received_date,
                    d.receipt_notes,
                    d.created_at,
                    s.shelter_name,
                    su.supplier_name,
                    dl.item_id,
                    dl.item_quantity,
                    i.item_name
             FROM Donations d
             LEFT JOIN Shelters s ON d.shelter_id = s.shelter_id
             LEFT JOIN Suppliers su ON d.supplier_id = su.supplier_id
             LEFT JOIN (
                 SELECT donation_id, item_id, item_quantity, line_notes
                 FROM DonationLines
                 WHERE donation_line_id = (
                     SELECT MIN(donation_line_id)
                     FROM DonationLines dl2
                     WHERE dl2.donation_id = DonationLines.donation_id
                 )
             ) dl ON dl.donation_id = d.donation_id
             LEFT JOIN Items i ON i.item_id = dl.item_id
             WHERE 1=1';

        $params = [];
        if (!isGlobalReader()) {
            $allowedShelters = getCurrentShelterIds();
            if (empty($allowedShelters)) {
                return respondSuccess(['donations' => []]);
            }
            $placeholders = implode(',', array_fill(0, count($allowedShelters), '?'));
            $sql .= ' AND d.shelter_id IN (' . $placeholders . ')';
            $params = $allowedShelters;
        }
        $sql .= ' ORDER BY d.received_date DESC, d.donation_id DESC';
        $donations = fetchAll($sql, $params);
        return respondSuccess(['donations' => $donations]);
    }

    if ($method === 'POST') {
        ensureRoles(['superadmin','shelter_manager','staff']);
        $body = requireJsonBody();
        @file_put_contents(__DIR__ . '/donations_debug.log', date('c') . " POST payload: " . json_encode($body) . "\n", FILE_APPEND);
        $donor_name = sanitize($body['donor_name'] ?? null);
        $description = sanitize($body['description'] ?? null);
        $shelter_id = !empty($body['shelter_id']) ? (int)$body['shelter_id'] : null;
        $supplier_id = !empty($body['supplier_id']) ? (int)$body['supplier_id'] : null;
        $received_date = sanitize($body['received_date'] ?? date('Y-m-d'));
        $receipt_notes = sanitize($body['receipt_notes'] ?? null);
        $lines = $body['lines'] ?? [];

        if (!$shelter_id) {
            return respondError('Shelter is required', 400);
        }
        if (!isGlobalReader() && !canAccessShelter($shelter_id)) {
            return respondError('Access denied', 403);
        }

        $rows = buildDonationLineRows($body);
        validateDonationLineRows($rows);

        $donation_id = null;
        tryTransaction(function() use ($body, $donor_name, $description, $shelter_id, $supplier_id, $received_date, $receipt_notes, &$donation_id) {
            global $pdo;
            $stmt = $pdo->prepare(
                'INSERT INTO Donations (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
                 VALUES (:donor_name, :description, :shelter_id, :supplier_id, :received_date, :receipt_notes)'
            );
            $stmt->execute([
                'donor_name' => $donor_name,
                'description' => $description,
                'shelter_id' => $shelter_id,
                'supplier_id' => $supplier_id,
                'received_date' => $received_date,
                'receipt_notes' => $receipt_notes
            ]);
            $donation_id = $pdo->lastInsertId();
            @file_put_contents(__DIR__ . '/donations_debug.log', date('c') . " Inserted donation_id: $donation_id\n", FILE_APPEND);

            $rows = buildDonationLineRows($body, $donation_id);
            if (empty($rows)) {
                throw new Exception('Donation lines are required');
            }
            insertMany('DonationLines', ['donation_id','item_id','item_quantity','line_notes'], $rows);
            @file_put_contents(__DIR__ . '/donations_debug.log', date('c') . " Inserted donation lines for donation_id: $donation_id (rows: " . count($rows) . ")\n", FILE_APPEND);
            return true;
        });
        return respondSuccess(['success' => true, 'donation_id' => (int)$donation_id], 201);
    }

    if ($method === 'PUT') {
        ensureRoles(['superadmin','shelter_manager']);
        if (!$body) return respondError('Invalid JSON body', 400);
        $donation_id = $body['donation_id'] ?? null;
        if (!$donation_id) return respondError('donation_id is required', 400);

        $donor_name = sanitize($body['donor_name'] ?? null);
        $description = sanitize($body['description'] ?? null);
        $shelter_id = isset($body['shelter_id']) ? (int)$body['shelter_id'] : null;
        $supplier_id = !empty($body['supplier_id']) ? (int)$body['supplier_id'] : null;
        $received_date = sanitize($body['received_date'] ?? null);
        $receipt_notes = sanitize($body['receipt_notes'] ?? null);
        $lines = $body['lines'] ?? [];

        $existingDon = fetchOne('SELECT shelter_id FROM Donations WHERE donation_id = :id LIMIT 1', ['id' => $donation_id]);
        if (!$existingDon) return respondError('Donation not found', 404);
        if (!isGlobalReader() && !canAccessShelter($existingDon['shelter_id'])) {
            return respondError('Access denied', 403);
        }
        if ($shelter_id && !isGlobalReader() && !canAccessShelter($shelter_id)) {
            return respondError('Access denied', 403);
        }

        $rows = buildDonationLineRows($body);
        validateDonationLineRows($rows);

        tryTransaction(function() use ($body, $donation_id, $donor_name, $description, $shelter_id, $supplier_id, $received_date, $receipt_notes, $rows, $existingDon) {
            global $pdo;

            $oldLines = fetchAll('SELECT item_id, item_quantity FROM DonationLines WHERE donation_id = :id', ['id' => $donation_id]);
            foreach ($oldLines as $oldLine) {
                execute('UPDATE Items SET on_hand_qty = on_hand_qty - :qty WHERE item_id = :item_id', [
                    'qty' => $oldLine['item_quantity'],
                    'item_id' => $oldLine['item_id']
                ]);
            }

            $stmt = $pdo->prepare(
                'UPDATE Donations
                 SET donor_name = :donor_name,
                     description = :description,
                     shelter_id = :shelter_id,
                     supplier_id = :supplier_id,
                     received_date = :received_date,
                     receipt_notes = :receipt_notes
                 WHERE donation_id = :donation_id'
            );
            $stmt->execute([
                'donor_name' => $donor_name,
                'description' => $description,
                'shelter_id' => $shelter_id ?: $existingDon['shelter_id'],
                'supplier_id' => $supplier_id ?: null,
                'received_date' => $received_date,
                'receipt_notes' => $receipt_notes,
                'donation_id' => $donation_id
            ]);

            $pdo->prepare('DELETE FROM DonationLines WHERE donation_id = :donation_id')->execute(['donation_id' => $donation_id]);
            $rows = buildDonationLineRows($body, $donation_id);
            if (empty($rows)) {
                throw new Exception('Donation lines are required');
            }
            insertMany('DonationLines', ['donation_id','item_id','item_quantity','line_notes'], $rows);
            return true;
        });
        return respondSuccess(['success' => true]);
    }

    if ($method === 'DELETE') {
        ensureRoles(['superadmin','shelter_manager']);
        if (!$body) return respondError('Invalid JSON body', 400);
        $donation_id = $body['donation_id'] ?? null;
        if (!$donation_id) return respondError('donation_id is required', 400);

        $existingDon = fetchOne('SELECT shelter_id FROM Donations WHERE donation_id = :id LIMIT 1', ['id' => $donation_id]);
        if (!$existingDon) return respondError('Donation not found', 404);
        if (!isGlobalReader() && !canAccessShelter($existingDon['shelter_id'])) {
            return respondError('Access denied', 403);
        }

        tryTransaction(function() use ($donation_id) {
            $oldLines = fetchAll('SELECT item_id, item_quantity FROM DonationLines WHERE donation_id = :id', ['id' => $donation_id]);
            foreach ($oldLines as $oldLine) {
                execute('UPDATE Items SET on_hand_qty = on_hand_qty - :qty WHERE item_id = :item_id', [
                    'qty' => $oldLine['item_quantity'],
                    'item_id' => $oldLine['item_id']
                ]);
            }
            execute('DELETE FROM DonationLines WHERE donation_id = :donation_id', ['donation_id' => $donation_id]);
            execute('DELETE FROM Donations WHERE donation_id = :donation_id', ['donation_id' => $donation_id]);
            return true;
        });

        return respondSuccess(['success' => true]);
    }

    respondError('Method not allowed', 405);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo && method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) $pdo->rollBack();
    
    @file_put_contents(__DIR__ . '/donations_error.log', date('c') . " " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
    respondError('Server error: ' . $e->getMessage(), 500);
}

