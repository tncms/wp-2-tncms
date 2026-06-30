<?php
/**
 * Cross-resource identifier resolution.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Services;

use WP2TNCMS\Support\SourceKey;
use WP2TNCMS\Transformers\MediaTransformer;
use WP2TNCMS\Transformers\MenuTransformer;
use WP2TNCMS\Transformers\PageTransformer;
use WP2TNCMS\Transformers\PostTransformer;
use WP2TNCMS\Transformers\TermTransformer;
use WP2TNCMS\Transformers\UserTransformer;
use WP_Post;
use WP_Term;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves an arbitrary identifier (id, slug, source key, hash, checksum or
 * URL) into a single canonical resource, transforming it with the matching
 * transformer.
 *
 * Every resolution returns `array{resource:string, data:array}` or null; no
 * collection is ever loaded. This is the shared engine behind the /lookup and
 * /resolve endpoints so both expose identical resolution semantics.
 */
final class ResourceLocator {

	/**
	 * Post service.
	 *
	 * @var PostService
	 */
	private $posts;

	/**
	 * Media service.
	 *
	 * @var MediaService
	 */
	private $media;

	/**
	 * Term service.
	 *
	 * @var TermService
	 */
	private $terms;

	/**
	 * User service.
	 *
	 * @var UserService
	 */
	private $users;

	/**
	 * Menu service.
	 *
	 * @var MenuService
	 */
	private $menus;

	/**
	 * Post transformer.
	 *
	 * @var PostTransformer
	 */
	private $post_transformer;

	/**
	 * Page transformer.
	 *
	 * @var PageTransformer
	 */
	private $page_transformer;

	/**
	 * Media transformer.
	 *
	 * @var MediaTransformer
	 */
	private $media_transformer;

	/**
	 * Term transformer.
	 *
	 * @var TermTransformer
	 */
	private $term_transformer;

	/**
	 * User transformer.
	 *
	 * @var UserTransformer
	 */
	private $user_transformer;

	/**
	 * Menu transformer.
	 *
	 * @var MenuTransformer
	 */
	private $menu_transformer;

	/**
	 * Constructor.
	 *
	 * @param PostService      $posts             Post service.
	 * @param MediaService     $media             Media service.
	 * @param TermService      $terms             Term service.
	 * @param UserService      $users             User service.
	 * @param MenuService      $menus             Menu service.
	 * @param PostTransformer  $post_transformer  Post transformer.
	 * @param PageTransformer  $page_transformer  Page transformer.
	 * @param MediaTransformer $media_transformer Media transformer.
	 * @param TermTransformer  $term_transformer  Term transformer.
	 * @param UserTransformer  $user_transformer  User transformer.
	 * @param MenuTransformer  $menu_transformer  Menu transformer.
	 */
	public function __construct(
		PostService $posts,
		MediaService $media,
		TermService $terms,
		UserService $users,
		MenuService $menus,
		PostTransformer $post_transformer,
		PageTransformer $page_transformer,
		MediaTransformer $media_transformer,
		TermTransformer $term_transformer,
		UserTransformer $user_transformer,
		MenuTransformer $menu_transformer
	) {
		$this->posts             = $posts;
		$this->media             = $media;
		$this->terms             = $terms;
		$this->users             = $users;
		$this->menus             = $menus;
		$this->post_transformer  = $post_transformer;
		$this->page_transformer  = $page_transformer;
		$this->media_transformer = $media_transformer;
		$this->term_transformer  = $term_transformer;
		$this->user_transformer  = $user_transformer;
		$this->menu_transformer  = $menu_transformer;
	}

	/**
	 * Resolve a stable source key (`wordpress:{resource}:{id}`).
	 *
	 * @param string $key Source key.
	 * @return array{resource:string, data:array}|null
	 */
	public function by_key( $key ) {
		$parsed = SourceKey::parse( $key );

		if ( null === $parsed ) {
			return null;
		}

		return $this->by_id( $parsed['resource'], $parsed['id'] );
	}

	/**
	 * Resolve a resource by type and numeric ID.
	 *
	 * @param string $type Resource type: post|page|media|user|term.
	 * @param int    $id   Resource ID.
	 * @return array{resource:string, data:array}|null
	 */
	public function by_id( $type, $id ) {
		$id = (int) $id;

		switch ( $type ) {
			case 'post':
				return $this->wrap( 'post', $this->posts->find( 'post', $id ) );
			case 'page':
				return $this->wrap( 'page', $this->posts->find( 'page', $id ) );
			case 'media':
				return $this->wrap( 'media', $this->media->find( $id ) );
			case 'user':
				return $this->wrap( 'user', $this->users->find( $id ) );
			case 'term':
				return $this->wrap( 'term', $this->terms->find( $id ) );
			case 'menu':
				return $this->wrap( 'menu', $this->menus->find( $id ) );
		}

		return null;
	}

	/**
	 * Resolve a resource by slug for a given type.
	 *
	 * @param string $type     Resource type.
	 * @param string $slug     Slug value.
	 * @param string $taxonomy Optional taxonomy when type is term.
	 * @return array{resource:string, data:array}|null
	 */
	public function by_slug( $type, $slug, $taxonomy = '' ) {
		switch ( $type ) {
			case 'post':
				return $this->wrap( 'post', $this->posts->find_by_slug( 'post', $slug ) );
			case 'page':
				return $this->wrap( 'page', $this->posts->find_by_slug( 'page', $slug ) );
			case 'user':
				return $this->wrap( 'user', $this->users->find_by_slug( $slug ) );
			case 'term':
				$term = '' !== $taxonomy
					? $this->terms->find_by_slug( $taxonomy, $slug )
					: $this->terms->find_by_slug_any( $slug );
				return $this->wrap( 'term', $term );
			case 'menu':
				return $this->wrap( 'menu', $this->menus->find_by_slug( $slug ) );
		}

		return null;
	}

	/**
	 * Resolve a post or page by its canonical permalink.
	 *
	 * @param string $url Canonical URL.
	 * @return array{resource:string, data:array}|null
	 */
	public function by_url( $url ) {
		$post = $this->posts->find_by_url( $url );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		if ( 'page' === $post->post_type ) {
			return $this->wrap( 'page', $post );
		}

		if ( 'post' === $post->post_type ) {
			return $this->wrap( 'post', $post );
		}

		return null;
	}

	/**
	 * Resolve by SHA-256 checksum (media only).
	 *
	 * @param string $checksum Checksum.
	 * @return array{resource:string, data:array}|null
	 */
	public function by_checksum( $checksum ) {
		return $this->wrap( 'media', $this->media->find_by_checksum( $checksum ) );
	}

	/**
	 * Resolve by content/payload hash or media checksum, in that order.
	 *
	 * @param string $hash Hash value.
	 * @return array{resource:string, data:array}|null
	 */
	public function by_hash( $hash ) {
		foreach ( array( 'post', 'page' ) as $type ) {
			$post = $this->posts->find_by_content_hash( $type, $hash );

			if ( $post instanceof WP_Post ) {
				return $this->wrap( $type, $post );
			}
		}

		$media = $this->media->find_by_checksum( $hash );

		if ( $media instanceof WP_Post ) {
			return $this->wrap( 'media', $media );
		}

		foreach ( array( 'post', 'page' ) as $type ) {
			$post = $this->posts->find_by_payload_hash( $type, $hash );

			if ( $post instanceof WP_Post ) {
				return $this->wrap( $type, $post );
			}
		}

		return null;
	}

	/**
	 * Wrap a resolved object in the resource envelope, transforming it.
	 *
	 * @param string                       $resource Resource type.
	 * @param WP_Post|WP_Term|WP_User|null $object   Resolved object.
	 * @return array{resource:string, data:array}|null
	 */
	private function wrap( $resource, $object ) {
		if ( null === $object ) {
			return null;
		}

		switch ( $resource ) {
			case 'post':
				$data = $this->post_transformer->transform( $object );
				break;
			case 'page':
				$data = $this->page_transformer->transform( $object );
				break;
			case 'media':
				$data = $this->media_transformer->transform( $object );
				break;
			case 'term':
				$data = $this->term_transformer->transform( $object );
				break;
			case 'user':
				$data = $this->user_transformer->transform( $object );
				break;
			case 'menu':
				$data = $this->menu_transformer->transform( $object );
				break;
			default:
				return null;
		}

		return array(
			'resource' => $resource,
			'data'     => $data,
		);
	}
}
