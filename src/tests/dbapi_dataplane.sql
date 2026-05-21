-- dbAPI data-plane test database (MySQL 8+ / MariaDB 10.5+)
--
-- Purpose: exercise the JSON:API data plane end-to-end — CRUD, relationships,
-- filters, sorting, pagination, views, constraints, and negative cases.
--
-- Usage:
--   mysql -u root -p < src/tests/dbapi_dataplane.sql
--
-- Connection (tests):
--   database: dbapi_dataplane
--   host: 127.0.0.1  port: 3306  user/password: your choice
--
-- Deterministic seed IDs are documented inline for assertions.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS `dbapi_dataplane`;
CREATE DATABASE `dbapi_dataplane`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `dbapi_dataplane`;

-- ---------------------------------------------------------------------------
-- Commerce model (relationships, FK RESTRICT/CASCADE, ENUM, generated column)
-- Seed IDs: customers 1-2, products 1-3, orders 1-3, order_lines 1-3
-- ---------------------------------------------------------------------------

CREATE TABLE `customers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(255) NOT NULL,
  `country_code`  CHAR(2) NOT NULL DEFAULT 'US',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customers_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE `products` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku`         VARCHAR(32) NOT NULL,
  `name`        VARCHAR(200) NOT NULL,
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_sku` (`sku`)
) ENGINE=InnoDB;

CREATE TABLE `orders` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`  INT UNSIGNED NOT NULL,
  `status`       ENUM('draft','placed','shipped','cancelled') NOT NULL DEFAULT 'placed',
  `ordered_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_orders_customer_id` (`customer_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `fk_orders_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `order_lines` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`    INT UNSIGNED NOT NULL,
  `product_id`  INT UNSIGNED NOT NULL,
  `quantity`    INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(10,2) NOT NULL,
  `line_total`  DECIMAL(12,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  PRIMARY KEY (`id`),
  KEY `idx_order_lines_order_id` (`order_id`),
  KEY `idx_order_lines_product_id` (`product_id`),
  CONSTRAINT `fk_order_lines_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_lines_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Notes: TEXT with commas/special chars (filter escaping), optional FK
CREATE TABLE `notes` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`  INT UNSIGNED NULL,
  `body`         TEXT NOT NULL,
  `priority`     TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_notes_customer_id` (`customer_id`),
  CONSTRAINT `fk_notes_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- filter_cases — curated rows for filter / sort / pagination assertions
-- Seed IDs: 1-6 (fixed)
-- ---------------------------------------------------------------------------

CREATE TABLE `filter_cases` (
  `id`           INT UNSIGNED NOT NULL,
  `label`        VARCHAR(64) NOT NULL,
  `status`       ENUM('open','closed','pending') NOT NULL DEFAULT 'open',
  `score`        INT NOT NULL DEFAULT 0,
  `amount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `starts_at`    DATE NOT NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `country`      CHAR(2) NOT NULL DEFAULT 'US',
  `note`         VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_filter_cases_status` (`status`),
  KEY `idx_filter_cases_score` (`score`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- catalog_items — varied column types (sparse fields, type coercion)
-- Seed IDs: 1-3
-- ---------------------------------------------------------------------------

CREATE TABLE `catalog_items` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`          VARCHAR(32) NOT NULL,
  `title`         VARCHAR(200) NOT NULL,
  `qty`           INT NOT NULL DEFAULT 0,
  `unit_price`    DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `weight_kg`     DECIMAL(8,3) NULL,
  `published_on`  DATE NULL,
  `metadata`      JSON NULL,
  `is_available`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_catalog_items_code` (`code`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- suppliers + shipments — second FK graph (inbound relation naming)
-- ---------------------------------------------------------------------------

CREATE TABLE `suppliers` (
  `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`  VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `shipments` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED NOT NULL,
  `supplier_id`  INT UNSIGNED NOT NULL,
  `tracking_no`  VARCHAR(64) NULL,
  `shipped_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shipments_order_id` (`order_id`),
  KEY `idx_shipments_supplier_id` (`supplier_id`),
  CONSTRAINT `fk_shipments_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shipments_supplier`
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Views & auth helpers (read-only resource, login SP — optional auth tests)
-- ---------------------------------------------------------------------------

CREATE OR REPLACE VIEW `v_order_totals_by_day` AS
SELECT
  DATE(`o`.`ordered_at`) AS `day`,
  COUNT(*) AS `order_count`,
  SUM(`o`.`total`) AS `revenue`
FROM `orders` AS `o`
WHERE `o`.`status` <> 'cancelled'
GROUP BY DATE(`o`.`ordered_at`);

CREATE TABLE `app_users` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password` VARCHAR(128) NOT NULL,
  `role`     VARCHAR(32) NOT NULL DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_app_users_username` (`username`)
) ENGINE=InnoDB;

CREATE OR REPLACE VIEW `v_app_login` AS
SELECT
  `u`.`id`   AS `user_id`,
  `u`.`username`,
  `u`.`role`
FROM `app_users` AS `u`;

DELIMITER $$
CREATE PROCEDURE `sp_validate_login` (
  IN p_username VARCHAR(64),
  IN p_password VARCHAR(128)
)
BEGIN
  SELECT `id` AS `user_id`, `username`, `role`
  FROM `app_users`
  WHERE `username` = p_username AND `password` = p_password
  LIMIT 1;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------------------

INSERT INTO `customers` (`id`, `name`, `email`, `country_code`) VALUES
  (1, 'Alice Example',   'alice@example.com',   'US'),
  (2, 'Bob Test',        'bob@example.com',     'DE'),
  (3, 'Carol Nullable',  'carol@example.com',   'GB');

INSERT INTO `products` (`id`, `sku`, `name`, `price`, `is_active`) VALUES
  (1, 'SKU-001', 'Widget A',  9.99,  1),
  (2, 'SKU-002', 'Widget B', 19.50, 1),
  (3, 'SKU-OLD', 'Retired',   5.00,  0);

INSERT INTO `orders` (`id`, `customer_id`, `status`, `ordered_at`, `total`) VALUES
  (1, 1, 'placed',   '2026-01-10 10:00:00', 39.98),
  (2, 1, 'shipped',  '2026-01-15 14:30:00', 19.50),
  (3, 2, 'draft',    '2026-02-01 09:00:00',  0.00),
  (4, 1, 'cancelled','2026-01-20 08:00:00',  9.99);

INSERT INTO `order_lines` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`) VALUES
  (1, 1, 1, 2,  9.99),
  (2, 1, 2, 1, 19.50),
  (3, 2, 2, 1, 19.50);

INSERT INTO `notes` (`id`, `customer_id`, `body`, `priority`) VALUES
  (1, 1, 'Plain note', 1),
  (2, NULL, 'Unassigned, has comma, and backslash\\test', 0),
  (3, 2, 'Line one, line two', 2);

INSERT INTO `filter_cases` (`id`, `label`, `status`, `score`, `amount`, `starts_at`, `is_active`, `country`, `note`) VALUES
  (1, 'alpha-low',    'open',    10,  1.00, '2025-01-01', 1, 'US', 'match-eq'),
  (2, 'alpha-high',   'open',    90, 99.00, '2025-06-15', 1, 'US', 'match-gt'),
  (3, 'beta-closed',  'closed',  50, 10.00, '2024-12-31', 0, 'DE', 'match-status'),
  (4, 'gamma-prefix', 'pending',  5,  5.50, '2026-03-01', 1, 'GB', 'prefix-2026'),
  (5, 'delta-inactive','open',    0,  0.01, '2025-02-02', 0, 'FR', NULL),
  (6, 'epsilon-list', 'open',    42, 42.00, '2025-07-07', 1, 'US', 'for-in-list');

INSERT INTO `catalog_items` (`id`, `code`, `title`, `qty`, `unit_price`, `weight_kg`, `published_on`, `metadata`, `is_available`) VALUES
  (1, 'CAT-A', 'Item A', 100, 12.5000, 1.250, '2025-01-10', '{"tier":"standard"}', 1),
  (2, 'CAT-B', 'Item B',   0,  0.0100, NULL,  NULL,         NULL,                  0),
  (3, 'CAT-C', 'Item C',   7, 99.9900, 0.001, '2026-05-01', '{"tier":"premium"}',  1);

INSERT INTO `suppliers` (`id`, `name`) VALUES
  (1, 'Acme Supply'),
  (2, 'Global Parts');

INSERT INTO `shipments` (`id`, `order_id`, `supplier_id`, `tracking_no`, `shipped_at`) VALUES
  (1, 2, 1, 'TRK-001', '2026-01-16 09:00:00');

INSERT INTO `app_users` (`id`, `username`, `password`, `role`) VALUES
  (1, 'testuser', 'testpass', 'user'),
  (2, 'admin',    'adminpass', 'admin');

SET FOREIGN_KEY_CHECKS = 1;
