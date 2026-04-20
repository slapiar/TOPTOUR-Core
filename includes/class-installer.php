<?php

if (! defined('ABSPATH')) {
    exit;
}

class Toptour_Core_Installer
{
    /**
     * DB schema version.
     */
    public const DB_VERSION = '1.1.0';

    /**
     * Run installer tasks.
     */
    public static function install()
    {
        self::create_tables();
    }

    /**
     * Create or update plugin custom tables.
     */
    private static function create_tables()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $requests_table = $wpdb->prefix . 'toptour_requests';
        $request_meta_table = $wpdb->prefix . 'toptour_request_meta';
        $customers_table = $wpdb->prefix . 'toptour_customers';

        $sql_requests = "CREATE TABLE {$requests_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            offer_id BIGINT UNSIGNED NOT NULL,
            manager_user_id BIGINT UNSIGNED NULL,
            request_type VARCHAR(32) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'new',
            customer_name VARCHAR(191) NOT NULL,
            customer_email VARCHAR(191) NOT NULL,
            customer_phone VARCHAR(64) NULL,
            date_from DATE NULL,
            date_to DATE NULL,
            adults INT UNSIGNED NULL,
            children INT UNSIGNED NULL,
            persons_total INT UNSIGNED NULL,
            note TEXT NULL,
            source VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY offer_id (offer_id),
            KEY manager_user_id (manager_user_id),
            KEY status (status),
            KEY request_type (request_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $sql_request_meta = "CREATE TABLE {$request_meta_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(191) NOT NULL,
            meta_value LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY meta_key (meta_key)
        ) {$charset_collate};";

        $sql_customers = "CREATE TABLE {$customers_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(191) NOT NULL,
            name VARCHAR(191) NULL,
            phone VARCHAR(64) NULL,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            inquiry_count INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'lead',
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) {$charset_collate};";

        dbDelta($sql_requests);
        dbDelta($sql_request_meta);
        dbDelta($sql_customers);
    }
}
