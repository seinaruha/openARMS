<?php
/**
 * openARMS - Inventory Management Page
 * 
 * Industry-standard structure with proper includes and XAMPP compatibility
 */

// Define base path for includes
define('BASE_PATH', dirname(__DIR__));

// Load configuration and functions
require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database connection
$conn = getMysqliConnection();

// Fetch shelters for dropdown
$shelters = $conn->query("SELECT shelter_id, shelter_name FROM Shelters ORDER BY shelter_id DESC");

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $item_id = (int)($_POST["item_id"] ?? 0);
    
    // Sanitize inputs
    $shelter_id = (int)($_POST["shelter_id"] ?? 0);
    $item_name = sanitizeInput($_POST["item_name"] ?? "");
    $item_type = sanitizeInput($_POST["item_type"] ?? "");
    $unit = sanitizeInput($_POST["unit"] ?? "");
    $active = isset($_POST["active"]) ? 1 : 0;
    
    // Date handling
    $received_date = trim($_POST["received_date"] ?? "");
    $expiry_date = trim($_POST["expiry_date"] ?? "");
    $expiry_date_param = ($expiry_date === "") ? null : $expiry_date;
    
    // Optional fields
    $notes = sanitizeInput($_POST["notes"] ?? "");
    $notes_param = ($notes === "") ? null : $notes;
    
    // Quantity handling
    $initial_qty = trim($_POST["initial_qty"] ?? "");
    if ($initial_qty === "") $initial_qty = "0";
    
    $on_hand_qty = trim($_POST["on_hand_qty"] ?? "");
    if ($on_hand_qty === "") $on_hand_qty = $initial_qty;

    try {
        if ($action === "add") {
            $stmt = $conn->prepare("
                INSERT INTO Items 
                (shelter_id, item_name, item_type, unit, active, received_date, expiry_date, notes, initial_qty, on_hand_qty)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) throw new Exception("Prepare failed (add): " . $conn->error);
            
            $stmt->bind_param("isssisssdd", $shelter_id, $item_name, $item_type, $unit, $active, 
                             $received_date, $expiry_date_param, $notes_param, $initial_qty, $on_hand_qty);
            if (!$stmt->execute()) throw new Exception("Execute failed (add): " . $stmt->error);
            $stmt->close();
            
            $_SESSION['success_message'] = 'Item added successfully!';
            header("Location: inventory.php");
            exit;
            
        } elseif ($action === "update") {
            $stmt = $conn->prepare("
                UPDATE Items 
                SET shelter_id = ?, item_name = ?, item_type = ?, unit = ?, active = ?, 
                    received_date = ?, expiry_date = ?, notes = ?, initial_qty = ?, on_hand_qty = ?
                WHERE item_id = ?
            ");
            if (!$stmt) throw new Exception("Prepare failed (update): " . $conn->error);
            
            $stmt->bind_param("isssisssddi", $shelter_id, $item_name, $item_type, $unit, $active, 
                             $received_date, $expiry_date_param, $notes_param, $initial_qty, $on_hand_qty, $item_id);
            if (!$stmt->execute()) throw new Exception("Execute failed (update): " . $stmt->error);
            $stmt->close();
            
            $_SESSION['success_message'] = 'Item updated successfully!';
            header("Location: inventory.php");
            exit;
            
        } elseif ($action === "delete") {
            $stmt = $conn->prepare("DELETE FROM Items WHERE item_id = ?");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success_message'] = 'Item deleted successfully!';
            header("Location: inventory.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Handle edit mode
$editItem = null;
if (isset($_GET["edit"])) {
    $editId = (int)$_GET["edit"];
    $stmt = $conn->prepare("
        SELECT item_id, shelter_id, item_name, item_type, unit, active, 
               received_date, expiry_date, notes, initial_qty, on_hand_qty, created_at, updated_at
        FROM Items WHERE item_id = ?
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editItem = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch all items for display
$result = $conn->query("
    SELECT i.item_id, i.shelter_id, i.item_name, i.item_type, i.unit, i.active,
           i.received_date, i.expiry_date, i.notes, i.initial_qty, i.on_hand_qty,
           i.created_at, i.updated_at
    FROM Items i
    ORDER BY i.item_id DESC
");

// Page setup variables
$pageTitle = 'Inventory Management';
$activeNav = 'inventory';

// Include header with navigation
include BASE_PATH . '/src/includes/header.php';
?>

<!-- Success/Error Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <?= h($_SESSION['success_message']) ?>
        <?php unset($_SESSION['success_message']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= h($_SESSION['error_message']) ?>
        <?php unset($_SESSION['error_message']); ?>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title"><i class="bi bi-box-seam"></i> Inventory Management</h1>
</div>

<!-- Add/Edit Form Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $editItem ? 'Edit Item' : 'Add New Item' ?></h3>
    </div>

    <form method="post" action="inventory.php" id="inventory-form" data-validate>
        <input type="hidden" name="action" value="<?= $editItem ? "update" : "add" ?>">
        <?php if ($editItem): ?>
            <input type="hidden" name="item_id" value="<?= (int)$editItem["item_id"] ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="shelter_id">Shelter *</label>
                <select name="shelter_id" id="shelter_id" class="form-control" required>
                    <option value="">-- Select Shelter --</option>
                    <?php while ($srow = $shelters->fetch_assoc()): 
                        $sid = (int)$srow["shelter_id"]; ?>
                        <option value="<?= $sid ?>" <?= ((int)($editItem["shelter_id"] ?? 0) === $sid) ? "selected" : "" ?>>
                            <?= h($srow["shelter_name"]) ?> (<?= $sid ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="item_name">Item Name *</label>
                <input type="text" name="item_name" id="item_name" class="form-control" required 
                       value="<?= h($editItem["item_name"] ?? "") ?>" placeholder="Enter item name">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="item_type">Item Type *</label>
                <input type="text" name="item_type" id="item_type" class="form-control" required 
                       value="<?= h($editItem["item_type"] ?? "")" placeholder="e.g., Food, Medicine, Supplies">
            </div>

            <div class="form-group">
                <label for="unit">Unit of Measure *</label>
                <input type="text" name="unit" id="unit" class="form-control" required 
                       value="<?= h($editItem["unit"] ?? "") ?>" placeholder="e.g., pcs, kg, boxes">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="received_date">Received Date *</label>
                <input type="date" name="received_date" id="received_date" class="form-control" required 
                       value="<?= h($editItem["received_date"] ?? date('Y-m-d')) ?>">
            </div>

            <div class="form-group">
                <label for="expiry_date">Expiry Date</label>
                <input type="date" name="expiry_date" id="expiry_date" class="form-control" 
                       value="<?= h($editItem["expiry_date"] ?? "") ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="initial_qty">Initial Quantity *</label>
                <input type="number" step="0.001" name="initial_qty" id="initial_qty" class="form-control" required 
                       value="<?= h($editItem["initial_qty"] ?? "0") ?>" placeholder="0">
            </div>

            <div class="form-group">
                <label for="on_hand_qty">On-hand Quantity *</label>
                <input type="number" step="0.001" name="on_hand_qty" id="on_hand_qty" class="form-control" required 
                       value="<?= h($editItem["on_hand_qty"] ?? ($editItem["initial_qty"] ?? "0")) ?>" placeholder="0">
                <small class="text-muted">Tip: Set equal to initial quantity for new receipts.</small>
            </div>
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <input type="text" name="notes" id="notes" class="form-control" 
                   value="<?= h($editItem["notes"] ?? "")" placeholder="Optional notes">
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="active" value="1" 
                       <?= (int)($editItem["active"] ?? 1) === 1 ? "checked" : "" ?>>
                Active Item
            </label>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> <?= $editItem ? 'Save Changes' : 'Add Item' ?>
            </button>
            <?php if ($editItem): ?>
                <a href="inventory.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Inventory Table -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Shelter</th>
                <th>Name</th>
                <th>Type</th>
                <th>Unit</th>
                <th>Status</th>
                <th>Received</th>
                <th>Expiry</th>
                <th>Notes</th>
                <th>Initial Qty</th>
                <th>On-hand</th>
                <th>Created</th>
                <th>Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row["item_id"] ?></td>
                    <td><?= (int)$row["shelter_id"] ?></td>
                    <td><strong><?= h($row["item_name"]) ?></strong></td>
                    <td><?= h($row["item_type"]) ?></td>
                    <td><?= h($row["unit"]) ?></td>
                    <td>
                        <span class="badge <?= ((int)$row["active"] === 1) ? 'badge-success' : 'badge-secondary' ?>">
                            <?= ((int)$row["active"] === 1) ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td><?= h($row["received_date"]) ?></td>
                    <td><?= h($row["expiry_date"]) ?: '-' ?></td>
                    <td><?= h($row["notes"]) ?: '-' ?></td>
                    <td><?= h($row["initial_qty"]) ?></td>
                    <td><strong><?= h($row["on_hand_qty"]) ?></strong></td>
                    <td><?= h($row["created_at"]) ?></td>
                    <td><?= h($row["updated_at"]) ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="inventory.php?edit=<?= (int)$row["item_id"] ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="post" action="inventory.php" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="item_id" value="<?= (int)$row["item_id"] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this item?">
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

<?php 
// Include footer
include BASE_PATH . '/src/includes/footer.php';
?>
