<?php
/**
 * Post transformer.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Transformers;

use WP2TNCMS\Services\MediaReferenceResolver;
use WP2TNCMS\Services\Seo\SeoManager;
use WP2TNCMS\Support\Hashes;
use WP2TNCMS\Support\SourceKey;
use WP2TNCMS\Support\UrlRewriteHints;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serialises a post into the stable posts schema.
 *
 * Author and taxonomy IDs are preserved for mapping, the GUID, comment and
 * ping status are exported, SEO metadata is resolved via the active provider,
 * and a reserved `meta` object is included for future phases. A stable
 * `source` identity, deduplication `hashes`, resolved `media_refs` and
 * `url_rewrite_hints` are added for the TNCMS importer.
 */
class PostTransformer {

	/**
	 * SEO manager.
	 *
	 * @var SeoManager
	 */
	protected $seo;

	/**
	 * Media reference resolver.
	 *
	 * @var MediaReferenceResolver
	 */
	protected $media_refs;

	/**
	 * Constructor.
	 *
	 * @param SeoManager             $seo        SEO manager.
	 * @param MediaReferenceResolver $media_refs Media reference resolver.
	 */
	public function __construct( SeoManager $seo, MediaReferenceResolver $media_refs ) {
		$this->seo        = $seo;
		$this->media_refs = $media_refs;
	}

	/**
	 * Transform a post.
	 *
	 * @param WP_Post $post   Post object.
	 * @param string  $fields 'full' (default) or 'summary'.
	 * @return array
	 */
	public function transform( WP_Post $post, $fields = 'full' ) {
		$id     = (int) $post->ID;
		$source = SourceKey::build( $this->resource(), $id );

		// Build the full payload so the payload hash is identical in both modes.
		$full = $this->full( $post );

		$hashes = array(
			'payload' => Hashes::payload( $full ),
			'content' => Hashes::sha256( $post->post_content ),
		);

		if ( 'summary' === $fields ) {
			return array_merge( $this->summary( $post, $full ), array(
				'source' => $source,
				'hashes' => $hashes,
			) );
		}

		return array_merge(
			$full,
			array(
				'source' => $source,
				'hashes' => $hashes,
			)
		);
	}

	/**
	 * The full payload (without source/hashes).
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	protected function full( WP_Post $post ) {
		$id = (int) $post->ID;

		return array_merge(
			array(
				'id'                => $id,
				'type'              => $post->post_type,
				'status'            => $post->post_status,
				'slug'              => $post->post_name,
				'title'             => $post->post_title,
				'content'           => $post->post_content,
				'excerpt'           => $post->post_excerpt,
				'guid'              => $post->guid,
				'author'            => (int) $post->post_author,
				'featured_media'    => (int) get_post_thumbnail_id( $id ),
				'comment_status'    => $post->comment_status,
				'ping_status'       => $post->ping_status,
				'menu_order'        => (int) $post->menu_order,
				'date_gmt'          => mysql_to_rfc3339( $post->post_date_gmt ),
				'modified_gmt'      => mysql_to_rfc3339( $post->post_modified_gmt ),
				'terms'             => $this->terms( $id ),
				'media_refs'        => $this->media_refs->resolve( $post ),
				'url_rewrite_hints' => UrlRewriteHints::build(),
				'seo'               => $this->seo->get_post_seo( $id ),
				'meta'              => (object) array(),
			),
			$this->extra( $post )
		);
	}

	/**
	 * The summary payload: identity and planning fields only, no full content.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $full The full payload (reused to avoid recomputation).
	 * @return array
	 */
	protected function summary( WP_Post $post, array $full ) {
		$summary = array(
			'id'             => $full['id'],
			'type'           => $full['type'],
			'status'         => $full['status'],
			'slug'           => $full['slug'],
			'title'          => $full['title'],
			'guid'           => $full['guid'],
			'author'         => $full['author'],
			'featured_media' => $full['featured_media'],
			'terms'          => $full['terms'],
			'media_refs'     => $full['media_refs'],
			'date_gmt'       => $full['date_gmt'],
			'modified_gmt'   => $full['modified_gmt'],
			'seo'            => $full['seo'],
		);

		foreach ( $this->summary_extra_keys() as $key ) {
			if ( array_key_exists( $key, $full ) ) {
				$summary[ $key ] = $full[ $key ];
			}
		}

		return $summary;
	}

	/**
	 * Additional fields contributed by subclasses (e.g. page hierarchy).
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	protected function extra( WP_Post $post ) {
		return array();
	}

	/**
	 * Subclass-contributed keys that should also appear in summaries.
	 *
	 * @return string[]
	 */
	protected function summary_extra_keys() {
		return array();
	}

	/**
	 * The source resource type for this transformer.
	 *
	 * @return string
	 */
	protected function resource() {
		return 'post';
	}

	/**
	 * Collect taxonomy term IDs grouped by taxonomy.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, int[]>
	 */
	protected function terms( $post_id ) {
		$grouped    = array();
		$taxonomies = get_object_taxonomies( get_post_type( $post_id ), 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			$ids = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );

			if ( is_wp_error( $ids ) || empty( $ids ) ) {
				continue;
			}

			$grouped[ $taxonomy ] = array_map( 'intval', $ids );
		}

		return $grouped;
	}
}
