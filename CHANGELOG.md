# Changelog

All notable changes to WP 2 TNCMS will be documented in this file.

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
