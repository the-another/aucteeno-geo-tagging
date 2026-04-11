<?php
/**
 * Plugin Name: Aucteeno Geo-Tagging
 * Plugin URI: https://theanother.org/plugin/aucteeno-geo-tagging/
 * Description: Cloudflare geo-header based filtering for Aucteeno Query Loop blocks.
 * Version: 0.1.1
 * Author: The Another
 * Author URI: https://theanother.org
 * Requires at least: 6.9
 * Requires PHP: 8.3
 * Requires Plugins: aucteeno
 * Text Domain: aucteeno-geo-tagging
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/the-another/aucteeno-geo-tagging
 * Primary Branch: master
 * Release Asset: true
 *
 * @package Aucteeno_Geo_Tagging
 * @since 0.1.0
 */

namespace The_Another\Plugin\Aucteeno_Geo_Tagging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'AUCTEENO_GEO_TAGGING_VERSION', '0.1.1' );
define( 'AUCTEENO_GEO_TAGGING_PLUGIN_FILE', __FILE__ );
define( 'AUCTEENO_GEO_TAGGING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUCTEENO_GEO_TAGGING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AUCTEENO_GEO_TAGGING_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum PHP version check.
if ( version_compare( PHP_VERSION, '8.3', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p><?php echo esc_html( 'Aucteeno Geo-Tagging requires PHP 8.3 or higher. Please upgrade your PHP version.' ); ?></p>
			</div>
			<?php
		}
	);
	return;
}

// Minimum WordPress version check.
global $wp_version;
if ( version_compare( $wp_version, '6.9', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p><?php echo esc_html( 'Aucteeno Geo-Tagging requires WordPress 6.9 or higher. Please upgrade WordPress.' ); ?></p>
			</div>
			<?php
		}
	);
	return;
}

// Autoloader. Bail entirely if missing — classmap-autoloaded classes won't be available.
if ( ! file_exists( AUCTEENO_GEO_TAGGING_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	return;
}
require_once AUCTEENO_GEO_TAGGING_PLUGIN_DIR . 'vendor/autoload.php';

// Initialize plugin after Aucteeno and Aucteeno Nexus load.
add_action(
	'before_woocommerce_init',
	function () {
		$geo_tagging = new Geo_Tagging(
			new Cloudflare_Headers(),
			new Bot_Detector()
		);
		$geo_tagging->init();
	},
	30 // Priority 30: after aucteeno (priority 10) and aucteeno-nexus (priority 20).
);
