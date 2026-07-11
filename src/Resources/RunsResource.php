<?php

declare(strict_types=1);

namespace BuiltByBerry\LaravelSwarmMcp\Resources;

use BuiltByBerry\LaravelSwarm\Contracts\ReadableRunHistoryStore;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Resource;

/**
 * Lists the most recent swarm runs as a lean projection.
 *
 * Wraps {@see ReadableRunHistoryStore::query()} — the decryption-free run-list
 * read. It resolves the bound public contract (never the `@internal`
 * `SwarmPersistenceCipher`) and decrypts nothing, so it is safe under a rotated
 * `APP_KEY`.
 */
#[Description('The most recent Laravel Swarm runs (id, swarm class, status, timestamps). A lean, decryption-free list projection.')]
class RunsResource extends Resource
{
    protected string $uri = 'swarm://runs';

    protected string $mimeType = 'application/json';

    public function __construct(
        protected readonly ReadableRunHistoryStore $runs,
    ) {}

    public function handle(Request $request): Response
    {
        return Response::json([
            'runs' => $this->runs->query(limit: 25),
        ]);
    }
}
