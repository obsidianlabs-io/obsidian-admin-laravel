<?php

declare(strict_types=1);

namespace Tests\Unit\Hooks;

use PHPUnit\Framework\TestCase;

/**
 * Property 4: Large File Blocking
 *
 * For any staged file whose size exceeds 5 MB, the pre-commit hook SHALL exit
 * with a non-zero status code and SHALL print the file path and its size.
 */
class CheckFileSizeTest extends TestCase
{
    private string $scriptPath;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scriptPath = realpath(__DIR__.'/../../../scripts/hooks/check-file-size.sh');
        $this->tempDir = sys_get_temp_dir().'/check-file-size-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up all temp files
        $files = glob($this->tempDir.'/*');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($this->tempDir);

        parent::tearDown();
    }

    /**
     * Create a temporary file of a specific size in bytes.
     */
    private function createFileOfSize(int $bytes, string $name = 'testfile'): string
    {
        $filePath = $this->tempDir.'/'.$name;
        $handle = fopen($filePath, 'wb');
        if ($bytes > 0) {
            // Write in chunks to avoid memory issues with large files
            $chunkSize = min($bytes, 1024 * 1024); // 1MB chunks
            $remaining = $bytes;
            while ($remaining > 0) {
                $writeSize = min($chunkSize, $remaining);
                fwrite($handle, str_repeat("\0", $writeSize));
                $remaining -= $writeSize;
            }
        }
        fclose($handle);

        return $filePath;
    }

    /**
     * Run the check-file-size.sh script with given file paths.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runScript(array $filePaths): array
    {
        $escapedPaths = array_map('escapeshellarg', $filePaths);
        $command = escapeshellarg($this->scriptPath).' '.implode(' ', $escapedPaths);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $this->tempDir);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * Property test: Files clearly under 5 MB should pass.
     */
    public function test_files_under_5mb_pass(): void
    {
        // Generate 100 random sizes between 1 byte and 4 MB
        $fiveMB = 5 * 1024 * 1024;

        for ($i = 0; $i < 100; $i++) {
            $size = random_int(1, $fiveMB - 1);

            // For performance, only actually create files at a few representative sizes
            // to avoid creating 100 multi-MB files
            if ($i < 5 || $size < 1024 * 1024) {
                $actualSize = $size < 1024 * 1024 ? $size : random_int(1, 1024 * 1024);
            } else {
                continue;
            }

            $filePath = $this->createFileOfSize($actualSize, "under_5mb_{$i}");
            $result = $this->runScript([$filePath]);

            $this->assertSame(
                0,
                $result['exitCode'],
                "File of size {$actualSize} bytes should pass but got exit code {$result['exitCode']}. Stderr: {$result['stderr']}"
            );
            unlink($filePath);
        }
    }

    /**
     * Property test: Files over 5 MB should be blocked.
     */
    public function test_files_over_5mb_are_blocked(): void
    {
        $fiveMB = 5 * 1024 * 1024;

        // Test with sizes just over 5 MB boundary and larger
        $sizes = [
            $fiveMB + 1,         // 5 MB + 1 byte
            $fiveMB + 1024,      // 5 MB + 1 KB
            $fiveMB + 1048576,   // 6 MB
            10 * 1024 * 1024,    // 10 MB
        ];

        foreach ($sizes as $index => $size) {
            $filePath = $this->createFileOfSize($size, "over_5mb_{$index}");
            $result = $this->runScript([$filePath]);

            $this->assertSame(
                1,
                $result['exitCode'],
                "File of size {$size} bytes should be blocked but got exit code {$result['exitCode']}"
            );

            // Verify error output contains the file path
            $this->assertStringContainsString(
                basename($filePath),
                $result['stderr'],
                "Error output should contain the file path for size {$size}"
            );

            // Verify error output contains size in MB
            $this->assertMatchesRegularExpression(
                '/\d+\.\d+\s*MB/',
                $result['stderr'],
                "Error output should contain the size in MB for file of {$size} bytes"
            );

            unlink($filePath);
        }
    }

    /**
     * Boundary test: Exactly 5 MB should pass (not strictly greater than).
     */
    public function test_exactly_5mb_passes(): void
    {
        $fiveMB = 5 * 1024 * 1024;
        $filePath = $this->createFileOfSize($fiveMB, 'exactly_5mb');
        $result = $this->runScript([$filePath]);

        $this->assertSame(
            0,
            $result['exitCode'],
            "File of exactly 5 MB should pass (limit is strictly greater than). Stderr: {$result['stderr']}"
        );
    }

    /**
     * Boundary test: 5 MB + 1 byte should fail.
     */
    public function test_5mb_plus_one_byte_fails(): void
    {
        $fiveMB = 5 * 1024 * 1024;
        $filePath = $this->createFileOfSize($fiveMB + 1, 'just_over_5mb');
        $result = $this->runScript([$filePath]);

        $this->assertSame(
            1,
            $result['exitCode'],
            'File of 5 MB + 1 byte should be blocked'
        );

        $this->assertStringContainsString(
            'just_over_5mb',
            $result['stderr'],
            'Error output should contain the file name'
        );
    }

    /**
     * Test: 1 MB file clearly passes.
     */
    public function test_1mb_file_passes(): void
    {
        $oneMB = 1 * 1024 * 1024;
        $filePath = $this->createFileOfSize($oneMB, 'one_mb_file');
        $result = $this->runScript([$filePath]);

        $this->assertSame(
            0,
            $result['exitCode'],
            "1 MB file should pass. Stderr: {$result['stderr']}"
        );
    }

    /**
     * Test: 10 MB file clearly fails.
     */
    public function test_10mb_file_fails(): void
    {
        $tenMB = 10 * 1024 * 1024;
        $filePath = $this->createFileOfSize($tenMB, 'ten_mb_file');
        $result = $this->runScript([$filePath]);

        $this->assertSame(
            1,
            $result['exitCode'],
            '10 MB file should be blocked'
        );

        $this->assertStringContainsString(
            'ten_mb_file',
            $result['stderr'],
            'Error output should contain the file path'
        );

        // Should report approximately 10 MB
        $this->assertMatchesRegularExpression(
            '/9\.\d+\s*MB|10\.\d+\s*MB/',
            $result['stderr'],
            'Error output should report size around 10 MB'
        );
    }

    /**
     * Property test: Multiple files where some exceed limit.
     * Only the oversized files should be reported, but exit code should be 1.
     */
    public function test_mixed_files_reports_only_oversized(): void
    {
        $fiveMB = 5 * 1024 * 1024;

        $smallFile = $this->createFileOfSize(1024, 'small_file.txt');
        $largeFile = $this->createFileOfSize($fiveMB + 1024, 'large_file.bin');
        $mediumFile = $this->createFileOfSize(2 * 1024 * 1024, 'medium_file.dat');

        $result = $this->runScript([$smallFile, $largeFile, $mediumFile]);

        $this->assertSame(1, $result['exitCode'], 'Should fail when any file exceeds limit');
        $this->assertStringContainsString('large_file.bin', $result['stderr'], 'Should report the large file');
        $this->assertStringNotContainsString('small_file.txt', $result['stderr'], 'Should not report the small file');
        $this->assertStringNotContainsString('medium_file.dat', $result['stderr'], 'Should not report the medium file');
    }

    /**
     * Test: No arguments should pass (no files to check).
     */
    public function test_no_arguments_passes(): void
    {
        $result = $this->runScript([]);

        $this->assertSame(
            0,
            $result['exitCode'],
            'No arguments should result in exit code 0'
        );
    }

    /**
     * Test: Non-existent file should be silently skipped (not crash).
     */
    public function test_nonexistent_file_is_skipped(): void
    {
        $result = $this->runScript(['/tmp/nonexistent_file_'.uniqid()]);

        $this->assertSame(
            0,
            $result['exitCode'],
            'Non-existent file should be skipped gracefully'
        );
    }
}
