<?php
/**
 * Dependencies endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\DependencyService;
use WP2TNCMS\Support\Response;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /dependencies - lightweight author/terms/media map for posts and pages.
 *
 * Lets the importer pre-build an import plan without fetching full content.
 */
final class DependenciesController extends AbstractController {

	/**
	 * Dependency service.
	 *
	 * @var DependencyService
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param DependencyService $service Dependency service.
	 */
	public function __construct( DependencyService $service ) {
		$this->service = $service;
	}

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$resource = (string) $request->get_param( 'resource' );
		$resource = in_array( $resource, array( 'posts', 'pages' ), true ) ? $resource : '';

		$data = $this->service->build(
			$resource,
			$this->page( $request ),
			$this->per_page( $request )
		);

		return Response::raw( $data );
	}
}
