<?php

require_once __DIR__ . '/api-helper.php';

$method = getMethod();
$body = getJsonBody();

requireAuth();

if ($method === 'GET') {
    $categories = fetchAll('SELECT category_id, category_name FROM InventoryCategories ORDER BY category_name ASC');
    respondSuccess(['categories' => $categories]);
}

if ($method === 'POST') {
    ensureRoles(['superadmin', 'shelter_manager']);
    $category_name = sanitize($body['category_name'] ?? null);
    if (!$category_name) {
        respondError('Category name is required', 400);
    }

    $existing = fetchOne('SELECT category_id FROM InventoryCategories WHERE category_name = :category_name LIMIT 1', ['category_name' => $category_name]);
    if ($existing) {
        respondError('Category already exists', 400);
    }

    try {
        $result = execute(
            'INSERT INTO InventoryCategories (category_name) VALUES (:category_name)',
            ['category_name' => $category_name]
        );
        $category_id = $result['last_insert_id'];
        respondSuccess(['category' => ['category_id' => (int)$category_id, 'category_name' => $category_name]], 201);
    } catch (Exception $e) {
        respondError('Failed to create category: ' . $e->getMessage(), 500);
    }
}

if ($method === 'DELETE') {
    ensureRoles(['superadmin', 'shelter_manager']);
    $category_id = isset($body['category_id']) ? (int)$body['category_id'] : null;
    if (!$category_id) {
        respondError('category_id is required', 400);
    }

    $category = fetchOne('SELECT category_name FROM InventoryCategories WHERE category_id = :id LIMIT 1', ['id' => $category_id]);
    if (!$category) {
        respondError('Category not found', 404);
    }

    $inUse = fetchOne('SELECT COUNT(*) AS count FROM Items WHERE item_type = :category_name', ['category_name' => $category['category_name']]);
    if ($inUse && $inUse['count'] > 0) {
        respondError('Cannot remove category while items still use it', 400);
    }

    execute('DELETE FROM InventoryCategories WHERE category_id = :id', ['id' => $category_id]);
    respondSuccess(['success' => true]);
}

respondError('Method not allowed', 405);
