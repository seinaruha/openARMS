<?php
/**
 * openARMS - Login API Endpoint
 * 
 * Handles authentication requests
 * XAMPP compatible - uses standard ports
 */

header("Content-Type: application/json");

// Load configuration
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/src/config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "No data received."]);
    exit;
}

$username = trim($data["username"] ?? "");
$password = trim($data["password"] ?? "");

if ($username === "" || $password === "") {
    http_response_code(400);
    echo json_encode(["error" => "Username and password are required."]);
    exit;
}

try {
    // Get database connection
    $conn = getMysqliConnection();
    
    // Search by username
    $sql = "SELECT personnel_id, personnel_name, username, role, shelter_id, password FROM Personnel WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(["error" => "Username not found."]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Verify password (plain text for now, upgrade to password_hash later)
    if ($password !== $user["password"]) {
        http_response_code(401);
        echo json_encode(["error" => "Incorrect password."]);
        exit;
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user["personnel_id"];
    $_SESSION['user_name'] = $user["personnel_name"];
    $_SESSION['user_role'] = $user["role"];
    $_SESSION['shelter_id'] = $user["shelter_id"];
    
    // Return success response
    echo json_encode([
        "success" => true,
        "user" => [
            "personnel_id" => $user["personnel_id"],
            "personnel_name" => $user["personnel_name"],
            "username" => $user["username"],
            "role" => $user["role"],
            "shelter_id" => $user["shelter_id"]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "An internal error occurred. Please try again."]);
}

$conn->close();
?>
