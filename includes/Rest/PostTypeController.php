<?php
/**
 * Shared controller for post-type backed resources.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\PostService;
use WP2TNCMS\Support\Errors;
use WP2TNCMS\Support\Response;
use WP2TNCMS\Transformers\PostTransformer;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backs both the posts and pages resources.
 *
 * Subclasses declare their post type and supply a matching transformer so the
 * querying, pagination and lookup logic is shared.
 */
abstract class PostTypeController extends AbstractController {

	/**
	 * Post service.
	 *
	 * @var PostService
	 */
	protected $service;

	/**
	 * Transformer for this post type.
	 *
	 * @var PostTransformer
	 */
	protected $transformer;

	/**
	 * Constructor.
	 *
	 * @param PostService     $service     Post service.
	 * @param PostTransformer $transformer Transformer for this post type.
	 */
	public function __construct( PostService $service, PostTransformer $transformer ) {
		$this->service     = $service;
		$this->transformer = $transformer;
	}

	/**
	 * Post type slug handled by this controller.
	 *
	 * @return string
	 */
	abstract protected function post_type();

	/**
	 * Message used when a single item is not found.
	 *
	 * @return string
	 */
	abstract protected function not_found_message();

	/**
	 * Handle the collection request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function index( WP_REST_Request $request ) {
		$filters = $this->filters( $request );

		$result = $this->service->paginate(
			$this->post_type(),
			$this->page( $request ),
			$this->per_page( $request ),
			$filters
		);

		$transformer = $this->transformer;
		$fields      = $filters['fields'];

		$items = array_map(
			static function ( $post ) use ( $transformer, $fields ) {
				return $transformer->transform( $post, $fields );
			},
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
		$post = $this->service->find( $this->post_type(), (int) $request->get_param( 'id' ) );

		if ( null === $post ) {
			return Errors::not_found( $this->not_found_message() );
		}

		return Response::item( $this->transformer->transform( $post ) );
	}
}
