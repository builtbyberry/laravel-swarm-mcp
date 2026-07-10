<?php

declare(strict_types=1);

namespace BuiltByBerry\LaravelSwarmMcp\Resources;

use BuiltByBerry\LaravelSwarm\Contracts\ReadableAuditOutbox;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Resource;

/**
 * A one-shot health summary of the audit outbox: availability plus pending,
 * dead-letter, and reserved counts and the oldest pending timestamp.
 *
 * Wraps {@see ReadableAuditOutbox::healthSummary()} — a pure SELECT that never
 * writes `reserved_at` and never deletes, so it coexists with a concurrent
 * `swarm:relay --type=audit` drainer instead of stealing its rows.
 */
#[Description('Health summary of the Laravel Swarm audit outbox: availability, pending/dead-letter/reserved counts, and the oldest pending timestamp.')]
class AuditOutboxHealthResource extends Resource
{
    protected string $uri = 'swarm://audit-outbox/health';

    protected string $mimeType = 'application/json';

    public function __construct(
        protected readonly ReadableAuditOutbox $outbox,
    ) {}

    public function handle(Request $request): Response
    {
        return Response::json($this->outbox->healthSummary());
    }
}
