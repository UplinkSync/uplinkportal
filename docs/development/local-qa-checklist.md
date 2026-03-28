# Local QA Checklist

Run this checklist before opening a pull request.

## Prerequisites

```bash
composer install
npm install --no-save cspell
```

## Required Checks

```bash
composer lint
composer test
./infrastructure/scripts/repo-hygiene-scan.sh
```

Expected outcome:
- `composer lint` exits `0` (PHPCS and cspell both pass).
- `composer test` exits `0`.
- repository hygiene scan exits `0`.

## Optional Sanity Checks

```bash
php -l uplinkportal.php
find includes src tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```
