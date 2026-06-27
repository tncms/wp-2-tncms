<?php
/**
 * Error model helpers.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Support;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralises the error model defined in 14-error-model.md.
 *
 * 401 invalid token, 403 exporter disabled, 404 resource, 422 validation,
 * 500 unexpected. All errors are returned as WP_Error so the REST server
 * renders a consistent JSON body and HTTP status.
 */
final class Errors {

	const UNAUTHORIZED = 'wp2tncms_unauthorized';
	const DISABLED     = 'wp2tncms_exporter_disabled';
	const NOT_FOUND    = 'wp2tncms_not_found';
	const VALIDATION   = 'wp2tncms_validation';
	const UNEXPECTED   = 'wp2tncms_unexpected';

	/**
	 * Build a WP_Error with an attached HTTP status.
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Human-readable message.
	 * @param int    $status  HTTP status code.
	 * @return WP_Error
	 */
	public static function make( $code, $message, $status ) {
		return new WP_Error( $code, $message, array( 'status' => (int) $status ) );
	}

	/**
	 * 401 - missing or invalid bearer token.
	 *
	 * @return WP_Error
	 */
	public static function unauthorized() {
		return self::make(
			self::UNAUTHORIZED,
			__( 'A valid bearer token is required to access this resource.', 'wp-2-tncms' ),
			401
		);
	}

	/**
	 * 403 - exporter has been disabled by an administrator.
	 *
	 * @return WP_Error
	 */
	public static function disabled() {
		return self::make(
			self::DISABLED,
			__( 'The TNCMS exporter is currently disabled.', 'wp-2-tncms' ),
			403
		);
	}

	/**
	 * 404 - requested resource does not exist.
	 *
	 * @param string $message Optional custom message.
	 * @return WP_Error
	 */
	public static function not_found( $message = '' ) {
		return self::make(
			self::NOT_FOUND,
			'' !== $message ? $message : __( 'The requested resource was not found.', 'wp-2-tncms' ),
			404
		);
	}
}
