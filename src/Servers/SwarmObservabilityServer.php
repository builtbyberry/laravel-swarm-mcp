<?php

declare(strict_types=1);

namespace BuiltByBerry\LaravelSwarmMcp\Servers;

use BuiltByBerry\LaravelSwarmMcp\Resources\AuditOutboxHealthResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\AuditOutboxQueueResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\AuditOutboxRecordResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\DurableRunResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\RunResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\RunsResource;
use Laravel\Mcp\Server;

/**
 * The read-only Laravel Swarm observability MCP server.
 *
 * It exposes the v0.19 display-read seams as MCP Resources and registers
 * **zero Tools** — a fail-safe, Resources-only surface (design-gate records
 * #699/#700). Operator control verbs are a deliberately deferred later minor;
 * this server can observe swarm runs but never control them.
 */
class SwarmObservabilityServer extends Server
{
    protected string $name = 'Laravel Swarm';

    protected string $version = '0.1.0';

    protected string $instructions = <<<'MARKDOWN'
        Read-only observability for Laravel Swarm. Use these resources to inspect
        swarm run history, assembled durable-run state, and audit-outbox health.

        This server is read-only: it exposes no tools and cannot pause, resume,
        cancel, or signal runs. Sealed payloads are display-decrypted per field;
        an undecryptable value is returned as null with an `*_available: false`
        flag rather than raw ciphertext.
        MARKDOWN;

    protected array $resources = [
        RunsResource::class,
        RunResource::class,
        DurableRunResource::class,
        AuditOutboxHealthResource::class,
        AuditOutboxQueueResource::class,
        AuditOutboxRecordResource::class,
    ];

    protected array $tools = [];

    protected array $prompts = [];
}
