<?php
/**
 * Plugin Name: TOPTOUR Core
 * Plugin URI:  https://toptour.sk
 * Description: Core modular foundation for TOPTOUR travel agency plugin.
 * Version:     1.1.30
 * Author:      TOPTOUR
 * Text Domain: toptour-core
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('TOPTOUR_CORE_PATH', plugin_dir_path(__FILE__));
define('TOPTOUR_CORE_URL', plugin_dir_url(__FILE__));
define('TOPTOUR_CORE_VERSION', '1.1.30');
define('TOPTOUR_CORE_DB_VERSION', '1.1.0');

require_once TOPTOUR_CORE_PATH . 'includes/class-i18n.php';
require_once TOPTOUR_CORE_PATH . 'includes/class-activator.php';
register_activation_hook(__FILE__, array('Toptour_Core_Activator', 'activate'));

require_once TOPTOUR_CORE_PATH . 'includes/class-loader.php';

Toptour_Core_Loader::run();
