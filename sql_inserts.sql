

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE AuditLog;
TRUNCATE TABLE DonationLines;
TRUNCATE TABLE InventoryLogs;
TRUNCATE TABLE ShelterInventory;
TRUNCATE TABLE Donations;
TRUNCATE TABLE Items;
TRUNCATE TABLE PersonnelRoles;
TRUNCATE TABLE Personnel;
TRUNCATE TABLE Suppliers;
TRUNCATE TABLE Shelters;
TRUNCATE TABLE Roles;

INSERT INTO Suppliers (supplier_name, contact, email, address, supplier_type, is_active)
VALUES ('Acme Supplies', 'Jane Doe', 'jane@acme.example', '123 Acme Rd', 'General', TRUE);

INSERT INTO Shelters (shelter_name, address, contact_person, contact_number, capacity, shelter_type, is_active)
VALUES ('Central Shelter', '100 Main St', 'John Manager', '555-0100', 200, 'General', TRUE);

INSERT INTO Personnel (personnel_name, username, password_hash, phone, is_active)
VALUES ('Admin User', 'admin', '$6$F1YasHBf8pkdWVBH$a6YAUcTTfgwqaMTcS.BB0XxLGa6QoE.AJmpiVSBfIhOZ27NE/d1FdgLfRHwFFuQocimc.4dVQu2Rz2Qwe1VXT0', '555-0001', TRUE);

INSERT INTO PersonnelRoles (personnel_id, role_id, shelter_id)
VALUES (
  (SELECT personnel_id FROM Personnel WHERE username='admin'),
  (SELECT role_id FROM Roles WHERE role_name='superadmin'),
  NULL
);

INSERT INTO Items (shelter_id, item_name, item_type, unit, active, received_date, expiry_date, notes, item_properties, on_hand_qty, initial_qty, is_active)
VALUES
  ((SELECT shelter_id FROM Shelters WHERE shelter_name='Central Shelter'), 'Dog Food - 15kg', 'Food', 'bag', TRUE, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 120 DAY), 'Dry kibble, balanced adult formula', 'lot_number=L-A1', 100, 100, TRUE),
  ((SELECT shelter_id FROM Shelters WHERE shelter_name='Central Shelter'), 'Cat Medicine - 50ml', 'Medicine', 'bottle', TRUE, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'Antibiotic drops for cats', 'lot_number=M-22', 5, 50, TRUE),
  ((SELECT shelter_id FROM Shelters WHERE shelter_name='Central Shelter'), 'Vaccination Syringes', 'Supplies', 'box', TRUE, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Disposable syringes nearing expiration', 'lot_number=S-03', 40, 100, TRUE);

INSERT INTO ShelterInventory (shelter_id, item_id, low_stock_threshold, near_expiry_days)
VALUES (
  (SELECT shelter_id FROM Shelters WHERE shelter_name='Central Shelter'),
  (SELECT item_id FROM Items WHERE item_name='Dog Food - 15kg' LIMIT 1),
  10, 30
);

INSERT INTO Donations (donor_name, description, shelter_id, supplier_id, received_date, receipt_notes)
VALUES ('Generous Donor', 'Food donation', (SELECT shelter_id FROM Shelters WHERE shelter_name='Central Shelter'), (SELECT supplier_id FROM Suppliers WHERE supplier_name='Acme Supplies'), CURDATE(), 'Left at front desk');

SET @donation_id = LAST_INSERT_ID();
SET @item_id = (SELECT item_id FROM Items WHERE item_name='Dog Food - 15kg' LIMIT 1);

INSERT INTO DonationLines (donation_id, item_id, item_quantity, line_notes)
VALUES (
  @donation_id,
  @item_id,
  20, 'Boxes of kibble'
);

INSERT INTO InventoryLogs (transaction_date, item_id, shelter_id, quantity, transaction_type, personnel_id, transaction_notes)
VALUES (CURDATE(), (SELECT item_id FROM Items WHERE item_name='Dog Food - 15kg' LIMIT 1), (SELECT shelter_id FROM Shelters WHERE shelter_name='Central Shelter'), 20, 'IN', (SELECT personnel_id FROM Personnel WHERE username='admin'), 'Initial stock from donation');

INSERT INTO AuditLog (table_name, record_id, action, new_data, changed_by)
VALUES ('Items', (SELECT item_id FROM Items WHERE item_name='Dog Food - 15kg' LIMIT 1), 'INSERT', JSON_OBJECT('item_name','Dog Food - 15kg'), (SELECT personnel_id FROM Personnel WHERE username='admin'));

SET FOREIGN_KEY_CHECKS = 1;

SET @current_personnel_id = (SELECT personnel_id FROM Personnel WHERE username='admin');
