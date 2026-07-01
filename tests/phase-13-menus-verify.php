<?php
/**
 * Phase 1.3 acceptance verification harness for the WP 2 TNCMS exporter.
 *
 * Exercises the additive Menus export API (collection, single by id/slug/
 * location, the recursive item tree, resolved item metadata, source keys,
 * hashes, URL rewrite hints, manifest changes and cross-resource lookup) and
 * re-asserts backward compatibility of the existing v1.2 endpoints.
 *
 * Usage:  php wp-content/plugins/wp-2-tncms/tests/phase-13-menus-verify.php
 *
 * @package WP2TNCMS
 */

// phpcs:disable WordPress.Security, WordPress.DB, WordPress.PHP.DevelopmentFunctions

define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST']       = 'localhost';
$_SERVER['REQUEST_SCHEME']  = 'http';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

require 'C:/laragon/www/wp-2-cms/wp-load.php';

if ( ! defined( 'WP2TNCMS_VERSION' ) ) {
	require __DIR__ . '/../wp-2-tncms.php';
	WP2TNCMS\Plugin::instance()->boot();
}

$tokens = new WP2TNCMS\Auth\TokenManager();
$token  = $tokens->ensure_token();
update_option( WP2TNCMS\Auth\TokenManager::OPTION_ENABLED, true );

$server = rest_get_server();
$ns     = '/' . WP2TNCMS_REST_NAMESPACE;

$passed   = 0;
$failed   = 0;
$failures = array();

/**
 * Dispatch a REST request through the internal server.
 *
 * @param string $method HTTP method.
 * @param string $route  Route path.
 * @param string $token  Bearer token (null to omit).
 * @param array  $params Query/body params.
 * @return array{0:int,1:mixed}
 */
function w2t_call( $method, $route, $token = null, $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	if ( null !== $token ) {
		$request->set_header( 'Authorization', 'Bearer ' . $token );
	}
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	$response = rest_do_request( $request );
	return array( $response->get_status(), $response->get_data() );
}

function w2t_assert( $label, $condition ) {
	global $passed, $failed, $failures;
	if ( $condition ) {
		++$passed;
		echo "  PASS  {$label}\n";
	} else {
		++$failed;
		$failures[] = $label;
		echo "  FAIL  {$label}\n";
	}
}

/**
 * Depth-first search of a menu item tree for the first node matching a callback.
 *
 * @param array    $items Item tree.
 * @param callable $match Predicate receiving each node.
 * @return array|null
 */
function w2t_find_item( array $items, callable $match ) {
	foreach ( $items as $item ) {
		if ( $match( $item ) ) {
			return $item;
		}
		if ( ! empty( $item['children'] ) ) {
			$found = w2t_find_item( $item['children'], $match );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

// ---------------------------------------------------------------------------
// Fixtures: a page + category linked from a menu, plus a child custom link.
// ---------------------------------------------------------------------------

$slug_suffix = (string) time();

$page_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Menu Fixture Home',
		'post_name'   => 'menu-fixture-home-' . $slug_suffix,
	)
);
$page = get_post( $page_id );

$cat_id = wp_insert_term( 'Menu Fixture Cat ' . $slug_suffix, 'category' );
$cat_id = is_wp_error( $cat_id ) ? 0 : (int) $cat_id['term_id'];
$cat    = $cat_id ? get_term( $cat_id, 'category' ) : null;

$menu_name = 'Menu Fixture ' . $slug_suffix;
$menu_id   = wp_create_nav_menu( $menu_name );
$menu_id   = is_wp_error( $menu_id ) ? 0 : (int) $menu_id;
$menu      = $menu_id ? wp_get_nav_menu_object( $menu_id ) : null;
$menu_slug = $menu ? $menu->slug : '';

// Parent item -> the page; a custom-link child nested under it; a term item.
$parent_item_id = wp_update_nav_menu_item(
	$menu_id,
	0,
	array(
		'menu-item-title'     => 'Home',
		'menu-item-object'    => 'page',
		'menu-item-object-id' => $page_id,
		'menu-item-type'      => 'post_type',
		'menu-item-status'    => 'publish',
		'menu-item-position'  => 1,
	)
);

$child_item_id = wp_update_nav_menu_item(
	$menu_id,
	0,
	array(
		'menu-item-title'     => 'External',
		'menu-item-url'       => 'https://old-site.com/external-link',
		'menu-item-type'      => 'custom',
		'menu-item-object'    => 'custom',
		'menu-item-status'    => 'publish',
		'menu-item-parent-id' => $parent_item_id,
		'menu-item-position'  => 2,
	)
);

if ( $cat_id ) {
	wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-title'     => 'Category',
			'menu-item-object'    => 'category',
			'menu-item-object-id' => $cat_id,
			'menu-item-type'      => 'taxonomy',
			'menu-item-status'    => 'publish',
			'menu-item-position'  => 3,
		)
	);
}

// Assign the menu to the first registered theme location, if any.
$registered    = get_registered_nav_menus();
$location_slug = $registered ? (string) array_key_first( $registered ) : '';
$saved_locs    = get_nav_menu_locations();
if ( '' !== $location_slug ) {
	$new_locs                   = $saved_locs;
	$new_locs[ $location_slug ] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $new_locs );
}

$menu_key = 'wordpress:menu:' . $menu_id;

// ---------------------------------------------------------------------------
// 0. Backward compatibility — existing endpoints must be untouched.
// ---------------------------------------------------------------------------
echo "== 0. Backward compatibility ==\n";
foreach ( array( 'site', 'users', 'terms', 'media', 'posts', 'pages' ) as $r ) {
	list( $status ) = w2t_call( 'GET', "{$ns}/{$r}", $token );
	w2t_assert( "GET /{$r} still 200", 200 === $status );
}
list( $status ) = w2t_call( 'GET', "{$ns}/health" );
w2t_assert( 'GET /health still public 200', 200 === $status );

list( $status, $manifest ) = w2t_call( 'GET', "{$ns}/manifest", $token );
w2t_assert( 'manifest api_version still v1', isset( $manifest['api_version'] ) && 'v1' === $manifest['api_version'] );
w2t_assert( 'manifest schema_version >= 1.3', isset( $manifest['schema_version'] ) && version_compare( (string) $manifest['schema_version'], '1.3', '>=' ) );
w2t_assert( 'manifest version >= 1.3.0', isset( $manifest['version'] ) && version_compare( (string) $manifest['version'], '1.3.0', '>=' ) );
w2t_assert( 'manifest capabilities.menus = true', ! empty( $manifest['capabilities']['menus'] ) );
w2t_assert( 'manifest counts.menus present', isset( $manifest['counts']['menus'] ) && $manifest['counts']['menus'] >= 1 );
w2t_assert( 'manifest still advertises lookup', ! empty( $manifest['lookup']['supported'] ) );

$resource_names = array_map(
	static function ( $r ) {
		return $r['name'];
	},
	isset( $manifest['resources'] ) ? $manifest['resources'] : array()
);
w2t_assert( 'manifest resources includes menus', in_array( 'menus', $resource_names, true ) );

$order = isset( $manifest['import_strategy']['recommended_order'] ) ? $manifest['import_strategy']['recommended_order'] : array();
$menus_pos = array_search( 'menus', $order, true );
$pages_pos = array_search( 'pages', $order, true );
$posts_pos = array_search( 'posts', $order, true );
w2t_assert(
	'recommended_order places menus after posts/pages',
	false !== $menus_pos && false !== $pages_pos && false !== $posts_pos && $menus_pos > $pages_pos && $menus_pos > $posts_pos
);

// ---------------------------------------------------------------------------
// 1. /menus collection.
// ---------------------------------------------------------------------------
echo "\n== 1. /menus collection ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/menus", $token, array( 'per_page' => 100 ) );
w2t_assert( 'GET /menus 200', 200 === $status );
w2t_assert( 'GET /menus has data array', isset( $body['data'] ) && is_array( $body['data'] ) );
w2t_assert( 'GET /menus has pagination', isset( $body['pagination']['total'] ) );

$listed = null;
foreach ( $body['data'] as $row ) {
	if ( (int) $row['id'] === $menu_id ) {
		$listed = $row;
		break;
	}
}
w2t_assert( 'collection lists the fixture menu', null !== $listed );
w2t_assert( 'collection row has name/slug', $listed && $listed['name'] === $menu_name && $listed['slug'] === $menu_slug );
w2t_assert( 'collection row has locations array', $listed && is_array( $listed['locations'] ) );
w2t_assert( 'collection row has count', $listed && isset( $listed['count'] ) && (int) $listed['count'] >= 3 );
w2t_assert( 'collection row source key correct', $listed && isset( $listed['source']['key'] ) && $listed['source']['key'] === $menu_key );
w2t_assert( 'collection row has hashes', $listed && isset( $listed['hashes']['payload'], $listed['hashes']['content'] ) );
w2t_assert( 'collection row is a summary (no items)', $listed && ! isset( $listed['items'] ) );

// ---------------------------------------------------------------------------
// 2. /menus/{id} single + item tree.
// ---------------------------------------------------------------------------
echo "\n== 2. /menus/{id} single + tree ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/menus/{$menu_id}", $token );
w2t_assert( 'GET /menus/{id} 200', 200 === $status );
$data = isset( $body['data'] ) ? $body['data'] : array();
w2t_assert( 'single returns the menu', isset( $data['id'] ) && (int) $data['id'] === $menu_id );
w2t_assert( 'single has items array', isset( $data['items'] ) && is_array( $data['items'] ) );
w2t_assert( 'single source key correct', isset( $data['source']['key'] ) && $data['source']['key'] === $menu_key );
w2t_assert( 'single has hashes', isset( $data['hashes']['payload'], $data['hashes']['content'] ) );

// Parent-child hierarchy: the custom link is nested under the page item.
$parent_node = w2t_find_item(
	$data['items'],
	static function ( $node ) use ( $parent_item_id ) {
		return (int) $node['id'] === (int) $parent_item_id;
	}
);
w2t_assert( 'parent item present at top level', null !== $parent_node && 0 === (int) $parent_node['parent_id'] );
w2t_assert(
	'child item nested under parent',
	$parent_node && ! empty( $parent_node['children'] ) && (int) $parent_node['children'][0]['id'] === (int) $child_item_id
);
w2t_assert(
	'child preserves parent_id',
	$parent_node && ! empty( $parent_node['children'] ) && (int) $parent_node['children'][0]['parent_id'] === (int) $parent_item_id
);

// Original URL preserved + not rewritten on the custom link.
$custom_node = $parent_node && ! empty( $parent_node['children'] ) ? $parent_node['children'][0] : null;
w2t_assert( 'custom link keeps original url', $custom_node && 'https://old-site.com/external-link' === $custom_node['url'] );
w2t_assert( 'custom link is type/object custom', $custom_node && 'custom' === $custom_node['type'] && 'custom' === $custom_node['object'] );
w2t_assert( 'custom link resolved is null', $custom_node && null === $custom_node['resolved'] );

// Resolved metadata on the page-linked item.
w2t_assert(
	'page item resolved -> page source key',
	$parent_node && isset( $parent_node['resolved']['source_key'] ) && $parent_node['resolved']['source_key'] === ( 'wordpress:page:' . $page_id )
);
w2t_assert(
	'page item resolved slug + resource',
	$parent_node && 'page' === $parent_node['resolved']['resource'] && $parent_node['resolved']['slug'] === $page->post_name
);

// Resolved metadata on the category-linked item.
if ( $cat_id ) {
	$cat_node = w2t_find_item(
		$data['items'],
		static function ( $node ) use ( $cat_id ) {
			return 'taxonomy' === $node['type'] && (int) $node['object_id'] === (int) $cat_id;
		}
	);
	w2t_assert(
		'category item resolved -> term source key',
		$cat_node && isset( $cat_node['resolved']['source_key'] ) && $cat_node['resolved']['source_key'] === ( 'wordpress:term:' . $cat_id )
	);
}

// URL rewrite hints present on the full payload.
w2t_assert( 'single has url_rewrite_hints', isset( $data['url_rewrite_hints']['rules'] ) && is_array( $data['url_rewrite_hints']['rules'] ) );
w2t_assert(
	'url_rewrite_hints carry site/home + APP_DOMAIN target',
	isset( $data['url_rewrite_hints']['site_url'], $data['url_rewrite_hints']['home_url'] )
		&& ! empty( $data['url_rewrite_hints']['rules'] )
		&& '{APP_DOMAIN}' === $data['url_rewrite_hints']['rules'][0]['to']
);

// ---------------------------------------------------------------------------
// 3. /menus/slug/{slug}.
// ---------------------------------------------------------------------------
echo "\n== 3. /menus/slug/{slug} ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/menus/slug/{$menu_slug}", $token );
w2t_assert( 'GET /menus/slug 200', 200 === $status );
w2t_assert( 'slug returns the menu', isset( $body['data']['id'] ) && (int) $body['data']['id'] === $menu_id );
w2t_assert( 'slug response has item tree', isset( $body['data']['items'] ) && is_array( $body['data']['items'] ) );
list( $status ) = w2t_call( 'GET', "{$ns}/menus/slug/no-such-menu-{$slug_suffix}", $token );
w2t_assert( 'unknown slug -> 404', 404 === $status );

// ---------------------------------------------------------------------------
// 4. /menus/location/{location}.
// ---------------------------------------------------------------------------
echo "\n== 4. /menus/location/{location} ==\n";
if ( '' !== $location_slug ) {
	list( $status, $body ) = w2t_call( 'GET', "{$ns}/menus/location/{$location_slug}", $token );
	w2t_assert( 'GET /menus/location 200', 200 === $status );
	w2t_assert( 'location returns the assigned menu', isset( $body['data']['id'] ) && (int) $body['data']['id'] === $menu_id );
	w2t_assert( 'menu reports its location', isset( $body['data']['locations'] ) && in_array( $location_slug, $body['data']['locations'], true ) );
} else {
	echo "  SKIP  no registered nav menu location in active theme\n";
}
list( $status ) = w2t_call( 'GET', "{$ns}/menus/location/this-location-does-not-exist", $token );
w2t_assert( 'unknown location -> 404', 404 === $status );

// ---------------------------------------------------------------------------
// 5. Cross-resource lookup by menu key; /resolve stays content-only.
// ---------------------------------------------------------------------------
echo "\n== 5. Lookup + resolve ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/lookup", $token, array( 'key' => $menu_key ) );
w2t_assert( 'lookup ?key=wordpress:menu:{id} 200', 200 === $status );
w2t_assert( 'lookup resource=menu', isset( $body['resource'] ) && 'menu' === $body['resource'] );
w2t_assert( 'lookup data is the menu', isset( $body['data']['id'] ) && (int) $body['data']['id'] === $menu_id );

// /resolve?url=page should still resolve the page, never a menu.
list( $status, $body ) = w2t_call( 'GET', "{$ns}/resolve", $token, array( 'url' => get_permalink( $page_id ) ) );
w2t_assert( 'resolve url -> page (not menu)', 200 === $status && isset( $body['resource'] ) && 'page' === $body['resource'] );

// ---------------------------------------------------------------------------
// 6. Auth enforced on every new route.
// ---------------------------------------------------------------------------
echo "\n== 6. Auth required ==\n";
$auth_routes = array(
	"{$ns}/menus",
	"{$ns}/menus/{$menu_id}",
	"{$ns}/menus/slug/{$menu_slug}",
);
if ( '' !== $location_slug ) {
	$auth_routes[] = "{$ns}/menus/location/{$location_slug}";
}
foreach ( $auth_routes as $route ) {
	list( $status ) = w2t_call( 'GET', $route, null );
	w2t_assert( "no token -> 401 for {$route}", 401 === $status );
}

// ---------------------------------------------------------------------------
// 7. HEAD support on single menu routes.
// ---------------------------------------------------------------------------
echo "\n== 7. HEAD support ==\n";
list( $status, $head_body ) = w2t_call( 'HEAD', "{$ns}/menus/{$menu_id}", $token );
w2t_assert( 'HEAD /menus/{id} -> 200', 200 === $status );
w2t_assert( 'HEAD body empty', empty( $head_body ) );
list( $status ) = w2t_call( 'HEAD', "{$ns}/menus/{$menu_id}9999", $token );
w2t_assert( 'HEAD missing menu -> 404', 404 === $status );

// ---------------------------------------------------------------------------
// Cleanup.
// ---------------------------------------------------------------------------
if ( '' !== $location_slug ) {
	set_theme_mod( 'nav_menu_locations', $saved_locs );
}
if ( $menu_id ) {
	wp_delete_nav_menu( $menu_id );
}
wp_delete_post( $page_id, true );
if ( $cat_id ) {
	wp_delete_term( $cat_id, 'category' );
}

echo "\n========================================\n";
echo "  PASSED: {$passed}   FAILED: {$failed}\n";
echo "========================================\n";
if ( $failed > 0 ) {
	echo "Failures:\n";
	foreach ( $failures as $f ) {
		echo "  - {$f}\n";
	}
	exit( 1 );
}
exit( 0 );
