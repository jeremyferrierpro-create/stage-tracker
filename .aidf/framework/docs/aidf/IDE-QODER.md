# AIDF ↔ Qoder IDE Adapter

Status: VALIDATED (contract), runtime activation NOT_PROVEN (by design)

## 1. Why Qoder first

Qoder IDE 1.106.3 is installed on the author machine; official documentation
defines project rules, skills, hooks and custom agents that AIDF can generate
deterministically. The adapter is the first implementation of the adapter
interface; `generic` (AGENTS.md block only) is the baseline fallback.

## 2. Installed artifacts

| Path | Content |
|---|---|
| `AGENTS.md` | managed block pointing to `.aidf/AIDF.md` (user content preserved) |
| `.qoder/rules/aidf-00-core.md` | operating rule: inspect before modify, statuses, evidence |
| `.qoder/rules/aidf-10-security.md` | secret policy, protected files, opt-in execution |
| `.qoder/rules/aidf-20-context.md` | how to read `.aidf/state/context/latest.md` |
| `.qoder/skills/aidf-<name>/SKILL.md` | 13 first-party skills |
| `.qoder/agents/aidf-orchestrator.md` | orchestrator agent (Read/Grep/Glob/Bash + skill list) |
| `.qoder/settings.json` (with `--with-hooks`) | `SessionStart` and `PostToolUse` hook entries (merged) |
| `.aidf/hooks/session-context.mjs` | SessionStart hook: phase reminder, blocked docs; fails open |
| `.aidf/hooks/audit.mjs` | PostToolUse hook: redacted audit line (metadata only, never content) |

## 3. Verification levels

`aidf doctor` runs `QoderAdapter.verify()` and reports:

- `PASS`: node version, config, manifest, document files, evidence integrity.
- `CONTRACT-PROVEN`: adapter files exist and satisfy constraints (agents block,
  3 rules, 13 skills parse, settings JSON valid).
- `NOT_PROVEN` until a human confirms in a real session:
    - `runtime-skill-discovery` → `/skills` lists `aidf-project-context`;
    - `runtime-rule-activation` → rules set to Always Apply in the Qoder UI;
    - `runtime-hook-activation` → an edit writes `.aidf/logs/audit-<date>.jsonl`.

This split is deliberate: a file existing is evidence of installation, not
evidence of runtime activation.

## 4. Manual activation checklist (one time per project)

1. Open the project in Qoder; wait for indexing.
2. Run `/skills`; verify the 13 `aidf-*` skills and any `speckit-*` skills.
3. Settings → Rules: set `aidf-00-core` and `aidf-10-security` to **Always
   Apply**; leave `aidf-20-context` as Model Decision or Specific Files.
4. With hooks: restart Qoder; make one edit; check `.aidf/logs/`.
5. Run `aidf evidence add --type qoder-runtime-activation --human --by "<name>"
   --note "/skills listing confirmed"` so the manual check becomes evidence.

## 5. Known limitations

- Activation type is a UI setting; the rule file can only carry the reminder.
- Qoder docs disagree on project vs user skill precedence (IDE doc says
  project wins; CLI doc says user wins). AIDF installs into project scope only
  and never depends on the resolution order.
- Hooks execute with the process environment of Qoder; `audit.mjs` logs only
  relative paths and tool names, never file content (secret-safe by design).
- `.qoder/commands` are legacy (dropped in Qoder IDE 1.24+); AIDF does not
  generate them. Spec Kit's qodercli integration installs skills, which is the
  current mechanism.
