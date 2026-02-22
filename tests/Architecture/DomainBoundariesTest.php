<?php

declare(strict_types=1);

test('auth domain does not depend on tenant models', function (): void {
    expect('App\Domains\Auth')
        ->not->toUse('App\Domains\Tenant\Models');
});

test('php source files declare strict types', function (): void {
    $roots = [
        base_path('app'),
        base_path('config'),
        base_path('database'),
        base_path('routes'),
        base_path('tests'),
    ];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            expect($contents)->not->toBeFalse();

            if (! str_contains((string) $contents, 'declare(strict_types=1);')) {
                throw new RuntimeException(sprintf('File missing strict types: %s', $file->getPathname()));
            }
        }
    }
});
