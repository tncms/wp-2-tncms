<?php
/**
 * Identifier resolution endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\ResourceLocator;
use WP2TNCMS\Support\Errors;
use WP2TNCMS\Support\Response;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /resolve — resolve an arbitrary identifier into a canonical resource.
 *
 * Unlike /lookup, this endpoint auto-detects the identifier kind. It accepts
 * explicit parameters (key, url, checksum, id, slug) or a single generic
 * `identifier` (alias `q`) whose shape is sniffed: source key, URL, hash,
 * numeric id or slug. The response echoes the resolved identifier alongside
 * the `{ resolved, resource, identifier, data }` envelope.
 */
final class ResolveController extends AbstractController {

	/**
	 * Resource locator.
	 *
	 * @var ResourceLocator
	 */
	private $locator;

	/**
	 * Constructor.
	 *
	 * @param ResourceLocator $locator Resource locator.
	 */
	public function __construct( ResourceLocator $locator ) {
		$this->locator = $locator;
	}

	/**
	 * Handle the resolve request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$key      = trim( (string) $request->get_param( 'key' ) );
		$url      = trim( (string) $request->get_param( 'url' ) );
		$checksum = trim( (string) $request->get_param( 'checksum' ) );
		$id       = (int) $request->get_param( 'id' );
		$slug     = trim( (string) $request->get_param( 'slug' ) );
		$type     = sanitize_key( (string) $request->get_param( 'type' ) );

		if ( '' !== $key ) {
			return $this->respond( $key, $this->locator->by_key( $key ) );
		}

		if ( '' !== $url ) {
			return $this->respond( $url, $this->locator->by_url( $url ) );
		}

		if ( '' !== $checksum ) {
			return $this->respond( $checksum, $this->locator->by_checksum( $checksum ) );
		}

		if ( $id > 0 && $this->valid_type( $type ) ) {
			return $this->respond( (string) $id, $this->locator->by_id( $type, $id ) );
		}

		if ( '' !== $slug && $this->valid_type( $type ) ) {
			$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
			return $this->respond( $slug, $this->locator->by_slug( $type, $slug, $taxonomy ) );
		}

		$identifier = trim( (string) $request->get_param( 'identifier' ) );

		if ( '' === $identifier ) {
			$identifier = trim( (string) $request->get_param( 'q' ) );
		}

		if ( '' === $identifier ) {
			return Errors::validation( __( 'An identifier is required to resolve a resource.', 'wp-2-tncms' ) );
		}

		if ( strlen( $identifier ) > 2048 ) {
			return Errors::validation( __( 'The supplied identifier is too long.', 'wp-2-tncms' ) );
		}

		return $this->respond( $identifier, $this->detect( $identifier ) );
	}

	/**
	 * Sniff an identifier's kind and resolve it.
	 *
	 * @param string $identifier Raw identifier.
	 * @return array{resource:string, data:array}|null
	 */
	private function detect( $identifier ) {
		if ( 0 === strpos( $identifier, 'wordpress:' ) ) {
			return $this->locator->by_key( $identifier );
		}

		if ( preg_match( '#^https?://#i', $identifier ) ) {
			return $this->locator->by_url( $identifier );
		}

		if ( preg_match( '/^[A-Fa-f0-9]{64}$/', $identifier ) ) {
			return $this->locator->by_hash( $identifier );
		}

		if ( ctype_digit( $identifier ) ) {
			foreach ( array( 'post', 'page', 'media', 'user', 'term' ) as $type ) {
				$result = $this->locator->by_id( $type, (int) $identifier );

				if ( null !== $result ) {
					return $result;
				}
			}

			return null;
		}

		foreach ( array( 'post', 'page', 'term', 'user' ) as $type ) {
			$result = $this->locator->by_slug( $type, $identifier );

			if ( null !== $result ) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * Whether a resource type is supported.
	 *
	 * @param string $type Resource type.
	 * @return bool
	 */
	private function valid_type( $type ) {
		return in_array( $type, array( 'post', 'page', 'media', 'user', 'term' ), true );
	}

	/**
	 * Turn a locator result into the resolve envelope or a 404.
	 *
	 * @param string                                  $identifier Resolved identifier (echoed back).
	 * @param array{resource:string, data:array}|null $result     Locator result.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function respond( $identifier, $result ) {
		if ( null === $result ) {
			return Errors::not_found( __( 'No resource matched the supplied identifier.', 'wp-2-tncms' ) );
		}

		return Response::raw(
			array(
				'resolved'   => true,
				'resource'   => $result['resource'],
				'identifier' => $identifier,
				'data'       => $result['data'],
			)
		);
	}
}
