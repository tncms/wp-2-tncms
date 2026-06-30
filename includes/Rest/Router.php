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
use WP2TNCMS\Services\MenuService;
use WP2TNCMS\Services\PostService;
use WP2TNCMS\Services\ResourceLocator;
use WP2TNCMS\Services\SearchService;
use WP2TNCMS\Services\Seo\SeoManager;
use WP2TNCMS\Services\SiteService;
use WP2TNCMS\Services\TermService;
use WP2TNCMS\Services\UserService;
use WP2TNCMS\Support\Pagination;
use WP2TNCMS\Transformers\MediaTransformer;
use WP2TNCMS\Transformers\MenuTransformer;
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

		// Shared service + transformer instances reused by the lookup engine.
		$post_service      = new PostService();
		$media_service     = new MediaService();
		$term_service      = new TermService();
		$user_service      = new UserService();
		$menu_service      = new MenuService();
		$post_transformer  = new PostTransformer( $seo, $media_refs );
		$page_transformer  = new PageTransformer( $seo, $media_refs );
		$media_transformer = new MediaTransformer();
		$term_transformer  = new TermTransformer();
		$user_transformer  = new UserTransformer();
		$menu_transformer  = new MenuTransformer( $menu_service );

		$locator = new ResourceLocator(
			$post_service,
			$media_service,
			$term_service,
			$user_service,
			$menu_service,
			$post_transformer,
			$page_transformer,
			$media_transformer,
			$term_transformer,
			$user_transformer,
			$menu_transformer
		);

		$health   = new HealthController();
		$manifest = new ManifestController( new ManifestService( new TermService() ) );
		$site     = new SiteController(
			new SiteService( $seo, new TermService() ),
			new SiteTransformer()
		);
		$users    = new UsersController( $user_service, $user_transformer );
		$terms    = new TermsController( $term_service, $term_transformer );
		$media    = new MediaController( $media_service, $media_transformer );
		$posts    = new PostsController( $post_service, $post_transformer );
		$pages    = new PagesController( $post_service, $page_transformer );
		$menus    = new MenusController( $menu_service, $menu_transformer );
		$deps     = new DependenciesController( new DependencyService( $post_service, $media_refs ) );

		$lookup  = new LookupController( $locator );
		$resolve = new ResolveController( $locator );
		$search  = new SearchController( new SearchService( $term_service ) );

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
		$this->register_collection( $namespace, '/menus', $menus );

		// Protected lookup routes (additive; collections registered first so
		// the numeric-id routes keep priority).
		$this->register_post_lookups( $namespace, '/posts', $posts );
		$this->register_post_lookups( $namespace, '/pages', $pages );
		$this->register_term_lookups( $namespace, $terms );
		$this->register_media_lookups( $namespace, $media );
		$this->register_user_lookups( $namespace, $users );
		$this->register_menu_lookups( $namespace, $menus );

		// Protected cross-resource endpoints.
		$this->register_singleton( $namespace, '/lookup', array( $lookup, 'handle' ), null, array( 'GET', 'HEAD' ) );
		$this->register_singleton( $namespace, '/resolve', array( $resolve, 'handle' ), null, array( 'GET', 'HEAD' ) );
		$this->register_singleton( $namespace, '/search', array( $search, 'handle' ) );

		// Protected dependency map.
		$this->register_dependencies( $namespace, $deps );
	}

	/**
	 * Register the slug/key/hash lookup routes for a post-type resource.
	 *
	 * @param string             $namespace REST namespace.
	 * @param string             $base      Base route path (e.g. /posts).
	 * @param PostTypeController $controller Posts or pages controller.
	 * @return void
	 */
	private function register_post_lookups( $namespace, $base, $controller ) {
		$this->register_lookup_route( $namespace, $base . '/slug/(?P<slug>[^/]+)', array( $controller, 'show_by_slug' ), 'slug' );
		$this->register_lookup_route( $namespace, $base . '/key/(?P<source_key>[^/]+)', array( $controller, 'show_by_key' ), 'source_key' );
		$this->register_lookup_route( $namespace, $base . '/hash/(?P<content_hash>[^/]+)', array( $controller, 'show_by_hash' ), 'content_hash' );
	}

	/**
	 * Register taxonomy-aware and key lookup routes for terms.
	 *
	 * @param string          $namespace  REST namespace.
	 * @param TermsController $controller Terms controller.
	 * @return void
	 */
	private function register_term_lookups( $namespace, $controller ) {
		// Source key first so /terms/key/... is never read as a taxonomy.
		$this->register_lookup_route( $namespace, '/terms/key/(?P<source_key>[^/]+)', array( $controller, 'show_by_key' ), 'source_key' );
		$this->register_lookup_route( $namespace, '/terms/(?P<taxonomy>[a-z][a-z0-9_-]*)/slug/(?P<slug>[^/]+)', array( $controller, 'show_by_taxonomy_slug' ), 'slug', 'taxonomy' );
		$this->register_lookup_route( $namespace, '/terms/(?P<taxonomy>[a-z][a-z0-9_-]*)/(?P<id>\d+)', array( $controller, 'show_in_taxonomy' ), 'id', 'taxonomy' );
	}

	/**
	 * Register path/checksum/key lookup routes for media.
	 *
	 * @param string          $namespace  REST namespace.
	 * @param MediaController $controller Media controller.
	 * @return void
	 */
	private function register_media_lookups( $namespace, $controller ) {
		// relative_path may contain multiple segments, so capture greedily.
		$this->register_lookup_route( $namespace, '/media/path/(?P<relative_path>.+)', array( $controller, 'show_by_path' ), 'relative_path' );
		$this->register_lookup_route( $namespace, '/media/checksum/(?P<sha256>[^/]+)', array( $controller, 'show_by_checksum' ), 'sha256' );
		$this->register_lookup_route( $namespace, '/media/key/(?P<source_key>[^/]+)', array( $controller, 'show_by_key' ), 'source_key' );
	}

	/**
	 * Register login/key lookup routes for users.
	 *
	 * @param string          $namespace  REST namespace.
	 * @param UsersController $controller Users controller.
	 * @return void
	 */
	private function register_user_lookups( $namespace, $controller ) {
		$this->register_lookup_route( $namespace, '/users/login/(?P<login>[^/]+)', array( $controller, 'show_by_login' ), 'login' );
		$this->register_lookup_route( $namespace, '/users/key/(?P<source_key>[^/]+)', array( $controller, 'show_by_key' ), 'source_key' );
	}

	/**
	 * Register slug/location lookup routes for menus.
	 *
	 * The numeric-id route (registered by the collection) only matches digits,
	 * so the string slug/location routes never collide with it.
	 *
	 * @param string          $namespace  REST namespace.
	 * @param MenusController $controller Menus controller.
	 * @return void
	 */
	private function register_menu_lookups( $namespace, $controller ) {
		$this->register_lookup_route( $namespace, '/menus/slug/(?P<slug>[^/]+)', array( $controller, 'show_by_slug' ), 'slug' );
		$this->register_lookup_route( $namespace, '/menus/location/(?P<location>[^/]+)', array( $controller, 'show_by_location' ), 'location' );
	}

	/**
	 * Register a single GET|HEAD lookup route with string path arguments.
	 *
	 * @param string   $namespace   REST namespace.
	 * @param string   $route       Route pattern with named groups.
	 * @param callable $callback    Controller callback.
	 * @param string   $primary     The primary string argument name.
	 * @param string   $taxonomy    Optional taxonomy argument name.
	 * @return void
	 */
	private function register_lookup_route( $namespace, $route, $callback, $primary, $taxonomy = '' ) {
		$args = array(
			$primary => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		if ( '' !== $taxonomy ) {
			$args[ $taxonomy ] = array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_key',
			);
		}

		register_rest_route(
			$namespace,
			$route,
			array(
				'methods'             => array( 'GET', 'HEAD' ),
				'callback'            => $callback,
				'permission_callback' => $this->protected_permission(),
				'args'                => $args,
			)
		);
	}

	/**
	 * Register the dependency-map endpoint.
	 *
	 * @param string                 $namespace REST namespace.
	 * @param DependenciesController $controller Dependencies controller.
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
	 * @param string       $namespace  REST namespace.
	 * @param string       $route      Route path.
	 * @param callable     $callback   Controller callback.
	 * @param callable     $permission Optional permission callback.
	 * @param string|array $methods    Allowed HTTP methods.
	 * @return void
	 */
	private function register_singleton( $namespace, $route, $callback, $permission = null, $methods = 'GET' ) {
		register_rest_route(
			$namespace,
			$route,
			array(
				'methods'             => $methods,
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
				'methods'             => array( 'GET', 'HEAD' ),
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
