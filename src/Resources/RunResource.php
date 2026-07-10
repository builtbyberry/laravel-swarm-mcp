<?php

declare(strict_types=1);

namespace BuiltByBerry\LaravelSwarmMcp\Resources;

use BuiltByBerry\LaravelSwarm\Contracts\ReadableRunHistoryStore;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

/**
 * A single swarm run with its steps, addressed by run id.
 *
 * Wraps {@see ReadableRunHistoryStore::findForDisplay()} — the display twin of
 * `find()`. Every sealed field (run context, run output, each step's
 * input/output) is opened per field and degrades to `null` with an explicit
 * `*_available: false` flag rather than throwing or leaking `sw0:` ciphertext,
 * so a rotated `APP_KEY` never breaks the read. It deliberately does NOT use the
 * non-degraded `SwarmHistory` facade.
 */
#[Description('A single Laravel Swarm run with its steps, addressed by run id. Sealed fields are display-decrypted per field with an *_available flag; never throws on a rotated key.')]
class RunResource extends Resource implements HasUriTemplate
{
    protected string $mimeType = 'application/json';

    public function __construct(
        protected readonly ReadableRunHistoryStore $runs,
    ) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('swarm://runs/{runId}');
    }

    public function handle(Request $request): Response
    {
        $runId = (string) $request->get('runId');

        $run = $this->runs->findForDisplay($runId);

        return $run === null
            ? Response::error("Run [{$runId}] not found.")
            : Response::json($run);
    }
}
