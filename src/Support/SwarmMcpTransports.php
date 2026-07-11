<?php

declare(strict_types=1);

namespace BuiltByBerry\LaravelSwarmMcp\Support;

use BuiltByBerry\LaravelSwarmMcp\Servers\SwarmObservabilityServer;
use Illuminate\Contracts\Config\Repository;
use Laravel\Mcp\Facades\Mcp;

/**
 * Registers the observability server over the configured laravel/mcp transports.
 *
 * Fail-safe by construction: the `stdio` transport (local, host-trust) is on by
 * default; the networked HTTP transport is OFF by default and, when enabled,
 * always carries the configured authentication middleware. Authenticating a
 * caller is required for the HTTP transport; authorizing WHICH runs that caller
 * may read remains the consuming application's concern (this release is
 * read-only).
 */
class SwarmMcpTransports
{
    /**
     * The local (stdio) server handle — `php artisan mcp:start laravel-swarm`.
     */
    public const HANDLE = 'laravel-swarm';

    public static function register(Repository $config): void
    {
        if (! $config->get('swarm-mcp.server.enabled', true)) {
            return;
        }

        if ($config->get('swarm-mcp.transports.stdio.enabled', true)) {
            Mcp::local(self::HANDLE, SwarmObservabilityServer::class);
        }

        if ($config->get('swarm-mcp.transports.http.enabled', false)) {
            /** @var array<int, string> $middleware */
            $middleware = $config->get('swarm-mcp.authentication.middleware', ['auth:sanctum']);

            Mcp::web(
                $config->get('swarm-mcp.transports.http.path', 'swarm-mcp'),
                SwarmObservabilityServer::class,
            )->middleware($middleware);
        }
    }
}
