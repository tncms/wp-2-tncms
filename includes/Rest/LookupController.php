<?php
/**
 * Resource lookup endpoint controller.
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
 * GET /lookup — resolve a single resource by one explicit identifier.
 *
 * Exactly one of key, url, hash, id or slug is honoured (in that precedence
 * order). The response is the `{ resource, data }` envelope; a miss returns
 * 404 and a malformed identifier returns 422.
 */
final class LookupController extends AbstractController {

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
	 * Handle the lookup request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$key  = trim( (string) $request->get_param( 'key' ) );
		$url  = trim( (string) $request->get_param( 'url' ) );
		$hash = trim( (string) $request->get_param( 'hash' ) );
		$id   = (int) $request->get_param( 'id' );
		$slug = trim( (string) $request->get_param( 'slug' ) );
		$type = sanitize_key( (string) $request->get_param( 'type' ) );

		if ( '' !== $key ) {
			return $this->respond( $this->locator->by_key( $key ) );
		}

		if ( '' !== $url ) {
			return $this->respond( $this->locator->by_url( $url ) );
		}

		if ( '' !== $hash ) {
			return $this->respond( $this->locator->by_hash( $hash ) );
		}

		if ( $id > 0 ) {
			if ( ! $this->valid_type( $type ) ) {
				return Errors::validation( __( 'A valid type is required when looking up by id.', 'wp-2-tncms' ) );
			}

			return $this->respond( $this->locator->by_id( $type, $id ) );
		}

		if ( '' !== $slug ) {
			if ( '' === $type ) {
				$type = 'post';
			}

			if ( ! $this->valid_type( $type ) ) {
				return Errors::validation( __( 'A valid type is required when looking up by slug.', 'wp-2-tncms' ) );
			}

			$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );

			return $this->respond( $this->locator->by_slug( $type, $slug, $taxonomy ) );
		}

		return Errors::validation( __( 'Provide one of: key, url, hash, id (with type) or slug (with type).', 'wp-2-tncms' ) );
	}

	/**
	 * Whether a resource type is one of the supported types.
	 *
	 * @param string $type Resource type.
	 * @return bool
	 */
	private function valid_type( $type ) {
		return in_array( $type, array( 'post', 'page', 'media', 'user', 'term', 'menu' ), true );
	}

	/**
	 * Turn a locator result into a response or a 404.
	 *
	 * @param array{resource:string, data:array}|null $result Locator result.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function respond( $result ) {
		if ( null === $result ) {
			return Errors::not_found( __( 'No resource matched the supplied identifier.', 'wp-2-tncms' ) );
		}

		return Response::raw(
			array(
				'resource' => $result['resource'],
				'data'     => $result['data'],
			)
		);
	}
}
