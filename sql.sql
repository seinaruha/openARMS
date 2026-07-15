

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE Roles (
  role_id       INT AUTO_INCREMENT PRIMARY KEY,
  role_name     VARCHAR(40) NOT NULL UNIQUE,
  is_global     BOOLEAN NOT NULL DEFAULT FALSE,   
  description   VARCHAR(255) NULL
);

INSERT INTO Roles (role_name, is_global, description) VALUES
  ('superadmin',      1, 'Full read/write access to all shelters and system configuration.'),
  ('shelter_manager',  0, 'Full read/write access within their assigned shelter(s).'),
  ('staff',            0, 'Can log transactions/donations and edit items within their shelter(s); cannot delete.'),
  ('volunteer',        0, 'Can log IN/OUT transactions within their shelter(s); read-only otherwise.'),
  ('auditor',          1, 'Global read-only access, including audit logs.');

CREATE TABLE Personnel (
  personnel_id    INT AUTO_INCREMENT PRIMARY KEY,
  personnel_name  VARCHAR(120) NOT NULL,
  username        VARCHAR(60)  NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,          
  phone           VARCHAR(30)  NULL,
  is_active       BOOLEAN   NOT NULL DEFAULT TRUE, 
  deleted_at      TIMESTAMP    NULL DEFAULT NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE Suppliers (
  supplier_id    INT AUTO_INCREMENT PRIMARY KEY,
  supplier_name  VARCHAR(120) NOT NULL,
  contact        VARCHAR(80)  NULL,
  email          VARCHAR(120) NULL,
  address        VARCHAR(150) NULL,
  supplier_type  VARCHAR(50)  NOT NULL DEFAULT 'General',
  is_active      BOOLEAN   NOT NULL DEFAULT TRUE,
  deleted_at     TIMESTAMP    NULL DEFAULT NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE Shelters (
  shelter_id      INT AUTO_INCREMENT PRIMARY KEY,
  shelter_name    VARCHAR(150) NOT NULL,
  address         VARCHAR(200) NOT NULL,
  contact_person  VARCHAR(120) NULL,
  contact_number  VARCHAR(30)  NULL,
  capacity        INT NULL,
  shelter_type    VARCHAR(70)  NULL,
  is_active       BOOLEAN   NOT NULL DEFAULT TRUE,
  deleted_at      TIMESTAMP    NULL DEFAULT NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE PersonnelRoles (
  personnel_role_id INT AUTO_INCREMENT PRIMARY KEY,
  personnel_id       INT NOT NULL,
  role_id            INT NOT NULL,
  shelter_id         INT NULL,   

  CONSTRAINT fk_pr_personnel FOREIGN KEY (personnel_id) REFERENCES Personnel(personnel_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pr_role FOREIGN KEY (role_id) REFERENCES Roles(role_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pr_shelter FOREIGN KEY (shelter_id) REFERENCES Shelters(shelter_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT uq_pr_person_role_shelter UNIQUE (personnel_id, role_id, shelter_id)
);

CREATE TABLE Items (
  item_id         INT AUTO_INCREMENT PRIMARY KEY,
  shelter_id      INT NOT NULL,     

  item_name       VARCHAR(100) NOT NULL,
  item_type       VARCHAR(50)  NOT NULL,
  unit            VARCHAR(30)  NOT NULL,
  active          BOOLEAN   NOT NULL DEFAULT TRUE,  

  received_date   DATE NOT NULL,
  expiry_date     DATE NULL,
  notes           VARCHAR(300) NULL,

  
  
  
    item_properties VARCHAR(300) NULL,

  on_hand_qty     DECIMAL(12,3) NOT NULL DEFAULT 0,
  initial_qty     DECIMAL(12,3) NOT NULL,

  is_active       BOOLEAN NOT NULL DEFAULT TRUE,     
  deleted_at      TIMESTAMP  NULL DEFAULT NULL,
  created_at      TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP  NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  
  
  
    

  CONSTRAINT fk_items_shelter FOREIGN KEY (shelter_id) REFERENCES Shelters(shelter_id)
    ON UPDATE CASCADE ON DELETE RESTRICT

  
);

CREATE TABLE ShelterInventory (
  shelter_inventory_id  INT AUTO_INCREMENT PRIMARY KEY,
  shelter_id            INT NOT NULL,
  item_id               INT NOT NULL,
  low_stock_threshold   DECIMAL(12,3) NOT NULL DEFAULT 0,  
  near_expiry_days      INT NOT NULL DEFAULT 14,           

  CONSTRAINT fk_si_shelter FOREIGN KEY (shelter_id) REFERENCES Shelters(shelter_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_si_item FOREIGN KEY (item_id) REFERENCES Items(item_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT uq_si_shelter_item UNIQUE (shelter_id, item_id)
);

CREATE TABLE InventoryLogs (
  transaction_id      INT AUTO_INCREMENT PRIMARY KEY,
  transaction_date    DATE NOT NULL,

  item_id             INT NOT NULL,
  shelter_id          INT NOT NULL,

  quantity            DECIMAL(12,3) NOT NULL,
  transaction_type    ENUM('IN','OUT','TRANSFER','ADJUST') NOT NULL,

  personnel_id        INT NOT NULL,
  transaction_notes    VARCHAR(300) NULL,

  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_il_item FOREIGN KEY (item_id) REFERENCES Items(item_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_il_shelter FOREIGN KEY (shelter_id) REFERENCES Shelters(shelter_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_il_personnel FOREIGN KEY (personnel_id) REFERENCES Personnel(personnel_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE Donations (
  donation_id     INT AUTO_INCREMENT PRIMARY KEY,

  donor_name      VARCHAR(100) NULL,
  description     VARCHAR(150) NULL,

  shelter_id      INT NULL,
  supplier_id     INT NULL,

  received_date   DATE NOT NULL,
  receipt_notes   VARCHAR(300) NULL,

  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_don_shelter FOREIGN KEY (shelter_id) REFERENCES Shelters(shelter_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_don_supplier FOREIGN KEY (supplier_id) REFERENCES Suppliers(supplier_id)
    ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE DonationLines (
  donation_line_id  INT AUTO_INCREMENT PRIMARY KEY,
  donation_id       INT NOT NULL,

  item_id           INT NOT NULL,
  item_quantity     DECIMAL(12,3) NOT NULL,

  line_notes        VARCHAR(300) NULL,

  CONSTRAINT fk_dl_donation FOREIGN KEY (donation_id) REFERENCES Donations(donation_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_dl_item FOREIGN KEY (item_id) REFERENCES Items(item_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE AuditLog (
  audit_id     BIGINT AUTO_INCREMENT PRIMARY KEY,
  table_name   VARCHAR(64) NOT NULL,
  record_id    INT NOT NULL,
  action       ENUM('INSERT','UPDATE','DELETE') NOT NULL,
  old_data     JSON NULL,             
  new_data     JSON NULL,             
  changed_by   INT NULL,              
  changed_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_audit_table_record (table_name, record_id),
  INDEX idx_audit_changed_by (changed_by),
  INDEX idx_audit_changed_at (changed_at)

  
  
);

SET FOREIGN_KEY_CHECKS = 1;

DELIMITER $$

CREATE TRIGGER trg_suppliers_ai AFTER INSERT ON Suppliers FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, new_data, changed_by)
  VALUES ('Suppliers', NEW.supplier_id, 'INSERT', JSON_OBJECT(
    'supplier_id', NEW.supplier_id, 'supplier_name', NEW.supplier_name, 'contact', NEW.contact,
    'email', NEW.email, 'address', NEW.address, 'supplier_type', NEW.supplier_type,
    'is_active', NEW.is_active), @current_personnel_id);
END$$

CREATE TRIGGER trg_suppliers_au AFTER UPDATE ON Suppliers FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, old_data, new_data, changed_by)
  VALUES ('Suppliers', NEW.supplier_id, 'UPDATE',
    JSON_OBJECT('supplier_id', OLD.supplier_id, 'supplier_name', OLD.supplier_name, 'contact', OLD.contact,
      'email', OLD.email, 'address', OLD.address, 'supplier_type', OLD.supplier_type, 'is_active', OLD.is_active),
    JSON_OBJECT('supplier_id', NEW.supplier_id, 'supplier_name', NEW.supplier_name, 'contact', NEW.contact,
      'email', NEW.email, 'address', NEW.address, 'supplier_type', NEW.supplier_type, 'is_active', NEW.is_active),
    @current_personnel_id);
END$$

CREATE TRIGGER trg_suppliers_ad AFTER DELETE ON Suppliers FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, old_data, changed_by)
  VALUES ('Suppliers', OLD.supplier_id, 'DELETE', JSON_OBJECT(
    'supplier_id', OLD.supplier_id, 'supplier_name', OLD.supplier_name, 'is_active', OLD.is_active),
    @current_personnel_id);
END$$

CREATE TRIGGER trg_shelters_ai AFTER INSERT ON Shelters FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, new_data, changed_by)
  VALUES ('Shelters', NEW.shelter_id, 'INSERT', JSON_OBJECT(
    'shelter_id', NEW.shelter_id, 'shelter_name', NEW.shelter_name, 'address', NEW.address,
    'contact_person', NEW.contact_person, 'contact_number', NEW.contact_number,
    'capacity', NEW.capacity, 'shelter_type', NEW.shelter_type, 'is_active', NEW.is_active), @current_personnel_id);
END$$

CREATE TRIGGER trg_shelters_au AFTER UPDATE ON Shelters FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, old_data, new_data, changed_by)
  VALUES ('Shelters', NEW.shelter_id, 'UPDATE',
    JSON_OBJECT('shelter_id', OLD.shelter_id, 'shelter_name', OLD.shelter_name, 'address', OLD.address,
      'contact_person', OLD.contact_person, 'contact_number', OLD.contact_number,
      'capacity', OLD.capacity, 'shelter_type', OLD.shelter_type, 'is_active', OLD.is_active),
    JSON_OBJECT('shelter_id', NEW.shelter_id, 'shelter_name', NEW.shelter_name, 'address', NEW.address,
      'contact_person', NEW.contact_person, 'contact_number', NEW.contact_number,
      'capacity', NEW.capacity, 'shelter_type', NEW.shelter_type, 'is_active', NEW.is_active),
    @current_personnel_id);
END$$

CREATE TRIGGER trg_shelters_ad AFTER DELETE ON Shelters FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, old_data, changed_by)
  VALUES ('Shelters', OLD.shelter_id, 'DELETE', JSON_OBJECT(
    'shelter_id', OLD.shelter_id, 'shelter_name', OLD.shelter_name, 'is_active', OLD.is_active),
    @current_personnel_id);
END$$

CREATE TRIGGER trg_personnel_ai AFTER INSERT ON Personnel FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, new_data, changed_by)
  VALUES ('Personnel', NEW.personnel_id, 'INSERT', JSON_OBJECT(
    'personnel_id', NEW.personnel_id, 'personnel_name', NEW.personnel_name, 'username', NEW.username,
    'phone', NEW.phone, 'is_active', NEW.is_active), @current_personnel_id);
END$$

CREATE TRIGGER trg_personnel_au AFTER UPDATE ON Personnel FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, old_data, new_data, changed_by)
  VALUES ('Personnel', NEW.personnel_id, 'UPDATE',
    JSON_OBJECT('personnel_id', OLD.personnel_id, 'personnel_name', OLD.personnel_name, 'username', OLD.username,
      'phone', OLD.phone, 'is_active', OLD.is_active),
    JSON_OBJECT('personnel_id', NEW.personnel_id, 'personnel_name', NEW.personnel_name, 'username', NEW.username,
      'phone', NEW.phone, 'is_active', NEW.is_active),
    @current_personnel_id);
END$$

CREATE TRIGGER trg_personnel_ad AFTER DELETE ON Personnel FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, old_data, changed_by)
  VALUES ('Personnel', OLD.personnel_id, 'DELETE', JSON_OBJECT(
    'personnel_id', OLD.personnel_id, 'personnel_name', OLD.personnel_name, 'is_active', OLD.is_active),
    @current_personnel_id);
END$$

CREATE TRIGGER trg_items_ai AFTER INSERT ON Items FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, new_data, changed_by)
  VALUES ('Items', NEW.item_id, 'INSERT', JSON_OBJECT(
    'item_id', NEW.item_id, 'shelter_id', NEW.shelter_id, 'item_name', NEW.item_name, 'item_type', NEW.item_type,
    'unit', NEW.unit, 'active', NEW.active, 'received_date', NEW.received_date, 'expiry_date', NEW.expiry_date,
    'item_properties', NEW.item_properties, 'on_hand_qty', NEW.on_hand_qty, 'initial_qty', NEW.initial_qty,
    'is_active', NEW.is_active), @current_personnel_id);
END$$

CREATE TRIGGER trg_items_au AFTER UPDATE ON Items FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, old_data, new_data, changed_by)
  VALUES ('Items', NEW.item_id, 'UPDATE',
    JSON_OBJECT('item_id', OLD.item_id, 'shelter_id', OLD.shelter_id, 'item_name', OLD.item_name, 'item_type', OLD.item_type,
      'unit', OLD.unit, 'active', OLD.active, 'received_date', OLD.received_date, 'expiry_date', OLD.expiry_date,
      'item_properties', OLD.item_properties, 'on_hand_qty', OLD.on_hand_qty, 'initial_qty', OLD.initial_qty,
      'is_active', OLD.is_active),
    JSON_OBJECT('item_id', NEW.item_id, 'shelter_id', NEW.shelter_id, 'item_name', NEW.item_name, 'item_type', NEW.item_type,
      'unit', NEW.unit, 'active', NEW.active, 'received_date', NEW.received_date, 'expiry_date', NEW.expiry_date,
      'item_properties', NEW.item_properties, 'on_hand_qty', NEW.on_hand_qty, 'initial_qty', NEW.initial_qty,
      'is_active', NEW.is_active),
    @current_personnel_id);
END$$

CREATE TRIGGER trg_items_ad AFTER DELETE ON Items FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, old_data, changed_by)
  VALUES ('Items', OLD.item_id, 'DELETE', JSON_OBJECT(
    'item_id', OLD.item_id, 'shelter_id', OLD.shelter_id, 'item_name', OLD.item_name, 'is_active', OLD.is_active),
    @current_personnel_id);
END$$

CREATE TRIGGER trg_invlogs_ai AFTER INSERT ON InventoryLogs FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, new_data, changed_by)
  VALUES ('InventoryLogs', NEW.transaction_id, 'INSERT', JSON_OBJECT(
    'transaction_id', NEW.transaction_id, 'transaction_date', NEW.transaction_date, 'item_id', NEW.item_id,
    'shelter_id', NEW.shelter_id, 'quantity', NEW.quantity, 'transaction_type', NEW.transaction_type,
    'personnel_id', NEW.personnel_id, 'transaction_notes', NEW.transaction_notes), @current_personnel_id);
END$$

CREATE TRIGGER trg_si_ai AFTER INSERT ON ShelterInventory FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, new_data, changed_by)
  VALUES ('ShelterInventory', NEW.shelter_inventory_id, 'INSERT', JSON_OBJECT(
    'shelter_inventory_id', NEW.shelter_inventory_id, 'shelter_id', NEW.shelter_id, 'item_id', NEW.item_id,
    'low_stock_threshold', NEW.low_stock_threshold, 'near_expiry_days', NEW.near_expiry_days), @current_personnel_id);
END$$

CREATE TRIGGER trg_si_au AFTER UPDATE ON ShelterInventory FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, old_data, new_data, changed_by)
  VALUES ('ShelterInventory', NEW.shelter_inventory_id, 'UPDATE',
    JSON_OBJECT('low_stock_threshold', OLD.low_stock_threshold, 'near_expiry_days', OLD.near_expiry_days),
    JSON_OBJECT('low_stock_threshold', NEW.low_stock_threshold, 'near_expiry_days', NEW.near_expiry_days),
    @current_personnel_id);
END$$

CREATE TRIGGER trg_donations_ai AFTER INSERT ON Donations FOR EACH ROW BEGIN
  INSERT INTO AuditLog (table_name, record_id, action, new_data, changed_by)
  VALUES ('Donations', NEW.donation_id, 'INSERT', JSON_OBJECT(
    'donation_id', NEW.donation_id, 'donor_name', NEW.donor_name, 'description', NEW.description,
    'shelter_id', NEW.shelter_id, 'supplier_id', NEW.supplier_id, 'received_date', NEW.received_date),
    @current_personnel_id);
END$$

CREATE TRIGGER trg_donationlines_ai AFTER INSERT ON DonationLines FOR EACH ROW
BEGIN
  DECLARE v_shelter_id INT;

  INSERT INTO AuditLog (table_name, record_id, action, new_data, changed_by)
  VALUES ('DonationLines', NEW.donation_line_id, 'INSERT', JSON_OBJECT(
    'donation_line_id', NEW.donation_line_id, 'donation_id', NEW.donation_id, 'item_id', NEW.item_id,
    'item_quantity', NEW.item_quantity), @current_personnel_id);

  SELECT shelter_id INTO v_shelter_id FROM Donations WHERE donation_id = NEW.donation_id;

  IF v_shelter_id IS NOT NULL THEN
    UPDATE Items SET on_hand_qty = on_hand_qty + NEW.item_quantity WHERE item_id = NEW.item_id;

    INSERT INTO InventoryLogs (transaction_date, item_id, shelter_id, quantity, transaction_type, personnel_id, transaction_notes)
    VALUES (CURDATE(), NEW.item_id, v_shelter_id, NEW.item_quantity, 'IN',
      COALESCE(@current_personnel_id, NEW.item_id), 
      CONCAT('Auto-logged from donation_id=', NEW.donation_id));
  END IF;
END$$

DELIMITER ;

