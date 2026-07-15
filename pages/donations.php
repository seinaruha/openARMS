<?php
/**
 * openARMS - Donations Management Page
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getMysqliConnection();

// Fetch reference data
$shelters = $conn->query("SELECT shelter_id, shelter_name FROM Shelters ORDER BY shelter_id DESC");
$suppliers = $conn->query("SELECT supplier_id, supplier_name FROM Suppliers ORDER BY supplier_id DESC");
$items = $conn->query("SELECT item_id, item_name, item_type, unit, shelter_id FROM Items WHERE active = 1 ORDER BY item_id DESC");
$donations = $conn->query("SELECT d.donation_id, d.donor_name, d.description, d.shelter_id, d.supplier_id, d.received_date, d.receipt_notes FROM Donations d ORDER BY d.donation_id DESC");

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $donation_id = (int)($_POST["donation_id"] ?? 0);
    
    // Sanitize inputs
    $donor_name = sanitizeInput($_POST["donor_name"] ?? "");
    $description = sanitizeInput($_POST["description"] ?? "");
    $receipt_notes = sanitizeInput($_POST["receipt_notes"] ?? "");
    
    // Optional foreign keys
    $shelter_id = $_POST["shelter_id"] ?? "";
    $supplier_id = $_POST["supplier_id"] ?? "";
    $shelter_id_param = ($shelter_id === "" ? null : (int)$shelter_id);
    $supplier_id_param = ($supplier_id === "" ? null : (int)$supplier_id);
    
    // Required fields
    $received_date = trim($_POST["received_date"] ?? "");
    if ($received_date === "") die("received_date is required.");
    
    // Donation line items
    $item_id = (int)($_POST["item_id"] ?? 0);
    $item_quantity = trim($_POST["item_quantity"] ?? "");
    if ($item_id <= 0) die("item_id is required.");
    if ($item_quantity === "") $item_quantity = "0";
    
    try {
        if ($action === "add") {
            $stmt = $conn->prepare("
                INSERT INTO Donations (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            
            $stmt->bind_param("ssiiss", 
                ($donor_name === "") ? null : $donor_name,
                ($description === "") ? null : $description,
                $shelter_id_param,
                $supplier_id_param,
                $received_date,
                ($receipt_notes === "") ? null : $receipt_notes
            );
            
            if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
            $donation_id = $conn->insert_id;
            $stmt->close();
            
            // Insert donation line
            $stmt = $conn->prepare("
                INSERT INTO DonationLines (donation_id, item_id, item_quantity, line_notes)
                VALUES (?, ?, ?, NULL)
            ");
            $stmt->bind_param("iid", $donation_id, $item_id, $item_quantity);
            if (!$stmt->execute()) throw new Exception("Failed to add donation line: " . $stmt->error);
            $stmt->close();
            
            $_SESSION['success_message'] = 'Donation added successfully!';
            header("Location: donations.php");
            exit;
            
        } elseif ($action === "update") {
            $stmt = $conn->prepare("
                UPDATE Donations 
                SET donor_name = ?, description = ?, shelter_id = ?, supplier_id = ?, received_date = ?, receipt_notes = ?
                WHERE donation_id = ?
            ");
            $stmt->bind_param("ssiiisi", 
                ($donor_name === "") ? null : $donor_name,
                ($description === "") ? null : $description,
                $shelter_id_param,
                $supplier_id_param,
                $received_date,
                ($receipt_notes === "") ? null : $receipt_notes,
                $donation_id
            );
            
            if (!$stmt->execute()) throw new Exception("Update failed: " . $stmt->error);
            $stmt->close();
            
            // Update donation lines
            $stmt = $conn->prepare("DELETE FROM DonationLines WHERE donation_id = ?");
            $stmt->bind_param("i", $donation_id);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO DonationLines (donation_id, item_id, item_quantity, line_notes) VALUES (?, ?, ?, NULL)");
            $stmt->bind_param("iid", $donation_id, $item_id, $item_quantity);
            if (!$stmt->execute()) throw new Exception("Failed to update donation line: " . $stmt->error);
            $stmt->close();
            
            $_SESSION['success_message'] = 'Donation updated successfully!';
            header("Location: donations.php");
            exit;
            
        } elseif ($action === "delete") {
            $stmt = $conn->prepare("DELETE FROM Donations WHERE donation_id = ?");
            $stmt->bind_param("i", $donation_id);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success_message'] = 'Donation deleted successfully!';
            header("Location: donations.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Edit mode
$editDonation = null;
$editLine = null;
if (isset($_GET["edit"])) {
    $editId = (int)$_GET["edit"];
    
    $stmt = $conn->prepare("SELECT donation_id, donor_name, description, shelter_id, supplier_id, received_date, receipt_notes FROM Donations WHERE donation_id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editDonation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT item_id, item_quantity FROM DonationLines WHERE donation_id = ? LIMIT 1");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editLine = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$pageTitle = 'Donation Management';
$activeNav = 'donations';

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
    <h1 class="page-title"><i class="bi bi-heart"></i> Donation Management</h1>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= $editDonation ? 'Edit Donation' : 'Record New Donation' ?></h3>
    </div>

    <form method="post" action="donations.php" data-validate>
        <input type="hidden" name="action" value="<?= $editDonation ? "update" : "add" ?>">
        <?php if ($editDonation): ?>
            <input type="hidden" name="donation_id" value="<?= (int)$editDonation["donation_id"] ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="donor_name">Donor Name</label>
                <input type="text" name="donor_name" id="donor_name" class="form-control" 
                       value="<?= h($editDonation["donor_name"] ?? "") ?>" placeholder="Optional">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" name="description" id="description" class="form-control" 
                       value="<?= h($editDonation["description"] ?? "")" placeholder="Optional description">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="shelter_id">Shelter</label>
                <select name="shelter_id" id="shelter_id" class="form-control">
                    <option value="">-- None --</option>
                    <?php while ($srow = $shelters->fetch_assoc()): 
                        $sid = (int)$srow["shelter_id"]; ?>
                        <option value="<?= $sid ?>" <?= ((int)($editDonation["shelter_id"] ?? 0) === $sid) ? "selected" : "" ?>>
                            <?= h($srow["shelter_name"]) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="supplier_id">Supplier</label>
                <select name="supplier_id" id="supplier_id" class="form-control">
                    <option value="">-- None --</option>
                    <?php while ($sup = $suppliers->fetch_assoc()): 
                        $spid = (int)$sup["supplier_id"]; ?>
                        <option value="<?= $spid ?>" <?= ((int)($editDonation["supplier_id"] ?? 0) === $spid) ? "selected" : "" ?>>
                            <?= h($sup["supplier_name"]) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="received_date">Received Date *</label>
                <input type="date" name="received_date" id="received_date" class="form-control" required 
                       value="<?= h($editDonation["received_date"] ?? date('Y-m-d')) ?>">
            </div>

            <div class="form-group">
                <label for="receipt_notes">Receipt Notes</label>
                <input type="text" name="receipt_notes" id="receipt_notes" class="form-control" 
                       value="<?= h($editDonation["receipt_notes"] ?? "")" placeholder="Optional notes">
            </div>
        </div>

        <hr style="margin: 20px 0; border-color: var(--gray-200);">
        
        <h4 style="margin-bottom: 15px;">Donation Items</h4>

        <div class="form-row">
            <div class="form-group">
                <label for="item_id">Item *</label>
                <select name="item_id" id="item_id" class="form-control" required>
                    <option value="">-- Select Item --</option>
                    <?php 
                    $selectedItemId = (int)($editLine["item_id"] ?? 0);
                    // Reset pointer
                    $items->data_seek(0);
                    while ($irow = $items->fetch_assoc()):
                        $iid = (int)$irow["item_id"]; ?>
                        <option value="<?= $iid ?>" <?= ($selectedItemId === $iid) ? "selected" : "" ?>>
                            <?= h($irow["item_name"]) ?> (<?= h($irow["unit"]) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="item_quantity">Quantity *</label>
                <input type="number" step="0.001" name="item_quantity" id="item_quantity" class="form-control" required 
                       value="<?= h($editLine["item_quantity"] ?? "0") ?>">
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> <?= $editDonation ? 'Save Changes' : 'Record Donation' ?>
            </button>
            <?php if ($editDonation): ?>
                <a href="donations.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Donor</th>
                <th>Description</th>
                <th>Shelter</th>
                <th>Supplier</th>
                <th>Received</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $donations->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row["donation_id"] ?></td>
                    <td><?= h($row["donor_name"]) ?: '<em class="text-muted">Anonymous</em>' ?></td>
                    <td><?= h($row["description"]) ?: '-' ?></td>
                    <td><?= $row["shelter_id"] !== null ? (int)$row["shelter_id"] : '-' ?></td>
                    <td><?= $row["supplier_id"] !== null ? (int)$row["supplier_id"] : '-' ?></td>
                    <td><?= h($row["received_date"]) ?></td>
                    <td><?= h($row["receipt_notes"]) ?: '-' ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="donations.php?edit=<?= (int)$row["donation_id"] ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="post" action="donations.php" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="donation_id" value="<?= (int)$row["donation_id"] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this donation?">
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
