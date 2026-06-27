<?php
/**
 * Collection query parameter parsing.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Support;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses the optional ordering, resume and filtering parameters shared by the
 * collection endpoints into a normalised filter array.
 *
 * All parameters are optional; when absent the defaults preserve the original
 * v1 behaviour (stable `id ASC` ordering, no filtering, full payloads).
 */
final class CollectionQuery {

	/**
	 * Build a normalised filter array from a request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return array{orderby:string, order:string, after_id:int, modified_after:string, status:?array, fields:string}
	 */
	public static function from_request( WP_REST_Request $request ) {
		return array(
			'orderby'        => 'modified' === $request->get_param( 'orderby' ) ? 'modified' : 'id',
			'order'          => 'desc' === strtolower( (string) $request->get_param( 'order' ) ) ? 'DESC' : 'ASC',
			'after_id'       => max( 0, (int) $request->get_param( 'after_id' ) ),
			'modified_after' => self::normalize_date( (string) $request->get_param( 'modified_after' ) ),
			'status'         => self::parse_status( (string) $request->get_param( 'status' ) ),
			'fields'         => 'summary' === $request->get_param( 'fields' ) ? 'summary' : 'full',
		);
	}

	/**
	 * Normalise a date string; returns '' when it cannot be parsed.
	 *
	 * @param string $value Raw date string.
	 * @return string
	 */
	private static function normalize_date( $value ) {
		$value = trim( $value );

		if ( '' === $value || false === strtotime( $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Parse a comma-separated status list into a clean array.
	 *
	 * @param string $value Raw status list.
	 * @return array|null Null when no status filter was supplied.
	 */
	private static function parse_status( $value ) {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		$statuses = array_filter(
			array_map(
				static function ( $status ) {
					return sanitize_key( trim( $status ) );
				},
				explode( ',', $value )
			)
		);

		return empty( $statuses ) ? null : array_values( array_unique( $statuses ) );
	}
}
