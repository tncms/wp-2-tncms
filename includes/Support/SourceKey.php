<?php
/**
 * Stable source identity builder.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the stable `source` identity object attached to every resource.
 *
 * The `key` (`wordpress:{resource}:{id}`) is permanent and is intended to be
 * used by the TNCMS importer as the `import_map` source key, so it must never
 * change format for the lifetime of the v1 API.
 */
final class SourceKey {

	/**
	 * Build the source identity for a resource.
	 *
	 * @param string $resource Resource type: post|page|media|user|term.
	 * @param int    $id       Resource ID.
	 * @return array
	 */
	public static function build( $resource, $id ) {
		$id = (int) $id;

		return array(
			'system'   => 'wordpress',
			'site_url' => home_url(),
			'resource' => $resource,
			'id'       => $id,
			'key'      => self::key( $resource, $id ),
		);
	}

	/**
	 * Build just the stable key string.
	 *
	 * @param string $resource Resource type.
	 * @param int    $id       Resource ID.
	 * @return string
	 */
	public static function key( $resource, $id ) {
		return 'wordpress:' . $resource . ':' . (int) $id;
	}
}
