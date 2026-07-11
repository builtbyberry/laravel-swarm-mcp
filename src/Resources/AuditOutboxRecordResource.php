<?php

declare(strict_types=1);

namespace BuiltByBerry\LaravelSwarmMcp\Resources;

use BuiltByBerry\LaravelSwarm\Contracts\ReadableAuditOutbox;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

/**
 * A single audit-outbox row with its full display-decrypted payload, addressed
 * by numeric id.
 *
 * Wraps {@see ReadableAuditOutbox::record()} — the on-demand full-payload read
 * (with a `payload_available` flag), kept separate from the list queues so the
 * decrypted evidence payload is only ever loaded when explicitly requested.
 */
#[Description('A single Laravel Swarm audit-outbox row with its full display-decrypted payload, addressed by numeric id.')]
class AuditOutboxRecordResource extends Resource implements HasUriTemplate
{
    protected string $mimeType = 'application/json';

    public function __construct(
        protected readonly ReadableAuditOutbox $outbox,
    ) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('swarm://audit-outbox/records/{id}');
    }

    public function handle(Request $request): Response
    {
        $id = (string) $request->get('id');

        if (! ctype_digit($id)) {
            return Response::error("Invalid outbox record id [{$id}].");
        }

        $record = $this->outbox->record((int) $id);

        return $record === null
            ? Response::error("Outbox record [{$id}] not found.")
            : Response::json($record);
    }
}
