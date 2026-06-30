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

	/**
	 * Build the rewrite hints attached to exported menus.
	 *
	 * Menu item URLs are exported verbatim, so they still point at the source
	 * site. These hints tell the importer how to rewrite the source site/home
	 * URLs onto the destination application domain without guessing. The exporter
	 * itself never rewrites a URL.
	 *
	 * @return array
	 */
	public static function menus() {
		$site = site_url();
		$home = home_url();

		$rules = array();

		foreach ( array_unique( array( $home, $site ) ) as $from ) {
			if ( '' === (string) $from ) {
				continue;
			}

			$rules[] = array(
				'from' => $from,
				'to'   => '{APP_DOMAIN}',
			);
		}

		return array(
			'site_url' => $site,
			'home_url' => $home,
			'rules'    => $rules,
		);
	}
}
