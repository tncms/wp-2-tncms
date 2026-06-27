<?php
/**
 * Uninstall handler.
 *
 * Removes the plugin's options. The exporter is read-only and never creates
 * content, so no posts, terms, users or media are touched.
 *
 * @package WP2TNCMS
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wp2tncms_token' );
delete_option( 'wp2tncms_exporter_enabled' );
