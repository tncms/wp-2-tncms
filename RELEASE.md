# Release Process

1. Update `CHANGELOG.md`.
2. Update the plugin version in `wp-2-tncms.php` (`WP2TNCMS_VERSION` and the
   plugin header) if needed.
3. Run local checks:

   ```bash
   composer install
   composer lint       # PHPCS — must be error-free (advisory warnings allowed)
   composer analyse    # PHPStan 2.2, level 5, WordPress-aware — must be clean
   php tests/phase-12-verify.php   # v1.2.0 acceptance checks (needs a running WP)
   ```

4. Commit changes.
5. Create and push a tag:

   ```bash
   git tag v1.2.0
   git push origin v1.2.0
   ```

6. The **Build Release ZIP** GitHub Actions workflow runs on the tag and:
   - assembles a `wp-2-tncms/` folder excluding dev/CI files
     (`.git`, `.github`, `vendor`, `node_modules`, `tests`, `build`, `dist`,
     `composer.lock`, `phpstan.neon`, `phpcs.xml`);
   - zips it to `wp-2-tncms-<tag>.zip`;
   - uploads it as a build artifact and publishes a GitHub Release.

The plugin ships a self-contained PSR-4 `spl_autoload_register`, so `vendor/`
is **not** required at runtime and is intentionally excluded from the ZIP.

## Installing the ZIP

WordPress Admin → **Plugins → Add New → Upload Plugin** → choose
`wp-2-tncms-<tag>.zip` → **Install Now** → **Activate**.
