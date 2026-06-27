<?php
/**
 * Page transformer.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Transformers;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serialises a page into the stable pages schema.
 *
 * Pages share the post schema and additionally export hierarchy (parent) and
 * the assigned page template.
 */
final class PageTransformer extends PostTransformer {

	/**
	 * The source resource type.
	 *
	 * @return string
	 */
	protected function resource() {
		return 'page';
	}

	/**
	 * Page-specific fields.
	 *
	 * @param WP_Post $post Page object.
	 * @return array
	 */
	protected function extra( WP_Post $post ) {
		$template = get_post_meta( $post->ID, '_wp_page_template', true );

		return array(
			'parent'   => (int) $post->post_parent,
			'template' => is_string( $template ) && '' !== $template ? $template : 'default',
		);
	}

	/**
	 * Page identity fields retained in summary mode.
	 *
	 * @return string[]
	 */
	protected function summary_extra_keys() {
		return array( 'parent', 'template' );
	}
}
