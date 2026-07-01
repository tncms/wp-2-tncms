# cURL Examples

All protected endpoints take `-H "Authorization: Bearer YOUR_TOKEN"`. Replace
`https://example.com` with your site URL.

## Discovery & collections

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/manifest"
```

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/posts?fields=summary&page=1&per_page=20"
```

## Featured image (embedded, no extra media request)

```bash
# The featured attachment is embedded as `featured_image`; `.featured_media`
# still returns just the ID. Prefer `featured_image` to avoid a second request.
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/posts/123" \
  | jq '.data | {featured_media, featured_image}'
```

## Posts & pages lookup

```bash
# By slug
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/posts/slug/hello-world"

# By source key
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/posts/key/wordpress:post:123"

# By content hash (64-char sha256)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/posts/hash/3a7bd3e2360a3d29eea436fcfb7e44c735d117c42d1c1835420b6b9942dd4f1b"

# Pages
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/pages/slug/about"
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/pages/key/wordpress:page:42"
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/pages/hash/3a7bd3e2360a3d29eea436fcfb7e44c735d117c42d1c1835420b6b9942dd4f1b"
```

## Terms lookup (taxonomy-aware)

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/terms/category/5"

curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/terms/category/slug/news"

curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/terms/post_tag/12"

curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/terms/post_tag/slug/featured"

curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/terms/key/wordpress:term:5"
```

## Media lookup

```bash
# By relative path (multiple path segments allowed)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/media/path/2026/05/image.png"

# By sha256 checksum
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/media/checksum/9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08"

# By source key
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/media/key/wordpress:media:8"
```

## Users lookup

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/users/login/admin"

curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/users/key/wordpress:user:1"
```

## Menus

```bash
# Collection of menu summaries
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/menus?page=1&per_page=20"

# Full menu (with recursive item tree) by id
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/menus/12"

# By slug
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/menus/slug/primary-menu"

# By theme location
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/menus/location/primary"

# Cross-resource lookup by source key
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/lookup?key=wordpress:menu:12"
```

## Cross-resource: lookup

```bash
# By source key
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/lookup?key=wordpress:post:123"

# By slug + type
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/lookup?slug=hello-world&type=post"

# By canonical URL (permalink resolution)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/lookup?url=https://example.com/hello-world/"

# By content hash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/lookup?hash=3a7bd3e2360a3d29eea436fcfb7e44c735d117c42d1c1835420b6b9942dd4f1b"
```

## Cross-resource: resolve

```bash
# Auto-detected identifier
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/resolve?identifier=wordpress:media:8"

curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/resolve?url=https://example.com/about/"

curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/resolve?checksum=9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08"
```

## Cross-resource: search

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/search?q=hello&type=post&limit=20"

curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/search?q=logo&type=media"
```

## HEAD requests (existence checks)

```bash
# 200 if it exists, 404 if not — no response body
curl -I -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/posts/123"

curl -I -H "Authorization: Bearer YOUR_TOKEN" \
  "https://example.com/wp-json/wp-2-tncms/v1/media/path/2026/05/image.png"
```
