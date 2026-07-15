<?php
/**
 * openARMS Header Component
 * 
 * Standard HTML header with navigation for all pages
 * 
 * @param string $pageTitle Page title
 * @param array $activeNav Active navigation item
 */

if (!isset($pageTitle)) {
    $pageTitle = 'openARMS';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="openARMS - Shelter Resource Management System">
    <title><?= h($pageTitle) ?> | openARMS</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= ASSETS_URL ?>/images/openARMS.png">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    
    <?php if (isset($extraStyles)): ?>
        <?= $extraStyles ?>
    <?php endif; ?>
</head>
<body>
    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="<?= APP_URL ?>" class="nav-brand">
                <img src="<?= ASSETS_URL ?>/images/openARMS.png" alt="openARMS" class="nav-logo">
                <span>openARMS</span>
            </a>
            
            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            
            <ul class="nav-menu" id="navMenu">
                <li><a href="<?= APP_URL ?>/pages/dashboard.php" class="<?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a></li>
                <li><a href="<?= APP_URL ?>/pages/inventory.php" class="<?= ($activeNav ?? '') === 'inventory' ? 'active' : '' ?>">
                    <i class="bi bi-box-seam"></i> Inventory
                </a></li>
                <li><a href="<?= APP_URL ?>/pages/donations.php" class="<?= ($activeNav ?? '') === 'donations' ? 'active' : '' ?>">
                    <i class="bi bi-heart"></i> Donations
                </a></li>
                <li><a href="<?= APP_URL ?>/pages/shelters.php" class="<?= ($activeNav ?? '') === 'shelters' ? 'active' : '' ?>">
                    <i class="bi bi-house"></i> Shelters
                </a></li>
                <li><a href="<?= APP_URL ?>/pages/personnel.php" class="<?= ($activeNav ?? '') === 'personnel' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i> Personnel
                </a></li>
                <li><a href="<?= APP_URL ?>/pages/suppliers.php" class="<?= ($activeNav ?? '') === 'suppliers' ? 'active' : '' ?>">
                    <i class="bi bi-truck"></i> Suppliers
                </a></li>
                <li><a href="<?= APP_URL ?>/pages/inventory_movements.php" class="<?= ($activeNav ?? '') === 'movements' ? 'active' : '' ?>">
                    <i class="bi bi-arrow-left-right"></i> Movements
                </a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="container">
