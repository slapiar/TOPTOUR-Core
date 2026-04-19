<?php

if (! defined('ABSPATH')) {
    exit;
}

class Toptour_Reservations_Module
{
    /**
     * @var string
     */
    private $table_suffix = 'toptour_reservations';

    /**
     * Initialize reservations module.
     */
    public function init()
    {
        $this->register_hooks();
    }

    /**
     * Register module hooks.
     */
    public function register_hooks()
    {
        // Table installation is intentionally not executed yet.
    }

    /**
     * Get reservations table name with WP prefix.
     *
     * @return string
     */
    public function get_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . $this->table_suffix;
    }

    /**
     * Get SQL schema for reservations table.
     *
     * @return string
     */
    public function get_table_schema()
    {
        $table_name = $this->get_table_name();

        return "CREATE TABLE {$table_name} (
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
        )";
    }
}
