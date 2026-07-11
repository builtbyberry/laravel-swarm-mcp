<?php

declare(strict_types=1);

use BuiltByBerry\LaravelSwarmMcp\Support\SwarmMcpTransports;
use Laravel\Mcp\Facades\Mcp;

it('keeps the stdio transport registered', function () {
    expect(Mcp::getLocalServer(SwarmMcpTransports::HANDLE))->not->toBeNull();
});

it('does not register the HTTP transport by default (fail-safe off)', function () {
    // config/swarm-mcp.php ships transports.http.enabled => false, so booting
    // the provider must not expose a networked route.
    expect(Mcp::getWebServer('swarm-mcp'))->toBeNull();
});

it('registers the HTTP transport behind the configured auth middleware when enabled', function () {
    config()->set('swarm-mcp.transports.http.enabled', true);
    config()->set('swarm-mcp.transports.http.path', 'swarm-mcp');
    config()->set('swarm-mcp.authentication.middleware', ['auth:sanctum']);

    SwarmMcpTransports::register(app('config'));

    $route = Mcp::getWebServer('swarm-mcp');

    expect($route)->not->toBeNull()
        ->and($route->middleware())->toContain('auth:sanctum');
});

it('does not register the HTTP transport when the server is disabled', function () {
    config()->set('swarm-mcp.server.enabled', false);
    config()->set('swarm-mcp.transports.http.enabled', true);

    SwarmMcpTransports::register(app('config'));

    expect(Mcp::getWebServer('swarm-mcp'))->toBeNull();
});
