<?php

declare(strict_types=1);

use BuiltByBerry\LaravelSwarm\Contracts\InspectsDurableRuns;
use BuiltByBerry\LaravelSwarm\Contracts\ReadableAuditOutbox;
use BuiltByBerry\LaravelSwarm\Contracts\ReadableRunHistoryStore;
use BuiltByBerry\LaravelSwarmMcp\SwarmMcpServiceProvider;

it('boots the package provider', function () {
    expect($this->app->getProvider(SwarmMcpServiceProvider::class))
        ->toBeInstanceOf(SwarmMcpServiceProvider::class);
});

it('publishes and loads the package config', function () {
    expect(config('swarm-mcp.server.enabled'))->toBeTrue()
        ->and(config('swarm-mcp.resources'))->toBe([
            'run_history' => true,
            'durable_inspection' => true,
            'audit_outbox' => true,
        ]);
});

it('resolves the laravel-swarm public display-read contracts the server will wrap', function () {
    expect($this->app->make(ReadableRunHistoryStore::class))->toBeInstanceOf(ReadableRunHistoryStore::class)
        ->and($this->app->make(InspectsDurableRuns::class))->toBeInstanceOf(InspectsDurableRuns::class)
        ->and($this->app->make(ReadableAuditOutbox::class))->toBeInstanceOf(ReadableAuditOutbox::class);
});
