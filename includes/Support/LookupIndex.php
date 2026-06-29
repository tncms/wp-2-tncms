<?php
/**
 * Content-hash / checksum lookup index.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists the deterministic hashes a resource already exposes into hidden
 * post meta so the lookup endpoints can resolve a resource by hash or checksum
 * with a single indexed query instead of scanning every record.
 *
 * The index is populated transparently while resources are transformed for
 * export, so by the time a consumer asks for a hash lookup the value is
 * already recorded ("available"). The stored meta is private (underscore
 * prefixed) and never appears in any API response, so this is invisible to the
 * public v1 contract.
 */
final class LookupIndex {

	const META_CONTENT_HASH = '_wp2tncms_content_hash';
	const META_PAYLOAD_HASH = '_wp2tncms_payload_hash';
	const META_CHECKSUM     = '_wp2tncms_checksum';

	/**
	 * Record the content and payload hashes for a post or page.
	 *
	 * Writes only happen when a stored value is missing or has changed, so
	 * re-exporting unchanged content performs no writes.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $content_hash SHA-256 of the post content.
	 * @param string $payload_hash SHA-256 of the normalised payload.
	 * @return void
	 */
	public static function remember_post( $post_id, $content_hash, $payload_hash ) {
		self::remember_meta( (int) $post_id, self::META_CONTENT_HASH, (string) $content_hash );
		self::remember_meta( (int) $post_id, self::META_PAYLOAD_HASH, (string) $payload_hash );
	}

	/**
	 * Record the file checksum for an attachment.
	 *
	 * @param int         $attachment_id Attachment ID.
	 * @param string|null $checksum      SHA-256 checksum, or null when unknown.
	 * @return void
	 */
	public static function remember_media( $attachment_id, $checksum ) {
		if ( null === $checksum || '' === $checksum ) {
			return;
		}

		self::remember_meta( (int) $attachment_id, self::META_CHECKSUM, (string) $checksum );
	}

	/**
	 * Update a meta value only when it differs from the stored value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $value   New value.
	 * @return void
	 */
	private static function remember_meta( $post_id, $key, $value ) {
		if ( $post_id < 1 || '' === $value ) {
			return;
		}

		$current = get_post_meta( $post_id, $key, true );

		if ( (string) $current !== $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
