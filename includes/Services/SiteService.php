<?php
/**
 * Site information service.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services;

use WP2TNCMS\Services\Seo\SeoManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gathers site-level metadata, counts and capabilities for export.
 */
final class SiteService {

	/**
	 * SEO manager.
	 *
	 * @var SeoManager
	 */
	private $seo;

	/**
	 * Term service (for term counts).
	 *
	 * @var TermService
	 */
	private $terms;

	/**
	 * Constructor.
	 *
	 * @param SeoManager  $seo   SEO manager.
	 * @param TermService $terms Term service.
	 */
	public function __construct( SeoManager $seo, TermService $terms ) {
		$this->seo   = $seo;
		$this->terms = $terms;
	}

	/**
	 * Build the site information payload.
	 *
	 * @return array
	 */
	public function info() {
		$uploads = wp_get_upload_dir();

		return array(
			'name'           => get_bloginfo( 'name' ),
			'description'    => get_bloginfo( 'description' ),
			'url'            => home_url(),
			'admin_url'      => admin_url(),
			'language'       => get_bloginfo( 'language' ),
			'charset'        => get_bloginfo( 'charset' ),
			'timezone'       => wp_timezone_string(),
			'versions'       => array(
				'wordpress' => get_bloginfo( 'version' ),
				'php'       => PHP_VERSION,
				'plugin'    => WP2TNCMS_VERSION,
			),
			'upload_base_url' => isset( $uploads['baseurl'] ) ? $uploads['baseurl'] : '',
			'counts'         => $this->counts(),
			'capabilities'   => array(
				'seo_provider' => $this->seo->provider(),
			),
			'api'            => array(
				'namespace' => WP2TNCMS_REST_NAMESPACE,
				'version'   => 'v1',
			),
		);
	}

	/**
	 * Resource counts used by the site resource.
	 *
	 * @return array
	 */
	private function counts() {
		$posts = wp_count_posts( 'post' );
		$pages = wp_count_posts( 'page' );
		$media = wp_count_posts( 'attachment' );

		$term_count = wp_count_terms(
			array(
				'taxonomy'   => $this->terms->taxonomies(),
				'hide_empty' => false,
			)
		);

		return array(
			'posts' => isset( $posts->publish ) ? (int) $posts->publish : 0,
			'pages' => isset( $pages->publish ) ? (int) $pages->publish : 0,
			'media' => isset( $media->inherit ) ? (int) $media->inherit : 0,
			'users' => (int) count_users()['total_users'],
			'terms' => is_wp_error( $term_count ) ? 0 : (int) $term_count,
		);
	}
}
