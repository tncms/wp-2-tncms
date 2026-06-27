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
use WP2TNCMS\Transformers\UserTransformer;
use WP_REST_Request;

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
		$user = $this->service->find( (int) $request->get_param( 'id' ) );

		if ( null === $user ) {
			return Errors::not_found( __( 'User not found.', 'wp-2-tncms' ) );
		}

		return Response::item( $this->transformer->transform( $user ) );
	}
}
