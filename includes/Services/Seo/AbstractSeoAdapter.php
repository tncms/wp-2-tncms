<?php
/**
 * Base SEO adapter.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared behaviour for SEO adapters: the normalised, empty SEO schema.
 *
 * The schema returned here is the public, stable shape. Provider adapters fill
 * in the values they can resolve and leave the rest at their empty defaults.
 */
abstract class AbstractSeoAdapter implements SeoAdapterInterface {

	/**
	 * The normalised, empty SEO structure for this provider.
	 *
	 * @return array
	 */
	protected function empty_seo() {
		return array(
			'provider'       => $this->get_slug(),
			'title'          => '',
			'description'    => '',
			'focus_keyword'  => '',
			'canonical'      => '',
			'robots'         => array(
				'index'    => null,
				'follow'   => null,
				'advanced' => array(),
			),
			'open_graph'     => array(
				'title'       => '',
				'description' => '',
				'image'       => '',
			),
			'twitter'        => array(
				'title'       => '',
				'description' => '',
				'image'       => '',
			),
		);
	}

	/**
	 * Read a single post meta value as a trimmed string.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	protected function meta_string( $post_id, $key ) {
		$value = get_post_meta( $post_id, $key, true );

		return is_string( $value ) ? trim( $value ) : '';
	}
}
