<?php

declare(strict_types=1);

namespace McpCompatibility;

use Composer\Semver\Semver;

const CORE = 'builtbyberry/laravel-swarm';
const CANDIDATE_REF = 'e25842cab4291837dcce2ff6f4815e58feab9079';
const PUBLISHED_REF = 'be7df78e8fde12362cfff9007cfe723d572a5e4f';
const AI_MINIMUM_REF = 'ee2c5162838d440c4e2e629ea93c8c87e838eaed';
const HISTORICAL = ['core-0.20' => '0.20.*', 'core-0.21' => '0.21.*', 'core-0.22' => '0.22.*', 'core-0.23' => '0.23.*'];
const LANES = ['lowest', 'published-0.25', 'adoption-minimum', 'adoption-current', 'core-0.20', 'core-0.21', 'core-0.22', 'core-0.23'];

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
    check(($root['require'][CORE] ?? null) === '^0.19 || ^0.20 || ^0.21 || ^0.22 || ^0.23 || ^0.24 || ^0.25 || ^0.26', 'Keep the complete supported core range.');
    if (isset(HISTORICAL[$lane])) {
        $root['require'][CORE] = HISTORICAL[$lane];
    } elseif ($lane === 'published-0.25') {
        $root['require'][CORE] = '0.25.0';
    } elseif (str_starts_with($lane, 'adoption-')) {
        check(($candidate['name'] ?? null) === CORE, 'Expected the official core candidate manifest.');
        check(($candidate['require']['laravel/ai'] ?? null) === '^0.11.2', 'Unexpected candidate AI contract.');
        // This synthetic version is CI-only: the source and archive are immutable.
        $candidate['version'] = '0.26.0';
        $candidate['source'] = ['type' => 'git', 'url' => 'https://github.com/builtbyberry/laravel-swarm.git', 'reference' => CANDIDATE_REF];
        $candidate['dist'] = ['type' => 'zip', 'url' => 'https://api.github.com/repos/builtbyberry/laravel-swarm/zipball/'.CANDIDATE_REF, 'reference' => CANDIDATE_REF];
        unset($candidate['require-dev'], $candidate['scripts'], $candidate['repositories']);
        $root['repositories'] = [['type' => 'package', 'package' => $candidate]];
        $root['require'][CORE] = '0.26.0';
        $root['require-dev']['laravel/ai'] = $lane === 'adoption-minimum' ? '0.11.2' : '^0.11.2';
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
            if ($adoption || $lane === 'published-0.25') {
                check($version === ($adoption ? '0.26.0' : '0.25.0'), 'Wrong core version for lane.');
                check($ref === ($adoption ? CANDIDATE_REF : PUBLISHED_REF), 'Wrong core source for lane.');
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
        } elseif ($name === 'laravel/framework') {
            check(str_starts_with($version, '13.'), 'Expected Laravel 13.');
        } elseif ($name === 'laravel/mcp') {
            check(str_starts_with($version, '0.8.'), 'Expected Laravel MCP ^0.8.');
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
            echo (! str_starts_with($lane, 'adoption-') ? 'Published dependency lane' : 'Pinned candidate lane; NOT published-installability proof')."\n";
            echo implode("\n", $evidence)."\n";
        }
    } catch (\Throwable $e) {
        fwrite(STDERR, $e->getMessage()."\n");
        exit(1);
    }
}
