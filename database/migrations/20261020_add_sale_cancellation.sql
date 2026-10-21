ALTER TABLE sales
    ADD COLUMN status ENUM('completed', 'voided') NOT NULL DEFAULT 'completed' AFTER change_amount,
    ADD COLUMN voided_at DATETIME DEFAULT NULL AFTER status,
    ADD COLUMN voided_by BIGINT UNSIGNED DEFAULT NULL AFTER voided_at,
    ADD COLUMN void_note VARCHAR(255) DEFAULT NULL AFTER voided_by,
    ADD KEY idx_sales_status_sold_at (status, sold_at),
    ADD KEY fk_sales_voided_by (voided_by),
    ADD CONSTRAINT fk_sales_voided_by FOREIGN KEY (voided_by) REFERENCES users (id) ON DELETE SET NULL;
