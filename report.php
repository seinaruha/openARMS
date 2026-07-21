<?php

require_once __DIR__ . '/api-helper.php';

$method = getMethod();
if ($method !== 'GET') {
    respondError('Method not allowed', 405);
}

$shelter_id = getQuery('shelter_id');
$params = [];
$where = 'WHERE i.is_active = 1';
if ($shelter_id) {
    $where .= ' AND i.shelter_id = :shelter_id';
    $params['shelter_id'] = $shelter_id;
}

$rows = fetchAll(
    'SELECT i.item_id,
            i.item_name AS name,
            i.item_type AS category,
            i.unit,
            i.on_hand_qty AS quantity,
            i.initial_qty,
            COALESCE(si.low_stock_threshold, i.initial_qty, 0) AS min_stock,
            i.expiry_date,
            s.shelter_name,
            CASE
              WHEN i.on_hand_qty <= COALESCE(si.low_stock_threshold, 0) THEN "Low"
              ELSE "OK"
            END AS stock_status,
            CASE
              WHEN i.expiry_date IS NULL THEN "Good"
              WHEN i.expiry_date < CURDATE() THEN "Expired"
              WHEN i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL COALESCE(si.near_expiry_days, 14) DAY) THEN "Expiring Soon"
              ELSE "Good"
            END AS expiry_status
     FROM Items i
     LEFT JOIN Shelters s ON s.shelter_id = i.shelter_id
     LEFT JOIN ShelterInventory si ON si.item_id = i.item_id AND si.shelter_id = i.shelter_id
     ' . $where . '
     ORDER BY i.item_name ASC',
    $params
);

respondSuccess(['reports' => $rows]);
