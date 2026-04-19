<?php
/**
 * Plugin Name: TOPTOUR Core
 * Plugin URI:  https://github.com/slapiar/TOPTOUR-Core
 * Description: Core foundation plugin for the TOPTOUR travel agency system.
 * Version:     1.0.0
 * Author:      TOPTOUR
 * Text Domain: toptour-core
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin path and URL constants.
$toptour_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
define( 'TOPTOUR_CORE_VERSION', $toptour_data['Version'] );
unset( $toptour_data );
define( 'TOPTOUR_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TOPTOUR_CORE_URL', plugin_dir_url( __FILE__ ) );

// Load the main loader.
require_once TOPTOUR_CORE_PATH . 'includes/class-toptour-loader.php';

// Initialise the plugin.
Toptour_Loader::init();
