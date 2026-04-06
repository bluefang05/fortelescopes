-- Fortelescopes Database Schema
-- Generated on 2026-04-06 04:36:22

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for admin_activity_log
-- ----------------------------
CREATE TABLE IF NOT EXISTS `admin_activity_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` int(10) unsigned DEFAULT NULL,
  `admin_username` varchar(100) NOT NULL DEFAULT '',
  `action_key` varchar(80) NOT NULL,
  `entity_type` varchar(40) NOT NULL DEFAULT '',
  `entity_id` bigint(20) DEFAULT NULL,
  `details_json` longtext DEFAULT NULL,
  `ip_address` varchar(64) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `created_at` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin_activity_user` (`admin_user_id`),
  KEY `idx_admin_activity_action` (`action_key`),
  KEY `idx_admin_activity_entity` (`entity_type`,`entity_id`),
  KEY `idx_admin_activity_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for maintenance_task_usage
-- ----------------------------
CREATE TABLE IF NOT EXISTS `maintenance_task_usage` (
  `task_key` varchar(64) NOT NULL,
  `last_run_at` varchar(40) NOT NULL,
  `last_status` varchar(20) NOT NULL,
  `last_message` varchar(255) NOT NULL DEFAULT '',
  `run_count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`task_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for outbound_clicks
-- ----------------------------
CREATE TABLE IF NOT EXISTS `outbound_clicks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `clicked_at` varchar(40) NOT NULL,
  `click_date` date NOT NULL,
  `from_path` varchar(255) NOT NULL,
  `product_id` int(10) unsigned NOT NULL DEFAULT 0,
  `target_host` varchar(255) NOT NULL,
  `target_url` text NOT NULL,
  `country_code` varchar(8) NOT NULL DEFAULT 'UNK',
  `source_type` varchar(20) NOT NULL DEFAULT 'direct',
  `referrer_host` varchar(255) NOT NULL DEFAULT 'direct',
  PRIMARY KEY (`id`),
  KEY `idx_outbound_date` (`click_date`),
  KEY `idx_outbound_product` (`product_id`),
  KEY `idx_outbound_source` (`source_type`),
  KEY `idx_outbound_country` (`country_code`),
  KEY `idx_outbound_from_path` (`from_path`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for page_views
-- ----------------------------
CREATE TABLE IF NOT EXISTS `page_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `view_date` date NOT NULL,
  `path` varchar(255) NOT NULL,
  `page_type` varchar(40) NOT NULL,
  `page_slug` varchar(255) NOT NULL DEFAULT '',
  `product_id` int(10) unsigned NOT NULL DEFAULT 0,
  `views` int(10) unsigned NOT NULL DEFAULT 1,
  `last_viewed_at` varchar(40) NOT NULL,
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_view_daily` (`view_date`,`path`,`page_type`,`page_slug`,`product_id`),
  KEY `idx_page_views_type` (`page_type`),
  KEY `idx_page_views_product` (`product_id`),
  KEY `idx_page_views_date` (`view_date`)
) ENGINE=InnoDB AUTO_INCREMENT=1762 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for page_view_hits
-- ----------------------------
CREATE TABLE IF NOT EXISTS `page_view_hits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `viewed_at` varchar(40) NOT NULL,
  `view_date` date NOT NULL,
  `path` varchar(255) NOT NULL,
  `page_type` varchar(40) NOT NULL,
  `page_slug` varchar(255) NOT NULL DEFAULT '',
  `product_id` int(10) unsigned NOT NULL DEFAULT 0,
  `country_code` varchar(8) NOT NULL DEFAULT 'UNK',
  `referrer_host` varchar(255) NOT NULL DEFAULT 'direct',
  `source_type` varchar(20) NOT NULL DEFAULT 'direct',
  `ip_hash` varchar(64) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_hits_date` (`view_date`),
  KEY `idx_hits_country` (`country_code`),
  KEY `idx_hits_source` (`source_type`),
  KEY `idx_hits_referrer` (`referrer_host`),
  KEY `idx_hits_path` (`path`),
  KEY `idx_hits_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1762 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for posts
-- ----------------------------
CREATE TABLE IF NOT EXISTS `posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content_html` mediumtext DEFAULT NULL,
  `featured_image` text DEFAULT NULL,
  `post_type` varchar(20) NOT NULL DEFAULT 'post',
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `extra_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_data`)),
  `hero_image_url` varchar(500) DEFAULT NULL,
  `card_image_url` varchar(500) DEFAULT NULL,
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL,
  `published_at` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_posts_status` (`status`),
  KEY `idx_posts_published` (`published_at`),
  KEY `idx_posts_type` (`post_type`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for products
-- ----------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
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
  `updated_at` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asin` (`asin`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `avatar_url` text DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `last_login_at` varchar(40) DEFAULT NULL,
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_username` (`username`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
