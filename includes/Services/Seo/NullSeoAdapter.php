<?php
/**
 * Null SEO adapter.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fallback adapter used when no supported SEO provider is active.
 *
 * Always reports the stable, empty SEO schema so consumers can rely on a
 * consistent structure regardless of the source site.
 */
final class NullSeoAdapter extends AbstractSeoAdapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'none';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_post_seo( $post_id ) {
		return $this->empty_seo();
	}
}
