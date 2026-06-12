-- Demo schema for Docker single-mode onboarding (database: myapp)
USE `myapp`;

CREATE TABLE IF NOT EXISTS `customers` (
  `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`  VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
  `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku`   VARCHAR(32) NOT NULL,
  `name`  VARCHAR(200) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `customers` (`name`, `email`) VALUES
  ('Alice Example', 'alice@example.com'),
  ('Bob Example', 'bob@example.com');

INSERT INTO `products` (`sku`, `name`, `price`) VALUES
  ('WIDGET-1', 'Basic Widget', 9.99),
  ('WIDGET-2', 'Premium Widget', 19.99);
