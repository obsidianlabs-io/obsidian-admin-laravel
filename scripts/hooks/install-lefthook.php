<?php

declare(strict_types=1);

// Skip in CI environments
$ciIndicators = ['CI', 'GITHUB_ACTIONS', 'GITLAB_CI', 'CIRCLECI', 'JENKINS_URL'];
foreach ($ciIndicators as $var) {
    $value = getenv($var);
    if ($value !== false && $value !== '' && $value !== '0') {
        exit(0);
    }
}

// Skip if no .git directory — tarball extract, Docker image build
if (! is_dir(__DIR__.'/../../.git')) {
    exit(0);
}

// Check if lefthook binary is available in PATH
$command = PHP_OS_FAMILY === 'Windows' ? 'where lefthook 2>NUL' : 'which lefthook 2>/dev/null';
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    echo PHP_EOL;
    echo "\u{26A0}\u{FE0F}  Lefthook is not installed. Git hooks will NOT be active.".PHP_EOL;
    echo PHP_EOL;
    echo 'Install Lefthook to enable pre-commit quality checks:'.PHP_EOL;
    echo '  macOS:   brew install lefthook'.PHP_EOL;
    echo '  Linux:   sudo snap install lefthook --classic'.PHP_EOL;
    echo '           # or: curl -fsSL https://get.lefthook.com | sh'.PHP_EOL;
    echo '  Windows: scoop install lefthook'.PHP_EOL;
    echo '  Any OS:  go install github.com/evilmartians/lefthook@latest'.PHP_EOL;
    echo PHP_EOL;
    echo 'Then run: composer run hooks:install'.PHP_EOL;
    echo PHP_EOL;
    exit(0);
}

// Install hooks via lefthook
passthru('lefthook install', $installExitCode);

// Always exit 0 so composer install never fails from hook setup
exit(0);
