<?php
/**
 * Shared pagination helpers.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the shared pagination argument schema and meta envelope so that
 * every collection endpoint paginates identically.
 */
final class Pagination {

	const DEFAULT_PER_PAGE = 20;
	const MAX_PER_PAGE     = 100;

	/**
	 * REST argument schema for paginated collections.
	 *
	 * Out-of-range values are clamped by the controller rather than rejected,
	 * so collection pagination always succeeds and stays within the documented
	 * error model (no undocumented 400 responses). The minimum/maximum keys are
	 * retained as schema documentation only.
	 *
	 * @return array
	 */
	public static function args() {
		return array(
			'page'     => array(
				'description'       => __( 'Current page of the collection.', 'wp-2-tncms' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items per page.', 'wp-2-tncms' ),
				'type'              => 'integer',
				'default'           => self::DEFAULT_PER_PAGE,
				'minimum'           => 1,
				'maximum'           => self::MAX_PER_PAGE,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * REST argument schema for collections, including the optional ordering,
	 * resume and filtering parameters.
	 *
	 * All filter parameters are optional and unvalidated at the schema level
	 * (values are normalised by CollectionQuery), so out-of-range or unknown
	 * values never produce an undocumented 400.
	 *
	 * @return array
	 */
	public static function collection_args() {
		return array_merge(
			self::args(),
			array(
				'orderby'        => array(
					'description'       => __( 'Sort field: id (default) or modified.', 'wp-2-tncms' ),
					'type'              => 'string',
					'default'           => 'id',
					'enum'              => array( 'id', 'modified' ),
					'sanitize_callback' => 'sanitize_key',
				),
				'order'          => array(
					'description'       => __( 'Sort direction: asc (default) or desc.', 'wp-2-tncms' ),
					'type'              => 'string',
					'default'           => 'asc',
					'enum'              => array( 'asc', 'desc' ),
					'sanitize_callback' => 'sanitize_key',
				),
				'after_id'       => array(
					'description'       => __( 'Return only records with an ID greater than this (resume cursor).', 'wp-2-tncms' ),
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
				),
				'modified_after' => array(
					'description'       => __( 'Return only records modified after this ISO-8601 datetime.', 'wp-2-tncms' ),
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'status'         => array(
					'description'       => __( 'Comma-separated post statuses (posts/pages only).', 'wp-2-tncms' ),
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'fields'         => array(
					'description'       => __( 'Payload mode: full (default) or summary (posts/pages only).', 'wp-2-tncms' ),
					'type'              => 'string',
					'default'           => 'full',
					'enum'              => array( 'full', 'summary' ),
					'sanitize_callback' => 'sanitize_key',
				),
			)
		);
	}

	/**
	 * Build the pagination meta block.
	 *
	 * @param int $total    Total number of items across all pages.
	 * @param int $count    Number of items in the current page.
	 * @param int $page     Current page number.
	 * @param int $per_page Items per page.
	 * @return array
	 */
	public static function meta( $total, $count, $page, $per_page ) {
		$total    = max( 0, (int) $total );
		$per_page = max( 1, (int) $per_page );

		return array(
			'total'        => $total,
			'count'        => max( 0, (int) $count ),
			'per_page'     => $per_page,
			'current_page' => max( 1, (int) $page ),
			'total_pages'  => (int) ceil( $total / $per_page ),
		);
	}
}
