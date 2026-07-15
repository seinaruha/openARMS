<?php
/**
 * openARMS - Inventory Movements Page
 * 
 * Handles IN, OUT, ADJUST, and TRANSFER transactions
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getMysqliConnection();

// Helper function to fetch items by shelter
function fetchItemsByShelter($conn, $shelter_id) {
    $stmt = $conn->prepare("SELECT item_id, item_name, unit FROM Items WHERE shelter_id = ? AND active = 1 ORDER BY item_id DESC");
    $stmt->bind_param("i", $shelter_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

// Fetch reference data
$shelters = $conn->query("SELECT shelter_id, shelter_name FROM Shelters ORDER BY shelter_id DESC");
$personnel = $conn->query("SELECT personnel_id, personnel_name FROM Personnel ORDER BY personnel_id DESC");

// Edit mode
$editMovement = null;
$movement_to_edit = isset($_GET["edit"]) ? (int)$_GET["edit"] : 0;
if ($movement_to_edit > 0) {
    $stmt = $conn->prepare("
        SELECT transaction_id, transaction_date, item_id, shelter_id, quantity, 
               transaction_type, personnel_id, transaction_notes, created_at
        FROM InventoryLogs WHERE transaction_id = ?
    ");
    $stmt->bind_param("i", $movement_to_edit);
    $stmt->execute();
    $editMovement = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    // Common fields
    $transaction_type = $_POST["transaction_type"] ?? "";
    $transaction_date = trim($_POST["transaction_date"] ?? "");
    $quantity = toFloat($_POST["quantity"] ?? "0");
    $personnel_id = (int)($_POST["personnel_id"] ?? 0);
    $transaction_notes = trim($_POST["transaction_notes"] ?? "") ?: null;
    
    // Validation
    if ($transaction_date === "") die("Transaction date is required.");
    if ($quantity <= 0) die("Quantity must be greater than 0.");
    if ($personnel_id <= 0) die("Personnel is required.");
    
    $conn->begin_transaction();
    
    try {
        // Helper to update item on-hand quantity
        $updateItemOnHand = function($item_id, $delta) use ($conn) {
            $stmt = $conn->prepare("SELECT on_hand_qty FROM Items WHERE item_id = ? FOR UPDATE");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$res) throw new Exception("Item not found (ID: $item_id).");
            
            $current = toFloat($res["on_hand_qty"]);
            $newQty = $current + $delta;
            
            $stmt2 = $conn->prepare("UPDATE Items SET on_hand_qty = ? WHERE item_id = ?");
            $stmt2->bind_param("di", $newQty, $item_id);
            $stmt2->execute();
            
            if ($stmt2->affected_rows !== 1) {
                $stmt2->close();
                throw new Exception("Failed to update quantity for item $item_id.");
            }
            $stmt2->close();
            
            return $newQty;
        };
        
        // Helper to insert log entry
        $insertLog = function($tDate, $itemId, $shelterId, $qty, $tType, $pId, $notes) use ($conn) {
            $stmt = $conn->prepare("
                INSERT INTO InventoryLogs (transaction_date, item_id, shelter_id, quantity, transaction_type, personnel_id, transaction_notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("siidsss", $tDate, $itemId, $shelterId, $qty, $tType, $pId, $notes);
            $stmt->execute();
            $stmt->close();
        };
        
        if ($action !== "add") {
            throw new Exception("Only add is supported for inventory movements.");
        }
        
        switch ($transaction_type) {
            case "IN":
                $item_id = (int)($_POST["item_id"] ?? 0);
                $shelter_id = (int)($_POST["shelter_id"] ?? 0);
                if ($item_id <= 0 || $shelter_id <= 0) throw new Exception("Select item and shelter.");
                
                $updateItemOnHand($item_id, +$quantity);
                $insertLog($transaction_date, $item_id, $shelter_id, $quantity, "IN", $personnel_id, $transaction_notes);
                break;
                
            case "OUT":
                $item_id = (int)($_POST["item_id"] ?? 0);
                $shelter_id = (int)($_POST["shelter_id"] ?? 0);
                if ($item_id <= 0 || $shelter_id <= 0) throw new Exception("Select item and shelter.");
                
                $stmt = $conn->prepare("SELECT on_hand_qty FROM Items WHERE item_id = ? FOR UPDATE");
                $stmt->bind_param("i", $item_id);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if (!$res) throw new Exception("Item not found.");
                
                $current = toFloat($res["on_hand_qty"]);
                if ($current - $quantity < 0) throw new Exception("Insufficient stock for OUT transaction.");
                
                $updateItemOnHand($item_id, -$quantity);
                $insertLog($transaction_date, $item_id, $shelter_id, $quantity, "OUT", $personnel_id, $transaction_notes);
                break;
                
            case "ADJUST":
                $item_id = (int)($_POST["item_id"] ?? 0);
                $shelter_id = (int)($_POST["shelter_id"] ?? 0);
                $adjust_mode = $_POST["adjust_mode"] ?? "INCREASE";
                if ($item_id <= 0 || $shelter_id <= 0) throw new Exception("Select item and shelter.");
                
                if ($adjust_mode === "DECREASE") {
                    $stmt = $conn->prepare("SELECT on_hand_qty FROM Items WHERE item_id = ? FOR UPDATE");
                    $stmt->bind_param("i", $item_id);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$res) throw new Exception("Item not found.");
                    $current = toFloat($res["on_hand_qty"]);
                    if ($current - $quantity < 0) throw new Exception("Insufficient stock for DECREASE adjustment.");
                    $updateItemOnHand($item_id, -$quantity);
                } else {
                    $updateItemOnHand($item_id, +$quantity);
                }
                
                $insertLog($transaction_date, $item_id, $shelter_id, $quantity, "ADJUST", $personnel_id, $transaction_notes);
                break;
                
            case "TRANSFER":
                $from_item_id = (int)($_POST["from_item_id"] ?? 0);
                $from_shelter_id = (int)($_POST["from_shelter_id"] ?? 0);
                $to_item_id = (int)($_POST["to_item_id"] ?? 0);
                $to_shelter_id = (int)($_POST["to_shelter_id"] ?? 0);
                
                if ($from_item_id <= 0 || $from_shelter_id <= 0 || $to_item_id <= 0 || $to_shelter_id <= 0) {
                    throw new Exception("Select all FROM and TO items/shelters.");
                }
                
                // Check source stock
                $stmt = $conn->prepare("SELECT on_hand_qty FROM Items WHERE item_id = ? FOR UPDATE");
                $stmt->bind_param("i", $from_item_id);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if (!$res) throw new Exception("Source item not found.");
                $current = toFloat($res["on_hand_qty"]);
                if ($current - $quantity < 0) throw new Exception("Insufficient stock for TRANSFER.");
                
                // Execute transfer
                $updateItemOnHand($from_item_id, -$quantity);
                $insertLog($transaction_date, $from_item_id, $from_shelter_id, $quantity, "TRANSFER", $personnel_id, $transaction_notes);
                
                $updateItemOnHand($to_item_id, +$quantity);
                $insertLog($transaction_date, $to_item_id, $to_shelter_id, $quantity, "TRANSFER", $personnel_id, $transaction_notes);
                break;
                
            default:
                throw new Exception("Invalid transaction type.");
        }
        
        $conn->commit();
        $_SESSION['success_message'] = 'Inventory movement recorded successfully!';
        header("Location: inventory_movements.php");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Prepare form data
$itemsForForm = [];
$itemsFromForm = [];
$itemsToForm = [];

$formTransactionType = $_POST["transaction_type"] ?? ($editMovement["transaction_type"] ?? "IN");

if ($_POST["shelter_id"] ?? null) {
    $itemsForForm = fetchItemsByShelter($conn, (int)$_POST["shelter_id"]);
}
if ($_POST["from_shelter_id"] ?? null) {
    $itemsFromForm = fetchItemsByShelter($conn, (int)$_POST["from_shelter_id"]);
}
if ($_POST["to_shelter_id"] ?? null) {
    $itemsToForm = fetchItemsByShelter($conn, (int)$_POST["to_shelter_id"]);
}

// Fetch movements log
$movementResult = $conn->query("
    SELECT il.transaction_id, il.transaction_date, il.transaction_type, il.quantity,
           il.item_id, il.shelter_id, il.personnel_id, il.transaction_notes, il.created_at,
           s.shelter_name, it.item_name, p.personnel_name
    FROM InventoryLogs il
    JOIN Shelters s ON s.shelter_id = il.shelter_id
    JOIN Items it ON it.item_id = il.item_id
    JOIN Personnel p ON p.personnel_id = il.personnel_id
    ORDER BY il.transaction_id DESC
");

$pageTitle = 'Inventory Movements';
$activeNav = 'movements';

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
    <h1 class="page-title"><i class="bi bi-arrow-left-right"></i> Inventory Movements</h1>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Record New Movement</h3>
    </div>

    <form method="post" action="inventory_movements.php" id="movement-form" data-validate>
        <input type="hidden" name="action" value="add">

        <div class="form-row">
            <div class="form-group">
                <label for="transaction_type">Transaction Type *</label>
                <select name="transaction_type" id="transaction_type" class="form-control" required 
                        onchange="toggleTransferFields()">
                    <?php
                    $types = ["IN", "OUT", "ADJUST", "TRANSFER"];
                    foreach ($types as $t): ?>
                        <option value="<?= $t ?>" <?= ($formTransactionType === $t) ? "selected" : "" ?>>
                            <?= $t ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="transaction_date">Transaction Date *</label>
                <input type="date" name="transaction_date" id="transaction_date" class="form-control" required 
                       value="<?= h($_POST["transaction_date"] ?? date("Y-m-d")) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="quantity">Quantity *</label>
                <input type="number" step="0.001" name="quantity" id="quantity" class="form-control" required 
                       value="<?= h($_POST["quantity"] ?? "0") ?>" min="0.001">
            </div>

            <div class="form-group">
                <label for="personnel_id">Personnel *</label>
                <select name="personnel_id" id="personnel_id" class="form-control" required>
                    <option value="">-- Select Personnel --</option>
                    <?php while ($prow = $personnel->fetch_assoc()): 
                        $pid = (int)$prow["personnel_id"]; ?>
                        <option value="<?= $pid ?>" <?= ((int)($_POST["personnel_id"] ?? 0) === $pid) ? "selected" : "" ?>>
                            <?= h($prow["personnel_name"]) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="transaction_notes">Notes</label>
            <input type="text" name="transaction_notes" id="transaction_notes" class="form-control" 
                   value="<?= h($_POST["transaction_notes"] ?? "")" placeholder="Optional notes">
        </div>

        <!-- IN/OUT/ADJUST Fields -->
        <div id="standard-fields">
            <div class="form-row">
                <div class="form-group">
                    <label for="shelter_id">Shelter *</label>
                    <select name="shelter_id" id="shelter_id" class="form-control" required>
                        <option value="">-- Select Shelter --</option>
                        <?php
                        $selShelter = (int)($_POST["shelter_id"] ?? 0);
                        $shelters->data_seek(0);
                        while ($srow = $shelters->fetch_assoc()):
                            $sid = (int)$srow["shelter_id"]; ?>
                            <option value="<?= $sid ?>" <?= ($selShelter === $sid) ? "selected" : "" ?>>
                                <?= h($srow["shelter_name"]) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="item_id">Item *</label>
                    <select name="item_id" id="item_id" class="form-control" required>
                        <option value="">-- Select Item --</option>
                        <?php foreach ($itemsForForm as $itrow):
                            $iid = (int)$itrow["item_id"]; ?>
                            <option value="<?= $iid ?>" <?= ((int)($_POST["item_id"] ?? 0) === $iid) ? "selected" : "" ?>>
                                <?= h($itrow["item_name"]) ?> (<?= h($itrow["unit"]) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Adjust Mode (only for ADJUST) -->
            <div id="adjust-mode-field" style="display:none;" class="form-group">
                <label for="adjust_mode">Adjustment Mode</label>
                <select name="adjust_mode" id="adjust_mode" class="form-control">
                    <option value="INCREASE" <?= ($_POST["adjust_mode"] ?? "INCREASE") === "INCREASE" ? "selected" : "" ?>>Increase Stock</option>
                    <option value="DECREASE" <?= ($_POST["adjust_mode"] ?? "") === "DECREASE" ? "selected" : "" ?>>Decrease Stock</option>
                </select>
            </div>
        </div>

        <!-- TRANSFER Fields -->
        <div id="transfer-fields" style="display:none;">
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <div style="flex:1; min-width:300px;">
                    <h4 style="margin-bottom:10px;">FROM</h4>
                    <div class="form-group">
                        <label>Source Shelter *</label>
                        <select name="from_shelter_id" class="form-control" required>
                            <option value="">-- Select Shelter --</option>
                            <?php
                            $selFS = (int)($_POST["from_shelter_id"] ?? 0);
                            $shelters->data_seek(0);
                            while ($srow = $shelters->fetch_assoc()):
                                $sid = (int)$srow["shelter_id"]; ?>
                                <option value="<?= $sid ?>" <?= ($selFS === $sid) ? "selected" : "" ?>>
                                    <?= h($srow["shelter_name"]) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Source Item *</label>
                        <select name="from_item_id" class="form-control" required>
                            <option value="">-- Select Item --</option>
                            <?php foreach ($itemsFromForm as $itrow):
                                $iid = (int)$itrow["item_id"]; ?>
                                <option value="<?= $iid ?>" <?= ((int)($_POST["from_item_id"] ?? 0) === $iid) ? "selected" : "" ?>>
                                    <?= h($itrow["item_name"]) ?> (<?= h($itrow["unit"]) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="flex:1; min-width:300px;">
                    <h4 style="margin-bottom:10px;">TO</h4>
                    <div class="form-group">
                        <label>Destination Shelter *</label>
                        <select name="to_shelter_id" class="form-control" required>
                            <option value="">-- Select Shelter --</option>
                            <?php
                            $selTS = (int)($_POST["to_shelter_id"] ?? 0);
                            $shelters->data_seek(0);
                            while ($srow = $shelters->fetch_assoc()):
                                $sid = (int)$srow["shelter_id"]; ?>
                                <option value="<?= $sid ?>" <?= ($selTS === $sid) ? "selected" : "" ?>>
                                    <?= h($srow["shelter_name"]) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Destination Item *</label>
                        <select name="to_item_id" class="form-control" required>
                            <option value="">-- Select Item --</option>
                            <?php foreach ($itemsToForm as $itrow):
                                $iid = (int)$itrow["item_id"]; ?>
                                <option value="<?= $iid ?>" <?= ((int)($_POST["to_item_id"] ?? 0) === $iid) ? "selected" : "" ?>>
                                    <?= h($itrow["item_name"]) ?> (<?= h($itrow["unit"]) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <p class="text-muted mt-2">TRANSFER creates two log entries (OUT from source, IN to destination).</p>
        </div>

        <div class="btn-group" style="margin-top:20px;">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Record Movement
            </button>
            <a href="inventory.php" class="btn btn-secondary">
                <i class="bi bi-box-seam"></i> Back to Inventory
            </a>
        </div>
    </form>
</div>

<!-- Movements Log Table -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Item</th>
                <th>Shelter</th>
                <th>Personnel</th>
                <th>Notes</th>
                <th>Recorded</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($m = $movementResult->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$m["transaction_id"] ?></td>
                    <td><?= h($m["transaction_date"]) ?></td>
                    <td>
                        <span class="badge badge-<?= 
                            $m["transaction_type"] === 'IN' ? 'success' : 
                            ($m["transaction_type"] === 'OUT' ? 'danger' : 
                            ($m["transaction_type"] === 'TRANSFER' ? 'info' : 'warning')) ?>">
                            <?= h($m["transaction_type"]) ?>
                        </span>
                    </td>
                    <td><strong><?= h($m["quantity"]) ?></strong></td>
                    <td><?= h($m["item_name"]) ?></td>
                    <td><?= h($m["shelter_name"]) ?></td>
                    <td><?= h($m["personnel_name"]) ?></td>
                    <td><?= h($m["transaction_notes"]) ?: '-' ?></td>
                    <td><?= h($m["created_at"]) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
function toggleTransferFields() {
    const type = document.getElementById('transaction_type').value;
    const standardFields = document.getElementById('standard-fields');
    const transferFields = document.getElementById('transfer-fields');
    const adjustField = document.getElementById('adjust-mode-field');
    
    if (type === 'TRANSFER') {
        standardFields.style.display = 'none';
        transferFields.style.display = 'block';
    } else {
        standardFields.style.display = 'block';
        transferFields.style.display = 'none';
        
        // Show/hide adjust mode based on ADJUST selection
        adjustField.style.display = (type === 'ADJUST') ? 'block' : 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', toggleTransferFields);
</script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>
