=== WP 2 TNCMS Exporter ===
Contributors: tncms
Tags: migration, export, rest-api, tncms
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Official, export-only companion plugin for migrating WordPress content to TNCMS via a stable, versioned, read-only REST API.

== Description ==

WP 2 TNCMS exposes a read-only REST API designed for long-term backward compatibility. It exports site metadata, users, terms, media, posts and pages, including SEO metadata from Yoast, Rank Math or All in One SEO.

The plugin is export-only: it never writes, modifies or deletes WordPress content, and it does not modify any other installed plugin.

= Public API =

All endpoints live under `/wp-json/wp-2-tncms/v1`:

* `GET /health` — public liveness check.
* `GET /manifest` — API surface and capabilities.
* `GET /site` — site metadata, counts and capabilities.
* `GET /users` — users (password hashes never exported).
* `GET /terms` — categories and tags.
* `GET /media` — original uploads only, with SHA-256 checksums.
* `GET /posts` — posts with relationships, GUID, SEO and featured media.
* `GET /pages` — pages, including hierarchy and template.

Collection endpoints support `page` and `per_page` query parameters and return a consistent pagination envelope. Single items are available at `/{resource}/{id}`.

= Authentication =

All endpoints except `/health` require a bearer token:

`Authorization: Bearer <token>`

A `token` query-string parameter is accepted as a fallback for local development only. Generate and manage the token under Settings → WP 2 TNCMS.

== Changelog ==

= 1.0.0 =
* Initial release. Phase 1 export API: health, manifest, site, users, terms, media, posts, pages.
* Schema 1.1 (import optimization, backward compatible): added `source` keys and dedup `hashes` to every resource; `media_refs` + `url_rewrite_hints` on posts/pages; `storage` block on media; new `/dependencies` endpoint; manifest counts/import_order/resume/dedupe/import_strategy; stable ordering and `orderby`/`order`/`after_id`/`modified_after`/`status`/`fields=summary` query parameters. No existing fields removed or renamed.
