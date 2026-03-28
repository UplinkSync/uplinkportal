# Local Development

## Requirements

- PHP `8.3+`
- WordPress `6.x`
- Composer

## Common Commands

```bash
composer phpcs
composer test
./infrastructure/scripts/repo-hygiene-scan.sh
```

## Environment Template (Optional)

For local backend/integration simulation, copy the template:

```bash
cp .env.example .env
```

`UplinkPortal` does not auto-load `.env` directly. Use your local bootstrap
(`wp-config.php`, must-use plugin, or deployment tooling) to map `.env` values
into WordPress constants/options/filters.

## Related

- [Contributing](contributing.md)
- [CI Notes](ci.md)
- [Architecture](architecture.md)
