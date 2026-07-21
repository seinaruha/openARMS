<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

$CURRENT_PERSONNEL = null;
$CURRENT_ROLES = [];

function getBearerToken() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        return $matches[1];
    }
    return null;
}

function initializeAuth() {
    $token = getBearerToken();
    if (!$token) {
        return;
    }

    if (substr_count($token, '.') !== 2) {
        return;
    }

    list($b64h, $b64p, $b64s) = explode('.', $token);
    $payload = json_decode(base64_decode(strtr($b64p, '-_', '+/')), true);
    if (!$payload || empty($payload['sub'])) {
        return;
    }

    global $JWT_SECRET, $pdo, $CURRENT_PERSONNEL, $CURRENT_ROLES;
    $sig = base64_decode(strtr($b64s, '-_', '+/'));
    $expected = hash_hmac('sha256', "$b64h.$b64p", $JWT_SECRET ?? '', true);
    if (!hash_equals($expected, $sig)) {
        return;
    }
    if (!empty($payload['exp']) && $payload['exp'] <= time()) {
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT personnel_id, personnel_name, username FROM Personnel WHERE personnel_id = :id LIMIT 1');
        $stmt->execute(['id' => $payload['sub']]);
        $personnel = $stmt->fetch();
        if (!empty($personnel['personnel_id'])) {
            $CURRENT_PERSONNEL = $personnel;
            $roleStmt = $pdo->prepare('SELECT r.role_name, pr.shelter_id FROM PersonnelRoles pr JOIN Roles r ON pr.role_id = r.role_id WHERE pr.personnel_id = :id');
            $roleStmt->execute(['id' => $personnel['personnel_id']]);
            $CURRENT_ROLES = $roleStmt->fetchAll();
            setCurrentPersonnel($personnel['personnel_id']);
        }
    } catch (PDOException $e) {
        
    }
}

initializeAuth();

function respondError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

function respondSuccess($data = null, $code = 200) {
    http_response_code($code);
    echo json_encode($data ?: ['success' => true]);
    exit;
}

function getMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

function getJsonBody() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?: [];
}

function getQuery($key, $default = null) {
    return $_GET[$key] ?? $default;
}

function getPathParam($index) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $parts = array_filter(explode('/', $path));
    return $parts[$index] ?? null;
}

function getCurrentPersonnel() {
    global $CURRENT_PERSONNEL;
    return $CURRENT_PERSONNEL;
}

function getCurrentPersonnelId() {
    $personnel = getCurrentPersonnel();
    return $personnel['personnel_id'] ?? null;
}

function getCurrentRoles() {
    global $CURRENT_ROLES;
    return $CURRENT_ROLES ?: [];
}

function getCurrentRoleNames() {
    return array_map(function($role) {
        return $role['role_name'] ?? null;
    }, getCurrentRoles());
}

function hasRole($role) {
    return in_array($role, getCurrentRoleNames(), true);
}

function hasAnyRole($roles) {
    foreach ($roles as $role) {
        if (hasRole($role)) {
            return true;
        }
    }
    return false;
}

function isSuperadmin() {
    return hasRole('superadmin');
}

function isGuest() {
    return hasRole('guest');
}

function isGlobalReader() {
    return isSuperadmin() || isGuest();
}

function getCurrentShelterIds() {
    if (isGlobalReader()) {
        return null;
    }
    $ids = [];
    foreach (getCurrentRoles() as $role) {
        if (!empty($role['shelter_id'])) {
            $ids[] = (int)$role['shelter_id'];
        }
    }
    return array_values(array_unique($ids));
}

function canAccessShelter($shelter_id) {
    if ($shelter_id === null || $shelter_id === '') {
        return false;
    }
    if (isGlobalReader()) {
        return true;
    }
    foreach (getCurrentRoles() as $role) {
        if (!empty($role['shelter_id']) && (int)$role['shelter_id'] === (int)$shelter_id) {
            return true;
        }
    }
    return false;
}

function requireAuth() {
    if (!getCurrentPersonnelId()) {
        respondError('Authentication required', 401);
    }
}

function ensureRoles($roles) {
    if (!hasAnyRole($roles)) {
        respondError('Access denied', 403);
    }
}

function fetchAll($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        respondError('Database error: ' . $e->getMessage(), 500);
    }
}

function fetchOne($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        respondError('Database error: ' . $e->getMessage(), 500);
    }
}

function execute($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return [
            'affected_rows' => $stmt->rowCount(),
            'last_insert_id' => $pdo->lastInsertId()
        ];
    } catch (PDOException $e) {
        $paramKeys = is_array($params) ? array_keys($params) : [];
        $msg = 'Database error: ' . $e->getMessage() . ' | Query: ' . $query . ' | Params: ' . json_encode($paramKeys);
        respondError($msg, 500);
    }
}

function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            respondError("Field '{$field}' is required", 400);
        }
    }
}

function sanitize($value) {
    if (is_null($value)) {
        return null;
    }
    return trim((string)$value) ?: null;
}

function setCurrentPersonnel($personnel_id) {
    global $pdo;
    $id = (int)$personnel_id;
    try {
        
        $pdo->exec("SET @current_personnel_id = $id");
    } catch (PDOException $e) {
        
    }
}

function ensureMethod($allowed) {
    $m = getMethod();
    $allowedArr = is_array($allowed) ? $allowed : array_map('trim', explode(',', $allowed));
    if (!in_array($m, $allowedArr)) {
        respondError('Method not allowed', 405);
    }
}

function requireJsonBody() {
    $b = getJsonBody();
    if (!$b) respondError('Invalid JSON body', 400);
    return $b;
}

function tryTransaction(callable $fn) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        $res = $fn();
        $pdo->commit();
        return $res;
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        respondError('Server error: ' . $e->getMessage(), 500);
    }
}

function insertMany($table, $columns, $rows) {
    global $pdo;
    if (empty($rows)) return;
    $cols = implode(', ', $columns);
    $placeholders = ':' . implode(', :', $columns);
    $sql = "INSERT INTO $table ($cols) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    foreach ($rows as $r) {
        $params = [];
        foreach ($columns as $c) {
            $params[$c] = $r[$c] ?? null;
        }
        $stmt->execute($params);
    }
}

function requireParam($value, $name, $code = 400) {
    if (!$value) respondError("{$name} required", $code);
}

function updateTable($table, $idColumn, $id, $data) {
    $sets = [];
    $params = ['id' => $id];
    foreach ($data as $col => $val) {
        if ($val !== null) {
            $sets[] = "$col = :$col";
            $params[$col] = $val;
        }
    }
    if (empty($sets)) return 0;
    $sql = sprintf('UPDATE %s SET %s WHERE %s = :id', $table, implode(', ', $sets), $idColumn);
    return execute($sql, $params);
}

function hasTrigger($table, $triggerName) {
    $trigger = fetchOne(
        'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = :table AND TRIGGER_NAME = :trigger LIMIT 1',
        ['table' => $table, 'trigger' => $triggerName]
    );
    return !empty($trigger['TRIGGER_NAME']);
}

function softDelete($table, $idColumn, $id) {
    return execute("UPDATE $table SET is_active = 0, deleted_at = NOW() WHERE $idColumn = :id", ['id' => $id]);
}
?>
