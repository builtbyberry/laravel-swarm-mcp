# Native MCP 1 compatibility

The v0.2.0 companion uses official Laravel MCP `^1.0`, adds core `^0.27` and
retains core `^0.19` through `^0.26`. v0.3.0 additionally adds core `^0.28`
(native Laravel AI feature access), validated against the frozen core `v0.28.0`
candidate `269f749102f8d4c525c12e5486c3f57893d78d6b` (release/v0.28.0) with the
same native AI 1.x and MCP `^1.0` lines; no runtime, MCP-contract or schema change.
Production Composer metadata contains no candidate repositories, replacements or
dependency aliases. The dev-main alias names only this companion's own development line.

## Tested wire surface

`tests/Feature/NativeProtocolTest.php` drives the registered HTTP route through
native middleware, dispatch and serialization with a real authenticated test
principal. Its persisted-resource tests use core's public stores and disposable
SQLite rows. `HttpAuthTest.php` rejects a well-formed native resource request
before its display contract can be read. Existing tests retain default-off HTTP,
stdio registration, configured middleware, direct resource reads, encrypted
run/step reads and poison-row degradation.

| Native method | Companion result |
| --- | --- |
| `server/discover` | Native supported protocol, resource capability and server identity metadata |
| `resources/list` | `swarm://runs`, `swarm://audit-outbox/health` |
| `resources/templates/list` | `swarm://runs/{runId}`, `swarm://durable-runs/{runId}`, `swarm://audit-outbox/queue/{state}`, `swarm://audit-outbox/records/{id}` |
| `tools/list`, `prompts/list` | Empty lists |
| `resources/read` | JSON content with the requested URI and `application/json` MIME type |

Six resource declarations therefore appear as two fixed entries and four
templates. Inherited native capability fields do not imply registered tools or
prompts. Resource tests assert identifiers, real read data, null/availability
flags on undecryptable fields, absence of ciphertext and unchanged stored rows.

At the tested MCP 1.0.0 source, the `2026-07-28` request metadata keys are
`io.modelcontextprotocol/protocolVersion` and
`io.modelcontextprotocol/clientCapabilities`. HTTP mirrors protocol, method and
resource URI in `MCP-Protocol-Version`, `MCP-Method` and `MCP-Name`. Responses retain
JSON-RPC IDs and native result metadata. The missing-capabilities test retains
the protocol key: omitting both keys invokes native legacy mode, not validation
of a new-protocol request.

| Failure | Native JSON-RPC code | HTTP status |
| --- | --- | --- |
| Missing run/durable/outbox row, invalid outbox ID or queue state (`Response::error`) | -32603 | 500 |
| Unknown resource URI | -32602 | 400 |
| Missing required client-capability metadata | -32602 | 400 |
| Mismatched method header | -32020 | 400 |
| Unknown method | -32601 | 404 |
| Malformed JSON | -32700 | 400 |

These are the native error semantics, preserved rather than normalized by this
companion. Official source: [MCP Server](https://github.com/laravel/mcp/blob/cfa4f38f82873eeb6848527883545f98f871e229/src/Server.php),
[resource serialization](https://github.com/laravel/mcp/blob/cfa4f38f82873eeb6848527883545f98f871e229/src/Server/Methods/ReadResource.php),
[response errors](https://github.com/laravel/mcp/blob/cfa4f38f82873eeb6848527883545f98f871e229/src/Server/Methods/Concerns/InteractsWithResponses.php),
[HTTP headers](https://github.com/laravel/mcp/blob/cfa4f38f82873eeb6848527883545f98f871e229/src/Server/Middleware/ValidateMcpHeaders.php),
and [HTTP statuses](https://github.com/laravel/mcp/blob/cfa4f38f82873eeb6848527883545f98f871e229/src/Server/Transport/HttpTransport.php).

## Dependency proof

| Lane family | Core source | Native AI constraint |
| --- | --- | --- |
| Retained core 0.26 candidate | `e25842cab4291837dcce2ff6f4815e58feab9079` | `^0.11.2` |
| Core 0.27 candidate | `48ad4ef690363ca40ba7d3bd50e63e7fbe76ba4b` | `^1.0` |
| Core 0.28 candidate | `269f749102f8d4c525c12e5486c3f57893d78d6b` | `^1.0` |

Native minimum pins are AI 1.0.0
`101c7ea33cd8569d82570f753fbf38e48b7d3d95` and MCP 1.0.0
`cfa4f38f82873eeb6848527883545f98f871e229`. The verifier checks stable versions,
official immutable source/archive references, lock/installed agreement and the
resolved core's actual AI constraint. Negative controls reject wrong generations,
wrong minimum references, missing evidence, forks, replaced archives and drift.

CI retains twelve runtime lanes: PHP 8.4/8.5 × lowest, published-0.25,
adoption-minimum and adoption-current; plus PHP 8.5 core0.20–0.23. Four lanes cover
PHP 8.4/8.5 × native1-minimum/current (core 0.27), and four further lanes cover
PHP 8.4/8.5 × native1-028-minimum/current (core 0.28), for twenty lanes overall.
Every lane resolves MCP 1.x and runs the Feature suite. The existing branch-naming
gate remains. The published0.25, adoption-current, native1-current and
native1-028-current lanes also run analysis/lint.

To reproduce a candidate lane, use a disposable checkout, save its production
manifest, fetch the frozen core manifest the workflow pins for that lane (core
0.27 `48ad4ef` for native1-*, core 0.28 `269f749` for native1-028-*), then run:

```sh
php .github/scripts/compatibility.php prepare native1-minimum /path/to/core-candidate.json
composer update --prefer-lowest --prefer-stable --prefer-dist --no-interaction
php .github/scripts/compatibility-test.php /path/to/saved-production-composer.json
php .github/scripts/compatibility.php verify native1-minimum
composer test
composer analyse
composer lint
```

For native1-current, use that lane name and omit `--prefer-lowest`. Preserve the
lock and installed metadata as evidence. The preparation helper injects only a
temporary frozen core package; never commit the prepared manifest or use it as
production installation proof. The new native dependencies still resolve from
their official published packages.

Fault evidence targets removal of a resource registration, removal of configured
authentication, alteration of a resource URI and bypass of the MCP minimum-source
guard. The relevant inventory/auth/read/negative-control checks must fail and
pass again after exact restoration. Candidate local results and fault logs are
recorded in the component evidence; final hosted results belong to its reviewed
component head. The separate five-package integration and post-publication
Packagist-only proofs are not established by these companion lanes.

## Local component result

On PHP 8.5.8, four isolated Composer solves passed lock/installed verification
and the full Feature suite (39 tests, 195 assertions each):

| Lane | Resolved core / AI / MCP / Laravel | Additional gates |
| --- | --- | --- |
| native1-minimum | candidate 0.27.0 / 1.0.0 / 1.0.0 / 13.16.0 | analysis, lint |
| native1-current | candidate 0.27.0 / 1.0.0 / 1.0.0 / 13.33.0 | analysis, lint |
| lowest | published 0.19.0 / 0.8.0 / 1.0.0 / 13.14.0 | existing lowest-lane test gate |
| adoption-current | candidate 0.26.0 / 0.11.2 / 1.0.0 / 13.33.0 | analysis, lint |

The dependency guard suite passed twelve positive lane models and 1,111 negative
controls in each environment (v0.2.0 ran ten lanes and 923 controls; v0.3.0 adds
the two `native1-028` lanes and their discriminating controls, including one that
rejects a v0.27 core on the v0.28 lane). All fault probes failed their targeted
check and passed after byte-exact restoration. The production manifest was never
prepared in place; temporary overrides, locks and installed metadata remain in
isolated evidence fixtures. No production PHP adapter was necessary. The v0.2.0
runtime lanes passed at the reviewed component head in [Actions run 35952942926](https://github.com/builtbyberry/laravel-swarm-mcp/actions/runs/35952942926);
the v0.3.0 `native1-028` lanes are proven by this companion's CI on the v0.3.0
compatibility pull request.
