<?php
/**
 * User query service.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services;

use WP_User;
use WP_User_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries users for export.
 *
 * Supports ordering and the after_id resume cursor (users have no modified
 * timestamp, so modified_after does not apply).
 */
final class UserService {

	/**
	 * Query a page of users.
	 *
	 * @param int   $page     Page number (1-based).
	 * @param int   $per_page Items per page.
	 * @param array $filters  Optional filters from CollectionQuery.
	 * @return array{items: WP_User[], total: int}
	 */
	public function paginate( $page, $per_page, array $filters = array() ) {
		$args = array(
			'number'      => (int) $per_page,
			'paged'       => (int) $page,
			'orderby'     => 'ID',
			'order'       => isset( $filters['order'] ) && 'DESC' === $filters['order'] ? 'DESC' : 'ASC',
			'count_total' => true,
			'fields'      => 'all',
		);

		$after_id = isset( $filters['after_id'] ) ? (int) $filters['after_id'] : 0;
		$callback = null;

		if ( $after_id > 0 ) {
			global $wpdb;
			$callback = static function ( $query ) use ( $wpdb, $after_id ) {
				$query->query_where .= $wpdb->prepare( " AND {$wpdb->users}.ID > %d", $after_id );
			};
			add_action( 'pre_user_query', $callback );
		}

		$query = new WP_User_Query( $args );

		if ( null !== $callback ) {
			remove_action( 'pre_user_query', $callback );
		}

		return array(
			'items' => $query->get_results(),
			'total' => (int) $query->get_total(),
		);
	}

	/**
	 * Find a single user by ID.
	 *
	 * @param int $id User ID.
	 * @return WP_User|null
	 */
	public function find( $id ) {
		$user = get_user_by( 'id', (int) $id );

		return $user instanceof WP_User ? $user : null;
	}
}
