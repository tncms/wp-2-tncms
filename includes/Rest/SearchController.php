<?php
/**
 * Search endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\SearchService;
use WP2TNCMS\Support\Errors;
use WP2TNCMS\Support\Response;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /search — lightweight cross-resource search.
 *
 * Returns identity-only summary rows (never full content) so a consumer can
 * locate candidates cheaply and then fetch the full payload via the relevant
 * single-resource endpoint.
 */
final class SearchController extends AbstractController {

	/**
	 * Search service.
	 *
	 * @var SearchService
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param SearchService $service Search service.
	 */
	public function __construct( SearchService $service ) {
		$this->service = $service;
	}

	/**
	 * Handle the search request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$query = trim( (string) $request->get_param( 'q' ) );

		if ( '' === $query ) {
			return Errors::validation( __( 'A search query (q) is required.', 'wp-2-tncms' ) );
		}

		$type = sanitize_key( (string) $request->get_param( 'type' ) );

		if ( '' !== $type && ! in_array( $type, SearchService::types(), true ) ) {
			return Errors::validation( __( 'Unsupported search type.', 'wp-2-tncms' ) );
		}

		$limit = SearchService::clamp_limit( (int) $request->get_param( 'limit' ) );
		$rows  = $this->service->search( $query, $type, $limit );

		return Response::raw(
			array(
				'data' => $rows,
				'meta' => array(
					'query' => $query,
					'type'  => '' === $type ? 'all' : $type,
					'limit' => $limit,
					'count' => count( $rows ),
				),
			)
		);
	}
}
