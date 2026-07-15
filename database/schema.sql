-- ============================================
-- openARMS Database Schema
-- 
-- Run this SQL in phpMyAdmin or MySQL command line
-- to create the required database and tables
-- Compatible with XAMPP MySQL (MariaDB)
-- ============================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS openARMS_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE openARMS_db;

-- ============================================
-- Table: Shelters
-- ============================================
CREATE TABLE IF NOT EXISTS Shelters (
    shelter_id INT AUTO_INCREMENT PRIMARY KEY,
    shelter_name VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    contact_person VARCHAR(255) DEFAULT NULL,
    contact_number VARCHAR(50) DEFAULT NULL,
    capacity INT DEFAULT NULL,
    shelter_type VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: Items (Inventory)
-- ============================================
CREATE TABLE IF NOT EXISTS Items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    shelter_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    item_type VARCHAR(100) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    active TINYINT(1) DEFAULT 1,
    received_date DATE NOT NULL,
    expiry_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    initial_qty DECIMAL(15,3) DEFAULT 0.000,
    on_hand_qty DECIMAL(15,3) DEFAULT 0.000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shelter_id) REFERENCES Shelters(shelter_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: Suppliers
-- ============================================
CREATE TABLE IF NOT EXISTS Suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(255) NOT NULL,
    contact VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    supplier_type VARCHAR(100) DEFAULT 'General',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: Personnel
-- ============================================
CREATE TABLE IF NOT EXISTS Personnel (
    personnel_id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_name VARCHAR(255) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,  -- TODO: Upgrade to password_hash()
    role VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    shelter_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shelter_id) REFERENCES Shelters(shelter_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: Donations
-- ============================================
CREATE TABLE IF NOT EXISTS Donations (
    donation_id INT AUTO_INCREMENT PRIMARY KEY,
    donor_name VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    shelter_id INT DEFAULT NULL,
    supplier_id INT DEFAULT NULL,
    received_date DATE NOT NULL,
    receipt_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shelter_id) REFERENCES Shelters(shelter_id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES Suppliers(supplier_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: DonationLines (Line items for donations)
-- ============================================
CREATE TABLE IF NOT EXISTS DonationLines (
    line_id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    item_id INT NOT NULL,
    item_quantity DECIMAL(15,3) DEFAULT 0.000,
    line_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_id) REFERENCES Donations(donation_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES Items(item_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: InventoryLogs (Movement transactions)
-- ============================================
CREATE TABLE IF NOT EXISTS InventoryLogs (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_date DATE NOT NULL,
    item_id INT NOT NULL,
    shelter_id INT NOT NULL,
    quantity DECIMAL(15,3) NOT NULL,
    transaction_type ENUM('IN', 'OUT', 'ADJUST', 'TRANSFER') NOT NULL,
    personnel_id INT NOT NULL,
    transaction_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES Items(item_id) ON DELETE RESTRICT,
    FOREIGN KEY (shelter_id) REFERENCES Shelters(shelter_id) ON DELETE RESTRICT,
    FOREIGN KEY (personnel_id) REFERENCES Personnel(personnel_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: ShelterInventory (Min stock settings)
-- ============================================
CREATE TABLE IF NOT EXISTS ShelterInventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shelter_id INT NOT NULL,
    item_id INT NOT NULL,
    min_stock DECIMAL(15,3) DEFAULT 0.000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_shelter_item (shelter_id, item_id),
    FOREIGN KEY (shelter_id) REFERENCES Shelters(shelter_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES Items(item_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Indexes for Performance
-- ============================================
CREATE INDEX idx_items_shelter ON Items(shelter_id);
CREATE INDEX idx_items_active ON Items(active);
CREATE INDEX idx_donations_shelter ON Donations(shelter_id);
CREATE INDEX idx_donations_supplier ON Donations(supplier_id);
CREATE INDEX idx_donationlines_donation ON DonationLines(donation_id);
CREATE INDEX idx_inventorylogs_item ON InventoryLogs(item_id);
CREATE INDEX idx_inventorylogs_shelter ON InventoryLogs(shelter_id);
CREATE INDEX idx_inventorylogs_date ON InventoryLogs(transaction_date);
CREATE INDEX idx_inventorylogs_type ON InventoryLogs(transaction_type);

-- ============================================
-- Sample Data (Optional - for testing)
-- Uncomment to insert sample data
-- ============================================

-- INSERT INTO Shelters (shelter_name, address, contact_person, contact_number, capacity, shelter_type) VALUES
-- ('Main Evacuation Center', '123 Main Street, Manila', 'Juan Dela Cruz', '+639123456789', 500, 'Evacuation'),
-- ('Medical Supplies Warehouse', '456 Health Ave, Quezon City', 'Maria Santos', '+639987654321', 100, 'Warehouse');

-- INSERT INTO Personnel (personnel_name, username, password, role, phone) VALUES
-- ('Administrator', 'admin', 'admin123', 'Administrator', '+639111222333'),
-- ('Staff User', 'staff', 'staff123', 'Staff', '+639444555666');
