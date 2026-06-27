<?php
/**
 * Yoast SEO adapter.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalises Yoast SEO post meta into the stable SEO schema.
 */
final class YoastSeoAdapter extends AbstractSeoAdapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'yoast';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return defined( 'WPSEO_VERSION' ) || class_exists( '\WPSEO_Options' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_post_seo( $post_id ) {
		$seo = $this->empty_seo();

		$seo['title']         = $this->meta_string( $post_id, '_yoast_wpseo_title' );
		$seo['description']   = $this->meta_string( $post_id, '_yoast_wpseo_metadesc' );
		$seo['focus_keyword'] = $this->meta_string( $post_id, '_yoast_wpseo_focuskw' );
		$seo['canonical']     = $this->meta_string( $post_id, '_yoast_wpseo_canonical' );

		// Yoast stores noindex as 1 (noindex) or 2 (index); empty means default.
		$noindex = $this->meta_string( $post_id, '_yoast_wpseo_meta-robots-noindex' );
		if ( '1' === $noindex ) {
			$seo['robots']['index'] = false;
		} elseif ( '2' === $noindex ) {
			$seo['robots']['index'] = true;
		}

		if ( '1' === $this->meta_string( $post_id, '_yoast_wpseo_meta-robots-nofollow' ) ) {
			$seo['robots']['follow'] = false;
		}

		$seo['open_graph']['title']       = $this->meta_string( $post_id, '_yoast_wpseo_opengraph-title' );
		$seo['open_graph']['description'] = $this->meta_string( $post_id, '_yoast_wpseo_opengraph-description' );
		$seo['open_graph']['image']       = $this->meta_string( $post_id, '_yoast_wpseo_opengraph-image' );

		$seo['twitter']['title']       = $this->meta_string( $post_id, '_yoast_wpseo_twitter-title' );
		$seo['twitter']['description'] = $this->meta_string( $post_id, '_yoast_wpseo_twitter-description' );
		$seo['twitter']['image']       = $this->meta_string( $post_id, '_yoast_wpseo_twitter-image' );

		return $seo;
	}
}
