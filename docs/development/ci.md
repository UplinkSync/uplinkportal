# CI Notes

## Workflow Coverage

`ci.yml` runs:
- PHP syntax lint on plugin/test PHP files
- `composer phpcs`
- `composer test`
- repository hygiene scan (`infrastructure/scripts/repo-hygiene-scan.sh`)
- worker TypeScript typecheck (`infrastructure/worker`)

## Hygiene Scanner Rationale

The scanner fails builds when likely-sensitive content is committed, including:
- private key blocks
- common token formats (GitHub/Slack)
- hard-coded secret-like key/value literals
- forbidden payment-collection terminology in public core (see deny-list patterns in the scanner script)
- missing pro-placeholder marker in `src/Core/Modules/Pro/*.php`

Goal:
- reduce accidental secret exposure
- enforce public-core vs private-pro boundary
- prevent paid-logic leakage in public repo

## Other Workflows

- `db-migrate.yml`: manual SQL migration application for backend infra
- `deploy-worker.yml`: manual worker deploy + secrets sync
- `release-plugin.yml`: tagged plugin zip packaging/release
