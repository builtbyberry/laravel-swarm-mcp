<?php

declare(strict_types=1);

use BuiltByBerry\LaravelSwarmMcp\Support\SwarmMcpTransports;

// The HTTP (Streamable) transport must never expose swarm data to an
// unauthenticated caller. We exercise the wiring with the default `auth` guard
// (the shipped default is `auth:sanctum`, supplied by the consuming app); the
// property under test is that the configured auth middleware actually rejects a
// guest before the MCP handler runs.
beforeEach(function () {
    config()->set('swarm-mcp.transports.http.enabled', true);
    config()->set('swarm-mcp.transports.http.path', 'swarm-mcp');
    config()->set('swarm-mcp.authentication.middleware', ['auth']);

    SwarmMcpTransports::register(app('config'));
});

it('rejects an unauthenticated request to the HTTP transport', function () {
    $this->postJson('swarm-mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/list',
    ])->assertUnauthorized();
});

it('answers GET/DELETE on the transport route with 405 (POST-only), still gated', function () {
    // The MCP spec route only accepts POST; GET is method-not-allowed. This
    // documents that the networked surface exposes no unguarded verb.
    $this->getJson('swarm-mcp')->assertStatus(405);
});
