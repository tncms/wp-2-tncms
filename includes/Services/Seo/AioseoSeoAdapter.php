<?php
/**
 * All in One SEO adapter.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalises All in One SEO (AIOSEO) metadata into the stable SEO schema.
 *
 * AIOSEO v4 stores per-post SEO in the custom `{prefix}aioseo_posts` table.
 * This adapter reads that table when present and degrades gracefully to the
 * empty schema otherwise.
 */
final class AioseoSeoAdapter extends AbstractSeoAdapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'aioseo';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_post_seo( $post_id ) {
		global $wpdb;

		$seo   = $this->empty_seo();
		$table = $wpdb->prefix . 'aioseo_posts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- AIOSEO has no public read API for raw export values.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $exists !== $table ) {
			return $seo;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internally derived, value is prepared.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d LIMIT 1", $post_id ),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return $seo;
		}

		$seo['title']         = isset( $row['title'] ) ? (string) $row['title'] : '';
		$seo['description']   = isset( $row['description'] ) ? (string) $row['description'] : '';
		$seo['focus_keyword'] = isset( $row['keywords'] ) ? (string) $row['keywords'] : '';
		$seo['canonical']     = isset( $row['canonical_url'] ) ? (string) $row['canonical_url'] : '';

		if ( isset( $row['robots_noindex'] ) && '' !== $row['robots_noindex'] ) {
			$seo['robots']['index'] = ! (bool) (int) $row['robots_noindex'];
		}
		if ( isset( $row['robots_nofollow'] ) && '' !== $row['robots_nofollow'] ) {
			$seo['robots']['follow'] = ! (bool) (int) $row['robots_nofollow'];
		}

		$seo['open_graph']['title']       = isset( $row['og_title'] ) ? (string) $row['og_title'] : '';
		$seo['open_graph']['description'] = isset( $row['og_description'] ) ? (string) $row['og_description'] : '';
		$seo['open_graph']['image']       = isset( $row['og_image_custom_url'] ) ? (string) $row['og_image_custom_url'] : '';

		$seo['twitter']['title']       = isset( $row['twitter_title'] ) ? (string) $row['twitter_title'] : '';
		$seo['twitter']['description'] = isset( $row['twitter_description'] ) ? (string) $row['twitter_description'] : '';
		$seo['twitter']['image']       = isset( $row['twitter_image_custom_url'] ) ? (string) $row['twitter_image_custom_url'] : '';

		return $seo;
	}
}
