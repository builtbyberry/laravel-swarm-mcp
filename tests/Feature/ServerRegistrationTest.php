<?php

declare(strict_types=1);

use BuiltByBerry\LaravelSwarmMcp\Resources\AuditOutboxHealthResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\AuditOutboxQueueResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\AuditOutboxRecordResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\DurableRunResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\RunResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\RunsResource;
use BuiltByBerry\LaravelSwarmMcp\Servers\SwarmObservabilityServer;
use Laravel\Mcp\Facades\Mcp;

/**
 * @return array<string, mixed>
 */
function serverDefaults(): array
{
    return (new ReflectionClass(SwarmObservabilityServer::class))->getDefaultProperties();
}

it('registers the observability server over the default (stdio) transport', function () {
    expect(Mcp::getLocalServer('laravel-swarm'))->not->toBeNull();
});

it('lists exactly the six read-seam resources', function () {
    expect(serverDefaults()['resources'])->toBe([
        RunsResource::class,
        RunResource::class,
        DurableRunResource::class,
        AuditOutboxHealthResource::class,
        AuditOutboxQueueResource::class,
        AuditOutboxRecordResource::class,
    ]);
});

it('registers zero tools and zero prompts — a read-only surface', function () {
    expect(serverDefaults()['tools'])->toBe([])
        ->and(serverDefaults()['prompts'])->toBe([]);
});

it('identifies as the read-only Laravel Swarm server', function () {
    expect(serverDefaults()['name'])->toBe('Laravel Swarm')
        ->and(serverDefaults()['version'])->toBe('0.1.0');
});

it('serves its resources through the server transport', function () {
    SwarmObservabilityServer::resource(RunsResource::class)->assertOk();
    SwarmObservabilityServer::resource(AuditOutboxHealthResource::class)->assertOk();
});
