# API Documentation

REST namespace:

```text
/wp-json/wp-2-tncms/v1
```

`api_version` is `v1` and is stable for the plugin lifetime. The manifest
`schema_version` is `1.3`.

Protected endpoints require bearer authentication:

```text
Authorization: Bearer YOUR_TOKEN
```

## Discovery & collections (unchanged)

| Method | Route | Notes |
|--------|-------|-------|
| GET | `/health` | Public. |
| GET | `/manifest` | Counts, import strategy, lookup capabilities. |
| GET | `/site` | Site metadata. |
| GET | `/users` `/users/{id}` | Collection + single. |
| GET | `/terms` `/terms/{id}` | Collection + single. |
| GET | `/media` `/media/{id}` | Collection + single. |
| GET | `/posts` `/posts/{id}` | Collection + single. |
| GET | `/pages` `/pages/{id}` | Collection + single. |
| GET | `/menus` `/menus/{id}` | Collection (summaries) + single (full item tree). |
| GET | `/dependencies` | Dependency map. |

## Resource Lookup API (v1.2, additive)

Every lookup queries WordPress directly — no collection is loaded. Single
resource responses use the existing `{ "data": { ... } }` envelope.

### Posts & pages

| Method | Route | Description |
|--------|-------|-------------|
| GET/HEAD | `/posts/slug/{slug}` | Resolve a post by slug (`post_name`). |
| GET/HEAD | `/posts/key/{source_key}` | Resolve by `wordpress:post:{id}`. |
| GET/HEAD | `/posts/hash/{content_hash}` | Resolve by 64-char sha256 of content. |
| GET/HEAD | `/pages/slug/{slug}` | Resolve a page by slug. |
| GET/HEAD | `/pages/key/{source_key}` | Resolve by `wordpress:page:{id}`. |
| GET/HEAD | `/pages/hash/{content_hash}` | Resolve by content hash. |

### Terms (taxonomy-aware)

| Method | Route | Description |
|--------|-------|-------------|
| GET/HEAD | `/terms/{taxonomy}/{id}` | e.g. `/terms/category/5`. |
| GET/HEAD | `/terms/{taxonomy}/slug/{slug}` | e.g. `/terms/post_tag/slug/news`. |
| GET/HEAD | `/terms/key/{source_key}` | Resolve by `wordpress:term:{id}`. |

### Media

| Method | Route | Description |
|--------|-------|-------------|
| GET/HEAD | `/media/path/{relative_path}` | Multi-segment relative upload path, e.g. `2026/05/image.png`. Only the exported relative path is matched; absolute filesystem paths are never exposed. |
| GET/HEAD | `/media/checksum/{sha256}` | Resolve by file checksum (available once the item has been exported). |
| GET/HEAD | `/media/key/{source_key}` | Resolve by `wordpress:media:{id}`. |

### Users

| Method | Route | Description |
|--------|-------|-------------|
| GET/HEAD | `/users/login/{login}` | Resolve by login name. |
| GET/HEAD | `/users/key/{source_key}` | Resolve by `wordpress:user:{id}`. |

### Cross-resource

| Method | Route | Description |
|--------|-------|-------------|
| GET/HEAD | `/lookup` | One of `id`, `slug`, `key`, `hash`, `url` (+ `type`, `taxonomy`). Returns `{ "resource", "data" }`. |
| GET/HEAD | `/resolve` | `identifier` (alias `q`) auto-detected, or explicit `key`/`url`/`checksum`/`id`/`slug`. Returns `{ "resolved", "resource", "identifier", "data" }`. |
| GET | `/search` | `q` (required), `type` (`post`/`page`/`media`/`term`/`user`), `limit` (default 20, max 100). Returns lightweight summary rows, never full content. |

## Menus (v1.3, additive)

WordPress navigation menus are exported as their own resource. The collection
returns lightweight summaries; the single-menu routes return the full recursive
item tree.

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/menus` | Paginated menu summaries (`?page=`, `&per_page=`). |
| GET/HEAD | `/menus/{id}` | Full menu with recursive item tree. |
| GET/HEAD | `/menus/slug/{slug}` | Full menu by slug. |
| GET/HEAD | `/menus/location/{location}` | Full menu assigned to a theme location. |

A collection summary row carries `id`, `name`, `slug`, `locations`, `count`,
`source` and `hashes`. A full menu replaces `count` with the nested `items`
tree and adds `url_rewrite_hints`.

Each menu item exports:

| Field | Notes |
|-------|-------|
| `id`, `parent_id`, `order` | Identity and ordering; the tree is also reflected in nested `children`. |
| `title`, `url`, `target`, `attr_title`, `description`, `classes`, `xfn` | Verbatim item attributes. `url` is the **original** URL — the exporter never rewrites it. |
| `type`, `object`, `object_id` | `type` is `post_type`, `taxonomy` or `custom`. |
| `resolved` | For items pointing at a post, page or term: `{ resource, id, slug, source_key }` (e.g. `wordpress:page:2`, `wordpress:term:5`). Custom links resolve to `null`. |
| `children` | Nested child items, same shape, recursively. |

`url_rewrite_hints` describes how the importer should rewrite source URLs:

```json
{
  "site_url": "https://old-site.com",
  "home_url": "https://old-site.com",
  "rules": [ { "from": "https://old-site.com", "to": "{APP_DOMAIN}" } ]
}
```

Menus participate in the manifest (`capabilities.menus`, `counts.menus`, the
`menus` entry in `resources`, and `import_strategy.recommended_order` which
imports menus after posts and pages) and in `/lookup?key=wordpress:menu:{id}`.
`/resolve?url=…` continues to resolve posts and pages only, never menus.

## Source keys

Every resource exports a stable key `wordpress:{resource}:{id}` (e.g.
`wordpress:post:10`, `wordpress:media:8`, `wordpress:user:1`,
`wordpress:menu:12`). The lookup endpoints understand this format and return
`422` when it is malformed.

## Permalink resolution

`/lookup?url=…` and `/resolve?url=…` resolve posts and pages by their canonical
permalink using WordPress' own `url_to_postid()`, so any permalink structure is
supported.

## Error model

| Status | Meaning |
|--------|---------|
| 401 | Missing or invalid bearer token. |
| 403 | Exporter disabled. |
| 404 | Resource not found. |
| 422 | Invalid identifier (malformed key/hash/path). |

All errors use the standard WordPress REST `WP_Error` JSON body.
