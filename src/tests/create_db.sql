-- phpMyAdmin SQL Dump
-- version 5.1.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 06, 2025 at 03:47 PM
-- Server version: 8.0.42-0ubuntu0.22.04.1
-- PHP Version: 7.4.33

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `dbapiator_demo`
--

-- --------------------------------------------------------

--
-- Table structure for table `Customers`
--

CREATE TABLE `Customers` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Customers`
--

INSERT INTO `Customers` (`id`, `name`, `email`, `password`) VALUES
(1, 'sergiu', 'sv@softaccel.net', 'parola123');

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_orders_totals`
-- (See below for the actual view)
--
CREATE TABLE `daily_orders_totals` (
`daily_total` decimal(32,0)
,`day` varchar(10)
);

-- --------------------------------------------------------

--
-- Table structure for table `OrderItems`
--

CREATE TABLE `OrderItems` (
  `id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_price` int NOT NULL,
  `total` int NOT NULL
) ;

--
-- Dumping data for table `OrderItems`
--

INSERT INTO `OrderItems` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `total`) VALUES
(1, 1, 2, 2, 10, 20),
(2, 1, 1, 10, 2, 20);

-- --------------------------------------------------------

--
-- Table structure for table `Orders`
--

CREATE TABLE `Orders` (
  `id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `order_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `total` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Orders`
--

INSERT INTO `Orders` (`id`, `customer_id`, `order_date`, `total`) VALUES
(1, 1, '2025-04-22 07:00:34', 0);

-- --------------------------------------------------------

--
-- Table structure for table `Products`
--

CREATE TABLE `Products` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Products`
--

INSERT INTO `Products` (`id`, `name`, `price`) VALUES
(1, 'iphone', '10.00'),
(2, 'dell laptop', '20.00');

-- --------------------------------------------------------

--
-- Structure for view `daily_orders_totals`
--
DROP TABLE IF EXISTS `daily_orders_totals`;

CREATE OR REPLACE VIEW `daily_orders_totals`  AS SELECT sum(`Orders`.`total`) AS `daily_total`, left(`Orders`.`order_date`,10) AS `day` FROM `Orders` GROUP BY `day` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Customers`
--
ALTER TABLE `Customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `OrderItems`
--
ALTER TABLE `OrderItems`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `Orders`
--
ALTER TABLE `Orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `Products`
--
ALTER TABLE `Products`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Customers`
--
ALTER TABLE `Customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `OrderItems`
--
ALTER TABLE `OrderItems`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Orders`
--
ALTER TABLE `Orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `Products`
--
ALTER TABLE `Products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `OrderItems`
--
ALTER TABLE `OrderItems`
  ADD CONSTRAINT `OrderItems_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `Orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `OrderItems_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `Products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `Orders`
--
ALTER TABLE `Orders`
  ADD CONSTRAINT `Orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `Customers` (`id`) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;
