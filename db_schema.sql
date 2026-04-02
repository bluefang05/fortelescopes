-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 02, 2026 at 12:00 PM
-- Server version: 10.11.16-MariaDB-cll-lve-log
-- PHP Version: 8.4.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aspierd1_fortelescopes`
--

-- --------------------------------------------------------

--
-- Table structure for table `outbound_clicks`
--

CREATE TABLE `outbound_clicks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clicked_at` varchar(40) NOT NULL,
  `click_date` date NOT NULL,
  `from_path` varchar(255) NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `target_host` varchar(255) NOT NULL,
  `target_url` text NOT NULL,
  `country_code` varchar(8) NOT NULL DEFAULT 'UNK',
  `source_type` varchar(20) NOT NULL DEFAULT 'direct',
  `referrer_host` varchar(255) NOT NULL DEFAULT 'direct'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `page_views`
--

CREATE TABLE `page_views` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `view_date` date NOT NULL,
  `path` varchar(255) NOT NULL,
  `page_type` varchar(40) NOT NULL,
  `page_slug` varchar(255) NOT NULL DEFAULT '',
  `product_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `views` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `last_viewed_at` varchar(40) NOT NULL,
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `page_view_hits`
--

CREATE TABLE `page_view_hits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `viewed_at` varchar(40) NOT NULL,
  `view_date` date NOT NULL,
  `path` varchar(255) NOT NULL,
  `page_type` varchar(40) NOT NULL,
  `page_slug` varchar(255) NOT NULL DEFAULT '',
  `product_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `country_code` varchar(8) NOT NULL DEFAULT 'UNK',
  `referrer_host` varchar(255) NOT NULL DEFAULT 'direct',
  `source_type` varchar(20) NOT NULL DEFAULT 'direct',
  `ip_hash` varchar(64) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content_html` mediumtext DEFAULT NULL,
  `featured_image` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `hero_image_url` varchar(500) DEFAULT NULL,
  `card_image_url` varchar(500) DEFAULT NULL,
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL,
  `published_at` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `asin` varchar(32) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category_slug` varchar(120) NOT NULL,
  `category_name` varchar(120) NOT NULL,
  `price_amount` decimal(10,2) DEFAULT NULL,
  `price_currency` varchar(10) NOT NULL DEFAULT 'USD',
  `image_url` text DEFAULT NULL,
  `affiliate_url` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'published',
  `last_synced_at` varchar(40) DEFAULT NULL,
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guides`
--

CREATE TABLE `guides` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `focus` varchar(50) DEFAULT 'telescopes',
  `intro` text DEFAULT NULL,
  `final_recommendation` text DEFAULT NULL,
  `cta_text` varchar(100) DEFAULT 'Check Price on Amazon',
  `cta_note` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
