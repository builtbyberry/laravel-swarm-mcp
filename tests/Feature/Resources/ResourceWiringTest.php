<?php

declare(strict_types=1);

use BuiltByBerry\LaravelSwarmMcp\Resources\AuditOutboxHealthResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\AuditOutboxQueueResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\AuditOutboxRecordResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\DurableRunResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\RunResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\RunsResource;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

// Core's durable-run + run-history tables are migrated on demand (persistence
// defaults to the cache driver), so materialize them for the resources whose
// backing contract reads the database — durable inspection is DB-backed
// regardless of the persistence driver.
beforeEach(fn () => Artisan::call('migrate', ['--database' => 'testing']));

/**
 * Resolve a resource from the container (proving its contract is injectable) and
 * invoke its handler with the given URI-template variables.
 *
 * @param  class-string  $resource
 * @param  array<string, string>  $vars
 */
function handleResource(string $resource, string $uri, array $vars = []): Response
{
    $instance = app()->make($resource);

    /** @var Request $request */
    $request = app()->make(Request::class);
    $request->setUri($uri);
    $request->merge($vars);

    return $instance->handle($request);
}

/**
 * @return array<string, mixed>
 */
function decode(Response $response): array
{
    return json_decode((string) $response->content(), true, flags: JSON_THROW_ON_ERROR);
}

it('exposes stable URIs for every resource', function () {
    expect(app()->make(RunsResource::class)->uri())->toBe('swarm://runs')
        ->and(app()->make(RunResource::class)->uri())->toBe('swarm://runs/{runId}')
        ->and(app()->make(DurableRunResource::class)->uri())->toBe('swarm://durable-runs/{runId}')
        ->and(app()->make(AuditOutboxHealthResource::class)->uri())->toBe('swarm://audit-outbox/health')
        ->and(app()->make(AuditOutboxQueueResource::class)->uri())->toBe('swarm://audit-outbox/queue/{state}')
        ->and(app()->make(AuditOutboxRecordResource::class)->uri())->toBe('swarm://audit-outbox/records/{id}');
});

it('lists runs as a decryption-free projection', function () {
    $response = handleResource(RunsResource::class, 'swarm://runs');

    expect($response->isError())->toBeFalse()
        ->and(decode($response))->toHaveKey('runs')
        ->and(decode($response)['runs'])->toBeArray();
});

it('returns a clean MCP error for an unknown run', function () {
    $response = handleResource(RunResource::class, 'swarm://runs/missing', ['runId' => 'missing']);

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toContain('not found');
});

it('returns a clean MCP error for an unknown durable run', function () {
    $response = handleResource(DurableRunResource::class, 'swarm://durable-runs/missing', ['runId' => 'missing']);

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toContain('not found');
});

it('reports audit-outbox health', function () {
    $response = handleResource(AuditOutboxHealthResource::class, 'swarm://audit-outbox/health');

    expect($response->isError())->toBeFalse()
        ->and(decode($response))->toHaveKey('available');
});

it('serves the pending audit-outbox queue and rejects an unknown state', function () {
    $pending = handleResource(AuditOutboxQueueResource::class, 'swarm://audit-outbox/queue/pending', ['state' => 'pending']);
    expect($pending->isError())->toBeFalse()
        ->and(decode($pending))->toHaveKey('records')
        ->and(decode($pending)['records'])->toBeArray();

    $unknown = handleResource(AuditOutboxQueueResource::class, 'swarm://audit-outbox/queue/nope', ['state' => 'nope']);
    expect($unknown->isError())->toBeTrue()
        ->and((string) $unknown->content())->toContain('Unknown outbox queue');
});

it('rejects a non-numeric audit-outbox record id and reports missing rows', function () {
    $invalid = handleResource(AuditOutboxRecordResource::class, 'swarm://audit-outbox/records/abc', ['id' => 'abc']);
    expect($invalid->isError())->toBeTrue()
        ->and((string) $invalid->content())->toContain('Invalid outbox record id');

    $missing = handleResource(AuditOutboxRecordResource::class, 'swarm://audit-outbox/records/999999', ['id' => '999999']);
    expect($missing->isError())->toBeTrue()
        ->and((string) $missing->content())->toContain('not found');
});
