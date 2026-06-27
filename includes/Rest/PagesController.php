<?php
/**
 * Pages endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /pages and GET /pages/{id}.
 */
final class PagesController extends PostTypeController {

	/**
	 * {@inheritDoc}
	 */
	protected function post_type() {
		return 'page';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function not_found_message() {
		return __( 'Page not found.', 'wp-2-tncms' );
	}
}
