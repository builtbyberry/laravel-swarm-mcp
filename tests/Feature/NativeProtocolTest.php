<?php

declare(strict_types=1);

use BuiltByBerry\LaravelSwarm\Contracts\RunHistoryStore;
use BuiltByBerry\LaravelSwarm\Support\RunContext;
use BuiltByBerry\LaravelSwarmMcp\Support\SwarmMcpTransports;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** @return array<string, mixed> */
function nativeMcpMessage(string $method, array $params = []): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 'native-request-1',
        'method' => $method,
        'params' => [
            ...$params,
            '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => (object) [],
            ],
        ],
    ];
}

/** @return array<string, string> */
function nativeMcpHeaders(string $method, ?string $uri = null): array
{
    return [
        'MCP-Protocol-Version' => '2026-07-28',
        'MCP-Method' => $method,
        ...$uri === null ? [] : ['MCP-Name' => $uri],
    ];
}

beforeEach(function () {
    config()->set('swarm.persistence.driver', 'database');
    config()->set('swarm-mcp.transports.http.enabled', true);
    config()->set('swarm-mcp.authentication.middleware', ['auth']);
    SwarmMcpTransports::register(app('config'));
    Artisan::call('migrate', ['--database' => 'testing']);
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('discovers the native protocol through the authenticated registered route', function () {
    $response = $this->postJson('swarm-mcp', nativeMcpMessage('server/discover'), nativeMcpHeaders('server/discover'))
        ->assertOk()
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', 'native-request-1')
        ->assertJsonPath('result.resultType', 'complete')
        ->assertJsonPath('result.supportedVersions', ['2026-07-28'])
        ->assertJsonPath('result.capabilities.resources.listChanged', false);

    expect($response->json('result._meta')['io.modelcontextprotocol/serverInfo']['name'])->toBe('Laravel Swarm');
});

it('lists exactly two fixed resources four templates and no tools or prompts on the wire', function () {
    $fixed = $this->postJson('swarm-mcp', nativeMcpMessage('resources/list'), nativeMcpHeaders('resources/list'))
        ->assertOk()->json('result.resources');
    expect(array_column($fixed, 'uri'))->toBe(['swarm://runs', 'swarm://audit-outbox/health'])
        ->and(array_column($fixed, 'mimeType'))->toBe(['application/json', 'application/json']);

    $templates = $this->postJson('swarm-mcp', nativeMcpMessage('resources/templates/list'), nativeMcpHeaders('resources/templates/list'))
        ->assertOk()->json('result.resourceTemplates');
    expect(array_column($templates, 'uriTemplate'))->toBe([
        'swarm://runs/{runId}',
        'swarm://durable-runs/{runId}',
        'swarm://audit-outbox/queue/{state}',
        'swarm://audit-outbox/records/{id}',
    ])->and(array_column($templates, 'mimeType'))->toBe(array_fill(0, 4, 'application/json'));

    foreach (['tools', 'prompts'] as $kind) {
        $method = $kind.'/list';
        $this->postJson('swarm-mcp', nativeMcpMessage($method), nativeMcpHeaders($method))
            ->assertOk()->assertJsonPath('result.'.$kind, []);
    }
});

it('reads all six resources through native serialization without changing persisted evidence', function () {
    $runId = 'native-wire-run';
    app(RunHistoryStore::class)->start($runId, 'App\\Swarms\\Example', 'sequential', new RunContext(runId: $runId, input: 'native input'), [], 3600);
    DB::table('swarm_run_histories')->where('run_id', $runId)->update([
        'output' => 'sw0:poison-output',
        'context' => json_encode(['input' => 'sw0:poison-context']),
    ]);
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
    $outboxId = DB::table('swarm_audit_outbox')->insertGetId([
        'category' => 'swarm.run.completed',
        'run_id' => $runId,
        'payload' => 'sw0:poison-payload',
        'attempts' => 0,
        'status' => 'pending',
        'reserved_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $tables = ['swarm_run_histories', 'swarm_durable_runs', 'swarm_audit_outbox'];
    $before = array_map(fn (string $table) => DB::table($table)->get()->toArray(), $tables);
    $bodies = [];
    foreach ([
        'runs' => 'swarm://runs',
        'run' => 'swarm://runs/'.$runId,
        'durable' => 'swarm://durable-runs/'.$runId,
        'health' => 'swarm://audit-outbox/health',
        'queue' => 'swarm://audit-outbox/queue/pending',
        'record' => 'swarm://audit-outbox/records/'.$outboxId,
    ] as $key => $uri) {
        $response = $this->postJson('swarm-mcp', nativeMcpMessage('resources/read', ['uri' => $uri]), nativeMcpHeaders('resources/read', $uri))
            ->assertOk()
            ->assertJsonPath('id', 'native-request-1')
            ->assertJsonPath('result.contents.0.uri', $uri)
            ->assertJsonPath('result.contents.0.mimeType', 'application/json')
            ->assertJsonCount(1, 'result.contents');
        expect($response->getContent())->not->toContain('sw0:');
        $bodies[$key] = json_decode($response->json('result.contents.0.text'), true, flags: JSON_THROW_ON_ERROR);
    }
    expect(array_column($bodies['runs']['runs'], 'run_id'))->toContain($runId)
        ->and($bodies['run']['run_id'])->toBe($runId)
        ->and($bodies['run']['output'])->toBeNull()
        ->and($bodies['run']['output_available'])->toBeFalse()
        ->and($bodies['run']['context_available'])->toBeFalse()
        ->and($bodies['durable']['run_id'])->toBe($runId)
        ->and($bodies['health']['available'])->toBeTrue()
        ->and(array_column($bodies['queue']['records'], 'id'))->toContain($outboxId)
        ->and($bodies['record']['id'])->toBe($outboxId)
        ->and($bodies['record']['payload'])->toBeNull()
        ->and($bodies['record']['payload_available'])->toBeFalse()
        ->and(array_map(fn (string $table) => DB::table($table)->get()->toArray(), $tables))->toEqual($before);
});

it('preserves native resource error envelopes and request IDs', function (string $uri, string $message, int $code, int $status) {
    $this->postJson('swarm-mcp', nativeMcpMessage('resources/read', ['uri' => $uri]), nativeMcpHeaders('resources/read', $uri))
        ->assertStatus($status)
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', 'native-request-1')
        ->assertJsonPath('error.code', $code)
        ->assertJsonPath('error.message', fn ($actual) => str_contains($actual, $message))
        ->assertJsonMissingPath('result');
})->with([
    'missing run' => ['swarm://runs/missing', 'Run [missing] not found.', -32603, 500],
    'missing durable run' => ['swarm://durable-runs/missing', 'Durable run [missing] not found.', -32603, 500],
    'invalid outbox ID' => ['swarm://audit-outbox/records/abc', 'Invalid outbox record id [abc].', -32603, 500],
    'missing outbox row' => ['swarm://audit-outbox/records/999999', 'Outbox record [999999] not found.', -32603, 500],
    'invalid queue state' => ['swarm://audit-outbox/queue/nope', 'Unknown outbox queue [nope].', -32603, 500],
    'unknown URI' => ['swarm://unknown', 'not found', -32602, 400],
]);

it('uses native method and parse errors', function () {
    $this->postJson('swarm-mcp', nativeMcpMessage('unknown/method'), nativeMcpHeaders('unknown/method'))
        ->assertNotFound()->assertJsonPath('id', 'native-request-1')->assertJsonPath('error.code', -32601);

    $this->call('POST', 'swarm-mcp', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{')
        ->assertBadRequest()->assertJsonPath('id', null)->assertJsonPath('error.code', -32700);
});

it('rejects incomplete native metadata without falling back to legacy mode', function () {
    $message = nativeMcpMessage('resources/list');
    unset($message['params']['_meta']['io.modelcontextprotocol/clientCapabilities']);
    $this->postJson('swarm-mcp', $message, nativeMcpHeaders('resources/list'))
        ->assertBadRequest()->assertJsonPath('id', 'native-request-1')->assertJsonPath('error.code', -32602)
        ->assertJsonPath('error.message', fn ($message) => str_contains($message, 'clientCapabilities'));
});

it('rejects a mismatched native HTTP method header', function () {
    $this->postJson('swarm-mcp', nativeMcpMessage('resources/list'), nativeMcpHeaders('tools/list'))
        ->assertBadRequest()->assertJsonPath('id', 'native-request-1')->assertJsonPath('error.code', -32020)
        ->assertJsonMissingPath('result');
});
