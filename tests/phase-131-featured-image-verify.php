<?php
/**
 * Phase 1.3.1 acceptance verification harness for the WP 2 TNCMS exporter.
 *
 * Exercises the additive embedded featured image (`featured_image`) on the
 * posts and pages payloads: presence and null handling, byte-for-byte parity
 * with the media endpoint (the shared MediaTransformer), checksum / relative
 * path / source key presence, and backward compatibility of the existing
 * `featured_media` field and all v1 endpoints.
 *
 * Usage:  php wp-content/plugins/wp-2-tncms/tests/phase-131-featured-image-verify.php
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
// Fixtures: a real attachment (so the checksum can be computed), a post and a
// page that reference it as their featured image, and a post with none.
// ---------------------------------------------------------------------------

$suffix  = (string) time();
$uploads = wp_upload_dir();

// A minimal but valid 1x1 PNG so hash_file()/filesize() have real bytes.
$png = base64_decode(
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
);

$filename = 'w2t-featured-fixture-' . $suffix . '.png';
$abs_path = trailingslashit( $uploads['path'] ) . $filename;
file_put_contents( $abs_path, $png );

$subdir   = ltrim( (string) $uploads['subdir'], '/' );
$relative = '' !== $subdir ? $subdir . '/' . $filename : $filename;

$attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'Featured Fixture ' . $suffix,
		'post_name'      => 'featured-fixture-' . $suffix,
		'post_excerpt'   => 'Fixture caption',
		'post_content'   => 'Fixture description',
		'post_status'    => 'inherit',
	),
	$abs_path
);

update_post_meta( $attachment_id, '_wp_attached_file', $relative );
update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Fixture alt text' );
wp_update_attachment_metadata(
	$attachment_id,
	array(
		'width'  => 1,
		'height' => 1,
		'file'   => $relative,
	)
);

$post_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'publish',
		'post_title'  => 'Featured Post ' . $suffix,
		'post_name'   => 'featured-post-' . $suffix,
		'post_content' => 'Post body.',
	)
);
set_post_thumbnail( $post_id, $attachment_id );

$page_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Featured Page ' . $suffix,
		'post_name'   => 'featured-page-' . $suffix,
		'post_content' => 'Page body.',
	)
);
set_post_thumbnail( $page_id, $attachment_id );

$plain_post_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'publish',
		'post_title'  => 'Plain Post ' . $suffix,
		'post_name'   => 'plain-post-' . $suffix,
		'post_content' => 'No featured image here.',
	)
);

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

list( , $manifest ) = w2t_call( 'GET', "{$ns}/manifest", $token );
w2t_assert( 'manifest api_version still v1', isset( $manifest['api_version'] ) && 'v1' === $manifest['api_version'] );
w2t_assert( 'manifest schema_version = 1.3.1', isset( $manifest['schema_version'] ) && '1.3.1' === $manifest['schema_version'] );
w2t_assert( 'manifest version = 1.3.1', isset( $manifest['version'] ) && '1.3.1' === $manifest['version'] );
w2t_assert( 'manifest advertises embedded_featured_image', ! empty( $manifest['capabilities']['embedded_featured_image'] ) );

// ---------------------------------------------------------------------------
// 1. Post with a featured image.
// ---------------------------------------------------------------------------
echo "\n== 1. Post with featured image ==\n";
list( $status, $post ) = w2t_call( 'GET', "{$ns}/posts/{$post_id}", $token );
$post = isset( $post['data'] ) ? $post['data'] : $post;

w2t_assert( 'GET /posts/{id} returns 200', 200 === $status );
w2t_assert( 'post has featured_media (int, unchanged)', isset( $post['featured_media'] ) && $attachment_id === (int) $post['featured_media'] );
w2t_assert( 'post has featured_image object', isset( $post['featured_image'] ) && is_array( $post['featured_image'] ) );

$fi = isset( $post['featured_image'] ) && is_array( $post['featured_image'] ) ? $post['featured_image'] : array();

w2t_assert( 'featured_image.id matches attachment', isset( $fi['id'] ) && $attachment_id === (int) $fi['id'] );
w2t_assert( 'featured_image.url is a non-empty string', ! empty( $fi['url'] ) && is_string( $fi['url'] ) );
w2t_assert( 'featured_image.relative_path present', isset( $fi['relative_path'] ) && $relative === $fi['relative_path'] );
w2t_assert( 'featured_image.mime_type present', isset( $fi['mime_type'] ) && 'image/png' === $fi['mime_type'] );
w2t_assert( 'featured_image.checksum present (sha256)', isset( $fi['checksum'] ) && is_string( $fi['checksum'] ) && 64 === strlen( $fi['checksum'] ) );
w2t_assert( 'featured_image.source.key present', isset( $fi['source']['key'] ) && "wordpress:media:{$attachment_id}" === $fi['source']['key'] );
w2t_assert( 'featured_image url is the original (no size suffix)', isset( $fi['url'] ) && false === strpos( $fi['url'], '-1x1' ) );

// ---------------------------------------------------------------------------
// 2. featured_image shape matches the MediaTransformer (media endpoint parity).
// ---------------------------------------------------------------------------
echo "\n== 2. Shape parity with MediaTransformer ==\n";
list( , $media ) = w2t_call( 'GET', "{$ns}/media/{$attachment_id}", $token );
$media = isset( $media['data'] ) ? $media['data'] : $media;

// Compare via JSON so nested (object) placeholders (e.g. `meta`) normalise;
// the on-the-wire payload is what clients actually receive.
w2t_assert( 'featured_image === media endpoint payload', wp_json_encode( $fi ) === wp_json_encode( $media ) );
w2t_assert( 'featured_image has same keys as media payload', ! empty( $media ) && array_keys( $fi ) === array_keys( $media ) );
w2t_assert( 'featured_image includes dimensions from MediaTransformer', isset( $fi['dimensions']['width'], $fi['dimensions']['height'] ) );
w2t_assert( 'featured_image includes storage block from MediaTransformer', isset( $fi['storage']['checksum']['algorithm'] ) && 'sha256' === $fi['storage']['checksum']['algorithm'] );

// ---------------------------------------------------------------------------
// 3. Page with a featured image.
// ---------------------------------------------------------------------------
echo "\n== 3. Page with featured image ==\n";
list( $status, $page ) = w2t_call( 'GET', "{$ns}/pages/{$page_id}", $token );
$page = isset( $page['data'] ) ? $page['data'] : $page;

w2t_assert( 'GET /pages/{id} returns 200', 200 === $status );
w2t_assert( 'page has featured_media (int, unchanged)', isset( $page['featured_media'] ) && $attachment_id === (int) $page['featured_media'] );
w2t_assert( 'page has featured_image object', isset( $page['featured_image'] ) && is_array( $page['featured_image'] ) );
w2t_assert( 'page featured_image === media payload', isset( $page['featured_image'] ) && wp_json_encode( $page['featured_image'] ) === wp_json_encode( $media ) );

// ---------------------------------------------------------------------------
// 4. Post without a featured image.
// ---------------------------------------------------------------------------
echo "\n== 4. Post without featured image ==\n";
list( $status, $plain ) = w2t_call( 'GET', "{$ns}/posts/{$plain_post_id}", $token );
$plain = isset( $plain['data'] ) ? $plain['data'] : $plain;

w2t_assert( 'GET /posts/{id} returns 200', 200 === $status );
w2t_assert( 'plain post featured_media === 0', isset( $plain['featured_media'] ) && 0 === (int) $plain['featured_media'] );
w2t_assert( 'plain post featured_image === null', array_key_exists( 'featured_image', $plain ) && null === $plain['featured_image'] );

// ---------------------------------------------------------------------------
// 5. Collection payloads carry the embedded featured image too.
// ---------------------------------------------------------------------------
echo "\n== 5. Collection payloads ==\n";
list( , $list ) = w2t_call( 'GET', "{$ns}/posts", $token, array( 'per_page' => 100 ) );
$rows = isset( $list['data'] ) ? $list['data'] : array();

$found = null;
foreach ( $rows as $row ) {
	if ( isset( $row['id'] ) && (int) $row['id'] === $post_id ) {
		$found = $row;
		break;
	}
}
w2t_assert( 'featured post present in collection', null !== $found );
w2t_assert( 'collection row keeps featured_media', null !== $found && $attachment_id === (int) $found['featured_media'] );
w2t_assert( 'collection row embeds featured_image', null !== $found && isset( $found['featured_image']['id'] ) && $attachment_id === (int) $found['featured_image']['id'] );

// ---------------------------------------------------------------------------
// Cleanup.
// ---------------------------------------------------------------------------
wp_delete_post( $post_id, true );
wp_delete_post( $page_id, true );
wp_delete_post( $plain_post_id, true );
wp_delete_attachment( $attachment_id, true );
if ( file_exists( $abs_path ) ) {
	@unlink( $abs_path );
}

// ---------------------------------------------------------------------------
// Summary.
// ---------------------------------------------------------------------------
echo "\n";
echo "============================================\n";
echo "  Passed: {$passed}   Failed: {$failed}\n";
if ( $failed > 0 ) {
	echo "  Failures:\n";
	foreach ( $failures as $f ) {
		echo "    - {$f}\n";
	}
}
echo "============================================\n";

exit( $failed > 0 ? 1 : 0 );
