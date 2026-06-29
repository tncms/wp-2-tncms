<?php
/**
 * Shared controller for post-type backed resources.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\PostService;
use WP2TNCMS\Support\Errors;
use WP2TNCMS\Support\LookupIndex;
use WP2TNCMS\Support\Response;
use WP2TNCMS\Support\SourceKey;
use WP2TNCMS\Transformers\PostTransformer;
use WP_REST_Request;
use WP_Post;

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

		$fields = $filters['fields'];
		$items  = array();

		foreach ( $result['items'] as $post ) {
			$data = $this->transformer->transform( $post, $fields );

			// Populate the hash index during the export pass so subsequent
			// hash lookups resolve directly; single-item reads stay read-only.
			if ( isset( $data['hashes']['content'], $data['hashes']['payload'] ) ) {
				LookupIndex::remember_post( (int) $post->ID, $data['hashes']['content'], $data['hashes']['payload'] );
			}

			$items[] = $data;
		}

		return $this->paginated( $items, $result['total'], $request );
	}

	/**
	 * Handle the single-item request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond( $this->service->find( $this->post_type(), (int) $request->get_param( 'id' ) ), $request );
	}

	/**
	 * GET|HEAD /{type}/slug/{slug}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_slug( WP_REST_Request $request ) {
		return $this->respond( $this->service->find_by_slug( $this->post_type(), (string) $request->get_param( 'slug' ) ), $request );
	}

	/**
	 * GET|HEAD /{type}/key/{source_key}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_key( WP_REST_Request $request ) {
		$parsed = SourceKey::parse( (string) $request->get_param( 'source_key' ) );

		if ( null === $parsed || $parsed['resource'] !== $this->post_type() ) {
			return Errors::validation( __( 'The supplied source key is invalid for this resource.', 'wp-2-tncms' ) );
		}

		return $this->respond( $this->service->find( $this->post_type(), $parsed['id'] ), $request );
	}

	/**
	 * GET|HEAD /{type}/hash/{content_hash}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_hash( WP_REST_Request $request ) {
		$hash = strtolower( trim( (string) $request->get_param( 'content_hash' ) ) );

		if ( ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
			return Errors::validation( __( 'A content hash must be a 64-character SHA-256 hex string.', 'wp-2-tncms' ) );
		}

		return $this->respond( $this->service->find_by_content_hash( $this->post_type(), $hash ), $request );
	}

	/**
	 * Build the single-item, HEAD-aware response for a resolved post.
	 *
	 * @param WP_Post|null    $post    Resolved post or null.
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function respond( $post, WP_REST_Request $request ) {
		if ( ! $post instanceof WP_Post ) {
			return Errors::not_found( $this->not_found_message() );
		}

		if ( $this->is_head( $request ) ) {
			return $this->head_ok();
		}

		return Response::item( $this->transformer->transform( $post ) );
	}
}
