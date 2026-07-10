# Laravel Swarm MCP

A read-only [Model Context Protocol](https://modelcontextprotocol.io) server for
[Laravel Swarm](https://github.com/builtbyberry/laravel-swarm), built on the
official [`laravel/mcp`](https://github.com/laravel/mcp) package. It exposes a
swarm's durable run history, durable-run inspection, and audit-outbox health as
MCP **Resources**, so any MCP-compatible AI client can observe your swarm runs.

> **Status:** `v0.1.0` in development. This release is **read-only** — it exposes
> observability Resources only. Operator control (pause/resume/cancel/signal) is
> a deliberately deferred later minor.

It wraps Laravel Swarm's public display-read contracts (introduced in core
`v0.19.0`) — `ReadableRunHistoryStore`, `InspectsDurableRuns`, and
`ReadableAuditOutbox` — so every read is display-decrypted and degrade-safe by
construction.

## License

The MIT License (MIT). Please see the [License File](LICENSE) for more
information.
