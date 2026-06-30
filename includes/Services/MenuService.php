<?php
/**
 * Navigation menu query service.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services;

use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries WordPress navigation menus (the `nav_menu` taxonomy) for export.
 *
 * Menus are terms, their items are `nav_menu_item` posts and their theme
 * locations come from the `nav_menu_locations` theme mod. This service hides
 * those details behind a small read-only API so the transformer and the
 * resource locator never touch WordPress menu internals directly.
 */
final class MenuService {

	/**
	 * Query a page of menus.
	 *
	 * Menus are ordered by term ID so pagination and the after_id resume cursor
	 * stay stable, matching the ordering used by the other collections.
	 *
	 * @param int   $page     Page number (1-based).
	 * @param int   $per_page Items per page.
	 * @param array $filters  Optional filters from CollectionQuery.
	 * @return array{items: WP_Term[], total: int}
	 */
	public function paginate( $page, $per_page, array $filters = array() ) {
		$menus = wp_get_nav_menus();

		if ( is_wp_error( $menus ) || ! is_array( $menus ) ) {
			$menus = array();
		}

		usort(
			$menus,
			static function ( $a, $b ) {
				return (int) $a->term_id - (int) $b->term_id;
			}
		);

		if ( isset( $filters['order'] ) && 'DESC' === $filters['order'] ) {
			$menus = array_reverse( $menus );
		}

		$after_id = isset( $filters['after_id'] ) ? (int) $filters['after_id'] : 0;

		if ( $after_id > 0 ) {
			$menus = array_values(
				array_filter(
					$menus,
					static function ( $menu ) use ( $after_id ) {
						return (int) $menu->term_id > $after_id;
					}
				)
			);
		}

		$total  = count( $menus );
		$offset = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;

		return array(
			'items' => array_slice( $menus, $offset, (int) $per_page ),
			'total' => $total,
		);
	}

	/**
	 * Find a single menu by ID.
	 *
	 * @param int $id Menu (term) ID.
	 * @return WP_Term|null
	 */
	public function find( $id ) {
		$menu = wp_get_nav_menu_object( (int) $id );

		return $menu instanceof WP_Term ? $menu : null;
	}

	/**
	 * Find a menu by its slug.
	 *
	 * @param string $slug Menu slug.
	 * @return WP_Term|null
	 */
	public function find_by_slug( $slug ) {
		$slug = sanitize_title( (string) $slug );

		if ( '' === $slug ) {
			return null;
		}

		$menu = wp_get_nav_menu_object( $slug );

		return $menu instanceof WP_Term ? $menu : null;
	}

	/**
	 * Find the menu assigned to a registered theme location.
	 *
	 * @param string $location Theme location slug.
	 * @return WP_Term|null
	 */
	public function find_by_location( $location ) {
		$location  = sanitize_key( (string) $location );
		$locations = get_nav_menu_locations();

		if ( '' === $location || empty( $locations[ $location ] ) ) {
			return null;
		}

		return $this->find( (int) $locations[ $location ] );
	}

	/**
	 * Theme locations a menu is currently assigned to.
	 *
	 * @param int $menu_id Menu (term) ID.
	 * @return string[]
	 */
	public function locations_for( $menu_id ) {
		$menu_id = (int) $menu_id;
		$out     = array();

		foreach ( get_nav_menu_locations() as $location => $assigned_id ) {
			if ( (int) $assigned_id === $menu_id ) {
				$out[] = (string) $location;
			}
		}

		return $out;
	}

	/**
	 * The ordered, flat list of items belonging to a menu.
	 *
	 * Items are returned in menu order so the transformer can rebuild the tree
	 * while preserving sibling ordering.
	 *
	 * @param int $menu_id Menu (term) ID.
	 * @return object[]
	 */
	public function items( $menu_id ) {
		$items = wp_get_nav_menu_items(
			(int) $menu_id,
			array( 'update_post_term_cache' => false )
		);

		return is_array( $items ) ? $items : array();
	}
}
