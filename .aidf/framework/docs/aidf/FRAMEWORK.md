# AIDF As-Installed Package

Status: VALIDATED (installable unit)
Date: 2026-10-31

This file documents what a *consumer* receives when AIDF is installed into
their own project, as shipped by version 0.1.0.

## 1. What ships on `aidf install`

| Destination | Content |
|---|---|
| `.aidf/framework/` | complete self-contained copy of the distribution's `src/` and `assets/` (plus README, LICENSE, package.json) |
| `.aidf/bin/aidf.mjs` | launcher wired to the copied framework |
| `.aidf/AIDF.md` | agent entry point (session protocol, conflict hierarchy, status vocabulary) |
| `.aidf/docs/*.md` | 11 honest templates: constitution, PRD, TRD, APPFLOW, UI-UX, BACKEND, PLAN, SECURITY, CONVENTIONS, DECISIONS, CHANGELOG |
| `.aidf/registry/*.yaml` | skills catalog (13), dependencies, activation map, sources |
| `.aidf/workflows/*.yaml` | phases (8) + executable gates (16) |
| `.qoder/*` (qoder adapter) | `aidf-*` rules, 13 skills, orchestrator agent, merged hook entries |
| `.aidf/evidence/`, `state/`, `reports/`, `backups/`, `logs/` | runtime state (logs and backups stay local) |

## 2. Verified behaviours as an installable unit (measured on real runs)

- **Idempotency**: re-running update twice → second run `created=0 updated=0
  unchanged=133`.
- **Conflict safety**: local edit of a managed rule → `CONFLICT`, file
  preserved, exit code 2.
- **Dry-run purity**: `install --dry-run` leaves no trace, including logs.
- **Uninstall**: restores pre-existing `AGENTS.md`, keeps unrelated rule
  files, removes only AIDF-owned files; `--keep-data` keeps docs+evidence.
- **Secrets**: a `.env` present in the E2E project had its filename listed and
  its content never read or echoed anywhere in reports.
- **Manifest honesty**: every created file is recorded in
  `.aidf/manifest.json` with its SHA-256; `doctor` verifies presence.

Reference the live validation record in `docs/aidf/VALIDATION.md`. Qoder
runtime activation remains a manual, human-evidenced step by design
(`docs/aidf/IDE-QODER.md`).
