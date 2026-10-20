USE si_smart_pos;

ALTER TABLE purchase_items
    ADD COLUMN ingredient_id BIGINT UNSIGNED NULL AFTER item_id,
    ADD COLUMN product_id BIGINT UNSIGNED NULL AFTER ingredient_id,
    ADD COLUMN item_name_snapshot VARCHAR(150) NULL AFTER product_id,
    ADD COLUMN unit_snapshot VARCHAR(30) NULL AFTER item_name_snapshot;

UPDATE purchase_items pi
LEFT JOIN ingredients i ON pi.item_type = 'ingredient' AND i.id = pi.item_id
LEFT JOIN products p ON pi.item_type = 'product' AND p.id = pi.item_id
SET pi.ingredient_id = CASE WHEN pi.item_type = 'ingredient' THEN i.id ELSE NULL END,
    pi.product_id = CASE WHEN pi.item_type = 'product' THEN p.id ELSE NULL END,
    pi.item_name_snapshot = COALESCE(i.name, p.name, CONCAT('Barang lama #', pi.item_id)),
    pi.unit_snapshot = COALESCE(i.unit, p.unit, 'unit');

ALTER TABLE purchase_items
    MODIFY COLUMN item_name_snapshot VARCHAR(150) NOT NULL,
    MODIFY COLUMN unit_snapshot VARCHAR(30) NOT NULL,
    ADD CONSTRAINT fk_purchase_items_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_purchase_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT;

ALTER TABLE inventory_movements
    ADD COLUMN ingredient_id BIGINT UNSIGNED NULL AFTER item_id,
    ADD COLUMN product_id BIGINT UNSIGNED NULL AFTER ingredient_id,
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER product_id,
    ADD COLUMN item_name_snapshot VARCHAR(150) NULL AFTER user_id,
    ADD COLUMN unit_snapshot VARCHAR(30) NULL AFTER item_name_snapshot;

UPDATE inventory_movements m
LEFT JOIN ingredients i ON m.item_type = 'ingredient' AND i.id = m.item_id
LEFT JOIN products p ON m.item_type = 'product' AND p.id = m.item_id
SET m.ingredient_id = CASE WHEN m.item_type = 'ingredient' THEN i.id ELSE NULL END,
    m.product_id = CASE WHEN m.item_type = 'product' THEN p.id ELSE NULL END,
    m.user_id = CASE WHEN m.reference_type = 'manual' THEN m.reference_id ELSE NULL END,
    m.item_name_snapshot = COALESCE(i.name, p.name, CONCAT('Barang lama #', m.item_id)),
    m.unit_snapshot = COALESCE(i.unit, p.unit, 'unit');

ALTER TABLE inventory_movements
    MODIFY COLUMN item_name_snapshot VARCHAR(150) NOT NULL,
    MODIFY COLUMN unit_snapshot VARCHAR(30) NOT NULL,
    ADD CONSTRAINT fk_inventory_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_inventory_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD INDEX idx_inventory_ingredient (ingredient_id, created_at),
    ADD INDEX idx_inventory_product (product_id, created_at);

ALTER TABLE sale_items
    ADD COLUMN unit_snapshot VARCHAR(30) NULL AFTER product_name;

UPDATE sale_items si
LEFT JOIN products p ON p.id = si.product_id
SET si.unit_snapshot = COALESCE(p.unit, 'unit');

ALTER TABLE sale_items
    MODIFY COLUMN unit_snapshot VARCHAR(30) NOT NULL;

ALTER TABLE cost_history
    ADD COLUMN ingredient_id BIGINT UNSIGNED NULL AFTER item_id,
    ADD COLUMN product_id BIGINT UNSIGNED NULL AFTER ingredient_id,
    ADD COLUMN item_name_snapshot VARCHAR(150) NULL AFTER product_id;

UPDATE cost_history h
LEFT JOIN ingredients i ON h.item_type = 'ingredient' AND i.id = h.item_id
LEFT JOIN products p ON h.item_type = 'product' AND p.id = h.item_id
SET h.ingredient_id = CASE WHEN h.item_type = 'ingredient' THEN i.id ELSE NULL END,
    h.product_id = CASE WHEN h.item_type = 'product' THEN p.id ELSE NULL END,
    h.item_name_snapshot = COALESCE(i.name, p.name, CONCAT('Barang lama #', h.item_id));

ALTER TABLE cost_history
    MODIFY COLUMN item_name_snapshot VARCHAR(150) NOT NULL,
    ADD CONSTRAINT fk_cost_history_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cost_history_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    ADD INDEX idx_cost_history_ingredient (ingredient_id, changed_at),
    ADD INDEX idx_cost_history_product (product_id, changed_at);

CREATE TABLE IF NOT EXISTS operational_expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method ENUM('cash','transfer','qris','debit','other') NOT NULL DEFAULT 'cash',
    status ENUM('recorded','voided') NOT NULL DEFAULT 'recorded',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    voided_at DATETIME NULL,
    voided_by BIGINT UNSIGNED NULL,
    void_note VARCHAR(255) NULL,
    CONSTRAINT fk_expenses_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_expenses_voided_by FOREIGN KEY (voided_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_expenses_date_status (expense_date, status),
    INDEX idx_expenses_user (user_id, created_at)
) ENGINE=InnoDB;
