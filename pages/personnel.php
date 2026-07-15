<?php
/**
 * openARMS - Personnel Management Page
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getMysqliConnection();

// Fetch all personnel
$personnel = $conn->query("SELECT personnel_id, personnel_name, role, phone, created_at FROM Personnel ORDER BY personnel_id DESC");

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $personnel_id = (int)($_POST["personnel_id"] ?? 0);
    
    // Sanitize inputs
    $personnel_name = sanitizeInput($_POST["personnel_name"] ?? "");
    $role = sanitizeInput($_POST["role"] ?? "") ?: null;
    $phone = sanitizeInput($_POST["phone"] ?? "") ?: null;
    
    if ($personnel_name === "") {
        $_SESSION['error_message'] = "Personnel name is required.";
    } else {
        try {
            if ($action === "add") {
                $stmt = $conn->prepare("INSERT INTO Personnel (personnel_name, role, phone) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $personnel_name, $role, $phone);
                
                if (!$stmt->execute()) throw new Exception("Failed to add personnel: " . $stmt->error);
                $stmt->close();
                
                $_SESSION['success_message'] = 'Personnel added successfully!';
                header("Location: personnel.php");
                exit;
                
            } elseif ($action === "update") {
                $stmt = $conn->prepare("UPDATE Personnel SET personnel_name = ?, role = ?, phone = ? WHERE personnel_id = ?");
                $stmt->bind_param("sssi", $personnel_name, $role, $phone, $personnel_id);
                
                if (!$stmt->execute()) throw new Exception("Failed to update personnel: " . $stmt->error);
                $stmt->close();
                
                $_SESSION['success_message'] = 'Personnel updated successfully!';
                header("Location: personnel.php");
                exit;
                
            } elseif ($action === "delete") {
                $stmt = $conn->prepare("DELETE FROM Personnel WHERE personnel_id = ?");
                $stmt->bind_param("i", $personnel_id);
                $stmt->execute();
                $stmt->close();
                
                $_SESSION['success_message'] = 'Personnel deleted successfully!';
                header("Location: personnel.php");
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
    }
}

// Edit mode
$editPersonnel = null;
if (isset($_GET["edit"])) {
    $editId = (int)$_GET["edit"];
    $stmt = $conn->prepare("SELECT personnel_id, personnel_name, role, phone, created_at FROM Personnel WHERE personnel_id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editPersonnel = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$pageTitle = 'Personnel Management';
$activeNav = 'personnel';

include BASE_PATH . '/src/includes/header.php';
?>

<!-- Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= h($_SESSION['success_message']) ?><?php unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($_SESSION['error_message']) ?><?php unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-people"></i> Personnel Management</h1>
</div>

<div class="card" style="max-width: 600px;">
    <div class="card-header">
        <h3 class="card-title"><?= $editPersonnel ? 'Edit Personnel' : 'Add New Personnel' ?></h3>
    </div>

    <form method="post" action="personnel.php" data-validate>
        <input type="hidden" name="action" value="<?= $editPersonnel ? "update" : "add" ?>">
        <?php if ($editPersonnel): ?>
            <input type="hidden" name="personnel_id" value="<?= (int)$editPersonnel["personnel_id"] ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="personnel_name">Full Name *</label>
            <input type="text" name="personnel_name" id="personnel_name" class="form-control" required 
                   value="<?= h($editPersonnel["personnel_name"] ?? "") ?>" placeholder="Enter full name">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="role">Role / Position</label>
                <input type="text" name="role" id="role" class="form-control" 
                       value="<?= h($editPersonnel["role"] ?? "")" placeholder="e.g., Manager, Staff, Volunteer">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" name="phone" id="phone" class="form-control" 
                       value="<?= h($editPersonnel["phone"] ?? "")" placeholder="Contact number">
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> <?= $editPersonnel ? 'Save Changes' : 'Add Personnel' ?>
            </button>
            <?php if ($editPersonnel): ?>
                <a href="personnel.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Personnel Table -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Phone</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $personnel->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row["personnel_id"] ?></td>
                    <td><strong><?= h($row["personnel_name"]) ?></strong></td>
                    <td><?= h($row["role"]) ?: '<span class="text-muted">-</span>' ?></td>
                    <td><?= h($row["phone"]) ?: '<span class="text-muted">-</span>' ?></td>
                    <td><?= h($row["created_at"]) ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="personnel.php?edit=<?= (int)$row["personnel_id"] ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="post" action="personnel.php" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="personnel_id" value="<?= (int)$row["personnel_id"] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this person?">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>
