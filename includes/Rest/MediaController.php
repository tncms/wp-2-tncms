<?php
/**
 * Media endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\MediaService;
use WP2TNCMS\Support\Errors;
use WP2TNCMS\Support\LookupIndex;
use WP2TNCMS\Support\Response;
use WP2TNCMS\Support\SourceKey;
use WP2TNCMS\Transformers\MediaTransformer;
use WP_Post;
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

		$items = array();

		foreach ( $result['items'] as $attachment ) {
			$data = $this->transformer->transform( $attachment );

			// Populate the checksum index during the export pass so subsequent
			// /media/checksum lookups resolve directly.
			if ( ! empty( $data['checksum'] ) ) {
				LookupIndex::remember_media( (int) $attachment->ID, $data['checksum'] );
			}

			$items[] = $data;
		}

		return $this->paginated( $items, $result['total'], $request );
	}

	/**
	 * Handle the single-item request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond( $this->service->find( (int) $request->get_param( 'id' ) ), $request );
	}

	/**
	 * GET|HEAD /media/path/{relative_path}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_path( WP_REST_Request $request ) {
		$path    = (string) $request->get_param( 'relative_path' );
		$decoded = rawurldecode( $path );

		// Reject traversal attempts and absolute paths (including URL-encoded
		// variants); only relative upload paths are accepted so filesystem
		// paths are never exposed.
		if ( '' === $path
			|| false !== strpos( $decoded, '..' )
			|| 0 === strpos( $decoded, '/' )
			|| preg_match( '#^[a-zA-Z]:[\\\\/]#', $decoded ) ) {
			return Errors::validation( __( 'A valid relative media path is required.', 'wp-2-tncms' ) );
		}

		return $this->respond( $this->service->find_by_relative_path( $path ), $request );
	}

	/**
	 * GET|HEAD /media/checksum/{sha256}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_checksum( WP_REST_Request $request ) {
		$checksum = strtolower( trim( (string) $request->get_param( 'sha256' ) ) );

		if ( ! preg_match( '/^[a-f0-9]{64}$/', $checksum ) ) {
			return Errors::validation( __( 'A checksum must be a 64-character SHA-256 hex string.', 'wp-2-tncms' ) );
		}

		return $this->respond( $this->service->find_by_checksum( $checksum ), $request );
	}

	/**
	 * GET|HEAD /media/key/{source_key}.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show_by_key( WP_REST_Request $request ) {
		$parsed = SourceKey::parse( (string) $request->get_param( 'source_key' ) );

		if ( null === $parsed || 'media' !== $parsed['resource'] ) {
			return Errors::validation( __( 'The supplied source key is invalid for this resource.', 'wp-2-tncms' ) );
		}

		return $this->respond( $this->service->find( $parsed['id'] ), $request );
	}

	/**
	 * Build the single-item, HEAD-aware response for a resolved attachment.
	 *
	 * @param WP_Post|null    $attachment Resolved attachment or null.
	 * @param WP_REST_Request $request    Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function respond( $attachment, WP_REST_Request $request ) {
		if ( ! $attachment instanceof WP_Post ) {
			return Errors::not_found( __( 'Media item not found.', 'wp-2-tncms' ) );
		}

		if ( $this->is_head( $request ) ) {
			return $this->head_ok();
		}

		return Response::item( $this->transformer->transform( $attachment ) );
	}
}
