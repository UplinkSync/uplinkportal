# Changelog Workflow

Use this workflow for every pull request and release.

## Source File

- Keep changelog entries in `CHANGELOG.md` at repository root.
- If `CHANGELOG.md` does not exist yet, create it before the next release cut.

## Entry Rules

- Add entries under the next unreleased heading (for example `## Unreleased`).
- Group entries under consistent labels:
  - `Added`
  - `Changed`
  - `Fixed`
  - `Security`
  - `Removed`
- Each entry should include:
  - short impact-focused summary
  - affected area (for example admin UI, REST, docs, CI)
  - reference to PR/issue id when available

## PR Flow

1. During implementation, draft changelog bullets in your branch.
2. Before review, ensure new behavior and docs changes are represented.
3. During merge, resolve duplicate bullets and keep wording user-facing.

## Release Flow

1. Move `Unreleased` bullets into a dated version section (for example `## 0.2.0 - 2026-02-18`).
2. Keep newest version at the top.
3. Link any related migration/release notes.
4. Tag and publish only after changelog updates are committed.

## Quality Gate

- PRs that change behavior, public APIs, security controls, or CI must include a changelog update.
- Docs-only typo fixes can be omitted.
