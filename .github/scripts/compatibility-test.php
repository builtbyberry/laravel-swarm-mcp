<?php

declare(strict_types=1);

namespace McpCompatibility;

require __DIR__.'/compatibility.php';
require __DIR__.'/../../vendor/autoload.php';

function package(string $name, string $version, string $ref): array
{
    $repository = $name;

    return [
        'name' => $name, 'version' => $version,
        'source' => ['type' => 'git', 'url' => "https://github.com/{$repository}.git", 'reference' => $ref],
        'dist' => ['type' => 'zip', 'url' => "https://api.github.com/repos/{$repository}/zipball/{$ref}", 'reference' => $ref],
    ];
}

function rejects(callable $callback, string $label): void
{
    try {
        $callback();
    } catch (\RuntimeException) {
        return;
    }
    throw new \RuntimeException("Guard accepted negative control: {$label}");
}

$root = readJson($argv[1] ?? __DIR__.'/../../composer.json');
$candidate = ['name' => CORE, 'require' => ['laravel/ai' => '^0.11.2']];
$controls = 0;
$published = [
    '0.19' => ['5c1df153aaeb927c1de788b8c7af626aca130c7d', '0.8.0', '^0.8'],
    '0.20' => ['8a6fe26cf6222c04d481bab085212d75b8bf174b', '0.9.0', '^0.9'],
    '0.21' => ['b49c50c161433eaf03598de64e49d1ca4b8d0257', '0.9.0', '^0.9'],
    '0.22' => ['315b654e0f4b65e09389537607b5ce7e9038ec8a', '0.9.0', '^0.9'],
    '0.23' => ['e3ca8b30af3b50592b2f15e1cc1130ecddad66fb', '0.9.0', '^0.9'],
    '0.25' => [PUBLISHED_REF, '0.10.3', '^0.10.3'],
];
foreach (LANES as $lane) {
    $adoption = str_starts_with($lane, 'adoption-');
    $native1 = str_starts_with($lane, 'native1-');
    $isCandidate = $adoption || $native1;
    $minor = isset(HISTORICAL[$lane]) ? substr($lane, 5) : ($lane === 'lowest' ? '0.19' : '0.25');
    [$coreRef, $aiVersion, $aiConstraint] = $adoption
        ? [CANDIDATE_REF, '0.11.2', '^0.11.2']
        : $published[$minor];
    if ($native1) {
        [$coreRef, $aiVersion, $aiConstraint] = [NATIVE1_CANDIDATE_REF, '1.0.0', '^1.0'];
    }
    $candidate['require']['laravel/ai'] = $aiConstraint;
    $set = packages([
        package(CORE, $native1 ? '0.27.0' : ($adoption ? '0.26.0' : 'v'.$minor.'.0'), $coreRef),
        package('laravel/ai', 'v'.$aiVersion, $native1 ? NATIVE1_AI_MINIMUM_REF : ($adoption ? AI_MINIMUM_REF : str_repeat('a', 40))),
        package('laravel/framework', 'v13.16.0', str_repeat('b', 40)),
        package('laravel/mcp', 'v1.0.0', MCP_MINIMUM_REF),
    ]);
    $set[CORE]['require']['laravel/ai'] = $aiConstraint;
    verify($set, $set, $lane);
    $prepared = prepare($root, $lane, $candidate);
    check($lane !== 'lowest' || $prepared === $root, 'Lowest lane must keep original constraints.');
    check(! $isCandidate || $prepared['repositories'][0]['package']['source']['reference'] === $coreRef, 'Candidate must be immutable.');
    check(! $isCandidate || $prepared['require-dev']['laravel/ai'] === (str_ends_with($lane, '-minimum') ? $aiVersion : $aiConstraint), 'Wrong AI lane pin.');
    check($lane !== 'published-0.25' || $prepared['require'][CORE] === '0.25.0', 'Published lane must stay pinned.');

    check($lane !== 'native1-minimum' || $prepared['require']['laravel/mcp'] === '1.0.0', 'Wrong MCP minimum pin.');

    // Mutate the Composer evidence shape, both independently and in agreement.
    foreach (array_keys($set) as $name) {
        foreach (['version', 'source', 'dist'] as $field) {
            $bad = $set;
            unset($bad[$name][$field]);
            rejects(fn () => verify($set, $bad, $lane), "missing installed {$field}");
            rejects(fn () => verify($bad, $set, $lane), "missing locked {$field}");
            $controls += 2;
        }
        foreach (['dev-main', 'v99.0.0'] as $version) {
            $bad = $set;
            $bad[$name]['version'] = $version;
            rejects(fn () => verify($bad, $bad, $lane), "incorrect {$name} version");
            $controls++;
        }
        $bad = $set;
        $bad[$name]['source']['url'] = 'https://github.com/example/fork.git';
        rejects(fn () => verify($bad, $bad, $lane), 'fork source');
        $bad = $set;
        $bad[$name]['dist']['url'] = 'https://example.com/patched.zip';
        rejects(fn () => verify($bad, $bad, $lane), 'replaced archive');
        $bad = $set;
        $bad[$name]['dist']['reference'] = str_repeat('d', 40);
        rejects(fn () => verify($bad, $bad, $lane), 'archive ref mismatch');
        $bad = $set;
        $bad[$name]['source']['reference'] = 'main';
        $bad[$name]['dist']['reference'] = 'main';
        $bad[$name]['dist']['url'] = 'https://api.github.com/repos/'.$name.'/zipball/main';
        rejects(fn () => verify($bad, $bad, $lane), 'moving source ref');
        $bad = $set;
        $bad[$name]['source']['type'] = 'svn';
        rejects(fn () => verify($bad, $bad, $lane), 'wrong source type');
        $bad = $set;
        $bad[$name]['dist'] = ['type' => 'path', 'url' => '/tmp/local-package', 'reference' => str_repeat('a', 40)];
        rejects(fn () => verify($bad, $bad, $lane), 'path distribution');
        foreach (['version', 'source', 'dist'] as $field) {
            $bad = $set;
            $replacement = package($name, 'v5.1.0', str_repeat('9', 40));
            $bad[$name][$field] = $replacement[$field];
            rejects(fn () => verify($set, $bad, $lane), 'installed field mismatch');
            rejects(fn () => verify($bad, $set, $lane), 'locked field mismatch');
            $controls += 2;
        }
        $controls += 6;
    }
    foreach (['locked', 'installed', 'both'] as $missing) {
        $lock = $set;
        $installed = $set;
        if ($missing !== 'installed') {
            unset($lock[CORE]['require']['laravel/ai']);
        }
        if ($missing !== 'locked') {
            unset($installed[CORE]['require']['laravel/ai']);
        }
        rejects(fn () => verify($lock, $installed, $lane), 'missing '.$missing.' core AI contract');
        $controls++;
    }
    $bad = $set;
    $bad[CORE]['require']['laravel/ai'] = '^99.0';
    rejects(fn () => verify($set, $bad, $lane), 'lock/install core AI contract mismatch');
    rejects(fn () => verify($bad, $set, $lane), 'installed/lock core AI contract mismatch');
    rejects(fn () => verify($bad, $bad, $lane), 'unsatisfied actual core AI contract');
    $controls += 3;
    foreach ($isCandidate ? [] : ($lane === 'published-0.25' ? ['0.10.0', '0.11.2'] : ['0.10.3']) as $incompatibleAi) {
        $bad = $set;
        $bad['laravel/ai']['version'] = $incompatibleAi;
        rejects(fn () => verify($bad, $bad, $lane), 'official stable AI outside resolved core contract');
        $controls++;
    }
    foreach (['0.10.3', '0.11.0', '0.11.1'] as $oldAi) {
        if ($adoption) {
            $bad = $set;
            $bad['laravel/ai']['version'] = $oldAi;
            rejects(fn () => verify($bad, $bad, $lane), 'old AI');
            $controls++;
        }
    }
    foreach (['0.8.0', '0.11.2'] as $oldVersion) {
        $bad = $set;
        $bad['laravel/mcp']['version'] = $oldVersion;
        rejects(fn () => verify($bad, $bad, $lane), 'old MCP');
        $controls++;
        if ($native1) {
            $bad = $set;
            $bad['laravel/ai']['version'] = $oldVersion;
            rejects(fn () => verify($bad, $bad, $lane), 'old AI on native1 candidate');
            $controls++;
        }
    }
    $differentInstalled = $set;
    $differentInstalled['laravel/framework'] = package('laravel/framework', 'v13.17.0', str_repeat('e', 40));
    rejects(fn () => verify($set, $differentInstalled, $lane), 'valid installed package differs from lock');
    $controls++;
    foreach ([CORE, 'laravel/ai', 'laravel/mcp'] as $name) {
        if (($name === CORE && ($lane === 'lowest' || isset(HISTORICAL[$lane]))) || ($name === 'laravel/ai' && ! in_array($lane, ['adoption-minimum', 'native1-minimum'], true)) || ($name === 'laravel/mcp' && $lane !== 'native1-minimum')) {
            continue;
        }
        $bad = $set;
        $bad[$name] = array_replace($set[$name], package($name, $set[$name]['version'], str_repeat('d', 40)));
        rejects(fn () => verify($bad, $bad, $lane), 'wrong pinned commit in otherwise consistent evidence');
        $controls++;
    }
}
rejects(fn () => prepare($root, 'adoption-current', ['name' => 'example/fork', 'require' => ['laravel/ai' => '^0.11.2']]), 'wrong manifest identity');
rejects(fn () => prepare($root, 'adoption-current', ['name' => CORE, 'require' => ['laravel/ai' => '^0.10']]), 'wrong manifest contract');
rejects(fn () => prepare($root, 'native1-current', ['name' => 'example/fork', 'require' => ['laravel/ai' => '^1.0']]), 'wrong native1 manifest identity');
rejects(fn () => prepare($root, 'native1-current', ['name' => CORE, 'require' => ['laravel/ai' => '^0.11.2']]), 'old native1 manifest contract');
rejects(fn () => prepare($root, 'adoption-current', ['name' => CORE, 'require' => ['laravel/ai' => '^1.0']]), 'new AI on old candidate');
$badMcpRoot = $root;
$badMcpRoot['require']['laravel/mcp'] = '^0.8';
rejects(fn () => prepare($badMcpRoot, 'native1-current', ['name' => CORE, 'require' => ['laravel/ai' => '^1.0']]), 'old MCP manifest');
$badRoot = $root;
$badRoot['require'][CORE] = '^0.26';
rejects(fn () => prepare($badRoot, 'adoption-current', ['name' => CORE, 'require' => ['laravel/ai' => '^0.11.2']]), 'dropped older ranges');
rejects(fn () => verify([], [], 'unknown'), 'unknown lane');
echo count(LANES).' positive lanes and '.($controls + 8)." negative dependency controls passed.\n";
