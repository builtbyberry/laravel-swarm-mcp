<?php

declare(strict_types=1);

namespace McpCompatibility;

use Composer\Semver\Semver;

const CORE = 'builtbyberry/laravel-swarm';
const CANDIDATE_REF = 'e25842cab4291837dcce2ff6f4815e58feab9079';
const NATIVE1_CANDIDATE_REF = '48ad4ef690363ca40ba7d3bd50e63e7fbe76ba4b';
const NATIVE1_AI_MINIMUM_REF = '101c7ea33cd8569d82570f753fbf38e48b7d3d95';
const MCP_MINIMUM_REF = 'cfa4f38f82873eeb6848527883545f98f871e229';
const PUBLISHED_REF = 'be7df78e8fde12362cfff9007cfe723d572a5e4f';
const AI_MINIMUM_REF = 'ee2c5162838d440c4e2e629ea93c8c87e838eaed';
const HISTORICAL = ['core-0.20' => '0.20.*', 'core-0.21' => '0.21.*', 'core-0.22' => '0.22.*', 'core-0.23' => '0.23.*'];
const LANES = ['lowest', 'published-0.25', 'adoption-minimum', 'adoption-current', 'native1-minimum', 'native1-current', 'core-0.20', 'core-0.21', 'core-0.22', 'core-0.23'];

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new \RuntimeException($message);
    }
}

function readJson(string $path): array
{
    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function prepare(array $root, string $lane, ?array $candidate): array
{
    check(in_array($lane, LANES, true), 'Unknown compatibility lane.');
    check(($root['require'][CORE] ?? null) === '^0.19 || ^0.20 || ^0.21 || ^0.22 || ^0.23 || ^0.24 || ^0.25 || ^0.26 || ^0.27', 'Keep the complete supported core range.');
    check(($root['require']['laravel/mcp'] ?? null) === '^1.0', 'Expected native MCP ^1.0.');
    if (isset(HISTORICAL[$lane])) {
        $root['require'][CORE] = HISTORICAL[$lane];
    } elseif ($lane === 'published-0.25') {
        $root['require'][CORE] = '0.25.0';
    } elseif (str_starts_with($lane, 'adoption-') || str_starts_with($lane, 'native1-')) {
        $native1 = str_starts_with($lane, 'native1-');
        $ai = $native1 ? '^1.0' : '^0.11.2';
        $ref = $native1 ? NATIVE1_CANDIDATE_REF : CANDIDATE_REF;
        check(($candidate['name'] ?? null) === CORE, 'Expected the official core candidate manifest.');
        check(($candidate['require']['laravel/ai'] ?? null) === $ai, 'Unexpected candidate AI contract.');
        // This synthetic version is CI-only: the source and archive are immutable.
        $candidate['version'] = $native1 ? '0.27.0' : '0.26.0';
        $candidate['source'] = ['type' => 'git', 'url' => 'https://github.com/builtbyberry/laravel-swarm.git', 'reference' => $ref];
        $candidate['dist'] = ['type' => 'zip', 'url' => 'https://api.github.com/repos/builtbyberry/laravel-swarm/zipball/'.$ref, 'reference' => $ref];
        unset($candidate['require-dev'], $candidate['scripts'], $candidate['repositories']);
        $root['repositories'] = [['type' => 'package', 'package' => $candidate]];
        $root['require'][CORE] = $candidate['version'];
        $root['require-dev']['laravel/ai'] = str_ends_with($lane, '-minimum') ? ($native1 ? '1.0.0' : '0.11.2') : $ai;
        if ($lane === 'native1-minimum') {
            $root['require']['laravel/mcp'] = '1.0.0';
        }
    }

    return $root;
}

function packages(array $packages): array
{
    return array_column($packages, null, 'name');
}

function verify(array $locked, array $installed, string $lane): array
{
    check(in_array($lane, LANES, true), 'Unknown compatibility lane.');
    $adoption = str_starts_with($lane, 'adoption-');
    $native1 = str_starts_with($lane, 'native1-');
    $evidence = [];
    foreach ([CORE, 'laravel/ai', 'laravel/framework', 'laravel/mcp'] as $name) {
        $lock = $locked[$name] ?? [];
        $actual = $installed[$name] ?? [];
        foreach (['version', 'source', 'dist'] as $field) {
            check(isset($lock[$field]) && ($actual[$field] ?? null) === $lock[$field], "{$name}: installed {$field} differs from lock or is absent.");
        }
        $version = ltrim($actual['version'], 'v');
        check((bool) preg_match('/^\d+\.\d+\.\d+$/D', $version), "{$name}: expected a stable version.");
        $repository = $name;
        check(($actual['source']['type'] ?? null) === 'git', "{$name}: expected git source.");
        check(($actual['source']['url'] ?? null) === "https://github.com/{$repository}.git", "{$name}: expected official source.");
        $ref = $actual['source']['reference'] ?? '';
        check((bool) preg_match('/^[a-f0-9]{40}$/D', $ref), "{$name}: expected immutable source reference.");
        check(($actual['dist']['type'] ?? null) === 'zip'
            && ($actual['dist']['reference'] ?? null) === $ref
            && ($actual['dist']['url'] ?? null) === "https://api.github.com/repos/{$repository}/zipball/{$ref}", "{$name}: expected matching official archive.");
        if ($name === CORE) {
            if ($native1 || $adoption || $lane === 'published-0.25') {
                check($version === ($native1 ? '0.27.0' : ($adoption ? '0.26.0' : '0.25.0')), 'Wrong core version for lane.');
                check($ref === ($native1 ? NATIVE1_CANDIDATE_REF : ($adoption ? CANDIDATE_REF : PUBLISHED_REF)), 'Wrong core source for lane.');
            } elseif (isset(HISTORICAL[$lane])) {
                check(str_starts_with($version, substr($lane, 5).'.'), 'Wrong historical core minor.');
            } else {
                check((bool) preg_match('/^0\.(19|20|21|22|23|24|25)\./', $version), 'Lowest lane must retain a supported pre-0.26 core.');
            }
        } elseif ($name === 'laravel/ai' && $adoption) {
            check(version_compare($version, '0.11.2', '>=') && version_compare($version, '0.12.0', '<'), 'Expected official stable AI ^0.11.2.');
            if ($lane === 'adoption-minimum') {
                check($version === '0.11.2' && $ref === AI_MINIMUM_REF, 'Expected exact official AI minimum.');
            }
        } elseif ($name === 'laravel/ai' && $native1) {
            check(Semver::satisfies($version, '^1.0'), 'Expected official stable AI ^1.0.');
            if ($lane === 'native1-minimum') {
                check($version === '1.0.0' && $ref === NATIVE1_AI_MINIMUM_REF, 'Expected exact official AI 1 minimum.');
            }
        } elseif ($name === 'laravel/framework') {
            check(str_starts_with($version, '13.'), 'Expected Laravel 13.');
        } elseif ($name === 'laravel/mcp') {
            check(Semver::satisfies($version, '^1.0'), 'Expected Laravel MCP ^1.0.');
            if ($lane === 'native1-minimum') {
                check($version === '1.0.0' && $ref === MCP_MINIMUM_REF, 'Expected exact official MCP 1 minimum.');
            }
        }
        $evidence[] = "{$name} {$actual['version']} {$ref}";
    }

    $constraint = $locked[CORE]['require']['laravel/ai'] ?? null;
    check(is_string($constraint) && ($installed[CORE]['require']['laravel/ai'] ?? null) === $constraint, 'Installed core AI contract differs from lock or is absent.');
    check(Semver::satisfies($installed['laravel/ai']['version'], $constraint), 'AI does not satisfy the resolved core contract.');

    return $evidence;
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        $command = $argv[1] ?? '';
        $lane = $argv[2] ?? '';
        if ($command === 'prepare') {
            $root = prepare(readJson('composer.json'), $lane, isset($argv[3]) ? readJson($argv[3]) : null);
            file_put_contents('composer.json', json_encode($root, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        } else {
            check($command === 'verify', 'Usage: compatibility.php prepare|verify LANE [candidate-manifest]');
            require 'vendor/autoload.php';
            $lock = readJson('composer.lock');
            $installed = readJson('vendor/composer/installed.json');
            $evidence = verify(packages(array_merge($lock['packages'], $lock['packages-dev'])), packages($installed['packages']), $lane);
            echo (! str_starts_with($lane, 'adoption-') && ! str_starts_with($lane, 'native1-') ? 'Published dependency lane' : 'Pinned candidate lane; NOT published-installability proof')."\n";
            echo implode("\n", $evidence)."\n";
        }
    } catch (\Throwable $e) {
        fwrite(STDERR, $e->getMessage()."\n");
        exit(1);
    }
}
