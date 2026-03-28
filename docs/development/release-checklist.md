# Release Checklist

Use this checklist before creating a release tag.

## 1. Pre-Release

1. Confirm target scope is complete and merged.
2. Update `CHANGELOG.md` using `changelog-workflow.md`.
3. Verify docs updates for any user-visible behavior changes.
4. Confirm no blocked security items are unintentionally shipped.

## 2. Local Validation

1. Run `composer lint`.
2. Run `composer test`.
3. Run `./infrastructure/scripts/repo-hygiene-scan.sh`.
4. Confirm WordPress readme metadata in `docs/reference/wordpress-readme.txt` is current.

## 3. CI and Workflow Checks

1. Ensure `.github/workflows/ci.yml` succeeds for the release commit.
2. Confirm no pending migration steps are missed for:
   - `infrastructure/wordpress/migrations/`
   - `infrastructure/supabase/migrations/`
3. Verify release artifacts can be built via `.github/workflows/release-plugin.yml`.

## 4. Tag and Publish

1. Create semantic version tag (`vX.Y.Z`).
2. Push tag to trigger `release-plugin.yml`.
3. Verify GitHub release includes `dist/uplinkportal.zip`.
4. Sanity-check plugin zip contents:
   - `uplinkportal.php`
   - `assets/`
   - `includes/`
   - `src/`
   - `readme.txt` (generated from docs reference)

## 5. Post-Release

1. Record release date/version in project tracking.
2. Open follow-up tasks for deferred items.
3. Start next `Unreleased` changelog section.
