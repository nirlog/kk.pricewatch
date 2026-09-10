---
name: module-architecture
description: >
  Design or revise kk.pricewatch architecture before implementation. Use for storage models,
  collector contracts, scheduler/queue design, admin architecture, integration boundaries,
  or other decisions that affect multiple future tasks.
---

# Module Architecture

1. Read repository `AGENTS.md`.
2. Read relevant files in `docs/architecture/`.
3. Inspect existing code before proposing structures that may already exist.
4. Treat explicit task requirements as authoritative.

## Process

1. Restate the capability in implementation-neutral terms.
2. Identify affected subsystems.
3. List invariants.
4. Identify data entities and ownership.
5. Define interfaces and boundaries before concrete classes.
6. Define failure modes, retries and idempotency implications.
7. Consider clean install and upgrade impact.
8. Consider permissions and security.
9. State what is out of scope.
10. Prefer reversible decisions while requirements are uncertain.

## Collector rules

When collectors are involved:

- do not assume HTML-only sites;
- preserve full product URLs including query parameters;
- keep the contract independent of Selenium, Python and specific competitors;
- support batch requests;
- distinguish global and per-item failures;
- represent money as decimal strings at integration boundaries;
- keep transport separate from orchestration;
- do not leak competitor-specific logic into module core.

## Output

For durable decisions, create/update an architecture note under `docs/architecture/` with:

- Context
- Decision
- Interfaces/data model
- Failure behavior
- Security implications
- Alternatives considered
- Out of scope
- Consequences

Do not implement production code unless the task explicitly asks for implementation too.
