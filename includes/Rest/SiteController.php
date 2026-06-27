<?php
/**
 * Site endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\SiteService;
use WP2TNCMS\Support\Response;
use WP2TNCMS\Transformers\SiteTransformer;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /site - site metadata, counts and capabilities.
 */
final class SiteController extends AbstractController {

	/**
	 * Site service.
	 *
	 * @var SiteService
	 */
	private $service;

	/**
	 * Site transformer.
	 *
	 * @var SiteTransformer
	 */
	private $transformer;

	/**
	 * Constructor.
	 *
	 * @param SiteService     $service     Site service.
	 * @param SiteTransformer $transformer Site transformer.
	 */
	public function __construct( SiteService $service, SiteTransformer $transformer ) {
		$this->service     = $service;
		$this->transformer = $transformer;
	}

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		return Response::item( $this->transformer->transform( $this->service->info() ) );
	}
}
