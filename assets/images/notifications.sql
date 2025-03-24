-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 24, 2025 at 08:56 PM
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
-- Database: `wastewise`
--

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `target_role` varchar(50) DEFAULT NULL,
  `target_branch_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `target_role`, `target_branch_id`, `message`, `notification_type`, `link`, `is_read`, `created_at`) VALUES
(26, NULL, 'staff', NULL, 'New donation request for Auro Chocolate (40 units)', 'donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/staff/donation_request.php', 0, '2025-03-22 20:33:42'),
(27, NULL, 'ngo', NULL, 'A donation is ready for pickup! Please check available donations.', 'donation_prepared', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/food_browse.php', 0, '2025-03-22 20:33:58'),
(28, NULL, 'admin', NULL, 'New donation request from Tondo for Auro Chocolate', 'ngo_donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-22 20:34:14'),
(29, 11, 'ngo', NULL, 'Your donation request for Auro Chocolate has been approved.', 'donation_request_approved', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php', 0, '2025-03-22 20:34:24'),
(30, NULL, 'admin', NULL, 'New donation request from Tondo for Auro Chocolate', 'ngo_donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-22 20:34:32'),
(31, NULL, 'admin', NULL, 'Kath Aguirre has confirmed receipt of Auro Chocolate.', 'donation_completed', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-22 20:34:56'),
(32, NULL, 'admin', NULL, 'Kath Aguirre has confirmed receipt of Auro Chocolate.', 'donation_completed', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-22 20:37:27'),
(33, 21, NULL, NULL, 'Your staff account has been approved. You can now log in.', NULL, NULL, 1, '2025-03-23 09:42:10'),
(34, 11, 'ngo', NULL, 'Your donation request for Auro Chocolate has been rejected.', 'donation_request_rejected', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php', 0, '2025-03-23 10:56:52'),
(35, NULL, 'staff', NULL, 'New donation request for Chocolate (50 units)', 'donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/staff/donation_request.php', 0, '2025-03-23 13:25:44'),
(36, NULL, 'ngo', NULL, 'A donation is ready for pickup! Please check available donations.', 'donation_prepared', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/food_browse.php', 0, '2025-03-23 13:26:54'),
(37, NULL, NULL, NULL, 'New user Ronjay Sarmiento (ngo) has been created by admin.', NULL, NULL, 1, '2025-03-23 14:07:18'),
(38, 3, NULL, NULL, 'NGO Project PEARLS has been approved', NULL, NULL, 1, '2025-03-23 14:07:21'),
(39, 22, NULL, NULL, 'Your NGO account has been approved. You can now log in.', NULL, NULL, 1, '2025-03-23 14:07:21'),
(40, NULL, 'admin', NULL, 'New donation request from Ronjay Sarmiento for Chocolate', 'ngo_donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-23 14:09:38'),
(41, 22, 'ngo', NULL, 'Your donation request for Chocolate has been approved.', 'donation_request_approved', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php', 0, '2025-03-23 14:10:25'),
(42, NULL, 'admin', NULL, 'Ronjay Sarmiento has confirmed receipt of Chocolate.', 'donation_completed', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-23 14:18:56'),
(43, NULL, 'staff', NULL, 'New donation request for Banana Muffin (50 units)', 'donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/staff/donation_request.php', 0, '2025-03-23 16:01:34'),
(44, NULL, 'ngo', NULL, 'A donation is ready for pickup! Please check available donations.', 'donation_prepared', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/food_browse.php', 0, '2025-03-23 16:01:50'),
(45, NULL, 'admin', NULL, 'New donation request from Tondo for Banana Muffin', 'ngo_donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-23 16:02:54'),
(46, 11, 'ngo', NULL, 'Your donation request for Banana Muffin has been approved.', 'donation_request_approved', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php', 0, '2025-03-23 16:03:13'),
(47, NULL, 'admin', NULL, 'Kath Aguirre has confirmed receipt of Banana Muffin.', 'donation_completed', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-23 16:04:45'),
(48, 23, NULL, NULL, 'Your staff account has been approved. You can now log in.', NULL, NULL, 1, '2025-03-23 16:17:35'),
(49, NULL, 'staff', NULL, 'New donation request for Auro Chocolate (50 units)', 'donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/staff/donation_request.php', 0, '2025-03-24 04:10:13'),
(50, NULL, 'staff', NULL, 'New donation request for Chocolate (50 units)', 'donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/staff/donation_request.php', 0, '2025-03-24 04:43:02'),
(51, NULL, 'staff', NULL, 'New donation request for Banana Muffin (50 units)', 'donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/staff/donation_request.php', 0, '2025-03-24 04:43:14'),
(52, NULL, 'ngo', NULL, 'A donation is ready for pickup! Please check available donations.', 'donation_prepared', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/food_browse.php', 0, '2025-03-24 04:43:29'),
(53, NULL, 'ngo', NULL, 'A donation is ready for pickup! Please check available donations.', 'donation_prepared', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/food_browse.php', 0, '2025-03-24 04:43:31'),
(54, NULL, 'ngo', NULL, 'A donation is ready for pickup! Please check available donations.', 'donation_prepared', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/food_browse.php', 0, '2025-03-24 04:43:39'),
(55, NULL, 'admin', NULL, 'New donation request from Tondo for Auro Chocolate', 'ngo_donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-24 04:45:01'),
(56, NULL, 'admin', NULL, 'New donation request from Tondo for Chocolate', 'ngo_donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-24 04:45:11'),
(57, NULL, 'admin', NULL, 'New donation request from Tondo for Banana Muffin', 'ngo_donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-24 04:45:20'),
(58, 11, 'ngo', NULL, 'Your donation request for Banana Muffin has been approved.', 'donation_request_approved', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php', 0, '2025-03-24 04:45:47'),
(59, 11, 'ngo', NULL, 'Your donation request for Chocolate has been approved.', 'donation_request_approved', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php', 0, '2025-03-24 04:45:52'),
(60, 11, 'ngo', NULL, 'Your donation request for Auro Chocolate has been approved.', 'donation_request_approved', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php', 0, '2025-03-24 04:45:56'),
(61, NULL, 'admin', NULL, 'Kath Aguirre has confirmed receipt of Banana Muffin.', 'donation_completed', '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php', 1, '2025-03-24 04:46:16');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
