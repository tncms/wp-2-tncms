<?php
/**
 * Manifest endpoint controller.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Services\ManifestService;
use WP2TNCMS\Support\Pagination;
use WP2TNCMS\Support\Response;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /manifest - describes the API surface, counts and import strategy.
 *
 * Lets a client discover the available resources, counts, pagination defaults,
 * deduplication capabilities and the recommended import strategy without
 * hard-coding them. Schema version 1.1 adds counts, import order/strategy,
 * resume and dedupe metadata. Schema version 1.2 advertises the additive
 * Resource Lookup API (lookup/resolve/search and per-resource lookups). All
 * original fields are preserved.
 */
final class ManifestController extends AbstractController {

	/**
	 * Manifest service.
	 *
	 * @var ManifestService
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param ManifestService $service Manifest service.
	 */
	public function __construct( ManifestService $service ) {
		$this->service = $service;
	}

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$base    = '/' . WP2TNCMS_REST_NAMESPACE;
		$uploads = wp_get_upload_dir();

		return Response::raw(
			array(
				'plugin'          => 'wp-2-tncms',
				'version'         => WP2TNCMS_VERSION,
				'api_version'     => 'v1',
				'schema_version'  => '1.2',
				'generated_at'    => gmdate( 'c' ),
				'namespace'       => WP2TNCMS_REST_NAMESPACE,
				'site'            => array(
					'url'             => site_url(),
					'home'            => home_url(),
					'upload_base_url' => isset( $uploads['baseurl'] ) ? $uploads['baseurl'] : '',
				),
				'auth'            => array(
					'type'   => 'bearer',
					'header' => 'Authorization',
				),
				'pagination'      => array(
					'default_per_page' => Pagination::DEFAULT_PER_PAGE,
					'max_per_page'     => Pagination::MAX_PER_PAGE,
				),
				'counts'          => $this->service->counts(),
				'resources'       => $this->resources( $base ),
				'import_order'    => array( 'users', 'terms', 'media', 'posts', 'pages' ),
				'resume'          => array(
					'supported'    => true,
					'strategy'     => 'page+id+modified_gmt',
					'stable_order' => 'id_asc',
				),
				'dedupe'          => array(
					'source_key'     => true,
					'payload_hash'   => true,
					'media_checksum' => true,
				),
				'media'           => array(
					'originals_only'        => true,
					'checksum'              => 'sha256',
					'content_scan_required' => false,
				),
				'lookup'          => array(
					'supported'  => true,
					'endpoints'  => array( 'lookup', 'resolve', 'search' ),
					'dimensions' => array(
						'posts' => array( 'id', 'slug', 'key', 'hash', 'url' ),
						'pages' => array( 'id', 'slug', 'key', 'hash', 'url' ),
						'terms' => array( 'id', 'taxonomy+id', 'taxonomy+slug', 'key' ),
						'media' => array( 'id', 'path', 'checksum', 'key' ),
						'users' => array( 'id', 'login', 'key' ),
					),
					'search'     => array(
						'types'         => array( 'post', 'page', 'media', 'term', 'user' ),
						'default_limit' => 20,
						'max_limit'     => 100,
					),
				),
				'import_strategy' => $this->service->import_strategy(),
			)
		);
	}

	/**
	 * The advertised resource list.
	 *
	 * @param string $base Namespace base path.
	 * @return array
	 */
	private function resources( $base ) {
		$protected = array( 'manifest', 'site', 'users', 'terms', 'media', 'posts', 'pages', 'dependencies', 'lookup', 'resolve', 'search' );
		$resources = array(
			array(
				'name'      => 'health',
				'path'      => $base . '/health',
				'protected' => false,
			),
		);

		foreach ( $protected as $name ) {
			$resources[] = array(
				'name'      => $name,
				'path'      => $base . '/' . $name,
				'protected' => true,
			);
		}

		return $resources;
	}
}
