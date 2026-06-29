<?php
/**
 * PHPStan bootstrap.
 *
 * Defines the plugin-level constants that are declared in the main plugin
 * bootstrap (wp-2-tncms.php) at runtime but are referenced across the
 * includes/ tree. Declaring them here lets PHPStan resolve them without
 * needing to ignore "constant not found" errors. WordPress core symbols are
 * supplied separately by php-stubs/wordpress-stubs via phpstan-wordpress.
 *
 * @package WP2TNCMS
 */

define( 'WP2TNCMS_VERSION', '1.2.0' );
define( 'WP2TNCMS_FILE', __FILE__ );
define( 'WP2TNCMS_DIR', __DIR__ . '/' );
define( 'WP2TNCMS_BASENAME', 'wp-2-tncms/wp-2-tncms.php' );
define( 'WP2TNCMS_REST_NAMESPACE', 'wp-2-tncms/v1' );
