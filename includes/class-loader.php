<?php

if (! defined('ABSPATH')) {
    exit;
}

class Toptour_Core_Loader
{
    /**
     * Bootstrap plugin modules.
     */
    public static function run()
    {
        self::maybe_upgrade_database();

        $modules = self::load_modules();

        foreach ($modules as $module_class) {
            if (class_exists($module_class)) {
                $module = new $module_class();

                if (method_exists($module, 'init')) {
                    $module->init();
                }
            }
        }
    }

    /**
     * Include module files and return class names.
     *
     * @return array<int, string>
     */
    private static function load_modules()
    {
        $module_files = array(
            TOPTOUR_CORE_PATH . 'modules/offers/class-offers.php',
            TOPTOUR_CORE_PATH . 'modules/managers/class-managers.php',
            TOPTOUR_CORE_PATH . 'modules/customers/class-customers.php',
            TOPTOUR_CORE_PATH . 'modules/reservations/class-reservations.php',
        );

        foreach ($module_files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }

        return array(
            'Toptour_Module_Offers',
            'Toptour_Module_Managers',
            'Toptour_Module_Customers',
            'Toptour_Module_Reservations',
        );
    }

    /**
     * Run idempotent runtime DB upgrade for existing installs.
     */
    private static function maybe_upgrade_database()
    {
        if (! function_exists('get_option') || ! function_exists('update_option')) {
            return;
        }

        $installed_version = get_option('toptour_core_db_version', '');
        $is_missing_version = ($installed_version === false || $installed_version === '');

        $installed_version = (string) $installed_version;

        if (! $is_missing_version && ! version_compare($installed_version, TOPTOUR_CORE_DB_VERSION, '<')) {
            return;
        }

        if (! class_exists('Toptour_Core_Installer')) {
            $installer_file = TOPTOUR_CORE_PATH . 'includes/class-installer.php';

            if (file_exists($installer_file)) {
                require_once $installer_file;
            }
        }

        if (! class_exists('Toptour_Core_Installer')) {
            return;
        }

        Toptour_Core_Installer::install();
        update_option('toptour_core_db_version', TOPTOUR_CORE_DB_VERSION);
    }
}
