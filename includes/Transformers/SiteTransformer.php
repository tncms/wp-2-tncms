<?php
/**
 * Site transformer.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Transformers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site payload is already a flat array assembled by SiteService.
 *
 * A dedicated transformer is retained so the contract stays consistent with
 * the other resources and can evolve independently of the service.
 */
final class SiteTransformer {

	/**
	 * Transform the site info payload.
	 *
	 * @param array $info Site info from SiteService.
	 * @return array
	 */
	public function transform( array $info ) {
		return $info;
	}
}
