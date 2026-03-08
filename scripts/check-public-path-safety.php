<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$targets = [
    'README*',
    'CHANGELOG*',
    'CONTRIBUTING.md',
    'SUPPORT.md',
    'SECURITY.md',
    'CODE_OF_CONDUCT.md',
    '.github/**/*',
    'docs/**/*',
];
$patterns = [
    '/\/Users\/[A-Za-z0-9._-]+\//',
    '/\/home\/runner\/work\//',
    '/[A-Za-z]:\\\\Users\\\\/',
    '/file:\/\//',
];

$command = 'git -C '.escapeshellarg($repoRoot).' ls-files '.implode(' ', array_map('escapeshellarg', $targets));
exec($command, $files, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Failed to enumerate tracked public text files.\n");
    exit(1);
}

$failures = [];

foreach ($files as $relativePath) {
    $absolutePath = $repoRoot.DIRECTORY_SEPARATOR.$relativePath;

    if (! is_file($absolutePath)) {
        continue;
    }

    $contents = file_get_contents($absolutePath);

    if ($contents === false) {
        fwrite(STDERR, "Failed to read {$relativePath}.\n");
        exit(1);
    }

    $lines = preg_split('/\R/', $contents) ?: [];

    foreach ($lines as $index => $line) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line) === 1) {
                $failures[] = sprintf('%s:%d: %s', $relativePath, $index + 1, trim($line));
                break;
            }
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Public text files contain machine-specific absolute paths:\n");
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Public path safety check passed.\n");
