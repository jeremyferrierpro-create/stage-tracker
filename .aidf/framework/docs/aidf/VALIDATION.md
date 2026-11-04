# AIDF Validation Record

Date: 2026-10-31 (Europe/Paris)
Toolchain: Node v24.21.0, `node --test` built-in runner.
Scope: AIDF 0.1.0 framework code + installation into two real scratch projects.

## 1. Test suites (all executed)

| Suite | Coverage | Result |
|---|---|---|
| `tests/yaml.test.mjs` | subset parser/dumper: maps, sequences, inline arrays, quoted values, block scalars `|`/`>`/`|-`, comments, roundtrip, rejection of malformed input, real `gates.yaml` (16 gates) and `skills.yaml` (13 skills) | PASS (6 tests) |
| `tests/paths.test.mjs` | `resolveInside` traversal/absolute/reserved-name/invalid-character rejection; managed block replace/remove; `deepMerge`; `slugify`; `truncate` | PASS (8 tests) |
| `tests/installer.test.mjs` | action engine: create/update-idempotence, unmanaged file preserved (CONFLICT), edited managed file preserved (CONFLICT), `--force` with backup, dry-run writes nothing, mkdir idempotent, managed block preserves user text, merge-json keeps keys/arrays, summary | PASS (9 tests) |
| `tests/skills.test.mjs` | skill validation rules (name/name-length/description 1024/duplicates); full registry build → 13 AVAILABLE with hashes; dependency cycle detection; catalog consistency | PASS (6 tests) |
| `tests/adapters.test.mjs` | registry, Qoder plan artifact set (3 rules + 13 skills + agent + hooks + settings merge), verify = CONTRACT-PROVEN with runtime checks NOT_PROVEN, failure on missing rule, generic adapter | PASS (5 tests) |
| `tests/cli.test.mjs` | end-to-end via spawned `bin/aidf.mjs`: dry-run (no side effects), install, AGENTS.md preservation, doctor JSON, scan --record with `.env` name-only, skills validate, context, intake-entry gate, config set/get, speckit status, resources inspect, uninstall refusal/dry-run/real + restoration of AGENTS.md & existing rule | PASS (1 test) |
| **Total** | | **43/43 PASS** |

## 2. Real-project installation matrix (executed)

| Project setup | Actions verified | Result |
|---|---|---|
| demo project: existing `AGENTS.md`, `.qoder/rules/existing.md`, `package.json`, `.env` | install dry-run → 131 WOULD_* / 0 write; install → 132 CREATED; idempotent re-run → `created=0 updated=0 unchanged=133`; edited rule → CONFLICT preserved, exit 2; `--force` not needed (content kept) | PASS |
| demo: `aidf doctor` (installed launcher) | config/manifest/documents/adapter/skills/spec-kit/evidence-integrity/workflow/security checks; `CONTRACT-PROVEN 5/8`, 3 runtime NOT_PROVEN warnings shown | PASS |
| demo: `scan --record` → `.aidf/reports/SCAN.md` + evidence; `skills validate` PASS (0 external in temp dir); `workflow status`; `context` assembled BACKEND/SECURITY + 5 skills AVAILABLE; `validate --gate intake-entry` PASS with evidence hash | PASS |
| AIDF+Spec Kit project (`aidf-speckit`) | `speckit status` PRESENT, EXTERNAL constitution, 10 speckit-* skills detected after fix; `speckit sync` → CONFLICT (no overwrite), mapping.md created, exit 2 | PASS |
| fresh-with-AIDF project (`aidf-speckit-init`, `-force`) | `speckit init` without flag → BLOCKED (non-empty); with `--specify-force` → CREATED via `specify init … --force`; `--force` merge verified additive (AGENTS.md 1066 bytes intact, `.aidf/`, `.qoder/` intact) | PASS |
| constitution sync roundtrip (`aidf-speckit-force`) | original moved aside → `sync --adopt` CREATED; re-sync → UNCHANGED; source edited → UPDATED; status → IN_SYNC (hash marker verified) | PASS |

## 3. Chain-of-trust checks executed on real runs

- `aidf evidence verify` / doctor `evidence-integrity`: all recorded evidence
  re-hashed OK (index `previous` → record `hash` chain intact).
- `doctor --json` returns machine-readable checks; exit codes 0 (success),
  1 (refusal), and 2 (conflicts) observed as designed (uninstall conflicts,
  speckit sync conflict, context BLOCKED path logic).

## 4. Explicitly NOT proven in this run (manual/IDE checks)

| Item | Why not proven by files | Required evidence |
|---|---|---|
| Qoder rule activation (Always Apply) | UI setting, not encoded in files | human sets it; `aidf evidence add --human --by …` |
| Qoder `/skills` runtime discovery | requires a live IDE session | screenshot/listing from `/skills` |
| Hook execution in Qoder runtime | hooks fire inside the IDE, not from the CLI | an edit writes `.aidf/logs/audit-<date>.jsonl` then human confirms |
| `npm publish` / remote push compatibility | intentionally not exercised (no authorization) | N/A for 0.1.0 |

## 5. Exit-code contract verification

| Scenario | Observed |
|---|---|
| `aidf doctor` on healthy install | exit 0 |
| `aidf speckit init` without permission | exit 1 (BLOCKED) |
| `aidf uninstall` without `--yes` | exit 1 (refusal) |
| install with 1 conflict | exit 2 |
| `aidf speckit sync` over external constitution | exit 2 (CONFLICT preserved) |
