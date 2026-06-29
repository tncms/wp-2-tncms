<?php
/**
 * Term query service.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services;

use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries taxonomy terms for export.
 *
 * Phase 1 supports the core `category` and `post_tag` taxonomies. The set of
 * taxonomies is filterable so future phases can add more without changing this
 * service. Supports ordering and the after_id resume cursor.
 */
final class TermService {

	/**
	 * Taxonomies exported in Phase 1.
	 *
	 * @return string[]
	 */
	public function taxonomies() {
		/**
		 * Filter the taxonomies exported by the terms resource.
		 *
		 * @param string[] $taxonomies Taxonomy slugs.
		 */
		return (array) apply_filters( 'wp2tncms_term_taxonomies', array( 'category', 'post_tag' ) );
	}

	/**
	 * Query a page of terms.
	 *
	 * @param int   $page     Page number (1-based).
	 * @param int   $per_page Items per page.
	 * @param array $filters  Optional filters from CollectionQuery.
	 * @return array{items: WP_Term[], total: int}
	 */
	public function paginate( $page, $per_page, array $filters = array() ) {
		$taxonomies = $this->taxonomies();
		$offset     = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;

		$after_id = isset( $filters['after_id'] ) ? (int) $filters['after_id'] : 0;
		$clause   = null;

		if ( $after_id > 0 ) {
			$clause = static function ( $clauses ) use ( $after_id ) {
				$clauses['where'] .= ' AND t.term_id > ' . $after_id;
				return $clauses;
			};
			add_filter( 'terms_clauses', $clause );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'number'     => (int) $per_page,
				'offset'     => $offset,
				'orderby'    => 'term_id',
				'order'      => isset( $filters['order'] ) && 'DESC' === $filters['order'] ? 'DESC' : 'ASC',
			)
		);

		if ( null !== $clause ) {
			remove_filter( 'terms_clauses', $clause );
		}

		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$total = wp_count_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
			)
		);

		return array(
			'items' => $terms,
			'total' => is_wp_error( $total ) ? 0 : (int) $total,
		);
	}

	/**
	 * Find a single term by ID within the exported taxonomies.
	 *
	 * @param int $id Term ID.
	 * @return WP_Term|null
	 */
	public function find( $id ) {
		$term = get_term( (int) $id );

		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		return in_array( $term->taxonomy, $this->taxonomies(), true ) ? $term : null;
	}

	/**
	 * Find a term by ID, constrained to a specific exported taxonomy.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param int    $id       Term ID.
	 * @return WP_Term|null
	 */
	public function find_in_taxonomy( $taxonomy, $id ) {
		if ( ! in_array( $taxonomy, $this->taxonomies(), true ) ) {
			return null;
		}

		$term = get_term( (int) $id, $taxonomy );

		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Find a term by slug within a specific exported taxonomy.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $slug     Term slug.
	 * @return WP_Term|null
	 */
	public function find_by_slug( $taxonomy, $slug ) {
		if ( ! in_array( $taxonomy, $this->taxonomies(), true ) ) {
			return null;
		}

		$slug = sanitize_title( (string) $slug );

		if ( '' === $slug ) {
			return null;
		}

		$term = get_term_by( 'slug', $slug, $taxonomy );

		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Find a term by slug across all exported taxonomies.
	 *
	 * @param string $slug Term slug.
	 * @return WP_Term|null
	 */
	public function find_by_slug_any( $slug ) {
		foreach ( $this->taxonomies() as $taxonomy ) {
			$term = $this->find_by_slug( $taxonomy, $slug );

			if ( null !== $term ) {
				return $term;
			}
		}

		return null;
	}
}
