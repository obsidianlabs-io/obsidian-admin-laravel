<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$tag = $argv[1] ?? getenv('GITHUB_REF_NAME') ?: '';

if ($tag === '') {
    fwrite(STDERR, "Release pairing check requires a tag like v1.2.0.\n");
    exit(1);
}

if (! preg_match('/^v(?P<version>\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?)$/', $tag, $tagMatch)) {
    fwrite(STDERR, "Unsupported release tag format: {$tag}\n");
    exit(1);
}

$version = $tagMatch['version'];
$releaseNotePath = $root."/docs/releases/{$tag}.md";
$changelogPath = $root.'/CHANGELOG.md';
$changelogZhPath = $root.'/CHANGELOG.zh_CN.md';
$matrixPath = $root.'/docs/compatibility-matrix.md';
$frontendRepo = 'https://github.com/obsidianlabs-io/obsidian-admin-vue.git';

assertFileExists($releaseNotePath, 'release note');
assertVersionHeading($changelogPath, $version);
assertVersionHeading($changelogZhPath, $version);

$matrix = file_get_contents($matrixPath);
if ($matrix === false) {
    fwrite(STDERR, "Unable to read compatibility matrix.\n");
    exit(1);
}

$stablePair = findBackendStablePair($matrix, $tag);
if ($stablePair === null) {
    fwrite(STDERR, "Compatibility matrix does not declare {$tag} as a stable backend release pair.\n");
    exit(1);
}

if (! remoteTagExists($frontendRepo, $stablePair['frontend'])) {
    fwrite(STDERR, "Expected paired frontend tag {$stablePair['frontend']} was not found in {$frontendRepo}.\n");
    exit(1);
}

fwrite(STDOUT, "Release pairing OK: {$tag} <-> {$stablePair['frontend']}\n");

function assertFileExists(string $path, string $label): void
{
    if (! is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

function assertVersionHeading(string $path, string $version): void
{
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }

    if (! preg_match('/^## \['.preg_quote($version, '/').'\](?:\s|$)/m', $content)) {
        fwrite(STDERR, "{$path} does not contain a release heading for {$version}.\n");
        exit(1);
    }
}

/**
 * @return array{backend:string,frontend:string}|null
 */
function findBackendStablePair(string $matrix, string $tag): ?array
{
    foreach (preg_split('/\R/', $matrix) ?: [] as $line) {
        if (! preg_match('/^\|\s*`(?P<backend>v[^`]+)`\s*\|\s*`(?P<frontend>v[^`]+)`\s*\|\s*(?P<status>[^|]+)\|/', $line, $match)) {
            continue;
        }

        if ($match['backend'] !== $tag) {
            continue;
        }

        if (! str_contains(strtolower(trim($match['status'])), 'stable')) {
            continue;
        }

        return [
            'backend' => $match['backend'],
            'frontend' => $match['frontend'],
        ];
    }

    return null;
}

function remoteTagExists(string $repo, string $tag): bool
{
    $command = sprintf(
        'git ls-remote --tags %s %s 2>/dev/null',
        escapeshellarg($repo),
        escapeshellarg('refs/tags/'.$tag)
    );

    exec($command, $output, $exitCode);

    return $exitCode === 0 && $output !== [];
}
