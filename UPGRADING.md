# Upgrading

## 0.1.x to 0.2.0

This minor requires official `laravel/mcp ^1.0`; native MCP 0.8 is no longer
supported. Laravel Swarm support remains `^0.19` through `^0.26` and adds `^0.27`.
PHP remains `^8.4` and Laravel remains 13. The companion has no direct Laravel AI
requirement: the selected core version determines it (core 0.27 requires AI 1.x).
Update Composer dependencies together and run your application's MCP client smoke
paths. Do not force an incompatible combination with aliases or replacements.

The companion retains six resource declarations, exposed as two fixed resources
and four resource templates, with unchanged URIs and JSON payloads. Tools and
prompts remain empty. No companion migrations or application data conversion are
introduced by this update. Core's own upgrade instructions apply separately when
changing core versions.

Native MCP 1.x owns discovery, protocol negotiation, HTTP headers and response
serialization. Its `2026-07-28` protocol uses `server/discover`, request protocol
and client-capability metadata, and matching `MCP-Protocol-Version`, `MCP-Method`
and (for resource reads) `MCP-Name` headers. Use a client compatible with that
native contract; the companion does not implement a compatibility protocol or
replace the native server. Older initialize handling remains upstream-owned.
See [the exact tested protocol and errors](docs/native-mcp-1-compatibility.md).

HTTP remains off by default. When enabled, the configured middleware still
controls authentication (`auth:sanctum` by default, supplied by your application).
Tenant/user visibility remains the application's responsibility. Read-only access
still exposes observability data and is not a substitute for application policy.

Core 0.27 candidate tests use temporary frozen source metadata. A successful
candidate solve is not proof that these versions can be installed from Packagist;
that check follows publication of core and companion.
