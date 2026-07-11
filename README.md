# Laravel Swarm MCP

[![tests](https://github.com/builtbyberry/laravel-swarm-mcp/actions/workflows/tests.yml/badge.svg)](https://github.com/builtbyberry/laravel-swarm-mcp/actions/workflows/tests.yml)

A read-only [Model Context Protocol](https://modelcontextprotocol.io) server for
[Laravel Swarm](https://github.com/builtbyberry/laravel-swarm), built on the
official [`laravel/mcp`](https://github.com/laravel/mcp) package. It exposes a
swarm's durable run history, durable-run inspection, and audit-outbox health as
MCP **Resources**, so any MCP-compatible AI client — Claude, Cursor, and others
— can observe your swarm runs.

> **Read-only by design.** This release exposes observability **Resources only**
> and registers **zero Tools**. It can *observe* swarm runs but can never pause,
> resume, cancel, or signal them. Operator control is a deliberately deferred
> later minor (see [Read-only by design](#read-only-by-design)).

It wraps Laravel Swarm's public display-read contracts (introduced in core
`v0.19.0`) — `ReadableRunHistoryStore`, `InspectsDurableRuns`, and
`ReadableAuditOutbox` — so every read is **display-decrypted and degrade-safe by
construction**: an undecryptable field (e.g. after an `APP_KEY` rotation) comes
back as `null` with an `*_available: false` flag rather than raw `sw0:`
ciphertext or a failed read.

## Requirements

- PHP `^8.5`
- `builtbyberry/laravel-swarm` `^0.19`
- `laravel/mcp` `^0.8`

## Installation

```bash
composer require builtbyberry/laravel-swarm-mcp
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="swarm-mcp-config"
```

## Configuration

`config/swarm-mcp.php`:

| Key | Default | Description |
|-----|---------|-------------|
| `server.enabled` | `true` | Master switch for the observability server. |
| `server.name` | `Laravel Swarm` | Display name reported to MCP clients. |
| `transports.stdio.enabled` | `true` | Expose the server over stdio (local / agent-host). |
| `transports.http.enabled` | `false` | Expose the server over the networked HTTP transport. **Off by default.** |
| `transports.http.path` | `swarm-mcp` | Route path for the HTTP transport. |
| `authentication.middleware` | `['auth:sanctum']` | Middleware applied to the HTTP transport route. |
| `resources.*` | `true` | Toggle individual resource groups (`run_history`, `durable_inspection`, `audit_outbox`). |

## Connecting an MCP client

### stdio (default)

The server is registered under the handle `laravel-swarm` and started with the
`laravel/mcp` command:

```bash
php artisan mcp:start laravel-swarm
```

Point your MCP client at that command. For example, a Claude Desktop entry:

```json
{
  "mcpServers": {
    "laravel-swarm": {
      "command": "php",
      "args": ["artisan", "mcp:start", "laravel-swarm"]
    }
  }
}
```

### HTTP (Streamable)

Set `transports.http.enabled` to `true`. The server is then reachable at the
configured path (default `POST /swarm-mcp`) behind the configured authentication
middleware. **A networked caller must authenticate** — the default guard is
`auth:sanctum` (your application provides Sanctum). See
[Authorization](#authorization).

## Resources

| URI | Backed by | Returns |
|-----|-----------|---------|
| `swarm://runs` | `ReadableRunHistoryStore::query()` | The most recent runs — a lean, decryption-free projection. |
| `swarm://runs/{runId}` | `ReadableRunHistoryStore::findForDisplay()` | A single run with its steps; sealed fields degrade per field. |
| `swarm://durable-runs/{runId}` | `InspectsDurableRuns::inspect()` | Assembled durable-run state: waits, signals, progress, children, branches, hierarchical outputs. |
| `swarm://audit-outbox/health` | `ReadableAuditOutbox::healthSummary()` | Outbox availability + pending / dead-letter / reserved counts. |
| `swarm://audit-outbox/queue/{state}` | `pending()` / `deadLettered()` | The `pending` or `dead-lettered` queue (metadata + `last_error` only). |
| `swarm://audit-outbox/records/{id}` | `ReadableAuditOutbox::record()` | A single outbox row with its full display-decrypted payload. |

### Display-safe reads

Every resource resolves the **bound public contract** — never the `@internal`
`SwarmPersistenceCipher`. Sealed payloads are opened through the display path,
which honors `swarm.persistence.decrypt_failure_policy` and **degrades per
field**: an undecryptable value becomes `null` with an explicit `*_available:
false` flag (`output_available`, `context_available`, `payload_available`, …).
A rotated `APP_KEY` or a single poison row therefore never throws, never aborts
the read, and never leaks `sw0:` ciphertext to the client. The audit-outbox
reads are pure `SELECT`s — they cannot drain or mutate the outbox that
`swarm:relay --type=audit` drains.

## Read-only by design

`v0.1.0` is intentionally **Resources-only**. The server registers no Tools, so
an AI client can read swarm state but cannot act on it. Exposing the operator
control verbs (pause / resume / cancel / signal) to an autonomous agent is a
subtle surface — it requires a per-run authorization callback and deliberately
excludes fleet-wide batch verbs — and is planned for a later minor behind an
explicit opt-in. Until then, this package is a safe, observe-only window into
your swarms.

## Authorization

The server authenticates a networked caller (via the configured
`authentication.middleware`), but it is **agnostic about *which* runs a caller
may read** — scoping run visibility to a tenant or user is your application's
responsibility. This release exposes read-only data only.

## Testing

```bash
composer test       # Pest
composer analyse    # PHPStan (level 8)
composer lint       # Pint
```

## License

The MIT License (MIT). Please see the [License File](LICENSE) for more
information.
