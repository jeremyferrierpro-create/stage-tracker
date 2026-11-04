---
title: TypeScript and JavaScript rules
source: AIDF original summary
references:
  - https://www.typescriptlang.org/docs/handbook/
license: MIT
status: AVAILABLE
retrieved: 2026-10-30
---

# TypeScript / JavaScript Rules

These rules are AIDF's own concise guidance. The official handbook remains the
authority for language semantics.

## Types

- `strict` must be enabled in `tsconfig.json` for new TypeScript code. If it is
  not, record why in `DECISIONS.md` before changing it.
- Do not use `any` to silence an error. Use `unknown` plus a narrowing guard, or
  a precise type. If `any` is unavoidable at a boundary, isolate it and explain.
- Prefer discriminated unions for state that has exclusive variants.
- Model invalid states out of existence where practical.
- Do not cast through `as unknown as T` to bypass a real mismatch.

## Modules and boundaries

- Respect the existing module system (`type: module` or CommonJS). Do not mix.
- A module owns its data and exposes a narrow interface. Do not reach into
  another module's internals.
- Avoid circular dependencies; if one appears, the boundary is probably wrong.

## Errors

- Throw `Error` subclasses with actionable messages, not strings.
- Do not swallow errors with empty `catch`.
- Distinguish expected domain failures from unexpected defects.
- Never include secrets or full request bodies in error messages.

## Async

- Do not leave floating promises; await or explicitly handle them.
- Use `AbortSignal` for cancellable work.
- Avoid unbounded concurrency; use a pool or a queue.

## Tests

- Test observable behaviour, not private implementation details.
- One reason to fail per test where possible.
- Deterministic: control time, randomness and network.
