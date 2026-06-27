<?php
/**
 * Media transformer.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Transformers;

use WP2TNCMS\Support\Hashes;
use WP2TNCMS\Support\SourceKey;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serialises an attachment into the stable media schema.
 *
 * Only the original upload is exported: the relative path from
 * `_wp_attached_file` is preserved, the absolute URL is rebuilt from the
 * uploads base, and a SHA-256 checksum of the original file is included.
 * Generated thumbnail sizes are never exported.
 *
 * A `storage` block (with a `wp-content`-free public target path) plus a
 * stable `source` identity and dedup `hashes` are added for the importer.
 */
final class MediaTransformer {

	/**
	 * Transform an attachment.
	 *
	 * @param WP_Post $attachment Attachment post.
	 * @return array
	 */
	public function transform( WP_Post $attachment ) {
		$id            = (int) $attachment->ID;
		$relative_path = (string) get_post_meta( $id, '_wp_attached_file', true );
		$uploads       = wp_get_upload_dir();

		$absolute_path = '';
		$url           = '';

		if ( '' !== $relative_path && ! empty( $uploads['basedir'] ) ) {
			$absolute_path = trailingslashit( $uploads['basedir'] ) . $relative_path;
			$url           = trailingslashit( $uploads['baseurl'] ) . $relative_path;
		}

		$metadata   = wp_get_attachment_metadata( $id );
		$dimensions = array(
			'width'  => isset( $metadata['width'] ) ? (int) $metadata['width'] : null,
			'height' => isset( $metadata['height'] ) ? (int) $metadata['height'] : null,
		);

		$filesize = $this->filesize( $absolute_path );
		$checksum = $this->checksum( $absolute_path );

		$data = array(
			'id'            => $id,
			'title'         => get_the_title( $attachment ),
			'slug'          => $attachment->post_name,
			'mime_type'     => $attachment->post_mime_type,
			'author'        => (int) $attachment->post_author,
			'parent'        => (int) $attachment->post_parent,
			'alt_text'      => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption'       => $attachment->post_excerpt,
			'description'   => $attachment->post_content,
			'relative_path' => $relative_path,
			'url'           => $url,
			'filesize'      => $filesize,
			'checksum'      => $checksum,
			'dimensions'    => $dimensions,
			'date_gmt'      => mysql_to_rfc3339( $attachment->post_date_gmt ),
			'storage'       => $this->storage( $attachment, $relative_path, $filesize, $checksum ),
			'meta'          => (object) array(),
		);

		$data['source'] = SourceKey::build( 'media', $id );
		$data['hashes'] = array(
			'payload' => Hashes::payload( $data ),
			'content' => null === $checksum ? '' : $checksum,
		);

		return $data;
	}

	/**
	 * Build the storage block used for skip-existing decisions.
	 *
	 * @param WP_Post     $attachment    Attachment post.
	 * @param string      $relative_path Relative upload path.
	 * @param int|null    $filesize      File size in bytes.
	 * @param string|null $checksum      SHA-256 checksum.
	 * @return array
	 */
	private function storage( WP_Post $attachment, $relative_path, $filesize, $checksum ) {
		return array(
			'relative_path'        => $relative_path,
			'target_relative_path' => $relative_path,
			'target_public_path'   => '' !== $relative_path ? 'uploads/' . $relative_path : '',
			'filename'             => '' !== $relative_path ? wp_basename( $relative_path ) : '',
			'extension'            => '' !== $relative_path ? strtolower( pathinfo( $relative_path, PATHINFO_EXTENSION ) ) : '',
			'mime_type'            => $attachment->post_mime_type,
			'filesize'             => $filesize,
			'checksum'             => array(
				'algorithm' => 'sha256',
				'value'     => $checksum,
			),
		);
	}

	/**
	 * SHA-256 checksum of the original file, when available.
	 *
	 * @param string $absolute_path Absolute file path.
	 * @return string|null
	 */
	private function checksum( $absolute_path ) {
		if ( '' === $absolute_path || ! is_file( $absolute_path ) ) {
			return null;
		}

		$hash = hash_file( 'sha256', $absolute_path );

		return false === $hash ? null : $hash;
	}

	/**
	 * File size in bytes, when available.
	 *
	 * @param string $absolute_path Absolute file path.
	 * @return int|null
	 */
	private function filesize( $absolute_path ) {
		if ( '' === $absolute_path || ! is_file( $absolute_path ) ) {
			return null;
		}

		$size = filesize( $absolute_path );

		return false === $size ? null : (int) $size;
	}
}
