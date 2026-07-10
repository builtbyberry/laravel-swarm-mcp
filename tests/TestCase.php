<?php

declare(strict_types=1);

namespace BuiltByBerry\LaravelSwarmMcp\Tests;

use BuiltByBerry\LaravelSwarm\SwarmServiceProvider;
use BuiltByBerry\LaravelSwarmMcp\SwarmMcpServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            SwarmServiceProvider::class,
            SwarmMcpServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Cache persistence keeps the scaffold smoke test DB-free:
        // ReadableRunHistoryStore resolves the cache store and
        // ReadableAuditOutbox the no-op.
        $app['config']->set('swarm.persistence.driver', 'cache');
    }
}
