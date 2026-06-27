<?php
/**
 * SEO adapter contract.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract every SEO provider adapter must implement.
 *
 * Adapters normalise provider-specific metadata into the stable SEO schema
 * exported by the posts and pages resources.
 */
interface SeoAdapterInterface {

	/**
	 * Stable provider slug (e.g. yoast, rankmath, aioseo, none).
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Whether this provider is active on the site.
	 *
	 * @return bool
	 */
	public function is_active();

	/**
	 * Return normalised SEO metadata for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_post_seo( $post_id );
}
