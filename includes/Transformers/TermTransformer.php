<?php
/**
 * Term transformer.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Transformers;

use WP2TNCMS\Support\Hashes;
use WP2TNCMS\Support\SourceKey;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serialises a WP_Term into the stable terms schema.
 *
 * IDs and parent IDs are preserved so taxonomy hierarchies can be rebuilt on
 * import. A reserved `meta` object is included for future use. A stable
 * `source` identity and dedup `hashes` are added for the importer.
 */
final class TermTransformer {

	/**
	 * Transform a term.
	 *
	 * @param WP_Term $term Term object.
	 * @return array
	 */
	public function transform( WP_Term $term ) {
		$data = array(
			'id'          => (int) $term->term_id,
			'taxonomy'    => $term->taxonomy,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
			'meta'        => (object) array(),
		);

		$payload_hash   = Hashes::payload( $data );
		$data['source'] = SourceKey::build( 'term', (int) $term->term_id );
		$data['hashes'] = array(
			'payload' => $payload_hash,
			'content' => $payload_hash,
		);

		return $data;
	}
}
