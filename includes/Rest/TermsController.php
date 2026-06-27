<?php
/**
 * Terms endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\TermService;
use WP2TNCMS\Support\Errors;
use WP2TNCMS\Support\Response;
use WP2TNCMS\Transformers\TermTransformer;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /terms and GET /terms/{id}.
 */
final class TermsController extends AbstractController {

	/**
	 * Term service.
	 *
	 * @var TermService
	 */
	private $service;

	/**
	 * Term transformer.
	 *
	 * @var TermTransformer
	 */
	private $transformer;

	/**
	 * Constructor.
	 *
	 * @param TermService     $service     Term service.
	 * @param TermTransformer $transformer Term transformer.
	 */
	public function __construct( TermService $service, TermTransformer $transformer ) {
		$this->service     = $service;
		$this->transformer = $transformer;
	}

	/**
	 * Handle the collection request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function index( WP_REST_Request $request ) {
		$result = $this->service->paginate( $this->page( $request ), $this->per_page( $request ), $this->filters( $request ) );

		$items = array_map(
			array( $this->transformer, 'transform' ),
			$result['items']
		);

		return $this->paginated( $items, $result['total'], $request );
	}

	/**
	 * Handle the single-item request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$term = $this->service->find( (int) $request->get_param( 'id' ) );

		if ( null === $term ) {
			return Errors::not_found( __( 'Term not found.', 'wp-2-tncms' ) );
		}

		return Response::item( $this->transformer->transform( $term ) );
	}
}
