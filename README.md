# WP 2 TNCMS

Official WordPress export companion plugin for migrating WordPress content into TNCMS.

## Features

- Export users, categories, tags, media, posts and pages.
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
git tag v1.2.0
git push origin v1.2.0
```

GitHub Actions builds the installable release ZIP (a `wp-2-tncms/` folder, dev and
CI files excluded) and publishes it as a GitHub Release. The ZIP installs via
**WordPress Admin → Plugins → Add New → Upload Plugin**.

## License

See [LICENSE](LICENSE).
