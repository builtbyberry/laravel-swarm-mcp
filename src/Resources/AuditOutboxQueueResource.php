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
 * The pending or dead-lettered audit-outbox queue, addressed by state.
 *
 * Wraps {@see ReadableAuditOutbox::pending()} / {@see ReadableAuditOutbox::deadLettered()}
 * — pure SELECTs that return metadata plus the display-decrypted `last_error`
 * only. The full evidence payload is fetched on demand per row via
 * {@see AuditOutboxRecordResource}, minimizing decrypted-evidence exposure.
 */
#[Description("A Laravel Swarm audit-outbox queue by state — 'pending' or 'dead-lettered'. Metadata + display-decrypted last_error only; fetch a row's full payload via the record resource.")]
class AuditOutboxQueueResource extends Resource implements HasUriTemplate
{
    protected string $mimeType = 'application/json';

    public function __construct(
        protected readonly ReadableAuditOutbox $outbox,
    ) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('swarm://audit-outbox/queue/{state}');
    }

    public function handle(Request $request): Response
    {
        $state = (string) $request->get('state');

        return match ($state) {
            'pending' => Response::json(['records' => $this->outbox->pending(100)]),
            'dead-lettered' => Response::json(['records' => $this->outbox->deadLettered(100)]),
            default => Response::error("Unknown outbox queue [{$state}]. Use 'pending' or 'dead-lettered'."),
        };
    }
}
