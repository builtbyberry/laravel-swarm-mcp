<?php

declare(strict_types=1);

namespace BuiltByBerry\LaravelSwarmMcp\Resources;

use BuiltByBerry\LaravelSwarm\Contracts\InspectsDurableRuns;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

/**
 * The assembled durable-run state for a run id: waits, signals, progress,
 * children, branches, and hierarchical node outputs.
 *
 * Wraps {@see InspectsDurableRuns::inspect()} — the read-only counterpart to the
 * `SwarmOperator` control contract. Existence is checked with the non-throwing
 * `find()` first so an unknown run returns a clean MCP error rather than a
 * surfaced exception. Every sealed field is display-decrypted per row.
 */
#[Description('The assembled durable-run state (waits, signals, progress, children, branches, hierarchical outputs) for a run id. Read-only; display-decrypted per row.')]
class DurableRunResource extends Resource implements HasUriTemplate
{
    protected string $mimeType = 'application/json';

    public function __construct(
        protected readonly InspectsDurableRuns $durableRuns,
    ) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('swarm://durable-runs/{runId}');
    }

    public function handle(Request $request): Response
    {
        $runId = (string) $request->get('runId');

        if ($this->durableRuns->find($runId) === null) {
            return Response::error("Durable run [{$runId}] not found.");
        }

        return Response::json($this->durableRuns->inspect($runId)->toArray());
    }
}
