<?php
/**
 * Media URL rewrite hints builder.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds explicit URL rewrite hints so the importer can rewrite uploaded media
 * URLs (e.g. `https://site/wp-content/uploads/` -> `/uploads/`) without guessing.
 */
final class UrlRewriteHints {

	/**
	 * Build the rewrite hints for the current site.
	 *
	 * @return array
	 */
	public static function build() {
		$uploads = wp_get_upload_dir();
		$base    = isset( $uploads['baseurl'] ) ? $uploads['baseurl'] : '';

		return array(
			'upload_base_url'  => $base,
			'target_base_path' => '/uploads',
			'rules'            => array(
				array(
					'from' => '' !== $base ? trailingslashit( $base ) : '',
					'to'   => '/uploads/',
				),
			),
		);
	}
}
