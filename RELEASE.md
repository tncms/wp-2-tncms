# Release Process

1. Update `CHANGELOG.md`.
2. Update plugin version in the main plugin file if needed.
3. Run local checks.
4. Commit changes.
5. Create a tag:

```bash
git tag v1.1.0
git push origin v1.1.0
```

6. GitHub Actions builds the release ZIP.
