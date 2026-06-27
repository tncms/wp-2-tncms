<?php
/**
 * SEO provider detection and resolution.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects the active SEO provider and resolves normalised post SEO.
 *
 * Adapters are filterable via `wp2tncms_seo_adapters`, allowing future
 * providers to be registered without modifying this class.
 */
final class SeoManager {

	/**
	 * Resolved adapter, cached for the request.
	 *
	 * @var SeoAdapterInterface|null
	 */
	private $adapter = null;

	/**
	 * Resolve the active adapter, falling back to the null adapter.
	 *
	 * @return SeoAdapterInterface
	 */
	public function adapter() {
		if ( null !== $this->adapter ) {
			return $this->adapter;
		}

		/**
		 * Filter the ordered list of candidate SEO adapters.
		 *
		 * The first active adapter wins. Register additional adapters here to
		 * support further providers.
		 *
		 * @param SeoAdapterInterface[] $adapters Candidate adapters.
		 */
		$adapters = apply_filters(
			'wp2tncms_seo_adapters',
			array(
				new YoastSeoAdapter(),
				new RankMathSeoAdapter(),
				new AioseoSeoAdapter(),
			)
		);

		foreach ( $adapters as $adapter ) {
			if ( $adapter instanceof SeoAdapterInterface && $adapter->is_active() ) {
				$this->adapter = $adapter;
				return $this->adapter;
			}
		}

		$this->adapter = new NullSeoAdapter();

		return $this->adapter;
	}

	/**
	 * Slug of the active SEO provider.
	 *
	 * @return string
	 */
	public function provider() {
		return $this->adapter()->get_slug();
	}

	/**
	 * Normalised SEO metadata for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_post_seo( $post_id ) {
		return $this->adapter()->get_post_seo( (int) $post_id );
	}
}
