<?php

declare(strict_types=1);

use BuiltByBerry\LaravelSwarmMcp\Resources\AuditOutboxQueueResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\AuditOutboxRecordResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\DurableRunResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\RunResource;
use BuiltByBerry\LaravelSwarmMcp\Resources\RunsResource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Exercise every resource against REAL persisted rows (never a mocked cipher).
// Core's tables are migrated on demand; drive them from the database driver.
beforeEach(function () {
    config()->set('swarm.persistence.driver', 'database');
    Artisan::call('migrate', ['--database' => 'testing']);
});

/**
 * Seed a run in swarm_run_histories. The context input and output are written as
 * bare `sw0:` strings — undecryptable ciphertext that simulates a rotated
 * APP_KEY / poison row, which the display path must degrade rather than throw.
 */
function seedRun(string $runId, string $status = 'completed'): void
{
    $now = now('UTC');

    DB::table('swarm_run_histories')->insert([
        'run_id' => $runId,
        'swarm_class' => 'App\\Swarms\\Example',
        'topology' => 'sequential',
        'status' => $status,
        'context' => json_encode(['input' => 'sw0:poison-context']),
        'metadata' => json_encode([]),
        'steps' => json_encode([]),
        'output' => 'sw0:poison-output',
        'usage' => json_encode([]),
        'error' => null,
        'artifacts' => json_encode([]),
        'finished_at' => $now,
        'expires_at' => $now->copy()->addHour(),
        'execution_token' => 'operational-token',
        'leased_until' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function seedDurableRun(string $runId): void
{
    $now = now('UTC');

    DB::table('swarm_durable_runs')->insert([
        'run_id' => $runId,
        'swarm_class' => 'App\\Swarms\\Example',
        'topology' => 'sequential',
        'status' => 'running',
        'next_step_index' => 0,
        'total_steps' => 3,
        'timeout_at' => $now->copy()->addHour(),
        'step_timeout_seconds' => 300,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function seedOutboxRow(string $payload = 'sw0:poison-payload'): int
{
    $now = now('UTC');

    return (int) DB::table('swarm_audit_outbox')->insertGetId([
        'category' => 'swarm.run.completed',
        'run_id' => 'run-seed',
        'payload' => $payload,
        'attempts' => 0,
        'status' => 'pending',
        'reserved_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

it('lists a seeded run through the decryption-free projection', function () {
    seedRun('run-seed');

    $runs = decode(handleResource(RunsResource::class, 'swarm://runs'))['runs'];

    expect(collect($runs)->pluck('run_id'))->toContain('run-seed');
});

it('degrades a poison/rotated-key run row instead of throwing or leaking ciphertext', function () {
    seedRun('run-seed');

    $response = handleResource(RunResource::class, 'swarm://runs/run-seed', ['runId' => 'run-seed']);
    $body = decode($response);

    expect($response->isError())->toBeFalse()
        // the row is still returned...
        ->and($body['run_id'])->toBe('run-seed')
        ->and($body['status'])->toBe('completed')
        // ...but the undecryptable fields degrade, flagged, never thrown
        ->and($body['output'])->toBeNull()
        ->and($body['output_available'])->toBeFalse()
        ->and($body['context_available'])->toBeFalse()
        // and raw sw0: ciphertext never reaches the client
        ->and((string) $response->content())->not->toContain('sw0:');
});

it('returns assembled durable-run state for a seeded run', function () {
    seedRun('run-durable');
    seedDurableRun('run-durable');

    $response = handleResource(DurableRunResource::class, 'swarm://durable-runs/run-durable', ['runId' => 'run-durable']);

    expect($response->isError())->toBeFalse()
        ->and((string) $response->content())->toContain('run-durable')
        ->and((string) $response->content())->not->toContain('sw0:');
});

it('lists a seeded pending audit-outbox row', function () {
    seedOutboxRow();

    $records = decode(handleResource(AuditOutboxQueueResource::class, 'swarm://audit-outbox/queue/pending', ['state' => 'pending']))['records'];

    expect($records)->not->toBeEmpty();
});

it('degrades a poison audit-outbox payload on record fetch', function () {
    $id = seedOutboxRow('sw0:poison-payload');

    $response = handleResource(AuditOutboxRecordResource::class, "swarm://audit-outbox/records/{$id}", ['id' => (string) $id]);
    $body = decode($response);

    expect($response->isError())->toBeFalse()
        ->and($body['payload'])->toBeNull()
        ->and($body['payload_available'])->toBeFalse()
        ->and((string) $response->content())->not->toContain('sw0:');
});
