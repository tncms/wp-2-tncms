<?php
/**
 * User transformer.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Transformers;

use WP2TNCMS\Support\Hashes;
use WP2TNCMS\Support\SourceKey;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serialises a WP_User into the stable users schema.
 *
 * Password hashes and activation keys are never exported. IDs are preserved so
 * TNCMS can map authorship on import. A stable `source` identity and dedup
 * `hashes` are added for the importer.
 */
final class UserTransformer {

	/**
	 * Transform a user.
	 *
	 * @param WP_User $user User object.
	 * @return array
	 */
	public function transform( WP_User $user ) {
		$data = array(
			'id'             => (int) $user->ID,
			'login'          => $user->user_login,
			'slug'           => $user->user_nicename,
			'display_name'   => $user->display_name,
			'email'          => $user->user_email,
			'url'            => $user->user_url,
			'description'    => $user->description,
			'roles'          => array_values( (array) $user->roles ),
			'registered_gmt' => $this->to_gmt( $user->user_registered ),
			'avatar_url'     => get_avatar_url( $user->ID ),
			'meta'           => (object) array(),
		);

		$payload_hash   = Hashes::payload( $data );
		$data['source'] = SourceKey::build( 'user', (int) $user->ID );
		$data['hashes'] = array(
			'payload' => $payload_hash,
			'content' => $payload_hash,
		);

		return $data;
	}

	/**
	 * Convert a site-local datetime string to a GMT ISO-8601 string.
	 *
	 * @param string $datetime Datetime string.
	 * @return string|null
	 */
	private function to_gmt( $datetime ) {
		if ( empty( $datetime ) ) {
			return null;
		}

		return mysql_to_rfc3339( $datetime );
	}
}
