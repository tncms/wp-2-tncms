<?php
/**
 * Rank Math SEO adapter.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalises Rank Math post meta into the stable SEO schema.
 */
final class RankMathSeoAdapter extends AbstractSeoAdapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'rankmath';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath\Helper' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_post_seo( $post_id ) {
		$seo = $this->empty_seo();

		$seo['title']         = $this->meta_string( $post_id, 'rank_math_title' );
		$seo['description']   = $this->meta_string( $post_id, 'rank_math_description' );
		$seo['focus_keyword'] = $this->meta_string( $post_id, 'rank_math_focus_keyword' );
		$seo['canonical']     = $this->meta_string( $post_id, 'rank_math_canonical_url' );

		$robots = get_post_meta( $post_id, 'rank_math_robots', true );
		$robots = is_array( $robots ) ? $robots : array();

		if ( in_array( 'noindex', $robots, true ) ) {
			$seo['robots']['index'] = false;
		} elseif ( in_array( 'index', $robots, true ) ) {
			$seo['robots']['index'] = true;
		}

		if ( in_array( 'nofollow', $robots, true ) ) {
			$seo['robots']['follow'] = false;
		} elseif ( in_array( 'follow', $robots, true ) ) {
			$seo['robots']['follow'] = true;
		}

		$seo['robots']['advanced'] = array_values(
			array_diff( $robots, array( 'index', 'noindex', 'follow', 'nofollow' ) )
		);

		$seo['open_graph']['title']       = $this->meta_string( $post_id, 'rank_math_facebook_title' );
		$seo['open_graph']['description'] = $this->meta_string( $post_id, 'rank_math_facebook_description' );
		$seo['open_graph']['image']       = $this->meta_string( $post_id, 'rank_math_facebook_image' );

		$seo['twitter']['title']       = $this->meta_string( $post_id, 'rank_math_twitter_title' );
		$seo['twitter']['description'] = $this->meta_string( $post_id, 'rank_math_twitter_description' );
		$seo['twitter']['image']       = $this->meta_string( $post_id, 'rank_math_twitter_image' );

		return $seo;
	}
}
