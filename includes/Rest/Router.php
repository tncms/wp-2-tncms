<?php
/**
 * REST route registration and dependency wiring.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Rest;

use WP2TNCMS\Auth\Authenticator;
use WP2TNCMS\Services\DependencyService;
use WP2TNCMS\Services\ManifestService;
use WP2TNCMS\Services\MediaReferenceResolver;
use WP2TNCMS\Services\MediaService;
use WP2TNCMS\Services\PostService;
use WP2TNCMS\Services\Seo\SeoManager;
use WP2TNCMS\Services\SiteService;
use WP2TNCMS\Services\TermService;
use WP2TNCMS\Services\UserService;
use WP2TNCMS\Support\Pagination;
use WP2TNCMS\Transformers\MediaTransformer;
use WP2TNCMS\Transformers\PageTransformer;
use WP2TNCMS\Transformers\PostTransformer;
use WP2TNCMS\Transformers\SiteTransformer;
use WP2TNCMS\Transformers\TermTransformer;
use WP2TNCMS\Transformers\UserTransformer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composes controllers with their services and registers every route under
 * the stable v1 namespace.
 */
final class Router {

	/**
	 * Authenticator providing the protected permission callback.
	 *
	 * @var Authenticator
	 */
	private $auth;

	/**
	 * Constructor.
	 *
	 * @param Authenticator $auth Authenticator.
	 */
	public function __construct( Authenticator $auth ) {
		$this->auth = $auth;
	}

	/**
	 * Register all routes. Hooked to rest_api_init.
	 *
	 * @return void
	 */
	public function register_routes() {
		$namespace = WP2TNCMS_REST_NAMESPACE;

		$seo        = new SeoManager();
		$media_refs = new MediaReferenceResolver();

		$health   = new HealthController();
		$manifest = new ManifestController( new ManifestService( new TermService() ) );
		$site     = new SiteController(
			new SiteService( $seo, new TermService() ),
			new SiteTransformer()
		);
		$users    = new UsersController( new UserService(), new UserTransformer() );
		$terms    = new TermsController( new TermService(), new TermTransformer() );
		$media    = new MediaController( new MediaService(), new MediaTransformer() );
		$posts    = new PostsController( new PostService(), new PostTransformer( $seo, $media_refs ) );
		$pages    = new PagesController( new PostService(), new PageTransformer( $seo, $media_refs ) );
		$deps     = new DependenciesController( new DependencyService( new PostService(), $media_refs ) );

		// Public discovery endpoint.
		$this->register_singleton( $namespace, '/health', array( $health, 'handle' ), '__return_true' );

		// Protected discovery + site endpoints.
		$this->register_singleton( $namespace, '/manifest', array( $manifest, 'handle' ) );
		$this->register_singleton( $namespace, '/site', array( $site, 'handle' ) );

		// Protected collections.
		$this->register_collection( $namespace, '/users', $users );
		$this->register_collection( $namespace, '/terms', $terms );
		$this->register_collection( $namespace, '/media', $media );
		$this->register_collection( $namespace, '/posts', $posts );
		$this->register_collection( $namespace, '/pages', $pages );

		// Protected dependency map.
		$this->register_dependencies( $namespace, $deps );
	}

	/**
	 * Register the dependency-map endpoint.
	 *
	 * @param string                 $namespace REST namespace.
	 * @param DependenciesController  $controller Dependencies controller.
	 * @return void
	 */
	private function register_dependencies( $namespace, $controller ) {
		register_rest_route(
			$namespace,
			'/dependencies',
			array(
				'methods'             => 'GET',
				'callback'            => array( $controller, 'handle' ),
				'permission_callback' => $this->protected_permission(),
				'args'                => array_merge(
					Pagination::args(),
					array(
						'resource' => array(
							'description'       => __( 'Limit to a single resource: posts or pages.', 'wp-2-tncms' ),
							'type'              => 'string',
							'default'           => '',
							'enum'              => array( '', 'posts', 'pages' ),
							'sanitize_callback' => 'sanitize_key',
						),
					)
				),
			)
		);
	}

	/**
	 * The permission callback for protected routes.
	 *
	 * @return callable
	 */
	private function protected_permission() {
		return array( $this->auth, 'authenticate' );
	}

	/**
	 * Register a singleton (non-paginated) GET route.
	 *
	 * @param string   $namespace  REST namespace.
	 * @param string   $route      Route path.
	 * @param callable $callback   Controller callback.
	 * @param callable $permission Optional permission callback.
	 * @return void
	 */
	private function register_singleton( $namespace, $route, $callback, $permission = null ) {
		register_rest_route(
			$namespace,
			$route,
			array(
				'methods'             => 'GET',
				'callback'            => $callback,
				'permission_callback' => $permission ? $permission : $this->protected_permission(),
			)
		);
	}

	/**
	 * Register a protected collection resource with index and single routes.
	 *
	 * @param string $namespace  REST namespace.
	 * @param string $route      Base route path (e.g. /posts).
	 * @param object $controller Controller exposing index() and show().
	 * @return void
	 */
	private function register_collection( $namespace, $route, $controller ) {
		register_rest_route(
			$namespace,
			$route,
			array(
				'methods'             => 'GET',
				'callback'            => array( $controller, 'index' ),
				'permission_callback' => $this->protected_permission(),
				'args'                => Pagination::collection_args(),
			)
		);

		register_rest_route(
			$namespace,
			$route . '/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $controller, 'show' ),
				'permission_callback' => $this->protected_permission(),
				'args'                => array(
					'id' => array(
						'description'       => __( 'Unique identifier for the resource.', 'wp-2-tncms' ),
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}
}
