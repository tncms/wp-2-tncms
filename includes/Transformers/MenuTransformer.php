<?php
/**
 * Navigation menu transformer.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Transformers;

use WP2TNCMS\Services\MenuService;
use WP2TNCMS\Support\Hashes;
use WP2TNCMS\Support\SourceKey;
use WP2TNCMS\Support\UrlRewriteHints;
use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serialises a navigation menu into the stable menus schema.
 *
 * The summary form (used by the collection) carries just the menu identity,
 * its theme locations and an item count. The full form additionally exports the
 * recursive item tree with the original item URLs preserved, the resolved
 * source identity of each linked resource and the URL rewrite hints the importer
 * needs. A stable `source` identity and dedup `hashes` are added in both forms.
 */
final class MenuTransformer {

	/**
	 * Menu service used to read locations and items.
	 *
	 * @var MenuService
	 */
	private $menus;

	/**
	 * Constructor.
	 *
	 * @param MenuService $menus Menu service.
	 */
	public function __construct( MenuService $menus ) {
		$this->menus = $menus;
	}

	/**
	 * Transform a menu into its lightweight collection summary.
	 *
	 * @param WP_Term $menu Menu term.
	 * @return array
	 */
	public function transform_summary( WP_Term $menu ) {
		$data = array(
			'id'        => (int) $menu->term_id,
			'name'      => $menu->name,
			'slug'      => $menu->slug,
			'locations' => $this->menus->locations_for( (int) $menu->term_id ),
			'count'     => (int) $menu->count,
		);

		$payload_hash   = Hashes::payload( $data );
		$data['source'] = SourceKey::build( 'menu', (int) $menu->term_id );
		$data['hashes'] = array(
			'payload' => $payload_hash,
			'content' => $payload_hash,
		);

		return $data;
	}

	/**
	 * Transform a menu into its full export payload, including the item tree.
	 *
	 * @param WP_Term $menu Menu term.
	 * @return array
	 */
	public function transform( WP_Term $menu ) {
		$data = array(
			'id'        => (int) $menu->term_id,
			'name'      => $menu->name,
			'slug'      => $menu->slug,
			'locations' => $this->menus->locations_for( (int) $menu->term_id ),
			'items'     => $this->build_tree( $this->menus->items( (int) $menu->term_id ) ),
		);

		// Hash the menu data itself; the rewrite hints are environment-derived
		// and intentionally excluded so the payload hash tracks content only.
		$payload_hash = Hashes::payload( $data );

		$data['url_rewrite_hints'] = UrlRewriteHints::menus();
		$data['source']            = SourceKey::build( 'menu', (int) $menu->term_id );
		$data['hashes']            = array(
			'payload' => $payload_hash,
			'content' => $payload_hash,
		);

		return $data;
	}

	/**
	 * Build the nested item tree from the flat, ordered item list.
	 *
	 * Items whose declared parent is absent from the menu are promoted to the
	 * root so no item is ever silently dropped. Sibling ordering follows the
	 * menu order supplied by WordPress.
	 *
	 * @param object[] $items Flat menu item list.
	 * @return array
	 */
	private function build_tree( array $items ) {
		$ids = array();

		foreach ( $items as $item ) {
			$ids[ (int) $item->ID ] = true;
		}

		$children = array();

		foreach ( $items as $item ) {
			$parent = (int) $item->menu_item_parent;

			if ( $parent > 0 && ! isset( $ids[ $parent ] ) ) {
				$parent = 0;
			}

			$children[ $parent ][] = $item;
		}

		return $this->build_branch( 0, $children );
	}

	/**
	 * Recursively assemble the children of a single parent item.
	 *
	 * @param int   $parent_id Parent item ID (0 for the root).
	 * @param array $children  Map of parent ID to ordered child items.
	 * @return array
	 */
	private function build_branch( $parent_id, array $children ) {
		$branch = array();

		if ( empty( $children[ $parent_id ] ) ) {
			return $branch;
		}

		foreach ( $children[ $parent_id ] as $item ) {
			$node             = $this->transform_item( $item );
			$node['children'] = $this->build_branch( (int) $item->ID, $children );
			$branch[]         = $node;
		}

		return $branch;
	}

	/**
	 * Serialise a single menu item (without its children).
	 *
	 * @param object $item Menu item object.
	 * @return array
	 */
	private function transform_item( $item ) {
		return array(
			'id'          => (int) $item->ID,
			'parent_id'   => (int) $item->menu_item_parent,
			'order'       => (int) $item->menu_order,
			'title'       => (string) $item->title,
			'url'         => (string) $item->url,
			'target'      => (string) $item->target,
			'attr_title'  => (string) $item->attr_title,
			'description' => (string) $item->description,
			'classes'     => $this->classes( $item->classes ),
			'xfn'         => (string) $item->xfn,
			'type'        => (string) $item->type,
			'object'      => (string) $item->object,
			'object_id'   => (int) $item->object_id,
			'resolved'    => $this->resolve_item( $item ),
			'children'    => array(),
		);
	}

	/**
	 * Normalise the menu item CSS class list, dropping the empty entries
	 * WordPress stores by default.
	 *
	 * @param mixed $classes Raw classes value.
	 * @return string[]
	 */
	private function classes( $classes ) {
		return array_values(
			array_filter(
				(array) $classes,
				static function ( $class ) {
					return '' !== (string) $class;
				}
			)
		);
	}

	/**
	 * Resolve the stable source identity of the resource a menu item links to.
	 *
	 * Items pointing at posts, pages or taxonomy terms gain a `resolved` block
	 * so the importer can re-link them by source key. Custom links and archive
	 * links resolve to null and remain `type=custom`/unresolved.
	 *
	 * @param object $item Menu item object.
	 * @return array|null
	 */
	private function resolve_item( $item ) {
		$object_id = (int) $item->object_id;

		if ( $object_id < 1 ) {
			return null;
		}

		if ( 'post_type' === $item->type ) {
			$post = get_post( $object_id );

			if ( ! $post instanceof WP_Post ) {
				return null;
			}

			$resource = 'page' === $post->post_type ? 'page' : ( 'post' === $post->post_type ? 'post' : $post->post_type );

			return array(
				'resource'   => $resource,
				'id'         => $object_id,
				'slug'       => $post->post_name,
				'source_key' => SourceKey::key( $resource, $object_id ),
			);
		}

		if ( 'taxonomy' === $item->type ) {
			$term = get_term( $object_id );

			if ( ! $term instanceof WP_Term ) {
				return null;
			}

			return array(
				'resource'   => 'term',
				'id'         => $object_id,
				'slug'       => $term->slug,
				'source_key' => SourceKey::key( 'term', $object_id ),
			);
		}

		return null;
	}
}
