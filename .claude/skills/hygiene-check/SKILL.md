---
name: hygiene-check
description: Run all UplinkPortal quality gates in one shot — PHPCS lint, cspell, UI placeholder scan, PHPUnit, repo-hygiene scan, and worker typecheck — then report a pass/fail summary.
disable-model-invocation: true
---

Run the following quality gates in order. After each step report ✅ PASS or ❌ FAIL with the relevant output. If a step fails, include the failure details so the developer can act on them immediately.

## Step 1 — PHP Lint (syntax check)

```bash
files=(uplinkportal.php $(find includes src tests -type f -name '*.php' | sort))
for file in "${files[@]}"; do php -l "$file"; done
```

## Step 2 — PHPCS + cspell + UI placeholder scan

```bash
composer lint
```

If PHPCS violations are found, suggest running `composer phpcbf` to auto-fix them, then re-run `composer lint`.

## Step 3 — PHPUnit

```bash
composer test
```

Report the test count and assertion count from the summary line.

## Step 4 — Repository hygiene scan (secrets + pro-boundary + payment terms)

```bash
./infrastructure/scripts/repo-hygiene-scan.sh
```

## Step 5 — Worker TypeScript typecheck

```bash
cd infrastructure/worker && npm run typecheck
```

## Final Summary

Print a table:

| Gate | Result |
|------|--------|
| PHP syntax lint | ✅ / ❌ |
| PHPCS + cspell + UI scan | ✅ / ❌ |
| PHPUnit | ✅ N tests, M assertions / ❌ |
| Repo hygiene scan | ✅ / ❌ |
| Worker typecheck | ✅ / ❌ |

If all gates pass: "All quality gates passed. Safe to open a PR."
If any gate fails: "Fix the failures above before opening a PR."
