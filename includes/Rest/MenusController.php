<?php
/**
 * Menus endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\MenuService;
use WP2TNCMS\Support\Errors;
use WP2TNCMS\Support\Response;
use WP2TNCMS\Transformers\MenuTransformer;
use WP_REST_Request;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /menus, /menus/{id}, /menus/slug/{slug} and /menus/location/{location}.
 *
 * The collection returns lightweight menu summaries; every single-menu route
 * returns the full menu with its recursive item tree. HEAD is supported on the
 * single-menu routes for existence probes.
 */
final class MenusController extends AbstractController {

	/**
	 * Menu service.
	 *
	 * @var MenuService
	 */
	private $service;

	/**
	 * Menu transformer.
	 *
	 * @var MenuTransformer
	 */
	private $transformer;

	/**
	 * Constructor.
	 *
	 * @param MenuService     $service     Menu service.
	 * @param MenuTransformer $transformer Menu transformer.
	 */
	public function __construct( MenuService $service, MenuTransformer $transformer ) {
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
			array( $this->transformer, 'transform_summary' ),
			$result['items']
		);

		return $this->paginated( $items, $result['total'], $request );
	}

	/**
	 * GET|HEAD /menus/{id}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond( $this->service->find( (int) $request->get_param( 'id' ) ), $request );
	}

	/**
	 * GET|HEAD /menus/slug/{slug}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_slug( WP_REST_Request $request ) {
		return $this->respond( $this->service->find_by_slug( (string) $request->get_param( 'slug' ) ), $request );
	}

	/**
	 * GET|HEAD /menus/location/{location}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_location( WP_REST_Request $request ) {
		return $this->respond( $this->service->find_by_location( (string) $request->get_param( 'location' ) ), $request );
	}

	/**
	 * Build the single-menu, HEAD-aware response for a resolved menu.
	 *
	 * @param WP_Term|null    $menu    Resolved menu or null.
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function respond( $menu, WP_REST_Request $request ) {
		if ( ! $menu instanceof WP_Term ) {
			return Errors::not_found( __( 'Menu not found.', 'wp-2-tncms' ) );
		}

		if ( $this->is_head( $request ) ) {
			return $this->head_ok();
		}

		return Response::item( $this->transformer->transform( $menu ) );
	}
}
