<?php
/**
 * openARMS - Dashboard Page
 * 
 * Main dashboard after login
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication (optional for now - can be enforced later)
// requireLogin();

$conn = getMysqliConnection();

// Fetch dashboard statistics
try {
    $totalItems = $conn->query("SELECT COUNT(*) as count FROM Items WHERE active = 1")->fetch_assoc()['count'];
    $totalShelters = $conn->query("SELECT COUNT(*) as count FROM Shelters")->fetch_assoc()['count'];
    $totalPersonnel = $conn->query("SELECT COUNT(*) as count FROM Personnel")->fetch_assoc()['count'];
    $totalDonations = $conn->query("SELECT COUNT(*) as count FROM Donations")->fetch_assoc()['count'];
    
    // Recent movements (last 10)
    $recentMovements = $conn->query("
        SELECT il.transaction_id, il.transaction_date, il.transaction_type, il.quantity,
               it.item_name, s.shelter_name, p.personnel_name
        FROM InventoryLogs il
        JOIN Items it ON it.item_id = il.item_id
        JOIN Shelters s ON s.shelter_id = il.shelter_id
        JOIN Personnel p ON p.personnel_id = il.personnel_id
        ORDER BY il.transaction_id DESC
        LIMIT 10
    ");
    
    // Low stock items (below min_stock)
    $lowStockItems = $conn->query("
        SELECT i.item_id, i.item_name, i.on_hand_qty, si.min_stock, s.shelter_name
        FROM Items i
        JOIN ShelterInventory si ON si.item_id = i.item_id
        JOIN Shelters s ON s.shelter_id = si.shelter_id
        WHERE i.active = 1 AND i.on_hand_qty <= si.min_stock
        ORDER BY i.on_hand_qty ASC
        LIMIT 5
    ");
    
} catch (Exception $e) {
    // Handle case where tables don't exist yet
    $totalItems = 0;
    $totalShelters = 0;
    $totalPersonnel = 0;
    $totalDonations = 0;
    $recentMovements = null;
    $lowStockItems = null;
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

include BASE_PATH . '/src/includes/header.php';
?>

<!-- Welcome Message -->
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-speedometer2"></i> Dashboard</h1>
        <p class="text-muted">Welcome to openARMS Shelter Resource Management System</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid-2" style="grid-template-columns: repeat(4, 1fr); margin-bottom: var(--spacing-lg);">
    <div class="card" style="text-align:center; padding:24px;">
        <div style="font-size:36px; color:var(--primary); font-weight:700;"><?= $totalItems ?></div>
        <div style="color:var(--gray-600); font-size:var(--font-size-sm); margin-top:4px;">Active Items</div>
    </div>
    <div class="card" style="text-align:center; padding:24px;">
        <div style="font-size:36px; color:var(--info); font-weight:700;"><?= $totalShelters ?></div>
        <div style="color:var(--gray-600); font-size:var(--font-size-sm); margin-top:4px;">Shelters</div>
    </div>
    <div class="card" style="text-align:center; padding:24px;">
        <div style="font-size:36px; color:var(--warning); font-weight:700;"><?= $totalPersonnel ?></div>
        <div style="color:var(--gray-600); font-size:var(--font-size-sm); margin-top:4px;">Personnel</div>
    </div>
    <div class="card" style="text-align:center; padding:24px;">
        <div style="font-size:36px; color:var(--accent); font-weight:700;"><?= $totalDonations ?></div>
        <div style="color:var(--gray-600); font-size:var(--font-size-sm); margin-top:4px;">Donations</div>
    </div>
</div>

<div class="grid-2">
    <!-- Recent Movements -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-clock-history"></i> Recent Movements</h3>
        </div>
        
        <?php if ($recentMovements && $recentMovements->num_rows > 0): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($m = $recentMovements->fetch_assoc()): ?>
                        <tr>
                            <td><?= h($m["transaction_date"]) ?></td>
                            <td><span class="badge badge-<?= $m["transaction_type"] === 'IN' ? 'success' : ($m["transaction_type"] === 'OUT' ? 'danger' : 'warning') ?>"><?= h($m["transaction_type"]) ?></span></td>
                            <td><?= h($m["item_name"]) ?></td>
                            <td><?= h($m["quantity"]) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-muted text-center" style="padding:20px;">No recent movements recorded.</p>
        <?php endif; ?>
        
        <div style="margin-top:16px;">
            <a href="inventory_movements.php" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-right"></i> View All Movements
            </a>
        </div>
    </div>

    <!-- Low Stock Alerts -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-exclamation-triangle"></i> Low Stock Alerts</h3>
        </div>
        
        <?php if ($lowStockItems && $lowStockItems->num_rows > 0): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Shelter</th>
                        <th>On Hand</th>
                        <th>Min Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $lowStockItems->fetch_assoc()): ?>
                        <tr style="background-color: var(--warning-light);">
                            <td><strong><?= h($item["item_name"]) ?></strong></td>
                            <td><?= h($item["shelter_name"]) ?></td>
                            <td style="color:var(--danger); font-weight:600;"><?= h($item["on_hand_qty"]) ?></td>
                            <td><?= h($item["min_stock"]) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-muted text-center" style="padding:20px;">All items are adequately stocked.</p>
        <?php endif; ?>
        
        <div style="margin-top:16px;">
            <a href="inventory.php" class="btn btn-secondary btn-sm">
                <i class="bi bi-box-seam"></i> Manage Inventory
            </a>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card" style="margin-top: var(--spacing-lg);">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-lightning"></i> Quick Actions</h3>
    </div>
    
    <div class="btn-group" style="flex-wrap:wrap;">
        <a href="inventory.php?action=add" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add Item
        </a>
        <a href="donations.php" class="btn btn-success">
            <i class="bi bi-heart"></i> Record Donation
        </a>
        <a href="inventory_movements.php" class="btn btn-info">
            <i class="bi bi-arrow-left-right"></i> Record Movement
        </a>
        <a href="shelters.php" class="btn btn-secondary">
            <i class="bi bi-house"></i> Manage Shelters
        </a>
    </div>
</div>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>
