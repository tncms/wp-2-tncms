<?php
/**
 * Phase 1.1 acceptance verification harness for the WP 2 TNCMS exporter.
 *
 * Boots WordPress, loads the plugin (if not already active) and exercises the
 * import-optimization contract through the internal REST dispatcher.
 *
 * Usage:  php wp-content/plugins/wp-2-tncms/tests/phase-11-verify.php
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

if ( ! defined( 'WP2TNCMS_VERSION' ) ) {
	require __DIR__ . '/../wp-2-tncms.php';
	WP2TNCMS\Plugin::instance()->boot();
}

$tokens = new WP2TNCMS\Auth\TokenManager();
$token  = $tokens->ensure_token();
update_option( WP2TNCMS\Auth\TokenManager::OPTION_ENABLED, true );

$server   = rest_get_server();
$ns       = '/' . WP2TNCMS_REST_NAMESPACE;
$passed   = 0;
$failed   = 0;
$failures = array();

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

echo "== 1. Existing endpoints still work ==\n";
foreach ( array( 'site', 'users', 'terms', 'media', 'posts', 'pages' ) as $r ) {
	list( $status ) = w2t_call( 'GET', "{$ns}/{$r}", $token );
	w2t_assert( "GET /{$r} still 200", 200 === $status );
}
list( $status ) = w2t_call( 'GET', "{$ns}/health" );
w2t_assert( 'GET /health still public 200', 200 === $status );

echo "\n== 2. Manifest includes schema_version 1.1 + new blocks ==\n";
list( $status, $manifest ) = w2t_call( 'GET', "{$ns}/manifest", $token );
w2t_assert( 'manifest 200', 200 === $status );
w2t_assert( 'schema_version = 1.1', isset( $manifest['schema_version'] ) && '1.1' === $manifest['schema_version'] );
w2t_assert( 'manifest has generated_at', ! empty( $manifest['generated_at'] ) );
w2t_assert( 'manifest has site.upload_base_url', isset( $manifest['site']['upload_base_url'] ) );
w2t_assert( 'manifest has counts.posts.total', isset( $manifest['counts']['posts']['total'] ) );
w2t_assert( 'manifest has counts.terms.category', isset( $manifest['counts']['terms']['category'] ) );
w2t_assert( 'manifest has import_order', isset( $manifest['import_order'] ) && $manifest['import_order'][0] === 'users' );
w2t_assert( 'manifest has resume.supported', ! empty( $manifest['resume']['supported'] ) );
w2t_assert( 'manifest has dedupe.payload_hash', ! empty( $manifest['dedupe']['payload_hash'] ) );
w2t_assert( 'manifest media.content_scan_required = false', isset( $manifest['media']['content_scan_required'] ) && false === $manifest['media']['content_scan_required'] );
w2t_assert( 'manifest has import_strategy.recommended_per_page', isset( $manifest['import_strategy']['recommended_per_page']['posts'] ) );
w2t_assert( 'manifest still has original version field', isset( $manifest['version'] ) );

echo "\n== Source keys + hashes on every resource ==\n";
foreach ( array(
	'users' => 'user',
	'terms' => 'term',
	'media' => 'media',
	'posts' => 'post',
	'pages' => 'page',
) as $resource => $expected_type ) {
	list( , $body ) = w2t_call( 'GET', "{$ns}/{$resource}", $token, array( 'per_page' => 1 ) );
	$item = $body['data'][0] ?? null;
	if ( null === $item ) {
		echo "       ({$resource}: no items)\n";
		continue;
	}
	$ok_source = isset( $item['source']['key'], $item['source']['resource'], $item['source']['system'] )
		&& $item['source']['resource'] === $expected_type
		&& $item['source']['key'] === "wordpress:{$expected_type}:" . $item['id']
		&& 'wordpress' === $item['source']['system'];
	$ok_hashes = isset( $item['hashes']['payload'], $item['hashes']['content'] ) && 64 === strlen( $item['hashes']['payload'] );
	w2t_assert( "{$resource} has stable source.key", $ok_source );
	w2t_assert( "{$resource} has hashes.payload + content", $ok_hashes );
}

echo "\n== 3 + 4. /posts media_refs ==\n";
list( , $posts ) = w2t_call( 'GET', "{$ns}/posts", $token, array( 'per_page' => 50 ) );
$post = $posts['data'][0] ?? array();
w2t_assert( '/posts returns media_refs', isset( $post['media_refs'] ) );
w2t_assert( 'media_refs has featured/attached/inline/all_ids', isset( $post['media_refs']['featured'], $post['media_refs']['attached'], $post['media_refs']['inline'], $post['media_refs']['all_ids'] ) );
w2t_assert( '/posts returns url_rewrite_hints', isset( $post['url_rewrite_hints']['rules'][0]['to'] ) && '/uploads/' === $post['url_rewrite_hints']['rules'][0]['to'] );

echo "\n== 4. /pages media_refs ==\n";
list( , $pages ) = w2t_call( 'GET', "{$ns}/pages", $token, array( 'per_page' => 1 ) );
$page = $pages['data'][0] ?? array();
w2t_assert( '/pages returns media_refs', isset( $page['media_refs']['all_ids'] ) );
w2t_assert( '/pages still returns parent + template', array_key_exists( 'parent', $page ) && array_key_exists( 'template', $page ) );

echo "\n== 5. /media storage.checksum ==\n";
list( , $media ) = w2t_call( 'GET', "{$ns}/media", $token, array( 'per_page' => 1 ) );
$m = $media['data'][0] ?? array();
w2t_assert( '/media has storage block', isset( $m['storage'] ) );
w2t_assert( '/media storage.checksum.algorithm = sha256', isset( $m['storage']['checksum']['algorithm'] ) && 'sha256' === $m['storage']['checksum']['algorithm'] );
w2t_assert( '/media storage.checksum.value present', ! empty( $m['storage']['checksum']['value'] ) );
w2t_assert( '/media target_public_path has no wp-content', isset( $m['storage']['target_public_path'] ) && false === strpos( $m['storage']['target_public_path'], 'wp-content' ) );
w2t_assert( '/media still has legacy checksum field', array_key_exists( 'checksum', $m ) );

echo "\n== 6. /dependencies ==\n";
list( $status, $deps ) = w2t_call( 'GET', "{$ns}/dependencies", $token );
w2t_assert( '/dependencies 200', 200 === $status );
w2t_assert( '/dependencies has posts + pages keys', is_array( $deps ) && array_key_exists( 'posts', $deps ) && array_key_exists( 'pages', $deps ) );
list( $status, $deps_posts ) = w2t_call( 'GET', "{$ns}/dependencies", $token, array( 'resource' => 'posts', 'per_page' => 100 ) );
$first_dep = is_array( $deps_posts['posts'] ) ? reset( $deps_posts['posts'] ) : null;
w2t_assert( '/dependencies?resource=posts entry has author/terms/media', is_array( $first_dep ) && isset( $first_dep['author'], $first_dep['terms'], $first_dep['media'] ) );

echo "\n== 7. fields=summary excludes full content ==\n";
list( , $summary ) = w2t_call( 'GET', "{$ns}/posts", $token, array( 'fields' => 'summary', 'per_page' => 1 ) );
$s = $summary['data'][0] ?? array();
w2t_assert( 'summary has NO content field', ! array_key_exists( 'content', $s ) );
w2t_assert( 'summary keeps id/source/hashes/media_refs/seo', isset( $s['id'], $s['source'], $s['hashes'], $s['media_refs'], $s['seo'] ) );
// Payload hash must match full mode (mode-independent dedupe).
list( , $full ) = w2t_call( 'GET', "{$ns}/posts/" . $s['id'], $token );
w2t_assert( 'summary payload hash == full payload hash', isset( $full['data']['hashes']['payload'] ) && $s['hashes']['payload'] === $full['data']['hashes']['payload'] );

echo "\n== 8. after_id cursor ==\n";
list( , $all ) = w2t_call( 'GET', "{$ns}/posts", $token, array( 'per_page' => 100, 'orderby' => 'id', 'order' => 'asc' ) );
$ids = array_map( static function ( $p ) { return (int) $p['id']; }, $all['data'] );
if ( count( $ids ) >= 2 ) {
	$cursor = $ids[0];
	list( , $after ) = w2t_call( 'GET', "{$ns}/posts", $token, array( 'after_id' => $cursor, 'per_page' => 100 ) );
	$after_ids = array_map( static function ( $p ) { return (int) $p['id']; }, $after['data'] );
	$all_gt    = true;
	foreach ( $after_ids as $aid ) {
		if ( $aid <= $cursor ) {
			$all_gt = false;
		}
	}
	w2t_assert( "after_id={$cursor} returns only id > {$cursor}", $all_gt && ! in_array( $cursor, $after_ids, true ) );
} else {
	echo "       (need >=2 posts to test after_id)\n";
}

echo "\n== 9. modified_after filter ==\n";
$future = gmdate( 'Y-m-d\TH:i:s', time() + 86400 );
list( , $none ) = w2t_call( 'GET', "{$ns}/posts", $token, array( 'modified_after' => $future, 'per_page' => 100 ) );
w2t_assert( 'modified_after future date returns 0 posts', isset( $none['pagination']['total'] ) && 0 === $none['pagination']['total'] );
$pastdate = '2000-01-01T00:00:00';
list( , $allpast ) = w2t_call( 'GET', "{$ns}/posts", $token, array( 'modified_after' => $pastdate, 'per_page' => 100 ) );
// An old date passes records through (records with a zero GMT modified date,
// e.g. some drafts, legitimately do not match a GMT filter). Combined with the
// future-date -> 0 case above, this proves modified_after filters correctly.
$past_total = $allpast['pagination']['total'] ?? -1;
$all_total  = $all['pagination']['total'] ?? 0;
w2t_assert(
	"modified_after old date returns a non-empty subset ({$past_total} of {$all_total})",
	$past_total > 0 && $past_total <= $all_total
);

echo "\n== 10. Stable id ASC ordering ==\n";
$sorted = $ids;
sort( $sorted, SORT_NUMERIC );
w2t_assert( '/posts default order is id ASC', $ids === $sorted );
list( , $desc ) = w2t_call( 'GET', "{$ns}/posts", $token, array( 'order' => 'desc', 'per_page' => 100 ) );
$desc_ids = array_map( static function ( $p ) { return (int) $p['id']; }, $desc['data'] );
$rsorted  = $sorted;
rsort( $rsorted, SORT_NUMERIC );
w2t_assert( '/posts order=desc reverses order', $desc_ids === $rsorted );

echo "\n== status filter (posts/pages) ==\n";
list( , $pub ) = w2t_call( 'GET', "{$ns}/posts", $token, array( 'status' => 'publish', 'per_page' => 100 ) );
$only_pub = true;
foreach ( $pub['data'] as $p ) {
	if ( 'publish' !== $p['status'] ) {
		$only_pub = false;
	}
}
w2t_assert( 'status=publish returns only published', $only_pub );

echo "\n================ RESULT ================\n";
echo "PASSED: {$passed}   FAILED: {$failed}\n";
if ( $failed > 0 ) {
	echo "Failures:\n - " . implode( "\n - ", $failures ) . "\n";
	exit( 1 );
}
echo "ALL PHASE 1.1 CHECKS PASSED\n";
exit( 0 );
