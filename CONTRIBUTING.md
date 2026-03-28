# Contributing

## Scope Rules

This repository is public core only.
- Do not add paid/proprietary logic.
- Keep pro modules as placeholders in `src/Core/Modules/Pro/`.
- Do not commit secrets, keys, or internal credentials.
- Keep `.env` untracked and use `.env.example` placeholders for shared configuration docs.

## Local Checks

Install cspell once per environment:

```bash
npm install --no-save cspell
```

Run:

```bash
composer lint
composer test
./infrastructure/scripts/repo-hygiene-scan.sh
```

## Optional Manual Validation

- Use VS Code as the primary editor/debugger workflow.
- For interactive WordPress verification, use your local WordPress install or WordPress Playground.
- When backend services are unavailable in local/Playground, mock behavior through plugin filters (for example `uplinkportal_backend_validation_result`) instead of embedding private backend logic in this repo.

## Coding Standards

- Follow WordPress Coding Standards (WPCS).
- Use capability checks, nonce verification, sanitization, and escaping.
- Use `msp_*` capabilities for MSP operators and `client_*` capabilities for client-facing access.
- Keep admin IA aligned with `docs/guides/admin-information-architecture.md`.
- Keep module registry/menu behavior aligned with `docs/reference/module-schema.md`.
- Keep backend-sensitive logic delegated to private backend/add-ons; for local/Playground simulation prefer filter-based mocks.
- Never ship user-facing `TODO:` placeholder strings in rendered UI.

## AI Assistant Notes

- Align all AI assistant behavior with `AGENTS.md` (the canonical source of truth, symlinked as `CLAUDE.md`, `CODEX.md`, and `.github/copilot-instructions.md`).
- When updating `docs/development/project-todo.md`, mark `[x]` only after objective verification and include a `Verified:` evidence line.
- If backend work is out-of-repo (private add-on/service), leave tasks blocked with explicit evidence needed for completion.

## Documentation Requirements

When behavior changes, update:
- `README.md`
- `docs/index.md`
- `docs/development/architecture.md`
- affected domain docs (`docs/guides/admin-information-architecture.md`, `docs/reference/module-schema.md`, `docs/guides/security-gdpr-best-practices.md`, `docs/reference/settings.md`)

## CI Expectations

Pull requests should pass:
- PHP lint
- PHPCS
- PHPUnit
- repository hygiene scan
- worker TypeScript typecheck (`infrastructure/worker`, `npm run typecheck`)
