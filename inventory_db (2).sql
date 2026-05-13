-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2026 at 09:54 AM
-- Server version: 8.0.45
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action_details` text COLLATE utf8mb4_general_ci NOT NULL,
  `action_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action_details`, `action_type`, `created_at`) VALUES
(1, 1, 'Super Admin created Admin: John Doe', 'User Management', '2026-04-08 04:03:28'),
(2, 1, 'Super Admin created Admin: benedict garis', 'User Management', '2026-05-13 02:57:34'),
(3, 4, 'Archived user ID: 3', 'User Management', '2026-05-13 03:03:15'),
(4, 4, 'Restored user ID: 3', 'User Management', '2026-05-13 03:03:29'),
(5, 4, 'Archived item: 62970253904aa7c2250d10e08387c71c', 'Inventory', '2026-05-13 04:55:00');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int NOT NULL,
  `public_id` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `item_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `brand` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `item_condition` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `warranty` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `item_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'default.png',
  `status` enum('pending','approved','rejected','archived') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `added_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `public_id`, `item_name`, `brand`, `category`, `item_condition`, `price`, `stock_quantity`, `description`, `warranty`, `item_image`, `status`, `added_by`) VALUES
(1, '62970253904aa7c2250d10e08387c71c', 'RT100', 'OPPO', 'Graphics Card', 'Brand New', 123.00, 2, 'NA', '2 weeks', 'IMG_10fa813909eabdaa.jpg', 'archived', 3),
(2, 'e49d4a3fa3eac106eb3ed3bc13ec8ee1', 'empanada', 'OPPO', 'Graphics Card', 'Used', 1221.98, 1, 'hehe', '1 year', 'IMG_d48c607c8163105f.jpg', 'approved', 3),
(3, '68f94c785ab8048611ca9a08af7d6c61', 'cellphone', 'OPPO', 'Graphics Card', 'Brand New', 1999.00, 3, 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.', '1YEAR', 'IMG_e93669f9800df492.png', 'approved', 3),
(4, '5245df7818a7a2849ac32209e4c5e2d4', 'RT100', 'OPPO', 'Motherboard', 'Brand New', 123.00, 1, 'NA', '2 weeks', 'IMG_655d2b289fce1570.jpg', 'approved', 4);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `role_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'Super Admin'),
(2, 'Admin'),
(3, 'Regular');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `firstname` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `lastname` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role_id` int DEFAULT NULL,
  `status` enum('active','archived') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `firstname`, `lastname`, `email`, `password`, `role_id`, `status`, `created_at`) VALUES
(1, 'Super', 'Admin', 'superadmin@gmail.com', '$2y$10$kYAUEE6CzvUz6U0JTgXG2uB5FwZIfpQcSrEWCHDnRZQKrVsa8Nnvq', 1, 'active', '2026-04-08 03:15:15'),
(2, 'John', 'Doe', 'mico@gmail.com', '$2y$10$HGRninC56HdQWKhiPwM39edTMexmw5Ha7AuYXGTlU24SQZ4kSppua', 2, 'active', '2026-04-08 04:03:28'),
(3, 'John Vincent', 'Herrera', 'herrerajohnvincent06@gmail.com', '$2y$10$mA0ql4POE4YeWnJCSRNYQuSlvqlytc8ABEKPUb4pGR7cVKDvjudGC', 3, 'active', '2026-04-08 04:04:37'),
(4, 'benedict', 'garis', 'johnvincentherrera2004@gmail.com', '$2y$10$XEfMewTdog2HMXMkA.f5i.3ThV7g2QEjbkk55Xbkg/MpDJUOC7T7G', 2, 'active', '2026-05-13 02:57:34');

-- --------------------------------------------------------

--
-- Table structure for table `user_tokens`
--

CREATE TABLE `user_tokens` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `public_id` (`public_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token` (`token`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_tokens`
--
ALTER TABLE `user_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
