<?php
/**
 * Users endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\UserService;
use WP2TNCMS\Support\Errors;
use WP2TNCMS\Support\Response;
use WP2TNCMS\Support\SourceKey;
use WP2TNCMS\Transformers\UserTransformer;
use WP_REST_Request;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /users and GET /users/{id}.
 */
final class UsersController extends AbstractController {

	/**
	 * User service.
	 *
	 * @var UserService
	 */
	private $service;

	/**
	 * User transformer.
	 *
	 * @var UserTransformer
	 */
	private $transformer;

	/**
	 * Constructor.
	 *
	 * @param UserService     $service     User service.
	 * @param UserTransformer $transformer User transformer.
	 */
	public function __construct( UserService $service, UserTransformer $transformer ) {
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
	 * GET|HEAD /users/login/{login}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_login( WP_REST_Request $request ) {
		return $this->respond( $this->service->find_by_login( (string) $request->get_param( 'login' ) ), $request );
	}

	/**
	 * GET|HEAD /users/key/{source_key}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_key( WP_REST_Request $request ) {
		$parsed = SourceKey::parse( (string) $request->get_param( 'source_key' ) );

		if ( null === $parsed || 'user' !== $parsed['resource'] ) {
			return Errors::validation( __( 'The supplied source key is invalid for this resource.', 'wp-2-tncms' ) );
		}

		return $this->respond( $this->service->find( $parsed['id'] ), $request );
	}

	/**
	 * Build the single-item, HEAD-aware response for a resolved user.
	 *
	 * @param WP_User|null    $user    Resolved user or null.
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function respond( $user, WP_REST_Request $request ) {
		if ( ! $user instanceof WP_User ) {
			return Errors::not_found( __( 'User not found.', 'wp-2-tncms' ) );
		}

		if ( $this->is_head( $request ) ) {
			return $this->head_ok();
		}

		return Response::item( $this->transformer->transform( $user ) );
	}
}
