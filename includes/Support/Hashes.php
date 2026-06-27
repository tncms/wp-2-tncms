<?php
/**
 * Deterministic content hashing for deduplication.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces stable SHA-256 hashes so the importer can skip unchanged records.
 *
 * The payload hash is computed over a canonical serialisation of the export
 * payload with the volatile `source` and `hashes` keys removed, so it changes
 * if and only if the exported data changes.
 */
final class Hashes {

	/**
	 * SHA-256 hex digest of a string.
	 *
	 * @param string $value Value to hash.
	 * @return string
	 */
	public static function sha256( $value ) {
		return hash( 'sha256', (string) $value );
	}

	/**
	 * Hash of the normalised export payload, excluding volatile fields.
	 *
	 * @param array $data Transformed payload (the `hashes`/`source` keys, if
	 *                    present, are ignored).
	 * @return string
	 */
	public static function payload( array $data ) {
		unset( $data['hashes'], $data['source'] );

		return self::sha256( self::canonical( $data ) );
	}

	/**
	 * Canonical, order-independent serialisation of a value.
	 *
	 * Associative arrays are key-sorted; lists preserve order. This guarantees
	 * the same payload always hashes to the same value.
	 *
	 * @param mixed $value Value to serialise.
	 * @return string
	 */
	private static function canonical( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( is_array( $value ) ) {
			if ( ! self::is_list( $value ) ) {
				ksort( $value );
			}

			$parts = array();
			foreach ( $value as $key => $item ) {
				$parts[] = $key . ':' . self::canonical( $item );
			}

			return '{' . implode( ',', $parts ) . '}';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( null === $value ) {
			return 'null';
		}

		return (string) $value;
	}

	/**
	 * Whether an array is a sequential list (0..n-1 integer keys).
	 *
	 * @param array $array Array to inspect.
	 * @return bool
	 */
	private static function is_list( array $array ) {
		if ( array() === $array ) {
			return true;
		}

		return array_keys( $array ) === range( 0, count( $array ) - 1 );
	}
}
