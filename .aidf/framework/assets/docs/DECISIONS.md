---
doc: DECISIONS
status: DRAFT
version: 0.2.0
source: aidf-template
---

# Decision Log

Architecture, security and product decisions are recorded here. A decision is
never deleted; it is superseded.

## Decision template

```markdown
## ADR-000 — Title

- Status: proposed | accepted | superseded | rejected | BLOCKED
- Date: YYYY-MM-DD
- Deciders: names/roles
- Related: PRD FR-xxx, TRD section, SECURITY T-xxx, Spec Kit feature

### Context
What is the real situation and constraint? What evidence supports it?

### Options considered
1. Option A — pros / cons
2. Option B — pros / cons

### Decision
What was decided, precisely.

### Consequences
Positive, negative, follow-up work, migration impact.

### Evidence
Commands, links, reviews, approvals.
```

## ADR-001 — Adopt AIDF as the development governance layer

- Status: accepted (installed)
- Date: `[TO FILL: installation date]`
- Deciders: `[TO FILL]`

### Context
AI-assisted changes need explicit context, reproducible controls and evidence.
The project already has its own architecture and interface.

### Decision
AIDF is installed as a governance layer under `.aidf/`. It does not impose an
application architecture and does not replace the project's cockpit.

### Consequences
- Documents and gates become part of the delivery process.
- The team must keep document statuses honest and record evidence.
- AIDF updates are explicit and conflict-aware.

### Evidence
`.aidf/manifest.json`, `.aidf/config.json`, installation report.
