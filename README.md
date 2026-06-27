# WP 2 TNCMS

Official WordPress export companion plugin for migrating WordPress content into TNCMS.

## Features

- Export users, categories, tags, media, posts and pages.
- Export SEO metadata from Rank Math, Yoast SEO and All in One SEO.
- Export original media only, not WordPress-generated thumbnails.
- Provide checksums, source keys, dependency maps and resume-friendly pagination.
- Support import planning without forcing the importer to scan post content.
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

## Development

```bash
composer install
composer lint
composer analyse
```

## Release

Create a Git tag:

```bash
git tag v1.1.0
git push origin v1.1.0
```

GitHub Actions can build a release ZIP automatically.

## License

See [LICENSE](LICENSE).
