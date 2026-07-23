# Project instructions

## Read the codex reference docs first

Before exploring this codebase by opening files, read the machine-readable reference docs in `docs/codex/`. They index the whole app (routes, DB schema, shared library functions, UI components, page tree, architecture) so most questions can be answered without grepping through the large PHP controllers (some are 3,000-6,000 lines).

| File | Covers |
|---|---|
| `docs/codex/routes.md` | Every page + POST/GET action: trigger, auth/role requirement, purpose |
| `docs/codex/schema.md` | Every DB table: fields, types, nullability, FKs, constraints |
| `docs/codex/lib.md` | All 144 functions in `includes/functions.php`: signature, purpose, line number |
| `docs/codex/components.md` | Reusable PHP partials (`includes/`) and JS modules (`js/`) |
| `docs/codex/pages.md` | Full page tree with CSS/JS dependencies per page |
| `docs/codex/architecture.md` | Tech stack, why things are built this way, conventions, known issues |

These are dense reference tables, not prose — grep them for a symbol/table/route name before reading the actual source. They may drift from the code over time (each file has a "Last generated" note at the bottom); if something looks stale or wrong, verify against the real file and prefer the source of truth, but still update the codex file so the next session isn't misled the same way.
