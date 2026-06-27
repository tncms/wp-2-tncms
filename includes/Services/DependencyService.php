<?php
/**
 * Dependency map assembly.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a lightweight dependency map (author, terms, media) for posts and
 * pages so the importer can pre-build an import plan without pulling full
 * content.
 */
final class DependencyService {

	/**
	 * Post service.
	 *
	 * @var PostService
	 */
	private $posts;

	/**
	 * Media reference resolver.
	 *
	 * @var MediaReferenceResolver
	 */
	private $media_refs;

	/**
	 * Constructor.
	 *
	 * @param PostService            $posts      Post service.
	 * @param MediaReferenceResolver $media_refs Media reference resolver.
	 */
	public function __construct( PostService $posts, MediaReferenceResolver $media_refs ) {
		$this->posts      = $posts;
		$this->media_refs = $media_refs;
	}

	/**
	 * Build the dependency map.
	 *
	 * @param string $resource Optional: 'posts' or 'pages'. Empty = both.
	 * @param int    $page     Page number.
	 * @param int    $per_page Items per page.
	 * @return array
	 */
	public function build( $resource, $page, $per_page ) {
		$map = array(
			'posts' => (object) array(),
			'pages' => (object) array(),
		);

		$targets = in_array( $resource, array( 'posts', 'pages' ), true )
			? array( $resource )
			: array( 'posts', 'pages' );

		foreach ( $targets as $target ) {
			$post_type = 'pages' === $target ? 'page' : 'post';
			$result    = $this->posts->paginate( $post_type, $page, $per_page );

			$entries = array();
			foreach ( $result['items'] as $post ) {
				$entries[ (string) $post->ID ] = $this->entry( $post );
			}

			$map[ $target ] = empty( $entries ) ? (object) array() : $entries;
		}

		return $map;
	}

	/**
	 * Build a single dependency entry.
	 *
	 * @param WP_Post $post Post or page.
	 * @return array
	 */
	private function entry( WP_Post $post ) {
		$refs = $this->media_refs->resolve( $post );

		return array(
			'author' => (int) $post->post_author,
			'terms'  => $this->terms( (int) $post->ID ),
			'media'  => array(
				'featured' => $refs['featured'],
				'inline'   => array_map( 'intval', wp_list_pluck( $refs['inline'], 'id' ) ),
				'attached' => $refs['attached'],
			),
		);
	}

	/**
	 * Taxonomy term IDs grouped by taxonomy.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, int[]>
	 */
	private function terms( $post_id ) {
		$grouped    = array();
		$taxonomies = get_object_taxonomies( get_post_type( $post_id ), 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			$ids = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );

			$grouped[ $taxonomy ] = ( is_wp_error( $ids ) || empty( $ids ) )
				? array()
				: array_map( 'intval', $ids );
		}

		return $grouped;
	}
}
