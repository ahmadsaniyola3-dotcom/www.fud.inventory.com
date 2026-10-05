-- ============================================================
-- Digital Inventory Management System for Computer Laboratories
-- B.Sc. Computer Science Final Year Project
-- Database Name: inventory_db
-- Compatibility: MySQL 5.7+ / MariaDB 10.1+ (Laragon, XAMPP, WAMP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `inventory_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `inventory_db`;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Default Admin User Data
-- Username: admin
-- Password: admin123
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `password`, `full_name`) VALUES
(1, 'admin', '$2y$10$45uD13kFvX9OQ/e.Y1uM9.uJ5Y6u7I8o9P0q1R2S3T4U5V6W7X8Y9', 'System Administrator');

-- --------------------------------------------------------
-- Table structure for `categories`
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample Categories Data
INSERT INTO `categories` (`id`, `category_name`, `description`) VALUES
(1, 'Desktop Computers', 'All workstation and desktop computer units in the lab'),
(2, 'Monitors & Displays', 'LED/LCD Monitors, Projectors, and Smart Displays'),
(3, 'Networking Devices', 'Switches, Routers, Access Points, and Network Cables'),
(4, 'Input Devices', 'Keyboards, Mice, Scanners, and Webcams'),
(5, 'Power Supplies & UPS', 'Uninterruptible Power Supplies, Power Strips, and Surge Protectors');

-- --------------------------------------------------------
-- Table structure for `inventory`
-- --------------------------------------------------------
CREATE TABLE `inventory` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_name` VARCHAR(150) NOT NULL,
  `category_id` INT NOT NULL,
  `brand` VARCHAR(100) NOT NULL,
  `model` VARCHAR(100) DEFAULT NULL,
  `total_qty` INT NOT NULL DEFAULT 0,
  `working_qty` INT NOT NULL DEFAULT 0,
  `damaged_qty` INT NOT NULL DEFAULT 0,
  `location` VARCHAR(100) NOT NULL,
  `date_added` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT NOT NULL,
  CONSTRAINT `fk_inventory_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_inventory_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample Inventory Items Data
INSERT INTO `inventory` (`id`, `item_name`, `category_id`, `brand`, `model`, `total_qty`, `working_qty`, `damaged_qty`, `location`, `created_by`) VALUES
(1, 'HP ProDesk 400 G6', 1, 'HP', 'ProDesk 400 G6 MT', 30, 27, 3, 'Computer Lab 1', 1),
(2, 'Dell UltraSharp 24 Monitor', 2, 'Dell', 'U2419H', 30, 29, 1, 'Computer Lab 1', 1),
(3, 'Cisco 24-Port Gigabit Switch', 3, 'Cisco', 'SG350-28', 4, 4, 0, 'Server Room', 1),
(4, 'Logitech MK270 Wireless Combo', 4, 'Logitech', 'MK270', 35, 30, 5, 'Computer Lab 2', 1),
(5, 'APC Smart-UPS 1500VA', 5, 'APC', 'SMC1500I', 6, 5, 1, 'Computer Lab 1', 1);