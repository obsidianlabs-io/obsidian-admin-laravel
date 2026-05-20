<?php

declare(strict_types=1);

namespace Tests\Unit\Hooks;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Integration smoke tests for lefthook.yml configuration.
 */
class LefthookConfigTest extends TestCase
{
    private string $repoRoot;

    private string $lefthookPath;

    private array $parsedConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = realpath(__DIR__.'/../../../');
        $this->lefthookPath = $this->repoRoot.'/lefthook.yml';

        $this->assertFileExists($this->lefthookPath, 'lefthook.yml must exist at the repository root');

        $content = file_get_contents($this->lefthookPath);
        $this->assertNotFalse($content);

        // Parse YAML using symfony/yaml if available, or the yaml extension, or a basic approach
        $this->parsedConfig = $this->parseYaml($content);
    }

    /**
     * Test that lefthook.yml exists at the repository root.
     */
    public function test_lefthook_yml_exists_at_repository_root(): void
    {
        $this->assertFileExists($this->lefthookPath);
    }

    /**
     * Test that lefthook.yml is valid YAML that can be parsed without errors.
     */
    public function test_lefthook_yml_is_valid_yaml(): void
    {
        $content = file_get_contents($this->lefthookPath);
        $this->assertNotFalse($content, 'lefthook.yml should be readable');
        $this->assertNotEmpty($content, 'lefthook.yml should not be empty');

        $parsed = $this->parseYaml($content);
        $this->assertIsArray($parsed, 'lefthook.yml should parse to a valid array structure');
        $this->assertNotEmpty($parsed, 'lefthook.yml should have configuration entries');

        // Verify expected top-level keys exist
        $this->assertArrayHasKey('pre-commit', $parsed, 'lefthook.yml should define a pre-commit stage');
        $this->assertArrayHasKey('commit-msg', $parsed, 'lefthook.yml should define a commit-msg stage');
        $this->assertArrayHasKey('pre-push', $parsed, 'lefthook.yml should define a pre-push stage');
    }

    /**
     * Test that all referenced shell scripts exist and are executable.
     */
    public function test_all_referenced_scripts_exist_and_are_executable(): void
    {
        $content = file_get_contents($this->lefthookPath);
        $this->assertNotFalse($content);

        // Extract script paths from the YAML content (scripts/hooks/*.sh patterns)
        $scriptPaths = [];
        if (preg_match_all('/\bscripts\/hooks\/[a-zA-Z0-9_-]+\.(?:sh|php)\b/', $content, $matches)) {
            $scriptPaths = array_unique($matches[0]);
        }

        $this->assertNotEmpty($scriptPaths, 'lefthook.yml should reference at least one script');

        foreach ($scriptPaths as $relativePath) {
            $fullPath = $this->repoRoot.'/'.$relativePath;

            $this->assertFileExists(
                $fullPath,
                "Referenced script '{$relativePath}' must exist"
            );

            // Check executable permission (only meaningful on Unix-like systems)
            if (PHP_OS_FAMILY !== 'Windows') {
                $this->assertTrue(
                    is_executable($fullPath),
                    "Referenced script '{$relativePath}' must be executable"
                );
            }
        }
    }

    /**
     * Test that all composer scripts referenced in lefthook.yml exist in composer.json.
     */
    public function test_all_composer_scripts_referenced_exist_in_composer_json(): void
    {
        $composerJsonPath = $this->repoRoot.'/composer.json';
        $this->assertFileExists($composerJsonPath);

        $composerContent = file_get_contents($composerJsonPath);
        $this->assertNotFalse($composerContent);

        $composerConfig = json_decode($composerContent, true);
        $this->assertIsArray($composerConfig);
        $this->assertArrayHasKey('scripts', $composerConfig, 'composer.json must have a scripts section');

        $availableScripts = array_keys($composerConfig['scripts']);

        // Extract all "composer run <script>" references from lefthook.yml
        $lefthookContent = file_get_contents($this->lefthookPath);
        $this->assertNotFalse($lefthookContent);

        $referencedScripts = [];
        if (preg_match_all('/composer\s+run\s+([a-zA-Z0-9:_-]+)/', $lefthookContent, $matches)) {
            $referencedScripts = array_unique($matches[1]);
        }

        $this->assertNotEmpty(
            $referencedScripts,
            'lefthook.yml should reference at least one composer script'
        );

        foreach ($referencedScripts as $script) {
            $this->assertContains(
                $script,
                $availableScripts,
                "Composer script '{$script}' referenced in lefthook.yml must exist in composer.json"
            );
        }
    }

    /**
     * Test that no absolute paths are used in the configuration.
     */
    public function test_no_absolute_paths_in_configuration(): void
    {
        $content = file_get_contents($this->lefthookPath);
        $this->assertNotFalse($content);

        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            // Skip comment lines
            if (preg_match('/^\s*#/', $line)) {
                continue;
            }

            // Skip URLs (http://, https://, git://)
            $lineWithoutUrls = preg_replace('#https?://[^\s]+#', '', $line);
            $lineWithoutUrls = preg_replace('#git://[^\s]+#', '', $lineWithoutUrls ?? $line);

            // Check for Unix absolute paths (starting with /) that aren't part of a command flag
            // We look for paths like /usr/, /home/, /opt/, /etc/, /var/, /tmp/
            $this->assertDoesNotMatchRegularExpression(
                '#(?<![a-zA-Z0-9_])/(usr|home|opt|etc|var|tmp|bin|sbin|lib|Users|root)/[^\s]*#',
                $lineWithoutUrls ?? '',
                'Line '.($lineNumber + 1)." contains an absolute path: {$line}"
            );

            // Check for Windows absolute paths
            $this->assertDoesNotMatchRegularExpression(
                '/[A-Z]:\\\\/',
                $lineWithoutUrls ?? '',
                'Line '.($lineNumber + 1)." contains a Windows absolute path: {$line}"
            );
        }
    }

    /**
     * Test that lefthook dump succeeds (if lefthook is installed).
     */
    public function test_lefthook_dump_succeeds_if_binary_available(): void
    {
        // Check if lefthook binary is available
        $checkCommand = PHP_OS_FAMILY === 'Windows' ? 'where lefthook' : 'which lefthook';
        exec($checkCommand, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->markTestSkipped('lefthook binary is not installed — skipping dump validation');
        }

        // Run lefthook dump to validate the configuration
        $command = 'lefthook dump 2>&1';
        exec($command, $dumpOutput, $dumpExitCode);

        $this->assertSame(
            0,
            $dumpExitCode,
            "lefthook dump should succeed. Output:\n".implode("\n", $dumpOutput)
        );
    }

    /**
     * Parse YAML content using the best available method.
     */
    private function parseYaml(string $content): array
    {
        // Try Symfony YAML component first (commonly available in Laravel projects)
        if (class_exists(Yaml::class)) {
            return Yaml::parse($content) ?? [];
        }

        // Try PHP yaml extension
        if (function_exists('yaml_parse')) {
            $result = yaml_parse($content);

            return is_array($result) ? $result : [];
        }

        // Fallback: basic validation that the file is parseable
        // We verify it's not empty and has expected structure markers
        $this->assertStringContainsString('pre-commit:', $content);
        $this->assertStringContainsString('commit-msg:', $content);
        $this->assertStringContainsString('pre-push:', $content);

        // Return a minimal structure to allow tests to proceed
        return [
            'pre-commit' => true,
            'commit-msg' => true,
            'pre-push' => true,
        ];
    }
}
