# Public Core Policy

This repository is public core only. It must remain safe to publish without exposing paid logic, private infrastructure internals, or secret implementation details.

## Policy Rules

1. Keep all premium/proprietary behavior out of this repository.
2. Keep Pro modules as placeholders only in `src/Core/Modules/Pro/`.
3. Keep entitlement-sensitive decisions delegated to secure backend/private add-ons.
4. Never commit live credentials, private keys, or backend secrets.
5. Keep custom-module examples generic and owner-operated (no hidden paid gates).

## Allowed Examples

- Public interfaces and extension hooks for future private integrations.
- Placeholder classes that clearly state implementation lives in private repositories.
- Admin UI scaffolding that does not unlock paid behavior in public core.
- Documentation that explains boundary responsibilities between public core and secure backend.

## Disallowed Examples

- Implementing paid feature execution paths directly in `src/` or `includes/`.
- Shipping hard-coded premium checks that unlock functionality in this repository.
- Marketing-style upsell strings in runtime code paths (for example direct premium-purchase prompts).
- Committing provider secrets, API keys, or private entitlement rules.

## Enforcement

- CI runs `./infrastructure/scripts/repo-hygiene-scan.sh` to block sensitive content and disallowed premium wording.
- Pro placeholder classes are required to keep the marker:
  - `TODO: Implement in private/pro repository only.`

## Related Docs

- [Module Schema](module-schema.md)
- [Feature Ownership Map](feature-ownership-map.md)
- [Architecture](../development/architecture.md)
