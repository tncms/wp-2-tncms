# Development

Install tools:

```bash
composer install
```

Run checks:

```bash
composer lint       # PHP_CodeSniffer (WordPress Coding Standards)
composer lint:fix   # PHPCBF auto-fix (safe formatting only)
composer analyse    # PHPStan 2.2, level 5
```

## Static analysis (PHPStan 2.x)

Static analysis runs on **PHPStan `^2.2`**. WordPress core symbols
(`add_action`, `apply_filters`, `register_rest_route`, `get_option`,
`get_post`, `get_posts`, `get_terms`, `get_user_by`, `wp_get_upload_dir`,
`wp_get_attachment_metadata`, `WP_Post`, `WP_User`, `WP_Term`, `WP_Error`,
`WP_Query`, `WP_User_Query`, `WP_REST_Request`, `WP_REST_Response`, …) are
provided by:

- [`php-stubs/wordpress-stubs`](https://github.com/php-stubs/wordpress-stubs) — the stub definitions, and
- [`szepeviktor/phpstan-wordpress`](https://github.com/szepeviktor/phpstan-wordpress) — the PHPStan extension that loads them and models WordPress-specific dynamic return types.

These are auto-registered via
[`phpstan/extension-installer`](https://github.com/phpstan/extension-installer)
(allowed in `composer.json` under `config.allow-plugins`), so no manual
`includes:` of the extension is needed in `phpstan.neon`.

Configuration notes (`phpstan.neon`):

- `level: 5`, paths `includes/` and `wp-2-tncms.php`.
- `bootstrapFiles: tests/phpstan-bootstrap.php` declares the plugin-level
  constants (`WP2TNCMS_*`) defined at runtime in the main file, so they resolve
  across the `includes/` tree without `ignoreErrors`.
- `treatPhpDocTypesAsCertain: false` — PHPDoc annotations are treated as
  advisory rather than runtime guarantees. This keeps the plugin's defensive
  guards (`is_string()` on request headers, `instanceof` checks on WordPress
  core return values and `apply_filters` results) instead of reporting them as
  "always true" and pressuring their removal at trust boundaries.
- The PHPStan-2.x-invalid `checkMissingIterableValueType` option is **not**
  used (it was removed in PHPStan 2.x).
- `composer analyse` runs with `--memory-limit=1G` because loading the full
  WordPress stub set exceeds the default 512M limit.

## Coding standards (PHPCS + PSR-4)

The plugin is a namespaced, PSR-4 autoloaded, OOP codebase (services,
transformers, controllers). `phpcs.xml` keeps the WordPress Coding Standards
useful while not rejecting the modern architecture:

- `WordPress.Files.FileName` and
  `WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase` are
  excluded — files are **not** renamed to the legacy `class-*.php` style.
- `Universal.NamingConventions.NoReservedKeywordParameterNames` is excluded —
  descriptive parameter names like `$namespace`, `$resource`, `$class`,
  `$object`, `$array` are local symbols only.
- **`WordPress.WP.CapitalPDangit.MisspelledInText` is excluded only for the
  files that contain the source-key literal** (`includes/Support/SourceKey.php`,
  `includes/Rest/ResolveController.php`). The Resource Lookup API source key is
  exactly `wordpress:{resource}:{id}` (lowercase); this sniff would otherwise
  "correct" it to `WordPress:` and break every stored key and REST contract.
  The sniff stays active everywhere else.
- Security sniffs remain enabled globally. Advisory warnings (e.g.
  `WordPress.DB.SlowDBQuery`) stay visible but do not fail CI
  (`ignore_warnings_on_exit`).

> **Never** let PHPCBF rewrite the `wordpress:` source-key literals. After any
> `composer lint:fix`, verify with:
>
> ```bash
> grep -rn "wordpress:" includes/   # must stay lowercase, never WordPress:
> ```

## Continuous integration

Three workflows live in `.github/workflows/`:

| Workflow | Runs | Purpose |
|----------|------|---------|
| `phpcs.yml` | `composer lint` | Coding standards (errors block, warnings advisory) |
| `phpstan.yml` | `composer analyse` | Static analysis, WordPress-aware |
| `release.yml` | on `v*` tags | Builds the installable ZIP and publishes a GitHub Release |

See [../../RELEASE.md](../../RELEASE.md) for the release/ZIP process.
