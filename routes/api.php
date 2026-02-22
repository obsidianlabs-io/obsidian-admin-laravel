<?php

declare(strict_types=1);

$registerModules = [
    require __DIR__.'/api/auth.php',
    require __DIR__.'/api/user.php',
    require __DIR__.'/api/access.php',
    require __DIR__.'/api/tenant.php',
    require __DIR__.'/api/system.php',
];

/**
 * @return list<string|null>
 */
$resolveApiRouteVersions = static function (): array {
    $currentVersion = trim((string) config('api.current_version', 'v1'));
    if ($currentVersion === '') {
        $currentVersion = 'v1';
    }

    $versions = [$currentVersion];
    if ((bool) config('api.legacy_unversioned_enabled', true)) {
        array_unshift($versions, null);
    }

    /** @var list<string|null> $versions */
    $versions = array_values(array_unique($versions));

    return $versions;
};

/**
 * @param  callable(?string):void  $callback
 */
$registerForApiVersions = static function (callable $callback) use ($resolveApiRouteVersions): void {
    foreach ($resolveApiRouteVersions() as $version) {
        $callback($version);
    }
};

/**
 * @param  string|null  $version
 */
$toVersionedPath = static function (?string $version, string $path): string {
    $normalizedPath = trim($path, '/');

    return $version ? "{$version}/{$normalizedPath}" : $normalizedPath;
};

$registerForApiVersions(static function (?string $version) use ($toVersionedPath, $registerModules): void {
    foreach ($registerModules as $registerModule) {
        $registerModule($version, $toVersionedPath);
    }
});
