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
use WP2TNCMS\Support\SourceKey;
use WP2TNCMS\Transformers\TermTransformer;
use WP_REST_Request;
use WP_Term;

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
		return $this->respond( $this->service->find( (int) $request->get_param( 'id' ) ), $request );
	}

	/**
	 * GET|HEAD /terms/{taxonomy}/{id}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_in_taxonomy( WP_REST_Request $request ) {
		$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
		$term     = $this->service->find_in_taxonomy( $taxonomy, (int) $request->get_param( 'id' ) );

		return $this->respond( $term, $request );
	}

	/**
	 * GET|HEAD /terms/{taxonomy}/slug/{slug}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_taxonomy_slug( WP_REST_Request $request ) {
		$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
		$term     = $this->service->find_by_slug( $taxonomy, (string) $request->get_param( 'slug' ) );

		return $this->respond( $term, $request );
	}

	/**
	 * GET|HEAD /terms/key/{source_key}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_key( WP_REST_Request $request ) {
		$parsed = SourceKey::parse( (string) $request->get_param( 'source_key' ) );

		if ( null === $parsed || 'term' !== $parsed['resource'] ) {
			return Errors::validation( __( 'The supplied source key is invalid for this resource.', 'wp-2-tncms' ) );
		}

		return $this->respond( $this->service->find( $parsed['id'] ), $request );
	}

	/**
	 * Build the single-item, HEAD-aware response for a resolved term.
	 *
	 * @param WP_Term|null    $term    Resolved term or null.
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function respond( $term, WP_REST_Request $request ) {
		if ( ! $term instanceof WP_Term ) {
			return Errors::not_found( __( 'Term not found.', 'wp-2-tncms' ) );
		}

		if ( $this->is_head( $request ) ) {
			return $this->head_ok();
		}

		return Response::item( $this->transformer->transform( $term ) );
	}
}
