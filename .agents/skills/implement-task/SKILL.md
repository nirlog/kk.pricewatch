---
name: implement-task
description: >
  Implement a scoped kk.pricewatch development task while preserving architecture,
  Bitrix compatibility and minimal regression risk.
---

# Implement Task

## Before coding

1. Read `AGENTS.md`.
2. Read the complete task.
3. Read relevant architecture notes/tests.
4. Inspect affected code paths.
5. Reuse existing interfaces/utilities where suitable.
6. Flag conflicts with durable architecture before changing it.

## Rules

- Keep the diff focused.
- Prefer small cohesive classes.
- Use D7 APIs/ORM where appropriate.
- Keep collector DTOs/value objects transport-independent.
- Do not hardcode competitors in module core.
- `MockCollector` must not be special from the caller's perspective.
- Validate at subsystem boundaries.
- Use structured errors/exceptions.
- Preserve full competitor URLs.
- Avoid float arithmetic for money.
- Avoid unnecessary external dependencies.
- Do not add future features outside task scope.

## Tests

When relevant, cover:
- happy path;
- invalid input;
- failure path;
- batch behavior;
- request/response ID round-trip.

For `MockCollector`, cover:
- exact URL match;
- contains URL match;
- explicit success;
- explicit item error;
- strict no-match;
- default-success no-match;
- mixed success/failure batch.

## Verification

1. Run available tests.
2. Run PHP syntax checks where practical.
3. Inspect `git diff`.
4. Verify autoload.
5. Check installer/update implications.
6. Check permissions/security.
7. Confirm no unrelated changes.

## Completion report

Return:
1. What changed
2. Key files
3. Tests/checks and results
4. Limitations/follow-up
5. Ready for review: yes/no

Never claim a check passed if it was not actually run.
