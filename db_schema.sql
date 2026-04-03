-- Fortelescopes Database Schema
-- Generated on 2026-04-02

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for products
-- ----------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `asin` VARCHAR(32) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `category_slug` VARCHAR(120) NOT NULL,
    `category_name` VARCHAR(120) NOT NULL,
    `price_amount` DECIMAL(10,2) NULL,
    `price_currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
    `image_url` TEXT NULL,
    `affiliate_url` TEXT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'published',
    `last_synced_at` VARCHAR(40) NULL,
    `created_at` VARCHAR(40) NOT NULL,
    `updated_at` VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for page_views
-- ----------------------------
CREATE TABLE IF NOT EXISTS `page_views` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `view_date` DATE NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `page_type` VARCHAR(40) NOT NULL,
    `page_slug` VARCHAR(255) NOT NULL DEFAULT '',
    `product_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `views` INT UNSIGNED NOT NULL DEFAULT 1,
    `last_viewed_at` VARCHAR(40) NOT NULL,
    `created_at` VARCHAR(40) NOT NULL,
    `updated_at` VARCHAR(40) NOT NULL,
    UNIQUE KEY `uq_page_view_daily` (`view_date`, `path`, `page_type`, `page_slug`, `product_id`),
    KEY `idx_page_views_type` (`page_type`),
    KEY `idx_page_views_product` (`product_id`),
    KEY `idx_page_views_date` (`view_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for page_view_hits
-- ----------------------------
CREATE TABLE IF NOT EXISTS `page_view_hits` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `viewed_at` VARCHAR(40) NOT NULL,
    `view_date` DATE NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `page_type` VARCHAR(40) NOT NULL,
    `page_slug` VARCHAR(255) NOT NULL DEFAULT '',
    `product_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `country_code` VARCHAR(8) NOT NULL DEFAULT 'UNK',
    `referrer_host` VARCHAR(255) NOT NULL DEFAULT 'direct',
    `source_type` VARCHAR(20) NOT NULL DEFAULT 'direct',
    `ip_hash` VARCHAR(64) NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
    KEY `idx_hits_date` (`view_date`),
    KEY `idx_hits_country` (`country_code`),
    KEY `idx_hits_source` (`source_type`),
    KEY `idx_hits_referrer` (`referrer_host`),
    KEY `idx_hits_path` (`path`),
    KEY `idx_hits_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for outbound_clicks
-- ----------------------------
CREATE TABLE IF NOT EXISTS `outbound_clicks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `clicked_at` VARCHAR(40) NOT NULL,
    `click_date` DATE NOT NULL,
    `from_path` VARCHAR(255) NOT NULL,
    `product_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `target_host` VARCHAR(255) NOT NULL,
    `target_url` TEXT NOT NULL,
    `country_code` VARCHAR(8) NOT NULL DEFAULT 'UNK',
    `source_type` VARCHAR(20) NOT NULL DEFAULT 'direct',
    `referrer_host` VARCHAR(255) NOT NULL DEFAULT 'direct',
    KEY `idx_outbound_date` (`click_date`),
    KEY `idx_outbound_product` (`product_id`),
    KEY `idx_outbound_source` (`source_type`),
    KEY `idx_outbound_country` (`country_code`),
    KEY `idx_outbound_from_path` (`from_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for posts
-- ----------------------------
CREATE TABLE IF NOT EXISTS `posts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `excerpt` TEXT NULL,
    `content_html` MEDIUMTEXT NULL,
    `featured_image` TEXT NULL,
    `post_type` VARCHAR(20) NOT NULL DEFAULT 'post',
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
    `meta_title` VARCHAR(255) NULL,
    `meta_description` TEXT NULL,
    `extra_data` JSON NULL,
    `created_at` VARCHAR(40) NOT NULL,
    `updated_at` VARCHAR(40) NOT NULL,
    `published_at` VARCHAR(40) NULL,
    KEY `idx_posts_type` (`post_type`),
    KEY `idx_posts_status` (`status`),
    KEY `idx_posts_published` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
