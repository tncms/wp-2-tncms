<?php
/**
 * Manifest data assembly.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the per-resource counts and import strategy advertised by the
 * manifest endpoint.
 */
final class ManifestService {

	/**
	 * Term service (for the exported taxonomy list).
	 *
	 * @var TermService
	 */
	private $terms;

	/**
	 * Exportable post statuses surfaced in the counts.
	 *
	 * @var string[]
	 */
	private $statuses = array( 'publish', 'future', 'draft', 'pending', 'private' );

	/**
	 * Constructor.
	 *
	 * @param TermService $terms Term service.
	 */
	public function __construct( TermService $terms ) {
		$this->terms = $terms;
	}

	/**
	 * Resource counts.
	 *
	 * @return array
	 */
	public function counts() {
		return array(
			'users' => (int) count_users()['total_users'],
			'terms' => $this->term_counts(),
			'media' => $this->attachment_count(),
			'posts' => $this->post_counts( 'post' ),
			'pages' => $this->post_counts( 'page' ),
		);
	}

	/**
	 * Recommended import strategy hints.
	 *
	 * @return array
	 */
	public function import_strategy() {
		return array(
			'recommended_order'     => array( 'users', 'terms', 'media', 'posts', 'pages' ),
			'safe_resume'           => true,
			'dedupe_key'            => 'source.key',
			'media_skip_strategy'   => 'relative_path+filesize+checksum',
			'content_scan_required' => false,
			'recommended_per_page'  => array(
				'users' => 100,
				'terms' => 100,
				'media' => 50,
				'posts' => 20,
				'pages' => 20,
			),
		);
	}

	/**
	 * Per-taxonomy term counts.
	 *
	 * @return array<string, int>
	 */
	private function term_counts() {
		$out = array();

		foreach ( $this->terms->taxonomies() as $taxonomy ) {
			$count = wp_count_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			$out[ $taxonomy ] = is_wp_error( $count ) ? 0 : (int) $count;
		}

		return $out;
	}

	/**
	 * Count of original attachments.
	 *
	 * @return int
	 */
	private function attachment_count() {
		$counts = wp_count_posts( 'attachment' );

		return isset( $counts->inherit ) ? (int) $counts->inherit : 0;
	}

	/**
	 * Per-status counts plus total for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<string, int>
	 */
	private function post_counts( $post_type ) {
		$counts = wp_count_posts( $post_type );
		$out    = array();
		$total  = 0;

		foreach ( $this->statuses as $status ) {
			$value          = isset( $counts->$status ) ? (int) $counts->$status : 0;
			$out[ $status ] = $value;
			$total         += $value;
		}

		$out['total'] = $total;

		return $out;
	}
}
