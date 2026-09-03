# Changelog

## v0.1.1 - 2026-09-03

Compatibility release. Supports PHP `^8.4` and Laravel Swarm `^0.19` through
`^0.25`, with CI covering PHP 8.4 and 8.5 against latest and lowest dependency
sets. The read-only MCP resource behavior is unchanged.

## v0.1.0 - 2026-07-11

Initial release. A read-only [Model Context Protocol](https://modelcontextprotocol.io)
server for Laravel Swarm, built on the official `laravel/mcp` package. It exposes
a swarm's run history, durable-run state, and audit-outbox health as MCP
**Resources**, so any MCP-compatible AI client can observe swarm runs. Resources-only
by design — the server registers zero Tools; operator control is a deliberately
deferred later minor.

### Added

- **`SwarmObservabilityServer`** — a `laravel/mcp` server exposing six read-only
  Resources over Swarm's `v0.19` public display contracts, registered over the
  default stdio transport (`php artisan mcp:start laravel-swarm`). It registers
  **zero Tools and zero Prompts** — a fail-safe, observe-only surface.
- **Run-history Resources** — `swarm://runs` (a decryption-free list projection)
  and `swarm://runs/{runId}` (a single run with its steps) over
  `ReadableRunHistoryStore`.
- **Durable-run Resource** — `swarm://durable-runs/{runId}` over
  `InspectsDurableRuns`, returning assembled durable state: waits, signals,
  progress, child runs, branches, and hierarchical outputs.
- **Audit-outbox Resources** — `swarm://audit-outbox/health`,
  `swarm://audit-outbox/queue/{state}` (`pending` | `dead-lettered`), and
  `swarm://audit-outbox/records/{id}` over `ReadableAuditOutbox` — pure SELECTs
  that never drain the outbox.
- **Display-safe reads** — every Resource resolves the bound public contract
  (never the `@internal` `SwarmPersistenceCipher`); sealed fields degrade per
  field with an `*_available: false` flag rather than leaking `sw0:` ciphertext
  or failing on a rotated `APP_KEY`.
- **Transports** — stdio (default, host trust) and an opt-in HTTP (Streamable)
  transport guarded by configurable authentication middleware (`auth:sanctum`
  by default).
- **Configuration** — `config/swarm-mcp.php` (server, transports, authentication,
  per-resource toggles), publishable with `php artisan vendor:publish --tag=swarm-mcp-config`.
- **Tooling** — a Pest suite of real-contract tests (seeded rows across all three
  seams, the poison/rotated-key degrade path, and HTTP unauthenticated
  rejection), PHPStan level 8, Pint, and GitHub Actions CI (prefer-stable +
  prefer-lowest) with branch-naming enforcement.

Requires PHP `^8.5`, `builtbyberry/laravel-swarm` `^0.19`, and `laravel/mcp` `^0.8`.
