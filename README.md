# WP 2 TNCMS

Official WordPress export companion plugin for migrating WordPress content into TNCMS.

## Features

- Export users, categories, tags, media, posts, pages and navigation menus.
- Embed the featured image (`featured_image`) directly in post and page payloads
  so clients skip the extra media lookup; `featured_media` (the ID) is retained.
- Export SEO metadata from Rank Math, Yoast SEO and All in One SEO.
- Export original media only, not WordPress-generated thumbnails.
- Provide checksums, source keys, dependency maps and resume-friendly pagination.
- Support import planning without forcing the importer to scan post content.
- Resolve any resource by id, slug, source key, content hash, checksum, media path or canonical URL without downloading whole collections.
- Stable REST API under `/wp-json/wp-2-tncms/v1`.

## Requirements

- WordPress 6.x or newer
- PHP 8.1 or newer

## REST API

Public health check:

```http
GET /wp-json/wp-2-tncms/v1/health
```

Protected endpoints require:

```http
Authorization: Bearer YOUR_TOKEN
```

Protected resources:

```text
/site
/manifest
/users
/terms
/media
/posts
/pages
/menus
/dependencies
```

### Resource Lookup API (v1.2)

Resolve a single resource by an identifier without paging a collection. Every
lookup queries WordPress directly. `HEAD` is supported on single-resource
routes and returns `200` or `404` with no body.

```text
# Posts
GET  /posts/{id}
GET  /posts/slug/{slug}
GET  /posts/key/{source_key}          # wordpress:post:{id}
GET  /posts/hash/{content_hash}       # 64-char sha256 of post content

# Pages
GET  /pages/slug/{slug}
GET  /pages/key/{source_key}
GET  /pages/hash/{content_hash}

# Terms (taxonomy-aware)
GET  /terms/{taxonomy}/{id}           # e.g. /terms/category/5
GET  /terms/{taxonomy}/slug/{slug}    # e.g. /terms/post_tag/slug/news
GET  /terms/key/{source_key}

# Media
GET  /media/path/{relative_path}      # e.g. /media/path/2026/05/image.png
GET  /media/checksum/{sha256}
GET  /media/key/{source_key}

# Users
GET  /users/login/{login}
GET  /users/key/{source_key}

# Cross-resource
GET  /lookup?id=&slug=&key=&hash=&url=&type=&taxonomy=
GET  /resolve?identifier=             # auto-detects key/url/hash/id/slug
GET  /search?q=&type=&limit=          # type: post|page|media|term|user; limit<=100
```

`/lookup` returns `{ "resource": "...", "data": { ... } }`. `/resolve` returns
`{ "resolved": true, "resource": "...", "identifier": "...", "data": { ... } }`.
`/search` returns lightweight summary rows (no full content). Errors use the
existing model: `404` not found, `422` invalid identifier, `401` unauthorized,
`403` exporter disabled.

### Menus API (v1.3)

Export WordPress navigation menus and their item trees. The collection returns
lightweight menu summaries; every single-menu route returns the full menu with
its recursive item tree. `HEAD` is supported on the single-menu routes.

```text
GET  /menus                          # paginated summaries (?page=&per_page=)
GET  /menus/{id}                     # full menu + item tree
GET  /menus/slug/{slug}              # full menu by slug
GET  /menus/location/{location}      # full menu assigned to a theme location
```

Each menu item preserves the original `url` (the exporter never rewrites URLs),
its `type`/`object`/`object_id`, and — for items pointing at a post, page or
taxonomy term — a `resolved` block carrying the canonical `source_key`
(`wordpress:page:2`, `wordpress:term:5`, …) so the importer can re-link it.
Custom links stay `type=custom`, `object=custom` with `resolved: null`. The full
payload also carries `url_rewrite_hints` describing how to rewrite the source
`site_url`/`home_url` onto the destination application domain.

Menus also participate in the cross-resource lookup:

```text
GET  /lookup?key=wordpress:menu:{id}
```

The manifest advertises menus via `capabilities.menus`, `counts.menus`, the
`menus` entry in `resources`, and an `import_strategy.recommended_order` that
imports menus after posts and pages.

## Development

```bash
composer install
composer lint       # PHPCS (WordPress Coding Standards, PSR-4 aware)
composer analyse    # PHPStan level 5, WordPress-aware
```

Static analysis runs on **PHPStan 2.2** with WordPress core symbols supplied by
[`php-stubs/wordpress-stubs`](https://github.com/php-stubs/wordpress-stubs) via
[`szepeviktor/phpstan-wordpress`](https://github.com/szepeviktor/phpstan-wordpress)
(auto-registered through `phpstan/extension-installer`). Linting uses PHP_CodeSniffer
with the WordPress Coding Standards, tuned so the namespaced PSR-4 architecture is not
penalised by the legacy file/class-naming sniffs. See
[docs/development/README.md](docs/development/README.md) for details.

## Release

See [RELEASE.md](RELEASE.md) for the full process. In short, create a Git tag:

```bash
git tag v1.3.1
git push origin v1.3.1
```

GitHub Actions builds the installable release ZIP (a `wp-2-tncms/` folder, dev and
CI files excluded) and publishes it as a GitHub Release. The ZIP installs via
**WordPress Admin → Plugins → Add New → Upload Plugin**.

## License

See [LICENSE](LICENSE).
