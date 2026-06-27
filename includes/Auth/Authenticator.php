<?php
/**
 * REST authentication guard.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Auth;

use WP2TNCMS\Support\Errors;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the permission callback used by every protected route.
 *
 * Accepts the token from the `Authorization: Bearer <token>` header, falling
 * back to a `token` query-string parameter for local development only.
 */
final class Authenticator {

	/**
	 * Token manager.
	 *
	 * @var TokenManager
	 */
	private $tokens;

	/**
	 * Constructor.
	 *
	 * @param TokenManager $tokens Token manager.
	 */
	public function __construct( TokenManager $tokens ) {
		$this->tokens = $tokens;
	}

	/**
	 * Permission callback for protected routes.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|\WP_Error True when authorised, WP_Error otherwise.
	 */
	public function authenticate( WP_REST_Request $request ) {
		if ( ! $this->tokens->is_enabled() ) {
			return Errors::disabled();
		}

		$candidate = $this->extract_token( $request );

		if ( ! $this->tokens->verify( $candidate ) ) {
			return Errors::unauthorized();
		}

		return true;
	}

	/**
	 * Extract the candidate token from the request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return string
	 */
	private function extract_token( WP_REST_Request $request ) {
		$header = $request->get_header( 'authorization' );

		if ( is_string( $header ) && '' !== $header
			&& preg_match( '/^\s*Bearer\s+(.+)\s*$/i', $header, $matches ) ) {
			return trim( $matches[1] );
		}

		// Query-string fallback for local testing only.
		$query = $request->get_param( 'token' );

		return is_string( $query ) ? trim( $query ) : '';
	}
}
