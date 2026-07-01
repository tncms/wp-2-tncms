# Changelog

All notable changes to WP 2 TNCMS will be documented in this file.

## [1.3.1] - 2026-07-01

### Added
- Posts and pages now embed the featured image as `featured_image`: the full
  media object produced by the shared `MediaTransformer` (id, original `url`,
  `relative_path`, `mime_type`, `dimensions`, `filesize`, `checksum`, `alt_text`,
  `caption`, `description`, `storage`, `source` and `hashes`). Clients no longer
  need a follow-up `/media/{id}` request to render a post's featured image.
- Manifest `capabilities.embedded_featured_image` advertises the new field.
- Acceptance harness `tests/phase-131-featured-image-verify.php`.

### Changed
- Manifest **schema version 1.3.1** (was `1.3`).

### Performance
- The collection endpoints prime the featured attachment post + meta caches once
  per page (`_prime_post_caches`), so embedding each `featured_image` is a cache
  hit rather than an N+1 attachment query.

### Notes
- Additive and backward compatible. `api_version` remains `v1`; `featured_media`
  (the integer attachment ID) is unchanged and all existing response shapes are
  preserved. `featured_image` is `null` when a post has no featured image.
  Only the original upload is described — generated thumbnail sizes are never
  exposed and URLs are never rewritten.

## [1.3.0] - 2026-06-30

### Added
- Menus export API: `/menus`, `/menus/{id}`, `/menus/slug/{slug}` and
  `/menus/location/{location}`, all bearer-protected with paginated collections
  and `HEAD` support on single-menu routes.
- `MenuService`, `MenuTransformer` and `MenusController` exporting the recursive
  menu item tree with parent/child hierarchy and original item URLs preserved.
- Resolved item metadata (`resolved` block with `source_key`) when a menu item
  points at a post, page or taxonomy term; custom links stay `type=custom`.
- Menu `url_rewrite_hints` describing how to rewrite the source `site_url`/
  `home_url` onto the destination application domain (the exporter never
  rewrites URLs itself).
- Cross-resource lookup by menu source key (`/lookup?key=wordpress:menu:{id}`);
  `menu` added to the recognised source-key resources.
- Manifest **schema version 1.3**: `capabilities.menus`, `counts.menus`, the
  `menus` entry in `resources`, and `import_strategy.recommended_order` /
  `import_order` now place menus after posts and pages.
- Acceptance harness `tests/phase-13-menus-verify.php`.

### Notes
- Additive only. `api_version` remains `v1`; all existing endpoints and response
  shapes are unchanged.

## [1.2.0] - 2026-06-29

### Added
- Resource Lookup API: resolve and look up resources by stable source key
  (`wordpress:{resource}:{id}`), plus cross-resource search.
- `/lookup`, `/resolve` and `/search` endpoints with their controllers and
  supporting services (`ResourceLocator`, `LookupIndex`, `SearchService`).

### Changed
- Tooling: migrated static analysis to **PHPStan 2.2** with WordPress-aware
  stubs (`php-stubs/wordpress-stubs` + `szepeviktor/phpstan-wordpress`, wired
  through `phpstan/extension-installer`). Removed PHPStan 1.x-only config.
- PHPCS configuration modernised for the namespaced PSR-4 architecture
  (WordPress file/class-naming sniffs excluded; source-key spelling literals
  protected from the `CapitalPDangit` autofix).
- Release workflow now produces an installable `wp-2-tncms/` ZIP and publishes
  a GitHub Release on tag push.

## [1.1.0] - 2026-06-27

### Added
- Import Optimization Contract.
- Stable source keys for all resources.
- Payload and content SHA-256 hashes.
- Manifest schema version 1.1.
- Resume parameters: `after_id`, `modified_after`, `orderby`, `order`, `status`.
- `media_refs` for posts and pages.
- `url_rewrite_hints`.
- Media storage metadata and checksum.
- Protected `/dependencies` endpoint.
- `fields=summary` lightweight mode.
- Import strategy recommendations.

## [1.0.0]

### Added
- Initial exporter API.
- Health, manifest, site, users, terms, media, posts and pages endpoints.
- Bearer token authentication.
- SEO adapter support.
