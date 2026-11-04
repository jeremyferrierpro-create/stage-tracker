# AIDF Usage Guide — daily workflow

Status: VALIDATED

## 0. Session start (human or agent)

```powershell
node .aidf/bin/aidf.mjs doctor
node .aidf/bin/aidf.mjs workflow status
node .aidf/bin/aidf.mjs context --task "<request>"
```

The context file (`.aidf/state/context/latest.md`) is the contract between the
human and the agent: it lists what to read, which skills to load, and any
contradictions. If it reports `BLOCKED` (exit code 2), the affected decisions
are frozen until the document is fixed or a human resolves the contradiction.

## 1. The cycle

```
intake → discovery → specification → planning → implementation → testing → security → release
```

Move forward only through gates:

```powershell
node .aidf/bin/aidf.mjs workflow enter <phase>      # runs <phase>-entry gate
node .aidf/bin/aidf.mjs workflow exit <phase>       # runs <phase>-exit gate
node .aidf/bin/aidf.mjs workflow next               # enter the next phase
aidf validate --gate <gate-id>                       # evaluate any single gate
```

Gates and their checks live in `.aidf/workflows/gates.yaml`; the column report
shows every check with `PASS / BLOCKED / FAIL / NOT_PROVEN / NOT_APPLICABLE`
and the reason.

## 2. Evidence

Everything that supports a `PASS` is recorded under `.aidf/evidence/` as a
hash-chained JSON record:

```powershell
aidf evidence add --type plan-approval --human --by "Jeremy" --note "approved plan v2"
aidf evidence capture --type implementation-change --note "auth module"
aidf evidence verify
```

- `capture` lists the git diff/untracked files; it stores filenames only.
- Agent-declared evidence is stored but not treated as human approval.
- `doc-status` and gate checks can require `humanApproval`; without it the gate
  becomes `BLOCKED`.

## 3. Documents

```powershell
aidf docs list
aidf docs show SECURITY
aidf docs status PRD VALIDATED --by "Jeremy"
aidf docs status UI-UX NOT_APPLICABLE --note "No UI in API-only phase"
```

`VALIDATED` needs an accountable human (`--by`); `NOT_APPLICABLE` needs a
reason. Statuses are also written to the document frontmatter.

## 4. Skills

```powershell
aidf skills list
aidf skills show aidf-testing-validation
aidf skills validate       # cycles, missing deps, compatibility
aidf skills refresh        # rebuild .aidf/registry/skills.yaml
aidf skills link <id>      # link an external skill into .qoder/skills
```

13 first-party skills exist; never load them all — the context command selects
by phase and keywords.

## 5. Command execution policy

Command checks (e.g. the `test` gate check) only execute when the project
explicitly allows it:

```powershell
aidf config set security.allowCommandExecution true    # project-wide
aidf validate --phase testing --kind exit --allow-exec  # one-shot
```

In both cases the runner is shell-free, rejects shell operators, and records
the command, exit code, duration and captured output in evidence.

## 6. Spec Kit usage

```powershell
aidf speckit status
aidf speckit init --allow-exec [--specify-force]
aidf speckit sync [--adopt]
```

- Feature-level work still uses `/speckit-*` commands from Spec Kit.
- Feature specs must reference `.aidf/docs/PRD.md` requirement IDs instead of
  restating them.
- The mapping contract is written to `.specify/aidf/mapping.md`.

## 7. Troubleshooting quick table

| Symptom | Cause | Action |
|---|---|---|
| `doctor` exit 1 | a FAIL check | read the check row and its `detail` |
| install `conflicts=1` | managed file edited locally | merge manually or `--force` (backup kept) |
| gate `BLOCKED` | missing evidence with human approval | `aidf evidence add --human --by …` |
| context `BLOCKED` | contradiction-sensitive doc `BLOCKED`/`OUTDATED` | fix the document or record a decision |
| `speckit init` BLOCKED | project dir not empty | add `--specify-force` (explicit opt-in) |
| `speckit sync` exit 2 | Spec Kit constitution is not AIDF-generated | merge manually; AIDF never overwrites |
