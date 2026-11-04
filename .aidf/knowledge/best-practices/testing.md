---
title: Testing and validation practices
source: AIDF original summary
references:
  - https://martinfowler.com/bliki/TestPyramid.html
license: MIT
status: AVAILABLE
retrieved: 2026-10-30
---

# Testing and Validation Practices

## What counts as proof

A test is proof only when:

1. the command was actually executed;
2. the exit code and output were recorded;
3. the test exercises the changed behaviour;
4. the environment is identified (local, CI, container).

A mock is not an integration. A skipped test is not a pass. A screenshot of a
previous run is not evidence for the current diff.

## Test levels

| Level | Purpose | Speed | Allowed dependencies |
|---|---|---|---|
| Unit | Logic and edge cases | fast | none / in-memory |
| Integration | Real boundaries (DB, HTTP) | slower | disposable real services |
| Contract | Producer/consumer agreement | medium | schema or recorded contract |
| E2E | User-visible journey | slow | full stack |

Use the lowest level that can prove the behaviour. Add a higher-level test only
for risks the lower level cannot cover.

## Design

- Cover nominal, boundary, invalid input and failure paths.
- One behaviour per test; the test name states the expected behaviour.
- Avoid testing implementation details that are free to change.
- Control time, randomness and network. Flaky tests are defects.
- A regression fix includes a test that fails before the fix.

## Evidence capture

```bash
aidf validate --phase testing --kind exit --allow-exec
aidf evidence capture --type test-run --note "unit + integration"
```

The recorded evidence includes the command, exit code, duration, output hashes
and the list of changed files. Do not edit evidence files by hand.
