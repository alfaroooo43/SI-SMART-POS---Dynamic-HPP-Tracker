CREATE DATABASE IF NOT EXISTS si_smart_pos
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE si_smart_pos;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('owner', 'admin') NOT NULL DEFAULT 'admin',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    stock DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    average_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    minimum_stock DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    type ENUM('culinary', 'retail') NOT NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
    selling_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    stock DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    average_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    minimum_stock DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    min_margin DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    target_margin DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY fk_products_category (category_id),
    KEY idx_products_active (active, name),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    invoice_number VARCHAR(100) DEFAULT NULL,
    purchased_at DATE NOT NULL,
    status ENUM('draft', 'confirmed', 'cancelled') NOT NULL DEFAULT 'draft',
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY fk_purchases_supplier (supplier_id),
    KEY fk_purchases_user (user_id),
    CONSTRAINT fk_purchases_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
    CONSTRAINT fk_purchases_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    yield_quantity DECIMAL(14,3) NOT NULL DEFAULT 1.000,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY product_id (product_id),
    CONSTRAINT fk_recipes_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_number VARCHAR(40) NOT NULL,
    checkout_token CHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    sold_at DATETIME NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL,
    discount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL,
    cost_total DECIMAL(14,2) NOT NULL,
    payment_method ENUM('cash', 'transfer', 'qris', 'debit') NOT NULL,
    amount_paid DECIMAL(14,2) NOT NULL,
    change_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('completed', 'voided') NOT NULL DEFAULT 'completed',
    voided_at DATETIME DEFAULT NULL,
    voided_by BIGINT UNSIGNED DEFAULT NULL,
    void_note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY receipt_number (receipt_number),
    UNIQUE KEY checkout_token (checkout_token),
    KEY fk_sales_user (user_id),
    KEY idx_sales_sold_at (sold_at),
    KEY idx_sales_status_sold_at (status, sold_at),
    KEY fk_sales_voided_by (voided_by),
    CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_sales_voided_by FOREIGN KEY (voided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_items (
    recipe_id BIGINT UNSIGNED NOT NULL,
    ingredient_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    PRIMARY KEY (recipe_id, ingredient_id),
    KEY fk_recipe_items_ingredient (ingredient_id),
    CONSTRAINT fk_recipe_items_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients (id),
    CONSTRAINT fk_recipe_items_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_id BIGINT UNSIGNED NOT NULL,
    item_type ENUM('ingredient', 'product') NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    ingredient_id BIGINT UNSIGNED DEFAULT NULL,
    product_id BIGINT UNSIGNED DEFAULT NULL,
    item_name_snapshot VARCHAR(150) NOT NULL,
    unit_snapshot VARCHAR(30) NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(14,2) NOT NULL,
    line_total DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (id),
    KEY fk_purchase_items_purchase (purchase_id),
    KEY idx_purchase_items_item (item_type, item_id),
    KEY fk_purchase_items_ingredient (ingredient_id),
    KEY fk_purchase_items_product (product_id),
    CONSTRAINT fk_purchase_items_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients (id) ON DELETE RESTRICT,
    CONSTRAINT fk_purchase_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT fk_purchase_items_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    unit_snapshot VARCHAR(30) NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL,
    unit_cost DECIMAL(14,2) NOT NULL,
    line_total DECIMAL(14,2) NOT NULL,
    line_cost DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (id),
    KEY fk_sale_items_sale (sale_id),
    KEY idx_sale_items_product (product_id),
    CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_type ENUM('ingredient', 'product') NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    ingredient_id BIGINT UNSIGNED DEFAULT NULL,
    product_id BIGINT UNSIGNED DEFAULT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    item_name_snapshot VARCHAR(150) NOT NULL,
    unit_snapshot VARCHAR(30) NOT NULL,
    movement_type ENUM('purchase', 'sale', 'adjustment') NOT NULL,
    reference_type VARCHAR(30) NOT NULL,
    reference_id BIGINT UNSIGNED NOT NULL,
    quantity_change DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inventory_reference (reference_type, reference_id),
    KEY idx_inventory_item (item_type, item_id, created_at),
    KEY fk_inventory_user (user_id),
    KEY idx_inventory_ingredient (ingredient_id, created_at),
    KEY idx_inventory_product (product_id, created_at),
    CONSTRAINT fk_inventory_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients (id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movement_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    movement_id BIGINT UNSIGNED DEFAULT NULL,
    action ENUM('updated', 'deleted') NOT NULL,
    item_type ENUM('ingredient', 'product') NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    item_name_snapshot VARCHAR(150) NOT NULL,
    unit_snapshot VARCHAR(30) NOT NULL,
    movement_type ENUM('purchase', 'sale', 'adjustment') NOT NULL,
    reference_type VARCHAR(30) NOT NULL,
    reference_id BIGINT UNSIGNED NOT NULL,
    quantity_change DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL,
    changed_by BIGINT UNSIGNED DEFAULT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inventory_audit_movement (movement_id),
    KEY idx_inventory_audit_item (item_type, item_id, changed_at),
    KEY idx_inventory_audit_user (changed_by),
    CONSTRAINT fk_inventory_audit_movement FOREIGN KEY (movement_id) REFERENCES inventory_movements (id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_audit_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cost_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_type ENUM('ingredient', 'product') NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    ingredient_id BIGINT UNSIGNED DEFAULT NULL,
    product_id BIGINT UNSIGNED DEFAULT NULL,
    item_name_snapshot VARCHAR(150) NOT NULL,
    old_cost DECIMAL(14,2) NOT NULL,
    new_cost DECIMAL(14,2) NOT NULL,
    source_type VARCHAR(30) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cost_history_item (item_type, item_id, changed_at),
    KEY idx_cost_history_ingredient (ingredient_id, changed_at),
    KEY idx_cost_history_product (product_id, changed_at),
    CONSTRAINT fk_cost_history_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients (id) ON DELETE SET NULL,
    CONSTRAINT fk_cost_history_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operational_expenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method ENUM('cash', 'transfer', 'qris', 'debit', 'other') NOT NULL DEFAULT 'cash',
    status ENUM('recorded', 'voided') NOT NULL DEFAULT 'recorded',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    voided_at DATETIME DEFAULT NULL,
    voided_by BIGINT UNSIGNED DEFAULT NULL,
    void_note VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY fk_expenses_voided_by (voided_by),
    KEY idx_expenses_date_status (expense_date, status),
    KEY idx_expenses_user (user_id, created_at),
    CONSTRAINT fk_expenses_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_expenses_voided_by FOREIGN KEY (voided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
