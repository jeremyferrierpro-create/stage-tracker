---
name: aidf-product-analysis
description: Turn a raw request into product goals, users, scope and measurable outcomes without inventing facts. Use for new features, ambiguous requests, or PRD updates.
metadata:
  aidf:
    id: product-analysis
    version: 1.0.0
    phases: [intake, specification]
    documents: [PRD, APPFLOW]
---

# AIDF Product Analysis

## Procedure

1. Restate the request in one sentence, using the user's vocabulary.
2. Identify the actor, the job to be done, and the observable outcome.
3. Separate:
   - **Stated** by the user;
   - **Observed** in the repository or analytics;
   - **Assumed** — label it and propose a verification.
4. Write or update the PRD sections: context, goals, non-goals, personas,
   functional requirements, non-functional requirements, metrics.
5. Give every requirement an ID (`FR-xxx`), a priority, an acceptance criterion
   and a verification method.
6. List open questions with an owner and a needed-by date. Do not fill them with
   guesses.
7. Identify security, privacy and data impacts; link to `SECURITY.md`.

## Quality bar

- A requirement without an acceptance criterion is not a requirement.
- A metric without a source of truth is a wish.
- A persona without evidence is an assumption.
- A non-goal is as important as a goal.

## Output

- Updated `PRD.md` (status stays `DRAFT` until a human validates it).
- A list of decisions needed from the product owner.
- A proposed scope for the current cycle.

## Anti-patterns

- Inventing users, market size or conversion numbers.
- Writing implementation details in the PRD.
- Expanding scope silently while "clarifying".
