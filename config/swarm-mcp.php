<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    |
    | The observability MCP server exposed by this package. It registers a set
    | of read-only Resources over Laravel Swarm's public display-read contracts.
    | This release is Resources-only — no control Tools are registered.
    |
    */

    'server' => [
        'enabled' => true,
        'name' => 'Laravel Swarm',
    ],

    /*
    |--------------------------------------------------------------------------
    | Transports
    |--------------------------------------------------------------------------
    |
    | Which laravel/mcp transports this server is reachable over. `stdio` is for
    | local / agent-host use and inherits host trust. The HTTP transport is
    | networked and MUST be authenticated (see `authentication` below).
    |
    */

    'transports' => [
        'stdio' => [
            'enabled' => true,
        ],
        'http' => [
            'enabled' => false,
            'path' => 'swarm-mcp',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Authentication for the networked (HTTP) transport. Reads are exposed over
    | an authenticated principal; the guard/middleware are applied to the HTTP
    | transport route. The `stdio` transport is not affected.
    |
    | NOTE ON AUTHORIZATION: this package is authorization-agnostic about WHICH
    | runs a caller may read — that policy belongs to your application. This
    | release exposes read-only Resources only; run-visibility scoping is the
    | consuming app's concern.
    |
    */

    'authentication' => [
        'middleware' => ['auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | The read-only Resources this server exposes, each wrapping a public
    | laravel-swarm v0.19 display-read contract. Toggle any off to narrow the
    | surface an MCP client can see.
    |
    */

    'resources' => [
        'run_history' => true,
        'durable_inspection' => true,
        'audit_outbox' => true,
    ],

];
