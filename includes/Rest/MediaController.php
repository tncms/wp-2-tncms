<?php
/**
 * Media endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\MediaService;
use WP2TNCMS\Support\Errors;
use WP2TNCMS\Support\Response;
use WP2TNCMS\Transformers\MediaTransformer;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /media and GET /media/{id}.
 */
final class MediaController extends AbstractController {

	/**
	 * Media service.
	 *
	 * @var MediaService
	 */
	private $service;

	/**
	 * Media transformer.
	 *
	 * @var MediaTransformer
	 */
	private $transformer;

	/**
	 * Constructor.
	 *
	 * @param MediaService     $service     Media service.
	 * @param MediaTransformer $transformer Media transformer.
	 */
	public function __construct( MediaService $service, MediaTransformer $transformer ) {
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
		$attachment = $this->service->find( (int) $request->get_param( 'id' ) );

		if ( null === $attachment ) {
			return Errors::not_found( __( 'Media item not found.', 'wp-2-tncms' ) );
		}

		return Response::item( $this->transformer->transform( $attachment ) );
	}
}
