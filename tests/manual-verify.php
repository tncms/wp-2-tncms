<?php
/**
 * Manual REST verification harness for the WP 2 TNCMS exporter.
 *
 * Boots WordPress, loads the plugin, and exercises every endpoint through the
 * internal REST dispatcher (rest_do_request) so the API can be verified without
 * needing the plugin to be activated through the admin UI.
 *
 * Usage:  php wp-content/plugins/wp-2-tncms/tests/manual-verify.php
 *
 * @package WP2TNCMS
 */

// phpcs:disable WordPress.Security, WordPress.DB, WordPress.PHP.DevelopmentFunctions

define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST']       = 'localhost';
$_SERVER['REQUEST_SCHEME']  = 'http';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

require 'C:/laragon/www/ai-seo-content-writer/wp-load.php';

// Load + boot the plugin only if it is not already active (avoids redeclaring
// the bootstrap functions, which PHP early-binds at compile time).
if ( ! defined( 'WP2TNCMS_VERSION' ) ) {
	require __DIR__ . '/../wp-2-tncms.php';
	WP2TNCMS\Plugin::instance()->boot();
}

// Ensure a known token and enabled state for the test run.
$tokens = new WP2TNCMS\Auth\TokenManager();
$token  = $tokens->ensure_token();
update_option( WP2TNCMS\Auth\TokenManager::OPTION_ENABLED, true );

// Initialise the REST server (fires rest_api_init -> registers our routes).
$server = rest_get_server();

$ns       = '/' . WP2TNCMS_REST_NAMESPACE;
$passed   = 0;
$failed   = 0;
$failures = array();

/**
 * Dispatch a request and return [status, body].
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

/**
 * Assertion helper.
 */
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

echo "== Auth & error model ==\n";

list( $status ) = w2t_call( 'GET', "{$ns}/health" );
w2t_assert( 'GET /health is public (200)', 200 === $status );

list( $status ) = w2t_call( 'GET', "{$ns}/site" );
w2t_assert( 'GET /site without token -> 401', 401 === $status );

list( $status ) = w2t_call( 'GET', "{$ns}/site", 'wrong-token' );
w2t_assert( 'GET /site with bad token -> 401', 401 === $status );

list( $status ) = w2t_call( 'GET', "{$ns}/site", $token );
w2t_assert( 'GET /site with valid token -> 200', 200 === $status );

// Disabled exporter -> 403.
update_option( WP2TNCMS\Auth\TokenManager::OPTION_ENABLED, false );
list( $status ) = w2t_call( 'GET', "{$ns}/posts", $token );
w2t_assert( 'Exporter disabled -> 403', 403 === $status );
update_option( WP2TNCMS\Auth\TokenManager::OPTION_ENABLED, true );

echo "\n== Discovery ==\n";

list( $status, $body ) = w2t_call( 'GET', "{$ns}/health" );
w2t_assert( 'health status ok', isset( $body['status'] ) && 'ok' === $body['status'] );

list( $status, $body ) = w2t_call( 'GET', "{$ns}/manifest", $token );
w2t_assert( 'manifest 200', 200 === $status );
w2t_assert( 'manifest lists at least 8 resources', isset( $body['resources'] ) && count( $body['resources'] ) >= 8 );
w2t_assert( 'manifest namespace correct', isset( $body['namespace'] ) && WP2TNCMS_REST_NAMESPACE === $body['namespace'] );

echo "\n== Site ==\n";

list( $status, $body ) = w2t_call( 'GET', "{$ns}/site", $token );
w2t_assert( 'site has data envelope', isset( $body['data'] ) );
w2t_assert( 'site has counts', isset( $body['data']['counts'] ) );
w2t_assert( 'site exposes seo_provider', isset( $body['data']['capabilities']['seo_provider'] ) );
echo '       seo_provider = ' . ( $body['data']['capabilities']['seo_provider'] ?? '?' ) . "\n";
echo '       upload_base_url = ' . ( $body['data']['upload_base_url'] ?? '?' ) . "\n";

echo "\n== Collections (envelope + pagination) ==\n";

foreach ( array( 'users', 'terms', 'media', 'posts', 'pages' ) as $resource ) {
	list( $status, $body ) = w2t_call( 'GET', "{$ns}/{$resource}", $token, array( 'per_page' => 5 ) );
	$ok_env  = isset( $body['data'] ) && is_array( $body['data'] );
	$ok_page = isset( $body['pagination']['total'], $body['pagination']['per_page'], $body['pagination']['current_page'], $body['pagination']['total_pages'] );
	w2t_assert( "GET /{$resource} -> 200", 200 === $status );
	w2t_assert( "GET /{$resource} has data[] + pagination", $ok_env && $ok_page );
	$total = $body['pagination']['total'] ?? 0;
	$count = count( $body['data'] ?? array() );
	echo "       {$resource}: total={$total} returned={$count}\n";
}

echo "\n== Single items ==\n";

// Posts single + structure.
list( , $list ) = w2t_call( 'GET', "{$ns}/posts", $token, array( 'per_page' => 1 ) );
if ( ! empty( $list['data'] ) ) {
	$post_id = $list['data'][0]['id'];
	list( $status, $body ) = w2t_call( 'GET', "{$ns}/posts/{$post_id}", $token );
	w2t_assert( "GET /posts/{$post_id} -> 200", 200 === $status );
	$d = $body['data'] ?? array();
	foreach ( array( 'id', 'guid', 'author', 'comment_status', 'ping_status', 'featured_media', 'terms', 'seo', 'meta' ) as $field ) {
		w2t_assert( "post has '{$field}'", array_key_exists( $field, $d ) );
	}
	w2t_assert( 'post seo has provider', isset( $d['seo']['provider'] ) );
}

list( $status ) = w2t_call( 'GET', "{$ns}/posts/99999999", $token );
w2t_assert( 'GET /posts/{missing} -> 404', 404 === $status );

// Pages single + page-specific fields.
list( , $list ) = w2t_call( 'GET', "{$ns}/pages", $token, array( 'per_page' => 1 ) );
if ( ! empty( $list['data'] ) ) {
	$page_id = $list['data'][0]['id'];
	list( $status, $body ) = w2t_call( 'GET', "{$ns}/pages/{$page_id}", $token );
	$d = $body['data'] ?? array();
	w2t_assert( "GET /pages/{$page_id} -> 200", 200 === $status );
	w2t_assert( 'page has parent', array_key_exists( 'parent', $d ) );
	w2t_assert( 'page has template', array_key_exists( 'template', $d ) );
}

// Users: no password hashes.
list( , $body ) = w2t_call( 'GET', "{$ns}/users", $token, array( 'per_page' => 1 ) );
if ( ! empty( $body['data'] ) ) {
	$u = $body['data'][0];
	w2t_assert( 'user has id', isset( $u['id'] ) );
	w2t_assert( 'user has NO password fields', ! isset( $u['user_pass'] ) && ! isset( $u['password'] ) );
}

// Media: original-only, checksum present when file exists.
list( , $body ) = w2t_call( 'GET', "{$ns}/media", $token, array( 'per_page' => 1 ) );
if ( ! empty( $body['data'] ) ) {
	$m = $body['data'][0];
	w2t_assert( 'media has relative_path', array_key_exists( 'relative_path', $m ) );
	w2t_assert( 'media has checksum key', array_key_exists( 'checksum', $m ) );
	w2t_assert( 'media has no generated sizes', ! isset( $m['sizes'] ) );
} else {
	echo "       (no media items present)\n";
}

echo "\n================ RESULT ================\n";
echo "PASSED: {$passed}   FAILED: {$failed}\n";
if ( $failed > 0 ) {
	echo "Failures:\n - " . implode( "\n - ", $failures ) . "\n";
	exit( 1 );
}
echo "ALL CHECKS PASSED\n";
exit( 0 );
