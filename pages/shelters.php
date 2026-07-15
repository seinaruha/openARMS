<?php
/**
 * openARMS - Shelters Management Page
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getMysqliConnection();

// Fetch reference data
$sheltersResult = $conn->query("SELECT shelter_id, shelter_name FROM Shelters ORDER BY shelter_id DESC");
$itemsResult = $conn->query("SELECT item_id, item_name, item_type, unit FROM Items WHERE active = 1 ORDER BY item_id DESC");

// Inventory shelter filter
$inventoryShelterId = isset($_GET["shelter_id"]) ? (int)$_GET["shelter_id"] : 0;

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    if ($action === "add_shelter" || $action === "update_shelter") {
        $shelter_id = (int)($_POST["shelter_id"] ?? 0);
        $shelter_name = sanitizeInput($_POST["shelter_name"] ?? "");
        $address = sanitizeInput($_POST["address"] ?? "");
        $contact_person = sanitizeInput($_POST["contact_person"] ?? "") ?: null;
        $contact_number = sanitizeInput($_POST["contact_number"] ?? "") ?: null;
        $capacity = trim($_POST["capacity"] ?? "") !== "" ? (int)$_POST["capacity"] : null;
        $shelter_type = sanitizeInput($_POST["shelter_type"] ?? "") ?: null;
        
        try {
            if ($action === "add_shelter") {
                $stmt = $conn->prepare("
                    INSERT INTO Shelters (shelter_name, address, contact_person, contact_number, capacity, shelter_type)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ssssis", $shelter_name, $address, $contact_person, $contact_number, $capacity, $shelter_type);
                
                if (!$stmt->execute()) throw new Exception("Failed to add shelter: " . $stmt->error);
                $stmt->close();
                
                $_SESSION['success_message'] = 'Shelter added successfully!';
            } else {
                $stmt = $conn->prepare("
                    UPDATE Shelters 
                    SET shelter_name = ?, address = ?, contact_person = ?, contact_number = ?, capacity = ?, shelter_type = ?
                    WHERE shelter_id = ?
                ");
                $stmt->bind_param("sssisii", $shelter_name, $address, $contact_person, $contact_number, $capacity, $shelter_type, $shelter_id);
                
                if (!$stmt->execute()) throw new Exception("Failed to update shelter: " . $stmt->error);
                $stmt->close();
                
                $_SESSION['success_message'] = 'Shelter updated successfully!';
            }
            
            header("Location: shelters.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
    }
    
    elseif ($action === "delete_shelter") {
        $shelter_id = (int)($_POST["shelter_id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM Shelters WHERE shelter_id = ?");
        $stmt->bind_param("i", $shelter_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success_message'] = 'Shelter deleted successfully!';
        header("Location: shelters.php");
        exit;
    }
    
    elseif ($action === "update_inventory") {
        $invShelterId = (int)($_POST["inventory_shelter_id"] ?? 0);
        if ($invShelterId > 0) {
            $itemIds = $_POST["item_id"] ?? [];
            $minStocks = $_POST["min_stock"] ?? [];
            
            $stmt = $conn->prepare("
                INSERT INTO ShelterInventory (shelter_id, item_id, min_stock)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE min_stock = VALUES(min_stock)
            ");
            
            foreach ($itemIds as $idx => $itid) {
                $itid = (int)$itid;
                if ($itid <= 0) continue;
                
                $ms = trim((string)($minStocks[$idx] ?? "0"));
                $ms = ($ms === "") ? "0" : $ms;
                
                $stmt->bind_param("iid", $invShelterId, $itid, (float)$ms);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update inventory: " . $stmt->error);
                }
            }
            $stmt->close();
            
            $_SESSION['success_message'] = 'Inventory minimum stock updated!';
            header("Location: shelters.php?shelter_id=" . $invShelterId);
            exit;
        }
    }
}

// Edit mode
$editShelter = null;
if (isset($_GET["edit"])) {
    $editId = (int)$_GET["edit"];
    $stmt = $conn->prepare("SELECT shelter_id, shelter_name, address, contact_person, contact_number, capacity, shelter_type, created_at FROM Shelters WHERE shelter_id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editShelter = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Inventory data
$inventoryMinStock = [];
$inventoryShelterName = "";
if ($inventoryShelterId > 0) {
    $stmt = $conn->prepare("SELECT si.item_id, si.min_stock FROM ShelterInventory si WHERE si.shelter_id = ?");
    $stmt->bind_param("i", $inventoryShelterId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $inventoryMinStock[(int)$r["item_id"]] = (string)$r["min_stock"];
    }
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT shelter_name FROM Shelters WHERE shelter_id = ?");
    $stmt->bind_param("i", $inventoryShelterId);
    $stmt->execute();
    $nameResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $inventoryShelterName = $nameResult ? $nameResult["shelter_name"] : "";
}

$pageTitle = 'Shelter Management';
$activeNav = 'shelters';

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
    <h1 class="page-title"><i class="bi bi-house"></i> Shelter Management</h1>
</div>

<div class="grid-2">
    <!-- Add/Edit Shelter Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= $editShelter ? 'Edit Shelter' : 'Add New Shelter' ?></h3>
        </div>

        <form method="post" action="shelters.php" data-validate>
            <input type="hidden" name="action" value="<?= $editShelter ? "update_shelter" : "add_shelter" ?>">
            <?php if ($editShelter): ?>
                <input type="hidden" name="shelter_id" value="<?= (int)$editShelter["shelter_id"] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="shelter_name">Shelter Name *</label>
                <input type="text" name="shelter_name" id="shelter_name" class="form-control" required 
                       value="<?= h($editShelter["shelter_name"] ?? "") ?>" placeholder="Enter shelter name">
            </div>

            <div class="form-group">
                <label for="address">Address *</label>
                <input type="text" name="address" id="address" class="form-control" required 
                       value="<?= h($editShelter["address"] ?? "")" placeholder="Full address">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="contact_person">Contact Person</label>
                    <input type="text" name="contact_person" id="contact_person" class="form-control" 
                           value="<?= h($editShelter["contact_person"] ?? "")" placeholder="Optional">
                </div>

                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="text" name="contact_number" id="contact_number" class="form-control" 
                           value="<?= h($editShelter["contact_number"] ?? "")" placeholder="Optional">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="capacity">Capacity</label>
                    <input type="number" step="1" name="capacity" id="capacity" class="form-control" 
                           value="<?= h($editShelter["capacity"] ?? "")" placeholder="Max persons">
                </div>

                <div class="form-group">
                    <label for="shelter_type">Shelter Type</label>
                    <input type="text" name="shelter_type" id="shelter_type" class="form-control" 
                           value="<?= h($editShelter["shelter_type"] ?? "")" placeholder="e.g., Evacuation, Temporary">
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?= $editShelter ? 'Save Changes' : 'Add Shelter' ?>
                </button>
                <?php if ($editShelter): ?>
                    <a href="shelters.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Inventory Management -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Inventory Minimum Stock</h3>
        </div>

        <div class="form-group">
            <label>Select Shelter</label>
            <select onchange="window.location='shelters.php?shelter_id='+this.value;" class="form-control">
                <option value="">-- Select Shelter --</option>
                <?php
                $sheltersForSelect = $conn->query("SELECT shelter_id, shelter_name FROM Shelters ORDER BY shelter_id DESC");
                while ($s = $sheltersForSelect->fetch_assoc()):
                    $sid = (int)$s["shelter_id"]; ?>
                    <option value="<?= $sid ?>" <?= ($inventoryShelterId === $sid) ? "selected" : "" ?>>
                        <?= h($s["shelter_name"]) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <p class="text-muted">
            <?= $inventoryShelterId > 0 ? "Editing: " . h($inventoryShelterName) : "Choose a shelter to edit its inventory minimums." ?>
        </p>

        <?php if ($inventoryShelterId > 0): ?>
        <form method="post" action="shelters.php?shelter_id=<?= $inventoryShelterId ?>">
            <input type="hidden" name="action" value="update_inventory">
            <input type="hidden" name="inventory_shelter_id" value="<?= $inventoryShelterId ?>">

            <div class="table-container" style="margin-top: 15px;">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Unit</th>
                            <th style="width:120px;">Min Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $itemsForInv = $conn->query("SELECT item_id, item_name, item_type, unit FROM Items WHERE active = 1 ORDER BY item_id DESC");
                        while ($it = $itemsForInv->fetch_assoc()):
                            $itemId = (int)$it["item_id"];
                            $minVal = $inventoryMinStock[$itemId] ?? "0";
                        ?>
                            <tr>
                                <td><strong><?= h($it["item_name"]) ?></strong></td>
                                <td><?= h($it["item_type"]) ?></td>
                                <td><?= h($it["unit"]) ?></td>
                                <td>
                                    <input type="number" step="0.001" name="min_stock[]" class="form-control" 
                                           value="<?= h($minVal)" required style="padding:6px;">
                                    <input type="hidden" name="item_id[]" value="<?= $itemId ?>">
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:15px;">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save"></i> Save Inventory Settings
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Shelters Table -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Address</th>
                <th>Contact Person</th>
                <th>Contact Number</th>
                <th>Capacity</th>
                <th>Type</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $resShelters = $conn->query("SELECT shelter_id, shelter_name, address, contact_person, contact_number, capacity, shelter_type, created_at FROM Shelters ORDER BY shelter_id DESC");
            while ($r = $resShelters->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$r["shelter_id"] ?></td>
                    <td><strong><?= h($r["shelter_name"]) ?></strong></td>
                    <td><?= h($r["address"]) ?></td>
                    <td><?= h($r["contact_person"]) ?: '-' ?></td>
                    <td><?= h($r["contact_number"]) ?: '-' ?></td>
                    <td><?= h($r["capacity"]) ?: '-' ?></td>
                    <td><?= h($r["shelter_type"]) ?: '-' ?></td>
                    <td><?= h($r["created_at"]) ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="shelters.php?edit=<?= (int)$r["shelter_id"] ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="shelters.php?shelter_id=<?= (int)$r["shelter_id"] ?>" class="btn btn-sm btn-secondary">
                                <i class="bi bi-box-seam"></i>
                            </a>
                            <form method="post" action="shelters.php" style="display:inline;">
                                <input type="hidden" name="action" value="delete_shelter">
                                <input type="hidden" name="shelter_id" value="<?= (int)$r["shelter_id"] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this shelter?">
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
