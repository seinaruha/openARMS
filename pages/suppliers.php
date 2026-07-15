<?php
/**
 * openARMS - Suppliers Management Page
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getMysqliConnection();

// Fetch all suppliers
$suppliers = $conn->query("SELECT supplier_id, supplier_name, contact, email, address, supplier_type, created_at FROM Suppliers ORDER BY supplier_id DESC");

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $supplier_id = (int)($_POST["supplier_id"] ?? 0);
    
    // Sanitize inputs
    $supplier_name = sanitizeInput($_POST["supplier_name"] ?? "");
    $contact = sanitizeInput($_POST["contact"] ?? "") ?: null;
    $email = sanitizeInput($_POST["email"] ?? "") ?: null;
    $address = sanitizeInput($_POST["address"] ?? "") ?: null;
    $supplier_type = trim($_POST["supplier_type"] ?? "");
    if ($supplier_type === "") $supplier_type = "General";
    
    try {
        if ($action === "add") {
            $stmt = $conn->prepare("
                INSERT INTO Suppliers (supplier_name, contact, email, address, supplier_type)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssss", $supplier_name, $contact, $email, $address, $supplier_type);
            
            if (!$stmt->execute()) throw new Exception("Failed to add supplier: " . $stmt->error);
            $stmt->close();
            
            $_SESSION['success_message'] = 'Supplier added successfully!';
            header("Location: suppliers.php");
            exit;
            
        } elseif ($action === "update") {
            $stmt = $conn->prepare("
                UPDATE Suppliers 
                SET supplier_name = ?, contact = ?, email = ?, address = ?, supplier_type = ?
                WHERE supplier_id = ?
            ");
            $stmt->bind_param("sssssi", $supplier_name, $contact, $email, $address, $supplier_type, $supplier_id);
            
            if (!$stmt->execute()) throw new Exception("Failed to update supplier: " . $stmt->error);
            $stmt->close();
            
            $_SESSION['success_message'] = 'Supplier updated successfully!';
            header("Location: suppliers.php");
            exit;
            
        } elseif ($action === "delete") {
            $stmt = $conn->prepare("DELETE FROM Suppliers WHERE supplier_id = ?");
            $stmt->bind_param("i", $supplier_id);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success_message'] = 'Supplier deleted successfully!';
            header("Location: suppliers.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Edit mode
$editSupplier = null;
if (isset($_GET["edit"])) {
    $editId = (int)$_GET["edit"];
    $stmt = $conn->prepare("SELECT supplier_id, supplier_name, contact, email, address, supplier_type, created_at FROM Suppliers WHERE supplier_id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editSupplier = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$pageTitle = 'Supplier Management';
$activeNav = 'suppliers';

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
    <h1 class="page-title"><i class="bi bi-truck"></i> Supplier Management</h1>
</div>

<div class="card" style="max-width: 700px;">
    <div class="card-header">
        <h3 class="card-title"><?= $editSupplier ? 'Edit Supplier' : 'Add New Supplier' ?></h3>
    </div>

    <form method="post" action="suppliers.php" data-validate>
        <input type="hidden" name="action" value="<?= $editSupplier ? "update" : "add" ?>">
        <?php if ($editSupplier): ?>
            <input type="hidden" name="supplier_id" value="<?= (int)$editSupplier["supplier_id"] ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="supplier_name">Company/Organization Name *</label>
            <input type="text" name="supplier_name" id="supplier_name" class="form-control" required 
                   value="<?= h($editSupplier["supplier_name"] ?? "") ?>" placeholder="Enter supplier name">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="contact">Contact Person</label>
                <input type="text" name="contact" id="contact" class="form-control" 
                       value="<?= h($editSupplier["contact"] ?? "")" placeholder="Contact name">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" 
                       value="<?= h($editSupplier["email"] ?? "")" placeholder="email@example.com">
            </div>
        </div>

        <div class="form-group">
            <label for="address">Address</label>
            <textarea name="address" id="address" class="form-control" rows="3"
                      placeholder="Full address"><?= h($editSupplier["address"] ?? "") ?></textarea>
        </div>

        <div class="form-group">
            <label for="supplier_type">Supplier Type *</label>
            <input type="text" name="supplier_type" id="supplier_type" class="form-control" required 
                   value="<?= h($editSupplier["supplier_type"] ?? "General") ?>" placeholder="e.g., Food, Medical, General">
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> <?= $editSupplier ? 'Save Changes' : 'Add Supplier' ?>
            </button>
            <?php if ($editSupplier): ?>
                <a href="suppliers.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Suppliers Table -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Address</th>
                <th>Type</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $suppliers->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row["supplier_id"] ?></td>
                    <td><strong><?= h($row["supplier_name"]) ?></strong></td>
                    <td><?= h($row["contact"]) ?: '<span class="text-muted">-</span>' ?></td>
                    <td><?= h($row["email"]) ?: '<span class="text-muted">-</span>' ?></td>
                    <td><?= h($row["address"]) ?: '<span class="text-muted">-</span>' ?></td>
                    <td><span class="badge badge-info"><?= h($row["supplier_type"]) ?></span></td>
                    <td><?= h($row["created_at"]) ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="suppliers.php?edit=<?= (int)$row["supplier_id"] ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="post" action="suppliers.php" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="supplier_id" value="<?= (int)$row["supplier_id"] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this supplier?">
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
