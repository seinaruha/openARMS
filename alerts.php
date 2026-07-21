<?php

require_once __DIR__ . '/api-helper.php';

$shelterId = getQuery('shelter_id');
$shelterFilter = '';
$params = [];
if ($shelterId) {
    $shelterFilter = ' AND i.shelter_id = :shelter_id';
    $params['shelter_id'] = $shelterId;
}

$low_stock = fetchAll(
    'SELECT i.item_id, i.item_name AS name, i.on_hand_qty AS quantity, COALESCE(si.low_stock_threshold, 0) AS min_stock, s.shelter_name, i.expiry_date
     FROM Items i
     LEFT JOIN ShelterInventory si ON si.item_id = i.item_id AND si.shelter_id = i.shelter_id
     LEFT JOIN Shelters s ON s.shelter_id = i.shelter_id
     WHERE i.is_active = 1 AND i.on_hand_qty <= COALESCE(si.low_stock_threshold, 0)' . $shelterFilter,
    $params
);

$expired = fetchAll(
    'SELECT i.item_id, i.item_name AS name, i.expiry_date, i.on_hand_qty AS quantity, s.shelter_name
     FROM Items i
     LEFT JOIN Shelters s ON s.shelter_id = i.shelter_id
     WHERE i.is_active = 1 AND i.expiry_date < CURDATE()' . $shelterFilter,
    $params
);

$expiring_soon = fetchAll(
    'SELECT i.item_id, i.item_name AS name, i.expiry_date, i.on_hand_qty AS quantity, s.shelter_name, COALESCE(si.near_expiry_days, 14) AS near_expiry_days
     FROM Items i
     LEFT JOIN ShelterInventory si ON si.item_id = i.item_id AND si.shelter_id = i.shelter_id
     LEFT JOIN Shelters s ON s.shelter_id = i.shelter_id
     WHERE i.is_active = 1
       AND i.expiry_date IS NOT NULL
       AND i.expiry_date >= CURDATE()
       AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL COALESCE(si.near_expiry_days, 14) DAY)' . $shelterFilter,
    $params
);

respondSuccess([
    'low_stock' => $low_stock,
    'expired' => $expired,
    'expiring_soon' => $expiring_soon
]);
