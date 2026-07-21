<?php

require_once __DIR__ . '/api-helper.php';

$method = getMethod();

if ($method !== 'GET') {
    respondError('Method not allowed', 405);
}

requireAuth();

// Pagination
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
if ($limit <= 0 || $limit > 1000) $limit = 200;

if (isSuperadmin() || (isGuest)) {
    $logs = fetchAll('SELECT a.*, p.personnel_name FROM AuditLog a LEFT JOIN Personnel p ON p.personnel_id = a.changed_by ORDER BY a.changed_at DESC LIMIT :lim OFFSET :off', ['lim' => $limit, 'off' => $offset]);
    respondSuccess(['audit' => $logs]);
}

$shelterIds = getCurrentShelterIds();
if (empty($shelterIds)) {
    // No assigned shelters -> return empty set
    respondSuccess(['audit' => []]);
}

// Build JSON_EXTRACT conditions for shelter ids on old_data or new_data
$placeholders = [];
$params = ['lim' => $limit, 'off' => $offset];
$conds = [];
$i = 0;
foreach ($shelterIds as $sid) {
    $k1 = 'sid_new_' . $i;
    $k2 = 'sid_old_' . $i;
    $conds[] = "JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.shelter_id')) = :$k1";
    $conds[] = "JSON_UNQUOTE(JSON_EXTRACT(old_data, '$.shelter_id')) = :$k2";
    $params[$k1] = $sid;
    $params[$k2] = $sid;
    $i++;
}

$where = implode(' OR ', $conds);
$sql = "SELECT a.*, p.personnel_name FROM AuditLog a LEFT JOIN Personnel p ON p.personnel_id = a.changed_by WHERE ($where) ORDER BY a.changed_at DESC LIMIT :lim OFFSET :off";

$logs = fetchAll($sql, $params);
respondSuccess(['audit' => $logs]);

?>
