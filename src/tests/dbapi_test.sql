-- dbAPI automated test database (MySQL 8+ / MariaDB 10.5+)
-- Lightweight schema for Management API (control plane) e2e tests.
-- For full data-plane coverage (filters, negatives, extra tables), use:
--   src/tests/dbapi_dataplane.sql
--
-- Usage:
--   mysql -u root -p < src/tests/dbapi_test.sql
-- Connection for tests:
--   database: dbapi_test
--   host: 127.0.0.1  port: 3306  user/password: your choice

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS `dbapi_test`;
CREATE DATABASE `dbapi_test`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `dbapi_test`;

-- ---------------------------------------------------------------------------
-- Core commerce model (tables + FKs + seed data)
-- Exercises: introspect, relationships, CRUD, filters, related records
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

-- View: schema introspection + read-only resource
CREATE OR REPLACE VIEW `v_order_totals_by_day` AS
SELECT
  DATE(`o`.`ordered_at`) AS `day`,
  COUNT(*) AS `order_count`,
  SUM(`o`.`total`) AS `revenue`
FROM `orders` AS `o`
WHERE `o`.`status` <> 'cancelled'
GROUP BY DATE(`o`.`ordered_at`);

-- ---------------------------------------------------------------------------
-- Auth helper (optional dbAuth / login-query tests)
-- Plaintext passwords are intentional for local test DB only.
-- ---------------------------------------------------------------------------

CREATE TABLE `app_users` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password` VARCHAR(128) NOT NULL,
  `pin`      VARCHAR(16) NULL,
  `role`     VARCHAR(32) NOT NULL DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_app_users_username` (`username`)
) ENGINE=InnoDB;

-- Login check used by dbAPI authentication.php (example loginQuery target)
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
-- Seed data (deterministic IDs for assertions)
-- ---------------------------------------------------------------------------

INSERT INTO `customers` (`id`, `name`, `email`, `country_code`) VALUES
  (1, 'Alice Example',   'alice@example.com',   'US'),
  (2, 'Bob Test',        'bob@example.com',     'DE');

INSERT INTO `products` (`id`, `sku`, `name`, `price`, `is_active`) VALUES
  (1, 'SKU-001', 'Widget A',  9.99,  1),
  (2, 'SKU-002', 'Widget B', 19.50, 1),
  (3, 'SKU-OLD', 'Retired',   5.00,  0);

INSERT INTO `orders` (`id`, `customer_id`, `status`, `ordered_at`, `total`) VALUES
  (1, 1, 'placed',  '2026-01-10 10:00:00', 39.98),
  (2, 1, 'shipped', '2026-01-15 14:30:00', 19.50),
  (3, 2, 'draft',   '2026-02-01 09:00:00',  0.00);

INSERT INTO `order_lines` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`) VALUES
  (1, 1, 1, 2,  9.99),
  (2, 1, 2, 1, 19.50),
  (3, 2, 2, 1, 19.50);

INSERT INTO `app_users` (`id`, `username`, `password`, `pin`, `role`) VALUES
  (1, 'testuser', 'testpass', '1234', 'user'),
  (2, 'admin',    'adminpass', '9999', 'admin');

SET FOREIGN_KEY_CHECKS = 1;
