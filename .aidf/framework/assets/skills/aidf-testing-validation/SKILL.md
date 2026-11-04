---
name: aidf-testing-validation
description: Design and execute tests that prove the change, including failure paths, and record real command output as evidence. Use during implementation and before any phase exit.
metadata:
  aidf:
    id: testing-validation
    version: 1.0.0
    phases: [testing, implementation]
    documents: [CONVENTIONS, PLAN]
---

# AIDF Testing and Validation

## Procedure

1. Identify the real test command from `package.json`, `pyproject.toml`, `Makefile`
   or CI. Do not invent a command.
2. Run the focused test first, then the full suite.
3. Cover:
   - nominal path;
   - boundary values;
   - invalid input;
   - authorization failure;
   - dependency failure;
   - regression case.
4. Record the exact command, exit code, duration and output hash:

   ```bash
   aidf evidence capture --type test-run --note "focused + full suite"
   ```

5. List what is not covered and why. Mark it `NOT_PROVEN`, not `PASS`.
6. If a test fails, fix the cause. Do not skip, weaken or delete a test to get a
   green run unless the test itself is proven wrong and the decision is recorded.

## Evidence rules

- A mock is not an integration.
- A simulated run is not a real run.
- A test that was not executed is not evidence.
- A previous run does not prove the current diff.
- Screenshots supplement, but do not replace, executable tests.

## Output

- Test diff.
- Execution record with command and result.
- Coverage gaps and limitations.
