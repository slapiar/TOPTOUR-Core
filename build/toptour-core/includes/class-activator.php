<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once TOPTOUR_CORE_PATH . 'includes/class-installer.php';

class Toptour_Core_Activator
{
    /**
     * Handle plugin activation flow.
     */
    public static function activate()
    {
        if (! self::is_wordpress_ready()) {
            return;
        }

        // Keep WooCommerce check minimal and safe.
        self::is_woocommerce_active();

        Toptour_Core_Installer::install();

        update_option('toptour_core_db_version', TOPTOUR_CORE_DB_VERSION);
    }

    /**
     * Basic guard that core WP functions are available.
     *
     * @return bool
     */
    private static function is_wordpress_ready()
    {
        return function_exists('update_option')
            && function_exists('get_option');
    }

    /**
     * Lightweight WooCommerce status check.
     *
     * @return bool
     */
    private static function is_woocommerce_active()
    {
        if (class_exists('WooCommerce')) {
            return true;
        }

        $active_plugins = (array) get_option('active_plugins', array());
        if (in_array('woocommerce/woocommerce.php', $active_plugins, true)) {
            return true;
        }

        if (is_multisite()) {
            $network_active_plugins = array_keys((array) get_site_option('active_sitewide_plugins', array()));
            return in_array('woocommerce/woocommerce.php', $network_active_plugins, true);
        }

        return false;
    }
}
