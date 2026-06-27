<?php
/**
 * Post and page query service.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services;

use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries posts and pages for export.
 *
 * A single service backs both the posts and pages resources; the post type is
 * passed in by the caller so the same querying and pagination logic is reused.
 * Optional filters (ordering, after_id, modified_after, status) support stable
 * resume and incremental sync without changing the default behaviour.
 */
final class PostService {

	/**
	 * Post statuses considered exportable by default.
	 *
	 * Trashed and auto-draft content is intentionally excluded.
	 *
	 * @return string[]
	 */
	private function exportable_statuses() {
		return array( 'publish', 'future', 'draft', 'pending', 'private' );
	}

	/**
	 * Statuses a client may explicitly request.
	 *
	 * @return string[]
	 */
	private function requestable_statuses() {
		return array( 'publish', 'future', 'draft', 'pending', 'private', 'trash', 'any' );
	}

	/**
	 * Query a page of posts of a given type.
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $page      Page number (1-based).
	 * @param int    $per_page  Items per page.
	 * @param array  $filters   Optional filters from CollectionQuery.
	 * @return array{items: WP_Post[], total: int}
	 */
	public function paginate( $post_type, $page, $per_page, array $filters = array() ) {
		$args = array(
			'post_type'              => $post_type,
			'post_status'            => $this->resolve_statuses( $filters ),
			'posts_per_page'         => (int) $per_page,
			'paged'                  => (int) $page,
			'orderby'                => isset( $filters['orderby'] ) && 'modified' === $filters['orderby'] ? 'modified' : 'ID',
			'order'                  => isset( $filters['order'] ) && 'DESC' === $filters['order'] ? 'DESC' : 'ASC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
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
	 * Resolve the post statuses to query.
	 *
	 * @param array $filters Filters from CollectionQuery.
	 * @return string|string[]
	 */
	private function resolve_statuses( array $filters ) {
		if ( empty( $filters['status'] ) || ! is_array( $filters['status'] ) ) {
			return $this->exportable_statuses();
		}

		$requested = array_intersect( $filters['status'], $this->requestable_statuses() );

		if ( empty( $requested ) ) {
			return $this->exportable_statuses();
		}

		if ( in_array( 'any', $requested, true ) ) {
			return 'any';
		}

		return array_values( $requested );
	}

	/**
	 * Find a single post of a given type by ID.
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $id        Post ID.
	 * @return WP_Post|null
	 */
	public function find( $post_type, $id ) {
		$post = get_post( (int) $id );

		if ( ! $post instanceof WP_Post || $post->post_type !== $post_type ) {
			return null;
		}

		if ( ! in_array( $post->post_status, $this->exportable_statuses(), true ) ) {
			return null;
		}

		return $post;
	}
}
