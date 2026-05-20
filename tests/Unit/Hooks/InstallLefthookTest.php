<?php

declare(strict_types=1);

namespace Tests\Unit\Hooks;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for scripts/hooks/install-lefthook.php
 *
 *
 * These tests execute the install-lefthook.php script as a subprocess
 * with controlled environment variables and working directories to verify
 * CI detection, .git directory detection, missing binary behavior, and
 * the guarantee that the script always exits with code 0.
 */
class InstallLefthookTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scriptPath = realpath(__DIR__.'/../../../scripts/hooks/install-lefthook.php');
        $this->assertNotFalse($this->scriptPath, 'install-lefthook.php script must exist');
    }

    /**
     * Execute the install-lefthook.php script from a temporary directory structure.
     *
     * Creates a proper directory layout (scripts/hooks/) and copies the script there,
     * so that __DIR__/../../.git resolves correctly relative to the temp root.
     *
     * @param  array<string, string>  $env  Environment variables to set
     * @param  bool  $withGitDir  Whether to create a .git directory in the temp root
     * @return array{exitCode: int, output: string, tempRoot: string}
     */
    private function runScriptInIsolation(array $env = [], bool $withGitDir = false): array
    {
        $tempRoot = sys_get_temp_dir().'/lefthook-test-'.uniqid();
        $scriptsDir = $tempRoot.'/scripts/hooks';
        mkdir($scriptsDir, 0755, true);

        if ($withGitDir) {
            mkdir($tempRoot.'/.git', 0755, true);
        }

        $tempScript = $scriptsDir.'/install-lefthook.php';
        copy($this->scriptPath, $tempScript);

        $baseEnv = [
            'HOME' => getenv('HOME') ?: '/tmp',
            'PATH' => '/usr/local/bin:/usr/bin:/bin',
        ];

        $processEnv = array_merge($baseEnv, $env);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            [PHP_BINARY, $tempScript],
            $descriptors,
            $pipes,
            $tempRoot,
            $processEnv,
        );

        $this->assertIsResource($process, 'Failed to start PHP process');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'output' => $stdout.$stderr,
            'tempRoot' => $tempRoot,
        ];
    }

    /**
     * Recursively remove a temporary directory.
     */
    private function cleanupTempDir(string $tempRoot): void
    {
        $this->recursiveRemoveDir($tempRoot);
    }

    private function recursiveRemoveDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->recursiveRemoveDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    // =========================================================================
    // CI Environment Detection
    // =========================================================================

    public function test_skips_silently_when_ci_is_true(): void
    {
        $result = $this->runScriptInIsolation(['CI' => 'true'], withGitDir: true);

        try {
            $this->assertSame(0, $result['exitCode'], 'Script should exit 0 when CI=true');
            $this->assertEmpty(trim($result['output']), 'Script should produce no output when CI=true');
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    public function test_skips_silently_when_ci_is_one(): void
    {
        $result = $this->runScriptInIsolation(['CI' => '1'], withGitDir: true);

        try {
            $this->assertSame(0, $result['exitCode'], 'Script should exit 0 when CI=1');
            $this->assertEmpty(trim($result['output']), 'Script should produce no output when CI=1');
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    public function test_skips_silently_when_github_actions_is_true(): void
    {
        $result = $this->runScriptInIsolation(['GITHUB_ACTIONS' => 'true'], withGitDir: true);

        try {
            $this->assertSame(0, $result['exitCode'], 'Script should exit 0 when GITHUB_ACTIONS=true');
            $this->assertEmpty(trim($result['output']), 'Script should produce no output when GITHUB_ACTIONS=true');
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    public function test_skips_silently_when_gitlab_ci_is_true(): void
    {
        $result = $this->runScriptInIsolation(['GITLAB_CI' => 'true'], withGitDir: true);

        try {
            $this->assertSame(0, $result['exitCode'], 'Script should exit 0 when GITLAB_CI=true');
            $this->assertEmpty(trim($result['output']), 'Script should produce no output when GITLAB_CI=true');
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    public function test_skips_silently_when_circleci_is_true(): void
    {
        $result = $this->runScriptInIsolation(['CIRCLECI' => 'true'], withGitDir: true);

        try {
            $this->assertSame(0, $result['exitCode'], 'Script should exit 0 when CIRCLECI=true');
            $this->assertEmpty(trim($result['output']), 'Script should produce no output when CIRCLECI=true');
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    public function test_skips_silently_when_jenkins_url_is_set(): void
    {
        $result = $this->runScriptInIsolation(['JENKINS_URL' => 'http://jenkins.local'], withGitDir: true);

        try {
            $this->assertSame(0, $result['exitCode'], 'Script should exit 0 when JENKINS_URL is set');
            $this->assertEmpty(trim($result['output']), 'Script should produce no output when JENKINS_URL is set');
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    public function test_does_not_treat_empty_ci_var_as_truthy(): void
    {
        // CI='' should NOT trigger CI detection; script should proceed to .git check
        // Without .git, it exits silently
        $result = $this->runScriptInIsolation(['CI' => '']);

        try {
            $this->assertSame(0, $result['exitCode']);
            $this->assertEmpty(trim($result['output']), 'Empty CI var should not trigger CI detection');
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    public function test_does_not_treat_ci_zero_as_truthy(): void
    {
        // CI=0 should NOT trigger CI detection; script should proceed to .git check
        // Without .git, it exits silently
        $result = $this->runScriptInIsolation(['CI' => '0']);

        try {
            $this->assertSame(0, $result['exitCode']);
            $this->assertEmpty(trim($result['output']), 'CI=0 should not trigger CI detection');
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    // =========================================================================
    // Missing .git Directory
    // =========================================================================

    public function test_skips_silently_when_git_directory_missing(): void
    {
        // No .git directory in the temp root
        $result = $this->runScriptInIsolation([], withGitDir: false);

        try {
            $this->assertSame(0, $result['exitCode'], 'Script should exit 0 when .git directory is missing');
            $this->assertEmpty(trim($result['output']), 'Script should produce no output when .git is missing');
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    // =========================================================================
    // Missing Lefthook Binary
    // =========================================================================

    public function test_prints_install_hints_when_lefthook_binary_missing(): void
    {
        // With .git but empty PATH so lefthook cannot be found
        $result = $this->runScriptInIsolation(
            env: ['PATH' => '/tmp/nonexistent-bin-'.uniqid()],
            withGitDir: true,
        );

        try {
            // Should still exit 0 (never fails composer install)
            $this->assertSame(0, $result['exitCode'], 'Script should exit 0 even when lefthook is missing');

            // Should print install hints
            $this->assertStringContainsString('Lefthook is not installed', $result['output']);
            $this->assertStringContainsString('brew install lefthook', $result['output']);
            $this->assertStringContainsString('scoop install lefthook', $result['output']);
            $this->assertStringContainsString('snap install lefthook', $result['output']);
            $this->assertStringContainsString('go install', $result['output']);
            $this->assertStringContainsString('composer run hooks:install', $result['output']);
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    public function test_exits_zero_when_lefthook_binary_missing(): void
    {
        // Verify the exit code is always 0 even when binary is missing
        $result = $this->runScriptInIsolation(
            env: ['PATH' => '/tmp/nonexistent-bin-'.uniqid()],
            withGitDir: true,
        );

        try {
            $this->assertSame(0, $result['exitCode'], 'Script MUST exit 0 so composer install never fails');
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    // =========================================================================
    // Successful Installation Path
    // =========================================================================

    public function test_runs_lefthook_install_when_binary_available(): void
    {
        $tempRoot = sys_get_temp_dir().'/lefthook-test-'.uniqid();
        $scriptsDir = $tempRoot.'/scripts/hooks';
        $binDir = $tempRoot.'/bin';
        mkdir($scriptsDir, 0755, true);
        mkdir($tempRoot.'/.git', 0755, true);
        mkdir($binDir, 0755, true);

        $tempScript = $scriptsDir.'/install-lefthook.php';
        copy($this->scriptPath, $tempScript);

        // Create a fake lefthook binary that records it was called
        $markerFile = $tempRoot.'/lefthook-called.marker';
        $fakeBinary = $binDir.'/lefthook';
        file_put_contents($fakeBinary, "#!/bin/bash\necho \"lefthook install called\" > \"{$markerFile}\"\nexit 0\n");
        chmod($fakeBinary, 0755);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = [
            'HOME' => getenv('HOME') ?: '/tmp',
            'PATH' => $binDir.':/usr/bin:/bin',
        ];

        $process = proc_open(
            [PHP_BINARY, $tempScript],
            $descriptors,
            $pipes,
            $tempRoot,
            $env,
        );

        $this->assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        try {
            // Should exit 0
            $this->assertSame(0, $exitCode, 'Script should exit 0 on successful installation');

            // Should NOT print install hints
            $this->assertStringNotContainsString('Lefthook is not installed', $stdout.$stderr);

            // The fake lefthook binary should have been called
            $this->assertFileExists($markerFile, 'lefthook install should have been executed');
            $this->assertStringContainsString('lefthook install called', file_get_contents($markerFile));
        } finally {
            $this->cleanupTempDir($tempRoot);
        }
    }

    // =========================================================================
    // Exit Code Guarantee (all scenarios must exit 0)
    // =========================================================================

    public function test_always_exits_zero_in_ci(): void
    {
        $result = $this->runScriptInIsolation(['CI' => 'true'], withGitDir: true);

        try {
            $this->assertSame(0, $result['exitCode']);
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    public function test_always_exits_zero_without_git(): void
    {
        $result = $this->runScriptInIsolation([], withGitDir: false);

        try {
            $this->assertSame(0, $result['exitCode']);
        } finally {
            $this->cleanupTempDir($result['tempRoot']);
        }
    }

    public function test_always_exits_zero_when_lefthook_install_fails(): void
    {
        // Even if lefthook install returns non-zero, the script should exit 0
        $tempRoot = sys_get_temp_dir().'/lefthook-test-'.uniqid();
        $scriptsDir = $tempRoot.'/scripts/hooks';
        $binDir = $tempRoot.'/bin';
        mkdir($scriptsDir, 0755, true);
        mkdir($tempRoot.'/.git', 0755, true);
        mkdir($binDir, 0755, true);

        $tempScript = $scriptsDir.'/install-lefthook.php';
        copy($this->scriptPath, $tempScript);

        // Create a fake lefthook binary that fails
        $fakeBinary = $binDir.'/lefthook';
        file_put_contents($fakeBinary, "#!/bin/bash\nexit 1\n");
        chmod($fakeBinary, 0755);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = [
            'HOME' => getenv('HOME') ?: '/tmp',
            'PATH' => $binDir.':/usr/bin:/bin',
        ];

        $process = proc_open(
            [PHP_BINARY, $tempScript],
            $descriptors,
            $pipes,
            $tempRoot,
            $env,
        );

        $this->assertIsResource($process);

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        try {
            // Must ALWAYS exit 0 so composer install never fails
            $this->assertSame(0, $exitCode, 'Script MUST exit 0 even when lefthook install fails');
        } finally {
            $this->cleanupTempDir($tempRoot);
        }
    }
}
