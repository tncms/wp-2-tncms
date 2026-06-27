<?php
/**
 * Posts endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /posts and GET /posts/{id}.
 */
final class PostsController extends PostTypeController {

	/**
	 * {@inheritDoc}
	 */
	protected function post_type() {
		return 'post';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function not_found_message() {
		return __( 'Post not found.', 'wp-2-tncms' );
	}
}
