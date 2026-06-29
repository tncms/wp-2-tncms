<?php
/**
 * Base REST controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Support\CollectionQuery;
use WP2TNCMS\Support\Pagination;
use WP2TNCMS\Support\Response;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helpers for resource controllers: pagination parsing and envelopes.
 */
abstract class AbstractController {

	/**
	 * Resolve the requested page number.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return int
	 */
	protected function page( WP_REST_Request $request ) {
		return max( 1, (int) $request->get_param( 'page' ) );
	}

	/**
	 * Resolve the requested per-page size, clamped to the allowed range.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return int
	 */
	protected function per_page( WP_REST_Request $request ) {
		$per_page = (int) $request->get_param( 'per_page' );

		if ( $per_page < 1 ) {
			$per_page = Pagination::DEFAULT_PER_PAGE;
		}

		return min( $per_page, Pagination::MAX_PER_PAGE );
	}

	/**
	 * Parse the optional ordering, resume and filtering parameters.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return array
	 */
	protected function filters( WP_REST_Request $request ) {
		return CollectionQuery::from_request( $request );
	}

	/**
	 * Build a paginated collection response from a service result.
	 *
	 * @param array           $items       Transformed items.
	 * @param int             $total       Total items across all pages.
	 * @param WP_REST_Request $request     Incoming request.
	 * @return \WP_REST_Response
	 */
	protected function paginated( array $items, $total, WP_REST_Request $request ) {
		$pagination = Pagination::meta(
			$total,
			count( $items ),
			$this->page( $request ),
			$this->per_page( $request )
		);

		return Response::collection( $items, $pagination );
	}

	/**
	 * Whether the incoming request is a HEAD request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool
	 */
	protected function is_head( WP_REST_Request $request ) {
		return 'HEAD' === strtoupper( (string) $request->get_method() );
	}

	/**
	 * Build an empty 200 response for a HEAD request.
	 *
	 * Lets single-resource handlers confirm existence and return headers only,
	 * skipping payload transformation (and any file hashing) for HEAD probes.
	 *
	 * @return WP_REST_Response
	 */
	protected function head_ok() {
		return new WP_REST_Response( null, 200 );
	}
}
