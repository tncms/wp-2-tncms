<?php
/**
 * Response envelope builder.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Support;

use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the stable response envelopes shared across all resources.
 *
 * The envelope shapes are part of the public v1 contract and must remain
 * backward compatible. New keys may be added; existing keys must not change.
 */
final class Response {

	/**
	 * Build a paginated collection envelope.
	 *
	 * @param array $data       List of transformed items.
	 * @param array $pagination Pagination meta from Pagination::meta().
	 * @return WP_REST_Response
	 */
	public static function collection( array $data, array $pagination ) {
		return new WP_REST_Response(
			array(
				'data'       => array_values( $data ),
				'pagination' => $pagination,
			),
			200
		);
	}

	/**
	 * Build a single-item envelope.
	 *
	 * @param array $data Transformed item.
	 * @return WP_REST_Response
	 */
	public static function item( array $data ) {
		return new WP_REST_Response( array( 'data' => $data ), 200 );
	}

	/**
	 * Build a raw (non-enveloped) response, used by discovery endpoints.
	 *
	 * @param array $data Response body.
	 * @return WP_REST_Response
	 */
	public static function raw( array $data ) {
		return new WP_REST_Response( $data, 200 );
	}
}
