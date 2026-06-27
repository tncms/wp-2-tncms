<?php
/**
 * Resolves all media references for a post or page.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discovers every media item a post depends on so the importer never has to
 * parse HTML to find media.
 *
 * Resolves the featured image, attached uploads (`post_parent`) and inline
 * media URLs found inside the content, mapping each back to its original
 * attachment ID where possible.
 */
final class MediaReferenceResolver {

	/**
	 * Resolve all media references for a post.
	 *
	 * @param WP_Post $post Post or page.
	 * @return array{featured:int, attached:int[], inline:array[], all_ids:int[]}
	 */
	public function resolve( WP_Post $post ) {
		$id       = (int) $post->ID;
		$featured = (int) get_post_thumbnail_id( $id );
		$attached = $this->attached( $id );
		$inline   = $this->inline( $post );

		$all_ids = array_merge(
			$featured > 0 ? array( $featured ) : array(),
			$attached,
			wp_list_pluck( $inline, 'id' )
		);
		$all_ids = array_values( array_unique( array_filter( array_map( 'intval', $all_ids ) ) ) );
		sort( $all_ids );

		return array(
			'featured' => $featured,
			'attached' => $attached,
			'inline'   => $inline,
			'all_ids'  => $all_ids,
		);
	}

	/**
	 * Attachment IDs whose `post_parent` is this post.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private function attached( $post_id ) {
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Inline media URLs in the content, mapped back to attachment IDs.
	 *
	 * @param WP_Post $post Post or page.
	 * @return array[]
	 */
	private function inline( WP_Post $post ) {
		$uploads = wp_get_upload_dir();
		$base    = isset( $uploads['baseurl'] ) ? $uploads['baseurl'] : '';

		if ( '' === $base || '' === (string) $post->post_content ) {
			return array();
		}

		if ( ! preg_match_all( '#https?://[^\s"\'<>()]+#i', $post->post_content, $matches ) ) {
			return array();
		}

		$seen    = array();
		$results = array();

		foreach ( array_unique( $matches[0] ) as $url ) {
			if ( 0 !== strpos( $url, $base ) ) {
				continue;
			}

			$attachment_id = $this->url_to_attachment_id( $url );

			if ( $attachment_id <= 0 || isset( $seen[ $attachment_id ] ) ) {
				continue;
			}

			$seen[ $attachment_id ] = true;

			$relative = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
			$mime     = (string) get_post_mime_type( $attachment_id );

			$results[] = array(
				'id'            => $attachment_id,
				'url'           => '' !== $relative ? trailingslashit( $base ) . $relative : $url,
				'relative_path' => $relative,
				'kind'          => 0 === strpos( $mime, 'image/' ) ? 'image' : 'file',
				'source'        => 'content_img',
			);
		}

		return $results;
	}

	/**
	 * Map a URL to an attachment ID, stripping any resized-image suffix.
	 *
	 * @param string $url Media URL.
	 * @return int Attachment ID, or 0 when not found.
	 */
	private function url_to_attachment_id( $url ) {
		$attachment_id = (int) attachment_url_to_postid( $url );

		if ( $attachment_id > 0 ) {
			return $attachment_id;
		}

		// Retry with any "-WIDTHxHEIGHT" size suffix removed (inline thumbnails).
		$original = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $url );

		if ( is_string( $original ) && $original !== $url ) {
			return (int) attachment_url_to_postid( $original );
		}

		return 0;
	}
}
