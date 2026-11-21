---
doc: constitution
status: DRAFT
version: 0.2.0
source: aidf-template
---

# Project Constitution

> Ratified by: `[TO FILL: name and role]`
> Ratification date: `[TO FILL]`
> Amendment procedure: `[TO FILL: who can amend, review required]`

This constitution defines the non-negotiable principles of the project. It is
installed by AIDF as a starting point and must be reviewed and ratified by a
human. Until ratification it remains `DRAFT`; it is still binding as a default
safety baseline.

## Article I — Evidence over assertion

A claim about behaviour, quality, security or completeness must be backed by a
verifiable artefact: a command and its output, a test result, a file diff, a
review record, or a human decision. Declarations by an agent are not evidence.

- A simulated test is not a real test.
- A mock is not an integration.
- Documentation is not implementation.
- A file's presence is not proof that a mechanism is activated.

## Article II — Inspect before modifying

No change is made before reading the real code, configuration, tests and git
state relevant to the change. Assumptions are labelled as assumptions.

## Article III — Security and privacy first

- Secrets are never read, printed, logged or committed.
- Least privilege applies to tools, tokens and automation.
- Inputs are validated; paths are constrained; downloaded content is untrusted.
- No external script is executed automatically without an explicit policy and
  human authorization.
- Sensitive operations require human validation as defined by the project.

## Article IV — The project owns its architecture

AIDF adapts to the host project. It never imposes a new application
architecture, replaces the project's cockpit, or creates a competing interface.
For projects with an existing product surface (for example KelyOS/ARIA), the
existing architecture and interface are authoritative.

## Article V — Explicit state, explicit gates

Work moves through phases with declared entry and exit criteria. A phase is not
`PASS` unless its mandatory criteria are demonstrated. Blocked work is reported
as blocked, never hidden behind optimistic language.

## Article VI — Traceable decisions

Architectural, security and product decisions are recorded in
`.aidf/docs/DECISIONS.md` with context, options, decision, consequences and
status. Superseded decisions are marked, not deleted.

## Article VII — Human accountability

Humans remain accountable for product decisions, risk acceptance, sensitive
operations and release approval. AIDF reduces error and makes controls
reproducible; it does not replace a senior engineer or guarantee the absence of
vulnerabilities.

## Article VIII — Proportionality

Documentation, skills and controls are loaded in proportion to the task. AIDF
selects context instead of flooding the agent with every document and skill.
Unused process is removed or marked `NOT_APPLICABLE`, not performed for show.

## Article IX — Licences and provenance

External resources are recorded with origin, licence, version or commit, and
retrieval date. Redistribution respects the licence. Unknown provenance is
`BLOCKED` for installation.

## Article X — Amendment

Amendments require a written proposal, a recorded decision, and human
ratification. Amendments that weaken Articles I, III or IV require an explicit
security and architecture review.
