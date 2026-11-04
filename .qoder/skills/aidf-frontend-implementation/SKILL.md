---
name: aidf-frontend-implementation
description: Implement frontend changes that respect the existing design system, component patterns, state model and accessibility requirements. Use for UI features and fixes.
metadata:
  aidf:
    id: frontend-implementation
    version: 1.0.0
    phases: [implementation]
    documents: [UI-UX, APPFLOW, CONVENTIONS]
---

# AIDF Frontend Implementation

## Before coding

1. Find the existing design system, component library and styling approach.
   Reuse them; do not introduce a parallel one.
2. Locate the state model (local, store, server cache) and follow it.
3. Read `UI-UX.md` for the screen states and accessibility target.
4. Identify the test setup for components.

## Implementation rules

- Preserve the existing product surface and navigation.
- Implement loading, empty, error and success states.
- Use semantic HTML before ARIA. Label every form control.
- Keep focus visible; manage focus on route/modal changes.
- Do not hard-code user-facing strings that belong in the i18n layer.
- Do not fetch data in a component that already has a data owner.
- Avoid layout shift: reserve space for async content.

## Verification

- Component/unit tests for changed behaviour.
- Keyboard-only pass for interactive changes.
- Check contrast and reduced-motion behaviour when relevant.
- Record screenshots or test output as evidence.

## Anti-patterns

- Adding a dependency for a trivial utility.
- Copying a component instead of extending it.
- Styling by overriding global selectors.
- Declaring accessibility "done" without a check.
