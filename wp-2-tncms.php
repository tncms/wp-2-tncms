<?php
/**
 * Plugin Name:       WP 2 TNCMS Exporter
 * Plugin URI:        https://thenguyen.dev/wp-2-tncms
 * Description:       Official, export-only companion plugin for migrating WordPress content to TNCMS. Exposes a stable, versioned, read-only REST API.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            TNCMS
 * Author URI:        https://thenguyen.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-2-tncms
 *
 * @package WP2TNCMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP2TNCMS_VERSION' ) ) {
	return;
}

define( 'WP2TNCMS_VERSION', '1.2.0' );
define( 'WP2TNCMS_FILE', __FILE__ );
define( 'WP2TNCMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP2TNCMS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Public REST API namespace. v1 must remain stable for the plugin lifetime.
 */
define( 'WP2TNCMS_REST_NAMESPACE', 'wp-2-tncms/v1' );

/**
 * PSR-4 style autoloader for the WP2TNCMS namespace.
 *
 * Maps WP2TNCMS\Rest\PostsController to includes/Rest/PostsController.php.
 *
 * @param string $class Fully qualified class name.
 * @return void
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'WP2TNCMS\\';
		$length = strlen( $prefix );

		if ( 0 !== strncmp( $prefix, $class, $length ) ) {
			return;
		}

		$relative = substr( $class, $length );
		$path     = WP2TNCMS_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

/**
 * Run activation tasks: ensure an export token exists and defaults are set.
 *
 * @return void
 */
function wp2tncms_activate() {
	$token_manager = new \WP2TNCMS\Auth\TokenManager();
	$token_manager->ensure_token();

	if ( false === get_option( \WP2TNCMS\Auth\TokenManager::OPTION_ENABLED, false ) ) {
		add_option( \WP2TNCMS\Auth\TokenManager::OPTION_ENABLED, true );
	}
}
register_activation_hook( __FILE__, 'wp2tncms_activate' );

/**
 * Boot the plugin once all plugins are loaded.
 *
 * @return void
 */
function wp2tncms_boot() {
	\WP2TNCMS\Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'wp2tncms_boot' );
