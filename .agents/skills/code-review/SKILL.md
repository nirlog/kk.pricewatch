---
name: code-review
description: >
  Review a kk.pricewatch change or pull request for correctness, regressions,
  architecture, Bitrix compatibility, security and task compliance.
---

# Code Review

1. Read `AGENTS.md`.
2. Read the task being implemented.
3. Read relevant architecture notes.
4. Inspect the full diff and enough surrounding code to understand behavior.

Review priority:
1. correctness;
2. data loss / install / update regressions;
3. security and permissions;
4. collector contract compatibility;
5. error handling / partial batches;
6. D7 / PHP 8.2 compatibility;
7. architecture boundary violations;
8. tests;
9. maintainability.

If collector code changed, verify:
- schema version;
- request ID round-trip;
- item ID round-trip;
- decimal-string money;
- successful items survive partial failures;
- unknown fields do not unnecessarily break compatibility;
- no competitor-specific core logic;
- Mock and future HTTP collectors share one interface.

Output findings by severity: Critical, High, Medium, Low.

For each finding include file/line when available, failure scenario, impact, and minimal fix.

Finish with test gaps, architecture observations, and verdict:
`READY`, `READY WITH MINOR CHANGES`, or `NOT READY`.

Do not invent findings merely to fill severity levels.
