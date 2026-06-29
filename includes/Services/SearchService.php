<?php
/**
 * Lightweight cross-resource search.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services;

use WP2TNCMS\Support\SourceKey;
use WP_Query;
use WP_Term;
use WP_User;
use WP_User_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a free-text query into lightweight summary rows across the exported
 * resource types.
 *
 * Each row carries only identity and pointer fields (id, title, slug, source
 * key) and never full content, so a consumer can search efficiently and then
 * fetch the full payload through the relevant single-resource endpoint.
 */
final class SearchService {

	const DEFAULT_LIMIT = 20;
	const MAX_LIMIT     = 100;

	/**
	 * Term service (for the exported taxonomy list).
	 *
	 * @var TermService
	 */
	private $terms;

	/**
	 * Constructor.
	 *
	 * @param TermService $terms Term service.
	 */
	public function __construct( TermService $terms ) {
		$this->terms = $terms;
	}

	/**
	 * Resource types this search understands.
	 *
	 * @return string[]
	 */
	public static function types() {
		return array( 'post', 'page', 'media', 'term', 'user' );
	}

	/**
	 * Clamp a requested limit to the supported range.
	 *
	 * @param int $limit Requested limit.
	 * @return int
	 */
	public static function clamp_limit( $limit ) {
		$limit = (int) $limit;

		if ( $limit < 1 ) {
			$limit = self::DEFAULT_LIMIT;
		}

		return min( $limit, self::MAX_LIMIT );
	}

	/**
	 * Run a search.
	 *
	 * @param string $query Free-text query.
	 * @param string $type  Resource type, or '' for all supported types.
	 * @param int    $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function search( $query, $type, $limit ) {
		$query = trim( (string) $query );
		$limit = self::clamp_limit( $limit );

		if ( '' === $query ) {
			return array();
		}

		if ( '' !== $type && in_array( $type, self::types(), true ) ) {
			return $this->search_type( $type, $query, $limit );
		}

		// No type filter: query each type with an even share of the limit so
		// results are balanced rather than dominated by the first type, and so
		// we never fetch far more rows than the caller asked for.
		$types    = self::types();
		$per_type = (int) max( 1, ceil( $limit / count( $types ) ) );
		$rows     = array();

		foreach ( $types as $supported ) {
			$rows = array_merge( $rows, $this->search_type( $supported, $query, $per_type ) );
		}

		return array_slice( $rows, 0, $limit );
	}

	/**
	 * Dispatch a search to the matching resource query.
	 *
	 * @param string $type  Resource type.
	 * @param string $query Free-text query.
	 * @param int    $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function search_type( $type, $query, $limit ) {
		switch ( $type ) {
			case 'post':
			case 'page':
				return $this->search_posts( $type, $query, $limit );
			case 'media':
				return $this->search_media( $query, $limit );
			case 'term':
				return $this->search_terms( $query, $limit );
			case 'user':
				return $this->search_users( $query, $limit );
		}

		return array();
	}

	/**
	 * Search posts or pages.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $query     Search query.
	 * @param int    $limit     Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function search_posts( $post_type, $query, $limit ) {
		$found = new WP_Query(
			array(
				'post_type'              => $post_type,
				's'                      => $query,
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'         => $limit,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		$rows = array();

		foreach ( $found->posts as $post ) {
			$rows[] = array(
				'resource' => $post_type,
				'id'       => (int) $post->ID,
				'title'    => get_the_title( $post ),
				'slug'     => $post->post_name,
				'status'   => $post->post_status,
				'key'      => SourceKey::key( $post_type, $post->ID ),
			);
		}

		return $rows;
	}

	/**
	 * Search media attachments by title/slug.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function search_media( $query, $limit ) {
		$found = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				's'                      => $query,
				'posts_per_page'         => $limit,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		$rows = array();

		foreach ( $found->posts as $post ) {
			$rows[] = array(
				'resource'  => 'media',
				'id'        => (int) $post->ID,
				'title'     => get_the_title( $post ),
				'slug'      => $post->post_name,
				'mime_type' => $post->post_mime_type,
				'key'       => SourceKey::key( 'media', $post->ID ),
			);
		}

		return $rows;
	}

	/**
	 * Search exported taxonomy terms.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function search_terms( $query, $limit ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $this->terms->taxonomies(),
				'hide_empty' => false,
				'search'     => $query,
				'number'     => $limit,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$rows = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$rows[] = array(
				'resource' => 'term',
				'id'       => (int) $term->term_id,
				'title'    => $term->name,
				'slug'     => $term->slug,
				'taxonomy' => $term->taxonomy,
				'key'      => SourceKey::key( 'term', $term->term_id ),
			);
		}

		return $rows;
	}

	/**
	 * Search users.
	 *
	 * @param string $query Search query.
	 * @param int    $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function search_users( $query, $limit ) {
		$found = new WP_User_Query(
			array(
				'search'         => '*' . $query . '*',
				'search_columns' => array( 'user_login', 'user_nicename', 'display_name', 'user_email' ),
				'number'         => $limit,
				'count_total'    => false,
				'fields'         => 'all',
			)
		);

		$rows = array();

		foreach ( $found->get_results() as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}

			$rows[] = array(
				'resource' => 'user',
				'id'       => (int) $user->ID,
				'title'    => $user->display_name,
				'slug'     => $user->user_nicename,
				'login'    => $user->user_login,
				'key'      => SourceKey::key( 'user', $user->ID ),
			);
		}

		return $rows;
	}
}
