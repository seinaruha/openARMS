<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$dsn = "mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4";
$user = "YOUR_USER";
$pass = "YOUR_PASS";

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["error" => "DB connection failed"]);
  exit;
}

$resource = $_GET['resource'] ?? '';
if ($resource !== 'suppliers') {
  http_response_code(404);
  echo json_encode(["error" => "Not found"]);
  exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $stmt = $pdo->query("
  SELECT supplier_id AS id,
  supplier_name AS name,
  email,
  address,
  supplier_type,
  NULL AS contact,
  NOW() AS created_at
  FROM Suppliers
  ORDER BY supplier_id DESC
  ");
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents("php://input");
  $data = json_decode($raw, true);

  $name = trim((string)($data['name'] ?? ''));
  $contact = isset($data['contact']) ? trim((string)$data['contact']) : null;
  $email = isset($data['email']) ? trim((string)$data['email']) : null;
  $address = isset($data['address']) ? trim((string)$data['address']) : null;

  if ($name === '') {
    http_response_code(400);
    echo json_encode(["error" => "Supplier name is required"]);
    exit;
  }

  $stmt = $pdo->prepare("
  INSERT INTO Suppliers (supplier_name, email, address, supplier_type)
  VALUES (:name, :email, :address, :supplier_type)
  ");
  $stmt->execute([
    ':name' => $name,
    ':email' => ($email === '') ? null : $email,
    ':address' => ($address === '') ? null : $address,
    ':supplier_type' => $data['supplier_type'] ?? 'N/A'
  ]);


  echo json_encode(["ok" => true, "id" => $pdo->lastInsertId()]);
  exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
