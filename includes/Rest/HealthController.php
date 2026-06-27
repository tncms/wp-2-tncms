<?php
/**
 * Health endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Support\Response;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /health - public liveness check.
 *
 * Intentionally unauthenticated and free of sensitive data so monitoring and
 * discovery can confirm the exporter is installed and responding.
 */
final class HealthController extends AbstractController {

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		return Response::raw(
			array(
				'status'    => 'ok',
				'plugin'    => 'wp-2-tncms',
				'version'   => WP2TNCMS_VERSION,
				'timestamp' => gmdate( 'c' ),
			)
		);
	}
}
