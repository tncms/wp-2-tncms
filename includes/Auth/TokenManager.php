<?php
/**
 * Bearer token storage and verification.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the export bearer token and the global exporter switch.
 *
 * The token is generated from cryptographically secure random bytes and is
 * compared using a timing-safe comparison.
 */
final class TokenManager {

	const OPTION_TOKEN   = 'wp2tncms_token';
	const OPTION_ENABLED = 'wp2tncms_exporter_enabled';

	/**
	 * Number of random bytes used to build a token (hex-encoded => 64 chars).
	 */
	const TOKEN_BYTES = 32;

	/**
	 * Get the current token, or an empty string if none has been generated.
	 *
	 * @return string
	 */
	public function get_token() {
		return (string) get_option( self::OPTION_TOKEN, '' );
	}

	/**
	 * Ensure a token exists, generating one if necessary.
	 *
	 * @return string The existing or newly generated token.
	 */
	public function ensure_token() {
		$token = $this->get_token();

		if ( '' === $token ) {
			$token = $this->generate_token();
		}

		return $token;
	}

	/**
	 * Generate and persist a new token, replacing any existing one.
	 *
	 * @return string The new token.
	 */
	public function generate_token() {
		$token = bin2hex( random_bytes( self::TOKEN_BYTES ) );
		update_option( self::OPTION_TOKEN, $token, false );

		return $token;
	}

	/**
	 * Timing-safe verification of a candidate token.
	 *
	 * @param string $candidate Token supplied by the client.
	 * @return bool
	 */
	public function verify( $candidate ) {
		$token = $this->get_token();

		if ( '' === $token || ! is_string( $candidate ) || '' === $candidate ) {
			return false;
		}

		return hash_equals( $token, $candidate );
	}

	/**
	 * Whether the exporter is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) get_option( self::OPTION_ENABLED, true );
	}
}
