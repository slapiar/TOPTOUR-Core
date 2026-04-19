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
            'Toptour_Reservations_Module',
        );
    }
}
