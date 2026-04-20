-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 03, 2026 at 11:49 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `group_companies_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `module`, `reference_id`, `created_at`) VALUES
(1, 1, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-17 10:54:13'),
(2, 1, 'Login', 'User logged in', '127.0.0.1', NULL, 'auth', NULL, '2026-02-17 13:41:32'),
(3, 1, 'View Reports', 'Accessed reports page', '127.0.0.1', NULL, 'reports', NULL, '2026-02-17 13:41:32'),
(4, 1, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', NULL, NULL, '2026-02-17 14:32:15'),
(5, 1, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', NULL, NULL, '2026-02-17 14:32:15'),
(6, 1, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-21 11:23:30'),
(7, 2, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-21 11:24:18'),
(8, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-21 12:28:48'),
(9, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-21 12:28:48'),
(10, 5, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-23 09:37:49'),
(11, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-23 09:38:38'),
(12, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-23 09:38:38'),
(13, 2, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-23 09:38:55'),
(14, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-23 09:41:26'),
(15, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-23 09:41:26'),
(16, 5, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-23 09:41:39'),
(17, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-23 10:40:47'),
(18, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-23 10:40:47'),
(19, 5, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-23 10:46:03'),
(20, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-23 10:46:27'),
(21, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-23 10:46:27'),
(22, 1, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-23 10:47:59'),
(23, 1, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-23 18:46:46'),
(24, 2, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 11:25:11'),
(25, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:25:42'),
(26, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:25:42'),
(27, 3, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 11:26:01'),
(28, 3, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:26:22'),
(29, 3, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:26:22'),
(30, 4, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 11:26:36'),
(31, 4, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:26:58'),
(32, 4, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:26:58'),
(33, 5, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 11:27:11'),
(34, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:27:28'),
(35, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:27:28'),
(36, 2, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 11:27:41'),
(37, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:28:02'),
(38, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:28:02'),
(39, 1, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 11:28:15'),
(40, 1, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:29:25'),
(41, 1, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 11:29:25'),
(42, 1, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 12:00:29'),
(43, 1, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 12:00:59'),
(44, 1, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 12:00:59'),
(45, 2, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 12:01:14'),
(46, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 12:01:27'),
(47, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 12:01:27'),
(48, 2, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 13:37:13'),
(49, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:39:16'),
(50, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:39:16'),
(51, 2, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 13:41:19'),
(52, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:42:04'),
(53, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:42:04'),
(54, 5, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 13:42:25'),
(55, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:44:45'),
(56, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:44:45'),
(57, 4, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 13:45:06'),
(58, 4, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:46:13'),
(59, 4, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:46:13'),
(60, 2, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 13:46:23'),
(61, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:55:14'),
(62, 2, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:55:14'),
(63, 4, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 13:55:26'),
(64, 4, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:59:03'),
(65, 4, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 13:59:03'),
(66, 5, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 13:59:35'),
(67, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 14:00:56'),
(68, 5, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 14:00:56'),
(69, 3, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 14:01:10'),
(70, 3, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 14:01:26'),
(71, 3, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 14:01:26'),
(72, 3, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 14:01:50'),
(73, 3, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 14:09:56'),
(74, 3, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 14:09:56'),
(75, 1, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 14:10:11'),
(76, 1, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 14:10:49'),
(77, 1, 'Logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '2026-02-24 14:10:49'),
(78, 1, 'Login', 'User logged in successfully', '::1', NULL, NULL, NULL, '2026-02-24 14:11:45');

-- --------------------------------------------------------

--
-- Table structure for table `blockfactory_customers`
--

CREATE TABLE `blockfactory_customers` (
  `customer_id` int(11) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `alternate_phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `customer_type` enum('Individual','Company','Contractor','Government') DEFAULT 'Individual',
  `tax_number` varchar(50) DEFAULT NULL,
  `credit_limit` decimal(15,2) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blockfactory_deliveries`
--

CREATE TABLE `blockfactory_deliveries` (
  `delivery_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `delivery_note` varchar(50) NOT NULL,
  `delivery_date` date NOT NULL,
  `vehicle_number` varchar(50) NOT NULL,
  `driver_name` varchar(100) NOT NULL,
  `driver_phone` varchar(20) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `destination` text NOT NULL,
  `delivery_charges` decimal(10,2) DEFAULT 0.00,
  `status` enum('Scheduled','In Transit','Delivered','Cancelled') DEFAULT 'Scheduled',
  `recipient_name` varchar(100) DEFAULT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blockfactory_production`
--

CREATE TABLE `blockfactory_production` (
  `production_id` int(11) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `production_date` date NOT NULL,
  `shift` enum('Morning','Afternoon','Night') NOT NULL,
  `supervisor` varchar(100) NOT NULL,
  `machine_used` varchar(100) DEFAULT NULL,
  `planned_quantity` int(11) NOT NULL,
  `produced_quantity` int(11) NOT NULL,
  `good_quantity` int(11) NOT NULL,
  `defective_quantity` int(11) DEFAULT 0,
  `defect_rate` decimal(5,2) DEFAULT NULL,
  `raw_materials_used` text DEFAULT NULL,
  `cement_used` decimal(10,2) DEFAULT NULL,
  `sand_used` decimal(10,2) DEFAULT NULL,
  `aggregate_used` decimal(10,2) DEFAULT NULL,
  `water_used` decimal(10,2) DEFAULT NULL,
  `additive_used` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(15,2) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `quality_check_passed` tinyint(1) DEFAULT 1,
  `quality_notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blockfactory_products`
--

CREATE TABLE `blockfactory_products` (
  `product_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT 4,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_type` enum('Solid Block','Hollow Block','Interlocking Block','Paving Block','Kerbstones') NOT NULL,
  `dimensions` varchar(50) NOT NULL,
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `strength_mpa` varchar(20) DEFAULT NULL,
  `color` varchar(50) DEFAULT 'Grey',
  `price_per_unit` decimal(10,2) NOT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `minimum_stock` int(11) DEFAULT 100,
  `current_stock` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 200,
  `location` varchar(100) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blockfactory_products`
--

INSERT INTO `blockfactory_products` (`product_id`, `company_id`, `product_code`, `product_name`, `product_type`, `dimensions`, `weight_kg`, `strength_mpa`, `color`, `price_per_unit`, `cost_per_unit`, `minimum_stock`, `current_stock`, `reorder_level`, `location`, `image_path`, `status`, `description`, `created_at`, `updated_at`) VALUES
(1, 4, 'BLK001', '6\" Solid Block', 'Solid Block', '400x200x150', NULL, NULL, 'Grey', 180.00, NULL, 100, 5000, 200, NULL, NULL, 'Active', NULL, '2026-02-17 09:11:34', '2026-02-17 09:11:34'),
(2, 4, 'BLK002', '4\" Hollow Block', 'Hollow Block', '400x200x100', NULL, NULL, 'Grey', 120.00, NULL, 100, 3000, 200, NULL, NULL, 'Active', NULL, '2026-02-17 09:11:34', '2026-02-17 09:11:34'),
(3, 4, 'BLK003', 'Interlocking Block', 'Interlocking Block', '250x150x150', NULL, NULL, 'Grey', 220.00, NULL, 100, 1000, 200, NULL, NULL, 'Active', NULL, '2026-02-17 09:11:34', '2026-02-17 09:11:34');

-- --------------------------------------------------------

--
-- Table structure for table `blockfactory_raw_materials`
--

CREATE TABLE `blockfactory_raw_materials` (
  `material_id` int(11) NOT NULL,
  `material_code` varchar(50) NOT NULL,
  `material_name` varchar(100) NOT NULL,
  `material_type` enum('Cement','Sand','Aggregate','Water','Additive','Other') NOT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `unit` varchar(20) NOT NULL,
  `stock_quantity` decimal(10,2) DEFAULT 0.00,
  `minimum_stock` decimal(10,2) DEFAULT 0.00,
  `maximum_stock` decimal(10,2) DEFAULT 10000.00,
  `reorder_level` decimal(10,2) DEFAULT 100.00,
  `unit_cost` decimal(10,2) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `status` enum('Available','Low Stock','Out of Stock') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blockfactory_sales`
--

CREATE TABLE `blockfactory_sales` (
  `sale_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `sale_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT NULL,
  `payment_method` enum('Cash','Bank Transfer','Cheque','Mobile Money','Credit') NOT NULL,
  `payment_status` enum('Paid','Partial','Unpaid') DEFAULT 'Unpaid',
  `delivery_status` enum('Pending','Partial','Delivered') DEFAULT 'Pending',
  `delivery_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `sales_person` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `company_type` enum('Estate','Procurement','Works','Block Factory') NOT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `established_date` date DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`company_id`, `company_name`, `company_type`, `registration_number`, `tax_number`, `address`, `phone`, `email`, `website`, `established_date`, `logo_path`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Premium Estates Ltd', 'Estate', 'REG/2024/001', NULL, NULL, '+254700111222', 'info@premiumestates.co.ke', NULL, NULL, NULL, 'Active', '2026-02-17 09:11:34', '2026-02-17 09:11:34'),
(2, 'Global Procurement Services', 'Procurement', 'REG/2024/002', NULL, NULL, '+254700111223', 'info@globalprocurement.co.ke', NULL, NULL, NULL, 'Active', '2026-02-17 09:11:34', '2026-02-17 09:11:34'),
(3, 'Master Works Construction', 'Works', 'REG/2024/003', NULL, NULL, '+254700111224', 'info@masterworks.co.ke', NULL, NULL, NULL, 'Active', '2026-02-17 09:11:34', '2026-02-17 09:11:34'),
(4, 'Quality Block Manufacturers', 'Block Factory', 'REG/2024/004', NULL, NULL, '+254700111225', 'info@qualityblocks.co.ke', NULL, NULL, NULL, 'Active', '2026-02-17 09:11:34', '2026-02-17 09:11:34');

-- --------------------------------------------------------

--
-- Table structure for table `cross_company_transactions`
--

CREATE TABLE `cross_company_transactions` (
  `transaction_id` int(11) NOT NULL,
  `transaction_number` varchar(50) NOT NULL,
  `from_company_id` int(11) NOT NULL,
  `to_company_id` int(11) NOT NULL,
  `transaction_type` enum('Material Transfer','Service','Financial','Equipment Rental') NOT NULL,
  `reference_module` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estate_lease_documents`
--

CREATE TABLE `estate_lease_documents` (
  `document_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `document_type` enum('Lease Agreement','ID Copy','Income Proof','Guarantor Form','Inspection Report','Other') NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estate_maintenance`
--

CREATE TABLE `estate_maintenance` (
  `maintenance_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `request_date` datetime NOT NULL,
  `issue_category` enum('Plumbing','Electrical','Structural','Appliance','Pest Control','Cleaning','Other') NOT NULL,
  `priority` enum('Low','Medium','High','Emergency') DEFAULT 'Medium',
  `description` text NOT NULL,
  `images` text DEFAULT NULL,
  `assigned_to` varchar(100) DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `actual_cost` decimal(10,2) DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `completion_date` datetime DEFAULT NULL,
  `status` enum('Pending','Approved','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `contractor_name` varchar(100) DEFAULT NULL,
  `contractor_phone` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estate_payments`
--

CREATE TABLE `estate_payments` (
  `payment_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','Bank Transfer','Cheque','Mobile Money','Credit Card') NOT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `payment_period_start` date NOT NULL,
  `payment_period_end` date NOT NULL,
  `late_fee` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `status` enum('Paid','Pending','Cancelled','Refunded') DEFAULT 'Paid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estate_properties`
--

CREATE TABLE `estate_properties` (
  `property_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT 1,
  `property_code` varchar(50) NOT NULL,
  `property_name` varchar(100) NOT NULL,
  `property_type` enum('Residential','Commercial','Land','Industrial') NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Kenya',
  `total_area` decimal(10,2) DEFAULT NULL,
  `units` int(11) DEFAULT 1,
  `purchase_price` decimal(15,2) DEFAULT NULL,
  `current_value` decimal(15,2) DEFAULT NULL,
  `tax_assessment` decimal(15,2) DEFAULT NULL,
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `status` enum('Available','Occupied','Under Maintenance','Under Construction','Sold') DEFAULT 'Available',
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `images` text DEFAULT NULL,
  `documents` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `estate_properties`
--

INSERT INTO `estate_properties` (`property_id`, `company_id`, `property_code`, `property_name`, `property_type`, `address`, `city`, `state`, `postal_code`, `country`, `total_area`, `units`, `purchase_price`, `current_value`, `tax_assessment`, `insurance_value`, `insurance_expiry`, `status`, `description`, `features`, `images`, `documents`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'EST001', 'Sunset Heights', 'Residential', '123 Kilimani Road', 'Nairobi', NULL, NULL, 'Kenya', NULL, 24, NULL, NULL, NULL, NULL, NULL, 'Occupied', NULL, NULL, NULL, NULL, NULL, '2026-02-17 09:11:34', '2026-02-17 09:11:34'),
(2, 1, 'EST002', 'Westfield Mall', 'Commercial', '456 Westlands', 'Nairobi', NULL, NULL, 'Kenya', NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Available', NULL, NULL, NULL, NULL, NULL, '2026-02-17 09:11:34', '2026-02-17 09:11:34'),
(3, 1, 'EST003', 'Green Valley Estate', 'Residential', '789 Kiambu Road', 'Kiambu', NULL, NULL, 'Kenya', NULL, 12, NULL, NULL, NULL, NULL, NULL, 'Under Maintenance', NULL, NULL, NULL, NULL, NULL, '2026-02-17 09:11:34', '2026-02-17 09:11:34');

-- --------------------------------------------------------

--
-- Table structure for table `estate_tenants`
--

CREATE TABLE `estate_tenants` (
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `property_id` int(11) NOT NULL,
  `tenant_code` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `id_type` enum('National ID','Passport','Driving License') DEFAULT 'National ID',
  `id_number` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `alternate_phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `employer` varchar(100) DEFAULT NULL,
  `monthly_income` decimal(10,2) DEFAULT NULL,
  `lease_start_date` date NOT NULL,
  `lease_end_date` date NOT NULL,
  `lease_duration_months` int(11) DEFAULT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `deposit_amount` decimal(10,2) DEFAULT NULL,
  `deposit_paid` tinyint(1) DEFAULT 0,
  `rent_due_day` int(11) DEFAULT 1,
  `payment_frequency` enum('Monthly','Quarterly','Yearly') DEFAULT 'Monthly',
  `status` enum('Active','Notice','Terminated','Past') DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `documents` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `estate_tenants`
--

INSERT INTO `estate_tenants` (`tenant_id`, `company_id`, `property_id`, `tenant_code`, `full_name`, `id_type`, `id_number`, `phone`, `alternate_phone`, `email`, `emergency_contact_name`, `emergency_contact_phone`, `occupation`, `employer`, `monthly_income`, `lease_start_date`, `lease_end_date`, `lease_duration_months`, `monthly_rent`, `deposit_amount`, `deposit_paid`, `rent_due_day`, `payment_frequency`, `status`, `notes`, `documents`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'TEN001', 'James Otieno', 'National ID', '12345678', '+254722111111', NULL, 'james.otieno@email.com', NULL, NULL, NULL, NULL, NULL, '2024-01-01', '2024-12-31', NULL, 25000.00, NULL, 0, 1, 'Monthly', 'Active', NULL, NULL, NULL, '2026-02-17 09:11:34', '2026-02-21 12:22:55'),
(2, 1, 1, 'TEN002', 'Alice Wambui', 'National ID', '87654321', '+254733222222', NULL, 'alice.wambui@email.com', NULL, NULL, NULL, NULL, NULL, '2024-02-01', '2024-11-30', NULL, 28000.00, NULL, 0, 1, 'Monthly', 'Active', NULL, NULL, NULL, '2026-02-17 09:11:34', '2026-02-21 12:22:55');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `attempt_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`attempt_id`, `username`, `ip_address`, `user_id`, `attempt_time`) VALUES
(1, 'superadmin', '::1', NULL, '2026-02-17 10:41:28'),
(2, 'superadmin', '::1', NULL, '2026-02-17 10:42:09'),
(3, 'superadmin', '::1', NULL, '2026-02-17 10:46:34'),
(4, 'superadmin', '::1', NULL, '2026-02-17 10:48:48'),
(5, 'superadmin', '::1', NULL, '2026-02-17 10:49:21'),
(6, 'superadmin', '::1', NULL, '2026-02-24 12:00:18'),
(7, 'estate', '::1', NULL, '2026-02-24 13:41:00'),
(8, 'blocks', '::1', NULL, '2026-02-24 13:42:13'),
(9, 'works', '::1', NULL, '2026-02-24 13:44:56'),
(10, 'superadmin', '::1', NULL, '2026-02-24 14:11:18');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('Info','Success','Warning','Danger') DEFAULT 'Info',
  `module` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_global` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procurement_inventory`
--

CREATE TABLE `procurement_inventory` (
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `transaction_type` enum('Purchase','Sale','Adjustment','Return','Transfer') NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `previous_balance` int(11) DEFAULT NULL,
  `new_balance` int(11) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(15,2) DEFAULT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procurement_po_items`
--

CREATE TABLE `procurement_po_items` (
  `po_item_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 16.00,
  `tax_amount` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(15,2) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `status` enum('Pending','Partial','Received','Cancelled') DEFAULT 'Pending',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procurement_products`
--

CREATE TABLE `procurement_products` (
  `product_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `sub_category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) NOT NULL,
  `minimum_stock` int(11) DEFAULT 0,
  `maximum_stock` int(11) DEFAULT 1000,
  `current_stock` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT 16.00,
  `location` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive','Discontinued') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procurement_purchase_orders`
--

CREATE TABLE `procurement_purchase_orders` (
  `po_id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT NULL,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `payment_status` enum('Unpaid','Partial','Paid') DEFAULT 'Unpaid',
  `delivery_status` enum('Pending','Partial','Completed','Cancelled') DEFAULT 'Pending',
  `approval_status` enum('Draft','Pending','Approved','Rejected') DEFAULT 'Draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procurement_suppliers`
--

CREATE TABLE `procurement_suppliers` (
  `supplier_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT 2,
  `supplier_code` varchar(50) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `alternate_phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `products_services` text DEFAULT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `credit_limit` decimal(15,2) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT NULL,
  `contract_start` date DEFAULT NULL,
  `contract_end` date DEFAULT NULL,
  `status` enum('Active','Inactive','Blacklisted') DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `documents` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `procurement_suppliers`
--

INSERT INTO `procurement_suppliers` (`supplier_id`, `company_id`, `supplier_code`, `supplier_name`, `contact_person`, `phone`, `alternate_phone`, `email`, `website`, `address`, `city`, `country`, `category`, `products_services`, `tax_number`, `bank_name`, `bank_account`, `bank_branch`, `payment_terms`, `credit_limit`, `rating`, `contract_start`, `contract_end`, `status`, `notes`, `documents`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, 'SUP001', 'BuildMart Kenya', 'David Kimani', '+254722333333', NULL, 'info@buildmart.co.ke', NULL, NULL, NULL, NULL, 'Building Materials', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', NULL, NULL, NULL, '2026-02-17 09:11:34', '2026-02-17 09:11:34'),
(2, 2, 'SUP002', 'SteelMakers Ltd', 'Joseph Njoroge', '+254722444444', NULL, 'sales@steelmakers.co.ke', NULL, NULL, NULL, NULL, 'Steel Products', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', NULL, NULL, NULL, '2026-02-17 09:11:34', '2026-02-17 09:11:34');

-- --------------------------------------------------------

--
-- Table structure for table `report_schedules`
--

CREATE TABLE `report_schedules` (
  `schedule_id` int(11) NOT NULL,
  `report_name` varchar(100) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `frequency` enum('Daily','Weekly','Monthly','Quarterly','Yearly') NOT NULL,
  `format` enum('PDF','Excel','CSV') DEFAULT 'PDF',
  `recipients` text DEFAULT NULL,
  `parameters` text DEFAULT NULL,
  `next_run` date NOT NULL,
  `last_run` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `status` enum('Active','Paused','Completed') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_reports`
--

CREATE TABLE `saved_reports` (
  `report_id` int(11) NOT NULL,
  `report_name` varchar(100) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `parameters` text DEFAULT NULL,
  `generated_by` int(11) NOT NULL,
  `generated_date` datetime DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `format` enum('PDF','Excel','CSV','HTML') DEFAULT 'PDF',
  `is_scheduled` tinyint(1) DEFAULT 0,
  `schedule_frequency` enum('Daily','Weekly','Monthly','Quarterly','Yearly') DEFAULT NULL,
  `next_run_date` date DEFAULT NULL,
  `recipients` text DEFAULT NULL,
  `is_global` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `saved_reports`
--

INSERT INTO `saved_reports` (`report_id`, `report_name`, `report_type`, `module`, `parameters`, `generated_by`, `generated_date`, `file_path`, `format`, `is_scheduled`, `schedule_frequency`, `next_run_date`, `recipients`, `is_global`, `created_at`) VALUES
(1, 'Monthly Revenue Report', 'revenue', 'estate', NULL, 1, '2026-02-17 13:41:32', NULL, 'PDF', 0, NULL, NULL, NULL, 1, '2026-02-17 13:41:32'),
(2, 'Quarterly Performance', 'performance', 'procurement', NULL, 1, '2026-02-17 13:41:32', NULL, 'Excel', 0, NULL, NULL, NULL, 1, '2026-02-17 13:41:32'),
(3, 'Project Status Summary', 'projects', 'works', NULL, 1, '2026-02-17 13:41:32', NULL, 'PDF', 0, NULL, NULL, NULL, 1, '2026-02-17 13:41:32'),
(4, 'Production Report', 'production', 'blockfactory', NULL, 1, '2026-02-17 13:41:32', NULL, 'CSV', 0, NULL, NULL, NULL, 1, '2026-02-17 13:41:32');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `role` enum('SuperAdmin','CompanyAdmin','Manager','Staff') DEFAULT 'Staff',
  `company_id` int(11) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `full_name`, `phone`, `profile_picture`, `role`, `company_id`, `last_login`, `last_ip`, `status`, `created_at`, `updated_at`) VALUES
(1, 'superadmin', '$2y$10$2CATE4t6rnoUKvow7XFIPOok5Y0yJ59OLPXJeSkCyLeRNSxed3Q86', 'admin@groupcompanies.com', 'System Administrator', '+233700111000', NULL, 'SuperAdmin', NULL, '2026-02-24 14:11:45', '::1', 'Active', '2026-02-17 09:11:34', '2026-02-24 14:11:45'),
(2, 'estate', '$2y$10$2CATE4t6rnoUKvow7XFIPOok5Y0yJ59OLPXJeSkCyLeRNSxed3Q86', 'john@premiumestates.co.ke', 'Antwi Desmond', '+233241400137', NULL, 'CompanyAdmin', 1, '2026-02-24 13:46:23', '::1', 'Active', '2026-02-17 09:11:34', '2026-02-24 13:46:23'),
(3, 'procurement', '$2y$10$2CATE4t6rnoUKvow7XFIPOok5Y0yJ59OLPXJeSkCyLeRNSxed3Q86', 'mary@globalprocurement.co.ke', 'Yaw Dankwah', '+233700111002', NULL, 'Manager', 2, '2026-02-24 14:01:50', '::1', 'Active', '2026-02-17 09:11:34', '2026-02-24 14:01:50'),
(4, 'works', '$2y$10$2CATE4t6rnoUKvow7XFIPOok5Y0yJ59OLPXJeSkCyLeRNSxed3Q86', 'peter@masterworks.co.ke', 'Martin Yafugeh', '+254700111003', NULL, 'Manager', 3, '2026-02-24 13:55:26', '::1', 'Active', '2026-02-17 09:11:34', '2026-02-24 13:55:26'),
(5, 'blocks', '$2y$10$2CATE4t6rnoUKvow7XFIPOok5Y0yJ59OLPXJeSkCyLeRNSxed3Q86', 'sarah@qualityblocks.co.ke', 'Kwame Dankwah', '+233700111004', NULL, 'Manager', 4, '2026-02-24 13:59:35', '::1', 'Active', '2026-02-17 09:11:34', '2026-02-24 13:59:35');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `permission_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 1,
  `can_create` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_approve` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`permission_id`, `user_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_approve`, `created_at`) VALUES
(1, 2, 'estate', 1, 1, 1, 1, 1, '2026-02-17 09:11:34'),
(2, 3, 'procurement', 1, 1, 1, 1, 1, '2026-02-17 09:11:34'),
(3, 4, 'works', 1, 1, 1, 1, 1, '2026-02-17 09:11:34'),
(4, 5, 'blockfactory', 1, 1, 1, 1, 1, '2026-02-17 09:11:34');

-- --------------------------------------------------------

--
-- Table structure for table `user_tokens`
--

CREATE TABLE `user_tokens` (
  `token_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expiry` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `works_daily_reports`
--

CREATE TABLE `works_daily_reports` (
  `report_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `weather_conditions` varchar(100) DEFAULT NULL,
  `temperature` varchar(20) DEFAULT NULL,
  `work_description` text NOT NULL,
  `employees_present` int(11) DEFAULT NULL,
  `hours_worked` decimal(10,2) DEFAULT NULL,
  `materials_used` text DEFAULT NULL,
  `equipment_used` text DEFAULT NULL,
  `challenges` text DEFAULT NULL,
  `achievements` text DEFAULT NULL,
  `next_plan` text DEFAULT NULL,
  `photos` text DEFAULT NULL,
  `supervisor_notes` text DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `status` enum('Draft','Submitted','Approved') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `works_employees`
--

CREATE TABLE `works_employees` (
  `employee_id` int(11) NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `alternate_phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `position` varchar(50) NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `qualification` text DEFAULT NULL,
  `hire_date` date NOT NULL,
  `contract_type` enum('Permanent','Contract','Temporary','Casual') DEFAULT 'Contract',
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `monthly_salary` decimal(10,2) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `status` enum('Active','Inactive','On Leave') DEFAULT 'Active',
  `profile_picture` varchar(255) DEFAULT NULL,
  `documents` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `works_materials`
--

CREATE TABLE `works_materials` (
  `material_id` int(11) NOT NULL,
  `material_code` varchar(50) NOT NULL,
  `material_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `minimum_stock` decimal(10,2) DEFAULT 0.00,
  `current_stock` decimal(10,2) DEFAULT 0.00,
  `location` varchar(100) DEFAULT NULL,
  `status` enum('Available','Low Stock','Out of Stock') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `works_projects`
--

CREATE TABLE `works_projects` (
  `project_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT 3,
  `project_code` varchar(50) NOT NULL,
  `project_name` varchar(100) NOT NULL,
  `project_type` enum('Construction','Renovation','Maintenance','Infrastructure','Other') NOT NULL,
  `client_name` varchar(100) DEFAULT NULL,
  `client_contact` varchar(20) DEFAULT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `location` text NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `duration_days` int(11) DEFAULT NULL,
  `budget` decimal(15,2) NOT NULL,
  `actual_cost` decimal(15,2) DEFAULT 0.00,
  `contingency` decimal(15,2) DEFAULT 0.00,
  `total_budget` decimal(15,2) DEFAULT NULL,
  `project_manager` int(11) DEFAULT NULL,
  `site_supervisor` varchar(100) DEFAULT NULL,
  `status` enum('Planning','In Progress','On Hold','Completed','Cancelled') DEFAULT 'Planning',
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `drawings` text DEFAULT NULL,
  `permits` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `works_projects`
--

INSERT INTO `works_projects` (`project_id`, `company_id`, `project_code`, `project_name`, `project_type`, `client_name`, `client_contact`, `client_email`, `location`, `start_date`, `end_date`, `duration_days`, `budget`, `actual_cost`, `contingency`, `total_budget`, `project_manager`, `site_supervisor`, `status`, `progress_percentage`, `description`, `specifications`, `drawings`, `permits`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 3, 'WRK001', 'Riverside Apartments', 'Construction', NULL, NULL, NULL, 'Riverside Drive, Nairobi', '2024-01-15', NULL, NULL, 5000000.00, 0.00, 0.00, NULL, NULL, NULL, 'In Progress', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-17 09:11:34', '2026-02-17 09:11:34'),
(2, 3, 'WRK002', 'City Mall Renovation', 'Renovation', NULL, NULL, NULL, 'CBD, Nairobi', '2024-03-01', NULL, NULL, 2000000.00, 0.00, 0.00, NULL, NULL, NULL, 'Planning', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-17 09:11:34', '2026-02-17 09:11:34');

-- --------------------------------------------------------

--
-- Table structure for table `works_project_assignments`
--

CREATE TABLE `works_project_assignments` (
  `assignment_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `role` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `hours_worked` decimal(10,2) DEFAULT 0.00,
  `overtime_hours` decimal(10,2) DEFAULT 0.00,
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `total_payment` decimal(15,2) DEFAULT NULL,
  `status` enum('Active','Completed','Transferred') DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `works_project_materials`
--

CREATE TABLE `works_project_materials` (
  `usage_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(15,2) DEFAULT NULL,
  `date_used` date NOT NULL,
  `used_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `works_project_progress`
--

CREATE TABLE `works_project_progress` (
  `progress_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `completion_percentage` decimal(5,2) NOT NULL,
  `milestone` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `issues` text DEFAULT NULL,
  `next_milestone` varchar(200) DEFAULT NULL,
  `estimated_completion` date DEFAULT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `blockfactory_customers`
--
ALTER TABLE `blockfactory_customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`);

--
-- Indexes for table `blockfactory_deliveries`
--
ALTER TABLE `blockfactory_deliveries`
  ADD PRIMARY KEY (`delivery_id`),
  ADD UNIQUE KEY `delivery_note` (`delivery_note`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `blockfactory_production`
--
ALTER TABLE `blockfactory_production`
  ADD PRIMARY KEY (`production_id`),
  ADD UNIQUE KEY `batch_number` (`batch_number`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `blockfactory_products`
--
ALTER TABLE `blockfactory_products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `blockfactory_raw_materials`
--
ALTER TABLE `blockfactory_raw_materials`
  ADD PRIMARY KEY (`material_id`),
  ADD UNIQUE KEY `material_code` (`material_code`);

--
-- Indexes for table `blockfactory_sales`
--
ALTER TABLE `blockfactory_sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `sales_person` (`sales_person`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`company_id`);

--
-- Indexes for table `cross_company_transactions`
--
ALTER TABLE `cross_company_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD UNIQUE KEY `transaction_number` (`transaction_number`),
  ADD KEY `from_company_id` (`from_company_id`),
  ADD KEY `to_company_id` (`to_company_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `estate_lease_documents`
--
ALTER TABLE `estate_lease_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `estate_maintenance`
--
ALTER TABLE `estate_maintenance`
  ADD PRIMARY KEY (`maintenance_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `completed_by` (`completed_by`);

--
-- Indexes for table `estate_payments`
--
ALTER TABLE `estate_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `estate_properties`
--
ALTER TABLE `estate_properties`
  ADD PRIMARY KEY (`property_id`),
  ADD UNIQUE KEY `property_code` (`property_code`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `estate_tenants`
--
ALTER TABLE `estate_tenants`
  ADD PRIMARY KEY (`tenant_id`),
  ADD UNIQUE KEY `tenant_code` (`tenant_code`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_time` (`attempt_time`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `procurement_inventory`
--
ALTER TABLE `procurement_inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `procurement_po_items`
--
ALTER TABLE `procurement_po_items`
  ADD PRIMARY KEY (`po_item_id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `procurement_products`
--
ALTER TABLE `procurement_products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `product_code` (`product_code`);

--
-- Indexes for table `procurement_purchase_orders`
--
ALTER TABLE `procurement_purchase_orders`
  ADD PRIMARY KEY (`po_id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `procurement_suppliers`
--
ALTER TABLE `procurement_suppliers`
  ADD PRIMARY KEY (`supplier_id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `report_schedules`
--
ALTER TABLE `report_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_next_run` (`next_run`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `saved_reports`
--
ALTER TABLE `saved_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `unique_user_module` (`user_id`,`module_name`);

--
-- Indexes for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expiry` (`expiry`);

--
-- Indexes for table `works_daily_reports`
--
ALTER TABLE `works_daily_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `works_employees`
--
ALTER TABLE `works_employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`);

--
-- Indexes for table `works_materials`
--
ALTER TABLE `works_materials`
  ADD PRIMARY KEY (`material_id`),
  ADD UNIQUE KEY `material_code` (`material_code`);

--
-- Indexes for table `works_projects`
--
ALTER TABLE `works_projects`
  ADD PRIMARY KEY (`project_id`),
  ADD UNIQUE KEY `project_code` (`project_code`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `project_manager` (`project_manager`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `works_project_assignments`
--
ALTER TABLE `works_project_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `works_project_materials`
--
ALTER TABLE `works_project_materials`
  ADD PRIMARY KEY (`usage_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `works_project_progress`
--
ALTER TABLE `works_project_progress`
  ADD PRIMARY KEY (`progress_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `reported_by` (`reported_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `blockfactory_customers`
--
ALTER TABLE `blockfactory_customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blockfactory_deliveries`
--
ALTER TABLE `blockfactory_deliveries`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blockfactory_production`
--
ALTER TABLE `blockfactory_production`
  MODIFY `production_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blockfactory_products`
--
ALTER TABLE `blockfactory_products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `blockfactory_raw_materials`
--
ALTER TABLE `blockfactory_raw_materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blockfactory_sales`
--
ALTER TABLE `blockfactory_sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `company_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cross_company_transactions`
--
ALTER TABLE `cross_company_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `estate_lease_documents`
--
ALTER TABLE `estate_lease_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `estate_maintenance`
--
ALTER TABLE `estate_maintenance`
  MODIFY `maintenance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `estate_payments`
--
ALTER TABLE `estate_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `estate_properties`
--
ALTER TABLE `estate_properties`
  MODIFY `property_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `estate_tenants`
--
ALTER TABLE `estate_tenants`
  MODIFY `tenant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `procurement_inventory`
--
ALTER TABLE `procurement_inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `procurement_po_items`
--
ALTER TABLE `procurement_po_items`
  MODIFY `po_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `procurement_products`
--
ALTER TABLE `procurement_products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `procurement_purchase_orders`
--
ALTER TABLE `procurement_purchase_orders`
  MODIFY `po_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `procurement_suppliers`
--
ALTER TABLE `procurement_suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `report_schedules`
--
ALTER TABLE `report_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `saved_reports`
--
ALTER TABLE `saved_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_tokens`
--
ALTER TABLE `user_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `works_daily_reports`
--
ALTER TABLE `works_daily_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `works_employees`
--
ALTER TABLE `works_employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `works_materials`
--
ALTER TABLE `works_materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `works_projects`
--
ALTER TABLE `works_projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `works_project_assignments`
--
ALTER TABLE `works_project_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `works_project_materials`
--
ALTER TABLE `works_project_materials`
  MODIFY `usage_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `works_project_progress`
--
ALTER TABLE `works_project_progress`
  MODIFY `progress_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `blockfactory_deliveries`
--
ALTER TABLE `blockfactory_deliveries`
  ADD CONSTRAINT `blockfactory_deliveries_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `blockfactory_sales` (`sale_id`),
  ADD CONSTRAINT `blockfactory_deliveries_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `blockfactory_production`
--
ALTER TABLE `blockfactory_production`
  ADD CONSTRAINT `blockfactory_production_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `blockfactory_products` (`product_id`),
  ADD CONSTRAINT `blockfactory_production_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `blockfactory_products`
--
ALTER TABLE `blockfactory_products`
  ADD CONSTRAINT `blockfactory_products_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`);

--
-- Constraints for table `blockfactory_sales`
--
ALTER TABLE `blockfactory_sales`
  ADD CONSTRAINT `blockfactory_sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `blockfactory_customers` (`customer_id`),
  ADD CONSTRAINT `blockfactory_sales_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `blockfactory_products` (`product_id`),
  ADD CONSTRAINT `blockfactory_sales_ibfk_3` FOREIGN KEY (`sales_person`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `cross_company_transactions`
--
ALTER TABLE `cross_company_transactions`
  ADD CONSTRAINT `cross_company_transactions_ibfk_1` FOREIGN KEY (`from_company_id`) REFERENCES `companies` (`company_id`),
  ADD CONSTRAINT `cross_company_transactions_ibfk_2` FOREIGN KEY (`to_company_id`) REFERENCES `companies` (`company_id`),
  ADD CONSTRAINT `cross_company_transactions_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `cross_company_transactions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `estate_lease_documents`
--
ALTER TABLE `estate_lease_documents`
  ADD CONSTRAINT `estate_lease_documents_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `estate_tenants` (`tenant_id`),
  ADD CONSTRAINT `estate_lease_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `estate_maintenance`
--
ALTER TABLE `estate_maintenance`
  ADD CONSTRAINT `estate_maintenance_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `estate_properties` (`property_id`),
  ADD CONSTRAINT `estate_maintenance_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `estate_tenants` (`tenant_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `estate_maintenance_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `estate_maintenance_ibfk_4` FOREIGN KEY (`completed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `estate_payments`
--
ALTER TABLE `estate_payments`
  ADD CONSTRAINT `estate_payments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `estate_tenants` (`tenant_id`),
  ADD CONSTRAINT `estate_payments_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `estate_properties` (`property_id`),
  ADD CONSTRAINT `estate_payments_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `estate_properties`
--
ALTER TABLE `estate_properties`
  ADD CONSTRAINT `estate_properties_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`),
  ADD CONSTRAINT `estate_properties_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `estate_tenants`
--
ALTER TABLE `estate_tenants`
  ADD CONSTRAINT `estate_tenants_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `estate_properties` (`property_id`),
  ADD CONSTRAINT `estate_tenants_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`);

--
-- Constraints for table `procurement_inventory`
--
ALTER TABLE `procurement_inventory`
  ADD CONSTRAINT `procurement_inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `procurement_products` (`product_id`),
  ADD CONSTRAINT `procurement_inventory_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `procurement_po_items`
--
ALTER TABLE `procurement_po_items`
  ADD CONSTRAINT `procurement_po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `procurement_purchase_orders` (`po_id`),
  ADD CONSTRAINT `procurement_po_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `procurement_products` (`product_id`);

--
-- Constraints for table `procurement_purchase_orders`
--
ALTER TABLE `procurement_purchase_orders`
  ADD CONSTRAINT `procurement_purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `procurement_suppliers` (`supplier_id`),
  ADD CONSTRAINT `procurement_purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `procurement_purchase_orders_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `procurement_suppliers`
--
ALTER TABLE `procurement_suppliers`
  ADD CONSTRAINT `procurement_suppliers_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`),
  ADD CONSTRAINT `procurement_suppliers_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `report_schedules`
--
ALTER TABLE `report_schedules`
  ADD CONSTRAINT `report_schedules_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_reports`
--
ALTER TABLE `saved_reports`
  ADD CONSTRAINT `saved_reports_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `works_daily_reports`
--
ALTER TABLE `works_daily_reports`
  ADD CONSTRAINT `works_daily_reports_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `works_projects` (`project_id`),
  ADD CONSTRAINT `works_daily_reports_ibfk_2` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `works_projects`
--
ALTER TABLE `works_projects`
  ADD CONSTRAINT `works_projects_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`),
  ADD CONSTRAINT `works_projects_ibfk_2` FOREIGN KEY (`project_manager`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `works_projects_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `works_project_assignments`
--
ALTER TABLE `works_project_assignments`
  ADD CONSTRAINT `works_project_assignments_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `works_projects` (`project_id`),
  ADD CONSTRAINT `works_project_assignments_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `works_employees` (`employee_id`),
  ADD CONSTRAINT `works_project_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `works_project_materials`
--
ALTER TABLE `works_project_materials`
  ADD CONSTRAINT `works_project_materials_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `works_projects` (`project_id`),
  ADD CONSTRAINT `works_project_materials_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `works_materials` (`material_id`),
  ADD CONSTRAINT `works_project_materials_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `works_project_progress`
--
ALTER TABLE `works_project_progress`
  ADD CONSTRAINT `works_project_progress_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `works_projects` (`project_id`),
  ADD CONSTRAINT `works_project_progress_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
