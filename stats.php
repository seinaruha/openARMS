<?php

require_once __DIR__ . '/api-helper.php';

$totalItems = fetchOne('SELECT COUNT(*) AS total_items FROM Items WHERE is_active = 1');

$totalQuantity = fetchOne('SELECT COALESCE(SUM(on_hand_qty), 0) AS total_quantity FROM Items WHERE is_active = 1');

$lowStockCount = fetchOne(
    'SELECT COUNT(DISTINCT i.item_id) AS low_stock_count
     FROM Items i
     LEFT JOIN ShelterInventory si ON si.item_id = i.item_id AND si.shelter_id = i.shelter_id
     WHERE i.is_active = 1 AND i.on_hand_qty <= COALESCE(si.low_stock_threshold, 0)'
);

$expiredCount = fetchOne(
    'SELECT COUNT(DISTINCT i.item_id) AS expired_count
     FROM Items i
     WHERE i.is_active = 1 AND i.expiry_date < CURDATE()'
);

$expiringSoon = fetchAll(
    'SELECT i.item_id, i.item_name AS name, i.expiry_date, i.on_hand_qty AS quantity, s.shelter_name,
            COALESCE(si.near_expiry_days, 14) AS near_expiry_days
     FROM Items i
     LEFT JOIN ShelterInventory si ON si.item_id = i.item_id AND si.shelter_id = i.shelter_id
     LEFT JOIN Shelters s ON s.shelter_id = i.shelter_id
     WHERE i.is_active = 1
       AND i.expiry_date IS NOT NULL
       AND i.expiry_date >= CURDATE()
       AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL COALESCE(si.near_expiry_days, 14) DAY)'
);

$totalDonations = fetchOne('SELECT COUNT(*) AS total_donations FROM Donations');

$totalSuppliers = fetchOne('SELECT COUNT(*) AS total_suppliers FROM Suppliers WHERE is_active = 1');

$byCategory = fetchAll(
    'SELECT item_type AS category, COUNT(*) AS count, COALESCE(SUM(on_hand_qty),0) AS total_qty
     FROM Items
     WHERE is_active = 1
     GROUP BY item_type'
);

$recentLogs = fetchAll(
    'SELECT l.transaction_id, l.transaction_date, l.item_id, i.item_name AS item_name, l.quantity, l.transaction_type AS action, l.created_at AS logged_at
     FROM InventoryLogs l
     LEFT JOIN Items i ON i.item_id = l.item_id
     ORDER BY l.created_at DESC
     LIMIT 8'
);

respondSuccess([
    'total_items' => (int)$totalItems['total_items'],
    'total_quantity' => (float)$totalQuantity['total_quantity'],
    'low_stock_count' => (int)$lowStockCount['low_stock_count'],
    'expired_count' => (int)$expiredCount['expired_count'],
    'total_donations' => (int)$totalDonations['total_donations'],
    'total_suppliers' => (int)$totalSuppliers['total_suppliers'],
    'by_category' => $byCategory,
    'recent_logs' => $recentLogs
]);
