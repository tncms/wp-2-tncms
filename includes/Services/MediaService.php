<?php
/**
 * Media query service.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services;

use WP2TNCMS\Support\LookupIndex;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries media attachments for export.
 *
 * Only original uploads (those with an `_wp_attached_file` meta value) are
 * considered; generated thumbnail sizes are never exported. Supports the
 * shared after_id / modified_after / ordering filters for resume support.
 */
final class MediaService {

	/**
	 * Query a page of attachments.
	 *
	 * @param int   $page     Page number (1-based).
	 * @param int   $per_page Items per page.
	 * @param array $filters  Optional filters from CollectionQuery.
	 * @return array{items: WP_Post[], total: int}
	 */
	public function paginate( $page, $per_page, array $filters = array() ) {
		$args = array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'posts_per_page'         => (int) $per_page,
			'paged'                  => (int) $page,
			'orderby'                => isset( $filters['orderby'] ) && 'modified' === $filters['orderby'] ? 'modified' : 'ID',
			'order'                  => isset( $filters['order'] ) && 'DESC' === $filters['order'] ? 'DESC' : 'ASC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'meta_query'             => array(
				array(
					'key'     => '_wp_attached_file',
					'compare' => 'EXISTS',
				),
			),
		);

		if ( ! empty( $filters['modified_after'] ) ) {
			$args['date_query'] = array(
				array(
					'column'    => 'post_modified_gmt',
					'after'     => $filters['modified_after'],
					'inclusive' => false,
				),
			);
		}

		$after_id = isset( $filters['after_id'] ) ? (int) $filters['after_id'] : 0;
		$where    = null;

		if ( $after_id > 0 ) {
			global $wpdb;
			$where = static function ( $clause ) use ( $wpdb, $after_id ) {
				return $clause . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $after_id );
			};
			add_filter( 'posts_where', $where );
		}

		$query = new WP_Query( $args );

		if ( null !== $where ) {
			remove_filter( 'posts_where', $where );
		}

		return array(
			'items' => $query->posts,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Find a single attachment by ID.
	 *
	 * @param int $id Attachment ID.
	 * @return WP_Post|null
	 */
	public function find( $id ) {
		$post = get_post( (int) $id );

		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Find an attachment by its exported relative upload path.
	 *
	 * The path is matched against the original `_wp_attached_file` meta value
	 * (e.g. `2026/05/image.png`); only relative paths are accepted so absolute
	 * filesystem paths are never exposed.
	 *
	 * @param string $relative_path Relative upload path.
	 * @return WP_Post|null
	 */
	public function find_by_relative_path( $relative_path ) {
		$relative_path = ltrim( (string) $relative_path, '/' );

		if ( '' === $relative_path ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => '_wp_attached_file',
						'value' => $relative_path,
					),
				),
			)
		);

		return empty( $query->posts ) ? null : $query->posts[0];
	}

	/**
	 * Find an attachment by its exported SHA-256 file checksum.
	 *
	 * Resolves against the lookup index that is populated when media is
	 * exported, so the checksum is returned when available.
	 *
	 * @param string $checksum SHA-256 checksum.
	 * @return WP_Post|null
	 */
	public function find_by_checksum( $checksum ) {
		$checksum = strtolower( trim( (string) $checksum ) );

		if ( '' === $checksum ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => LookupIndex::META_CHECKSUM,
						'value' => $checksum,
					),
				),
			)
		);

		return empty( $query->posts ) ? null : $query->posts[0];
	}
}
