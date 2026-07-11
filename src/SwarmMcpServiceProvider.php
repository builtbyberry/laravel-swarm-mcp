<?php

declare(strict_types=1);

namespace BuiltByBerry\LaravelSwarmMcp;

use BuiltByBerry\LaravelSwarmMcp\Servers\SwarmObservabilityServer;
use Laravel\Mcp\Facades\Mcp;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered package provider (a Spatie {@see PackageServiceProvider}, the
 * Laravel package convention shared with the other laravel-swarm companions).
 * It wires package-level assets — the publishable `config/swarm-mcp.php` and the
 * install command — and is the seam where the MCP server + its Resources are
 * registered (added in later components; this scaffold only stands up the
 * package skeleton).
 *
 * Everything this package exposes over MCP reads persisted swarm data
 * exclusively through the public read-only contracts shipped in laravel-swarm
 * v0.19 — `InspectsDurableRuns`, `ReadableRunHistoryStore`, and
 * `ReadableAuditOutbox` — which display-decrypt per row (honoring
 * `swarm.persistence.decrypt_failure_policy`) and never mutate state. It never
 * touches the `@internal` `SwarmPersistenceCipher` or the strict-operational
 * reads.
 */
class SwarmMcpServiceProvider extends PackageServiceProvider
{
    public static string $name = 'laravel-swarm-mcp';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('builtbyberry/laravel-swarm-mcp');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }
    }

    public function packageBooted(): void
    {
        $config = $this->app->make('config');

        // Register the observability server over the default (stdio) transport,
        // reachable via `php artisan mcp:start laravel-swarm`. The networked HTTP
        // transport + its authentication is wired separately (transport-auth).
        if ($config->get('swarm-mcp.server.enabled', true)
            && $config->get('swarm-mcp.transports.stdio.enabled', true)) {
            Mcp::local('laravel-swarm', SwarmObservabilityServer::class);
        }
    }
}
