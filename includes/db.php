<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (DB_DRIVER !== 'mysql') {
        throw new RuntimeException('DB_DRIVER must be mysql.');
    }

    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('pdo_mysql extension is not enabled on this server.');
    }

    if (DB_NAME === '' || DB_USER === '') {
        throw new RuntimeException('MySQL configuration missing: DB_NAME / DB_USER');
    }

    $hosts = array_values(array_unique(array_filter([
        trim((string) DB_HOST),
        'localhost',
        '127.0.0.1',
    ], static function (string $h): bool {
        return $h !== '';
    })));

    $lastError = null;

    foreach ($hosts as $host) {
        $dsns = [];
        $dsns[] = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        if ($host === 'localhost') {
            $dsns[] = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $host,
                DB_NAME,
                DB_CHARSET
            );
        }

        foreach ($dsns as $dsn) {
            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS);
                break 2;
            } catch (PDOException $e) {
                $lastError = $e;
            }
        }
    }

    if (!$pdo instanceof PDO) {
        $message = $lastError instanceof Throwable
            ? $lastError->getMessage()
            : 'Unknown MySQL connection error.';
        throw new RuntimeException($message);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

function init_schema(PDO $pdo): void
{
    if (DB_DRIVER !== 'mysql') {
        throw new RuntimeException('init_schema only supports mysql.');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            asin VARCHAR(32) NOT NULL UNIQUE,
            slug VARCHAR(255) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            category_slug VARCHAR(120) NOT NULL,
            category_name VARCHAR(120) NOT NULL,
            image_url TEXT NULL,
            affiliate_url TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'published\',
            last_synced_at VARCHAR(40) NULL,
            created_at VARCHAR(40) NOT NULL,
            updated_at VARCHAR(40) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS page_views (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            view_date DATE NOT NULL,
            path VARCHAR(255) NOT NULL,
            page_type VARCHAR(40) NOT NULL,
            page_slug VARCHAR(255) NOT NULL DEFAULT \'\',
            product_id INT UNSIGNED NOT NULL DEFAULT 0,
            views INT UNSIGNED NOT NULL DEFAULT 1,
            last_viewed_at VARCHAR(40) NOT NULL,
            created_at VARCHAR(40) NOT NULL,
            updated_at VARCHAR(40) NOT NULL,
            UNIQUE KEY uq_page_view_daily (view_date, path, page_type, page_slug, product_id),
            KEY idx_page_views_type (page_type),
            KEY idx_page_views_product (product_id),
            KEY idx_page_views_date (view_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS page_view_hits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            viewed_at VARCHAR(40) NOT NULL,
            view_date DATE NOT NULL,
            path VARCHAR(255) NOT NULL,
            page_type VARCHAR(40) NOT NULL,
            page_slug VARCHAR(255) NOT NULL DEFAULT \'\',
            product_id INT UNSIGNED NOT NULL DEFAULT 0,
            country_code VARCHAR(8) NOT NULL DEFAULT \'UNK\',
            referrer_host VARCHAR(255) NOT NULL DEFAULT \'direct\',
            source_type VARCHAR(20) NOT NULL DEFAULT \'direct\',
            ip_hash VARCHAR(64) NOT NULL DEFAULT \'\',
            user_agent VARCHAR(255) NOT NULL DEFAULT \'\',
            KEY idx_hits_date (view_date),
            KEY idx_hits_country (country_code),
            KEY idx_hits_source (source_type),
            KEY idx_hits_referrer (referrer_host),
            KEY idx_hits_path (path),
            KEY idx_hits_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS outbound_clicks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            clicked_at VARCHAR(40) NOT NULL,
            click_date DATE NOT NULL,
            from_path VARCHAR(255) NOT NULL,
            product_id INT UNSIGNED NOT NULL DEFAULT 0,
            target_host VARCHAR(255) NOT NULL,
            target_url TEXT NOT NULL,
            country_code VARCHAR(8) NOT NULL DEFAULT \'UNK\',
            source_type VARCHAR(20) NOT NULL DEFAULT \'direct\',
            referrer_host VARCHAR(255) NOT NULL DEFAULT \'direct\',
            KEY idx_outbound_date (click_date),
            KEY idx_outbound_product (product_id),
            KEY idx_outbound_source (source_type),
            KEY idx_outbound_country (country_code),
            KEY idx_outbound_from_path (from_path)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS posts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(255) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            excerpt TEXT NULL,
            content_html MEDIUMTEXT NULL,
            featured_image TEXT NULL,
            post_type VARCHAR(20) NOT NULL DEFAULT \'post\',
            status VARCHAR(20) NOT NULL DEFAULT \'draft\',
            meta_title VARCHAR(255) NULL,
            meta_description TEXT NULL,
            extra_data JSON NULL,
            created_at VARCHAR(40) NOT NULL,
            updated_at VARCHAR(40) NOT NULL,
            published_at VARCHAR(40) NULL,
            KEY idx_posts_type (post_type),
            KEY idx_posts_status (status),
            KEY idx_posts_published (published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            username VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) NULL,
            avatar_url TEXT NULL,
            role VARCHAR(20) NOT NULL DEFAULT \'user\',
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            last_login_at VARCHAR(40) NULL,
            created_at VARCHAR(40) NOT NULL,
            updated_at VARCHAR(40) NOT NULL,
            UNIQUE KEY uq_users_email (email),
            UNIQUE KEY uq_users_username (username),
            KEY idx_users_email (email),
            KEY idx_users_username (username),
            KEY idx_users_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_activity_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            admin_user_id INT UNSIGNED NULL,
            admin_username VARCHAR(100) NOT NULL DEFAULT \'\',
            action_key VARCHAR(80) NOT NULL,
            entity_type VARCHAR(40) NOT NULL DEFAULT \'\',
            entity_id BIGINT NULL,
            details_json LONGTEXT NULL,
            ip_address VARCHAR(64) NOT NULL DEFAULT \'\',
            user_agent VARCHAR(255) NOT NULL DEFAULT \'\',
            created_at VARCHAR(40) NOT NULL,
            KEY idx_admin_activity_user (admin_user_id),
            KEY idx_admin_activity_action (action_key),
            KEY idx_admin_activity_entity (entity_type, entity_id),
            KEY idx_admin_activity_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($usersCount === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (
                email, username, password_hash, display_name, role, status, created_at, updated_at
            ) VALUES (
                :email, :username, :password_hash, :display_name, :role, :status, :created_at, :updated_at
            )'
        );
        $now = now_iso();
        $stmt->execute([
            ':email' => 'admin@fortelescopes.local',
            ':username' => 'admin',
            ':password_hash' => password_hash('change-this-now', PASSWORD_DEFAULT),
            ':display_name' => 'Administrator',
            ':role' => 'admin',
            ':status' => 'active',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    $stmt = $pdo->prepare('SHOW COLUMNS FROM posts LIKE \'post_type\'');
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE posts ADD COLUMN post_type VARCHAR(20) NOT NULL DEFAULT \'post\' AFTER featured_image');
        $pdo->exec('ALTER TABLE posts ADD INDEX idx_posts_type (post_type)');
    }

    $stmt = $pdo->prepare('SHOW COLUMNS FROM posts LIKE \'extra_data\'');
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE posts ADD COLUMN extra_data JSON NULL AFTER meta_description');
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($count > 0) {
        return;
    }
}
