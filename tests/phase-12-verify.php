<?php
/**
 * Phase 1.2 acceptance verification harness for the WP 2 TNCMS exporter.
 *
 * Exercises the additive Resource Lookup API (slug / key / hash / path / login
 * lookups, /lookup, /resolve, /search, HEAD support and permalink resolution)
 * through the internal REST dispatcher, and re-asserts backward compatibility
 * of the existing endpoints.
 *
 * Usage:  php wp-content/plugins/wp-2-tncms/tests/phase-12-verify.php
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

// ---------------------------------------------------------------------------
// Fixtures.
// ---------------------------------------------------------------------------

$slug_suffix  = (string) time();
$post_content = 'WP2TNCMS lookup fixture content ' . $slug_suffix;

$post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Lookup Fixture Post',
		'post_name'    => 'lookup-fixture-post-' . $slug_suffix,
		'post_content' => $post_content,
	)
);

$page_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Lookup Fixture Page',
		'post_name'   => 'lookup-fixture-page-' . $slug_suffix,
	)
);

$post      = get_post( $post_id );
$post_slug = $post->post_name;
$page      = get_post( $page_id );
$page_slug = $page->post_name;

$content_hash = hash( 'sha256', $post_content );
$post_key     = 'wordpress:post:' . $post_id;
$page_key     = 'wordpress:page:' . $page_id;

// Media fixture: write a real file under uploads so a checksum can be computed.
$uploads     = wp_get_upload_dir();
$rel_path    = '2026/05/wp2tncms-lookup-' . $slug_suffix . '.png';
$abs_path    = trailingslashit( $uploads['basedir'] ) . $rel_path;
$file_bytes  = 'WP2TNCMS-MEDIA-' . $slug_suffix;
wp_mkdir_p( dirname( $abs_path ) );
file_put_contents( $abs_path, $file_bytes );
$file_checksum = hash_file( 'sha256', $abs_path );

$media_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'Lookup Fixture Media',
		'post_status'    => 'inherit',
	),
	$abs_path
);
update_post_meta( $media_id, '_wp_attached_file', $rel_path );
$media_key = 'wordpress:media:' . $media_id;

// Prime the lookup index by paging the collections once (this is what a normal
// export pass does; single-item lookups never write to the index).
w2t_call( 'GET', "{$ns}/posts", $token, array( 'per_page' => 100 ) );
w2t_call( 'GET', "{$ns}/media", $token, array( 'per_page' => 100 ) );

// User fixture: use the first existing user (always present).
$admin = get_users( array( 'number' => 1 ) );
$admin = $admin ? $admin[0] : null;

echo "== 0. Backward compatibility ==\n";
foreach ( array( 'site', 'users', 'terms', 'media', 'posts', 'pages' ) as $r ) {
	list( $status ) = w2t_call( 'GET', "{$ns}/{$r}", $token );
	w2t_assert( "GET /{$r} still 200", 200 === $status );
}
list( $status ) = w2t_call( 'GET', "{$ns}/health" );
w2t_assert( 'GET /health still public 200', 200 === $status );
list( $status, $manifest ) = w2t_call( 'GET', "{$ns}/manifest", $token );
w2t_assert( 'manifest schema_version = 1.2', isset( $manifest['schema_version'] ) && '1.2' === $manifest['schema_version'] );
w2t_assert( 'manifest api_version = v1', isset( $manifest['api_version'] ) && 'v1' === $manifest['api_version'] );
w2t_assert( 'manifest advertises lookup', ! empty( $manifest['lookup']['supported'] ) );

echo "\n== 1. Post / page lookup by slug ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/posts/slug/{$post_slug}", $token );
w2t_assert( 'post by slug 200', 200 === $status );
w2t_assert( 'post by slug returns the post', isset( $body['data']['id'] ) && (int) $body['data']['id'] === (int) $post_id );

list( $status, $body ) = w2t_call( 'GET', "{$ns}/pages/slug/{$page_slug}", $token );
w2t_assert( 'page by slug 200', 200 === $status );
w2t_assert( 'page by slug returns the page', isset( $body['data']['id'] ) && (int) $body['data']['id'] === (int) $page_id );

echo "\n== 2. Media lookup by path ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/media/path/{$rel_path}", $token );
w2t_assert( 'media by path 200', 200 === $status );
w2t_assert( 'media by path returns the attachment', isset( $body['data']['id'] ) && (int) $body['data']['id'] === (int) $media_id );
w2t_assert( 'media by path exposes only relative_path', isset( $body['data']['relative_path'] ) && $body['data']['relative_path'] === $rel_path );

echo "\n== 3. Key lookup ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/posts/key/{$post_key}", $token );
w2t_assert( 'post by key 200', 200 === $status && isset( $body['data']['id'] ) && (int) $body['data']['id'] === (int) $post_id );
list( $status, $body ) = w2t_call( 'GET', "{$ns}/media/key/{$media_key}", $token );
w2t_assert( 'media by key 200', 200 === $status && (int) $body['data']['id'] === (int) $media_id );
list( $status ) = w2t_call( 'GET', "{$ns}/users/key/wordpress:user:{$admin->ID}", $token );
w2t_assert( 'user by key 200', 200 === $status );

echo "\n== 4. Hash lookup ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/posts/hash/{$content_hash}", $token );
w2t_assert( 'post by content hash 200', 200 === $status && isset( $body['data']['id'] ) && (int) $body['data']['id'] === (int) $post_id );
list( $status, $body ) = w2t_call( 'GET', "{$ns}/media/checksum/{$file_checksum}", $token );
w2t_assert( 'media by checksum 200', 200 === $status && (int) $body['data']['id'] === (int) $media_id );

echo "\n== 5. Users lookup by login ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/users/login/{$admin->user_login}", $token );
w2t_assert( 'user by login 200', 200 === $status && (int) $body['data']['id'] === (int) $admin->ID );

echo "\n== 6. Terms taxonomy-aware lookup ==\n";
$cat = get_term_by( 'slug', 'uncategorized', 'category' );
if ( $cat ) {
	list( $status, $body ) = w2t_call( 'GET', "{$ns}/terms/category/{$cat->term_id}", $token );
	w2t_assert( 'term by taxonomy+id 200', 200 === $status && (int) $body['data']['id'] === (int) $cat->term_id );
	list( $status, $body ) = w2t_call( 'GET', "{$ns}/terms/category/slug/{$cat->slug}", $token );
	w2t_assert( 'term by taxonomy+slug 200', 200 === $status && (int) $body['data']['id'] === (int) $cat->term_id );
	list( $status ) = w2t_call( 'GET', "{$ns}/terms/key/wordpress:term:{$cat->term_id}", $token );
	w2t_assert( 'term by key 200', 200 === $status );
} else {
	w2t_assert( 'category fixture present', false );
}

echo "\n== 7. /lookup endpoint ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/lookup", $token, array( 'key' => $post_key ) );
w2t_assert( 'lookup by key 200', 200 === $status );
w2t_assert( 'lookup envelope has resource=post', isset( $body['resource'] ) && 'post' === $body['resource'] );
w2t_assert( 'lookup envelope has data', isset( $body['data']['id'] ) && (int) $body['data']['id'] === (int) $post_id );

list( $status, $body ) = w2t_call( 'GET', "{$ns}/lookup", $token, array( 'slug' => $post_slug, 'type' => 'post' ) );
w2t_assert( 'lookup by slug+type 200', 200 === $status && 'post' === $body['resource'] );

list( $status ) = w2t_call( 'GET', "{$ns}/lookup", $token, array( 'hash' => $content_hash ) );
w2t_assert( 'lookup by hash 200', 200 === $status );

list( $status ) = w2t_call( 'GET', "{$ns}/lookup", $token, array( 'key' => 'wordpress:post:99999999' ) );
w2t_assert( 'lookup miss 404', 404 === $status );

echo "\n== 8. /resolve endpoint ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/resolve", $token, array( 'identifier' => $post_key ) );
w2t_assert( 'resolve key 200', 200 === $status );
w2t_assert( 'resolve resolved=true', isset( $body['resolved'] ) && true === $body['resolved'] );
w2t_assert( 'resolve echoes identifier', isset( $body['identifier'] ) && $body['identifier'] === $post_key );
list( $status, $body ) = w2t_call( 'GET', "{$ns}/resolve", $token, array( 'checksum' => $file_checksum ) );
w2t_assert( 'resolve checksum -> media', 200 === $status && 'media' === $body['resource'] );

echo "\n== 9. Permalink resolution ==\n";
$post_url = get_permalink( $post_id );
list( $status, $body ) = w2t_call( 'GET', "{$ns}/lookup", $token, array( 'url' => $post_url ) );
w2t_assert( 'lookup by url 200', 200 === $status && (int) $body['data']['id'] === (int) $post_id );
list( $status, $body ) = w2t_call( 'GET', "{$ns}/resolve", $token, array( 'url' => get_permalink( $page_id ) ) );
w2t_assert( 'resolve by url -> page', 200 === $status && 'page' === $body['resource'] );

echo "\n== 10. /search endpoint ==\n";
list( $status, $body ) = w2t_call( 'GET', "{$ns}/search", $token, array( 'q' => 'Lookup Fixture', 'type' => 'post' ) );
w2t_assert( 'search 200', 200 === $status );
w2t_assert( 'search returns data array', isset( $body['data'] ) && is_array( $body['data'] ) );
w2t_assert( 'search rows are lightweight (no content)', empty( $body['data'] ) || ! isset( $body['data'][0]['content'] ) );
w2t_assert( 'search meta limit default 20', isset( $body['meta']['limit'] ) && 20 === $body['meta']['limit'] );
list( $status, $body ) = w2t_call( 'GET', "{$ns}/search", $token, array( 'q' => 'x', 'limit' => 500 ) );
w2t_assert( 'search limit clamped to 100', isset( $body['meta']['limit'] ) && 100 === $body['meta']['limit'] );
list( $status ) = w2t_call( 'GET', "{$ns}/search", $token );
w2t_assert( 'search without q -> 422', 422 === $status );

echo "\n== 11. Invalid identifiers ==\n";
list( $status ) = w2t_call( 'GET', "{$ns}/posts/slug/this-slug-does-not-exist-" . $slug_suffix, $token );
w2t_assert( 'invalid slug -> 404', 404 === $status );
list( $status ) = w2t_call( 'GET', "{$ns}/posts/key/not-a-valid-key", $token );
w2t_assert( 'invalid key -> 422', 422 === $status );
list( $status ) = w2t_call( 'GET', "{$ns}/posts/key/wordpress:page:{$page_id}", $token );
w2t_assert( 'key resource mismatch -> 422', 422 === $status );
list( $status ) = w2t_call( 'GET', "{$ns}/posts/hash/not-a-hash", $token );
w2t_assert( 'invalid hash -> 422', 422 === $status );
list( $status ) = w2t_call( 'GET', "{$ns}/media/path/../../etc/passwd", $token );
w2t_assert( 'path traversal -> 422', 422 === $status );

echo "\n== 12. HEAD support ==\n";
list( $status, $body ) = w2t_call( 'HEAD', "{$ns}/posts/{$post_id}", $token );
w2t_assert( 'HEAD /posts/{id} -> 200', 200 === $status );
w2t_assert( 'HEAD body empty', empty( $body ) );
list( $status ) = w2t_call( 'HEAD', "{$ns}/media/path/{$rel_path}", $token );
w2t_assert( 'HEAD /media/path/... -> 200', 200 === $status );
list( $status ) = w2t_call( 'HEAD', "{$ns}/posts/{$post_id}9999", $token );
w2t_assert( 'HEAD missing -> 404', 404 === $status );

echo "\n== 13. Auth still enforced on new routes ==\n";
list( $status ) = w2t_call( 'GET', "{$ns}/lookup", null, array( 'key' => $post_key ) );
w2t_assert( 'lookup without token -> 401', 401 === $status );
list( $status ) = w2t_call( 'GET', "{$ns}/posts/slug/{$post_slug}", null );
w2t_assert( 'posts/slug without token -> 401', 401 === $status );

// ---------------------------------------------------------------------------
// Cleanup.
// ---------------------------------------------------------------------------
wp_delete_post( $post_id, true );
wp_delete_post( $page_id, true );
wp_delete_attachment( $media_id, true );
if ( file_exists( $abs_path ) ) {
	@unlink( $abs_path );
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
