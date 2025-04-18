<?php
/**
 *
 * Unused Media Cleaner
 *
 * Remove unused media attachements in easiest way.
 *
 * @link              https://vishalpadhariya.github.io/
 * @since             1.0.0
 * @package           Unused_Media_cleaner
 *
 * @wordpress-plugin
 * Plugin Name:       Unused Media Cleaner
 * Plugin URI:        https://github.com/vishalpadhariya/unused-media-cleaner
 * Description:       Scans and manages unused media attachments, supports ACF, Elementor, and WPBakery.
 * Version:           1.0.0
 * Author:            Vishal Padhariya
 * Author URI:        https://vishalpadhariya.github.io/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       unused-media-cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin path.
define( 'UUMC_VERSION', '1.0.0' );
define( 'UUMC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'UUMC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Main Class.
require_once UUMC_PLUGIN_PATH . '/includes/class-unused-media-cleaner.php';

// Activation/Deactivation Hooks.
register_activation_hook( __FILE__, array( 'Unused_Media_Cleaner', 'uumc_activate' ) );
register_deactivation_hook( __FILE__, array( 'Unused_Media_Cleaner', 'uumc_deactivate' ) );

// Init the plugin.
add_action(
	'plugins_loaded',
	function () {
		new Unused_Media_Cleaner();
	}
);
