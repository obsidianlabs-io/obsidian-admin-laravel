<?php

declare(strict_types=1);

namespace Tests\Unit\Hooks;

use PHPUnit\Framework\TestCase;

/**
 * Property 7: Protected Branch Push Guard
 *
 * For any `git push` whose remote ref matches `refs/heads/main` or `refs/heads/release/*`,
 * the pre-push hook SHALL exit with a non-zero status code and SHALL print guidance to use
 * a feature branch, unless the pre-push stage is excluded via bypass.
 */
class BranchGuardTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scriptPath = realpath(__DIR__.'/../../../scripts/hooks/branch-guard.sh');

        if (! $this->scriptPath || ! is_executable($this->scriptPath)) {
            $this->markTestSkipped('branch-guard.sh not found or not executable');
        }
    }

    /**
     * Execute the branch-guard.sh script with the given remote ref piped via stdin.
     *
     * @return array{exitCode: int, stderr: string, stdout: string}
     */
    private function executeBranchGuard(string $remoteRef): array
    {
        $localRef = 'refs/heads/some-branch';
        $localSha = str_repeat('a', 40);
        $remoteSha = str_repeat('0', 40);

        $stdinData = "$localRef $localSha $remoteRef $remoteSha";

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open(
            ['bash', $this->scriptPath],
            $descriptors,
            $pipes
        );

        $this->assertIsResource($process, 'Failed to start branch-guard.sh');

        fwrite($pipes[0], $stdinData."\n");
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stderr' => $stderr,
            'stdout' => $stdout,
        ];
    }

    /**
     * Generate random valid semver-like release branch names.
     *
     * @return string[]
     */
    private function generateRandomReleaseBranches(int $count): array
    {
        $branches = [];

        for ($i = 0; $i < $count; $i++) {
            $major = random_int(0, 99);
            $minor = random_int(0, 99);
            $patch = random_int(0, 99);

            $variants = [
                "release/$major.$minor.$patch",
                "release/v$major.$minor.$patch",
                "release/$major.$minor",
                "release/v$major.$minor",
                "release/hotfix-$major",
                "release/$major.$minor.$patch-rc.".random_int(1, 10),
            ];

            $branches[] = $variants[array_rand($variants)];
        }

        return $branches;
    }

    /**
     * Generate random feature branch names that should be allowed.
     *
     * @return string[]
     */
    private function generateRandomFeatureBranches(int $count): array
    {
        $prefixes = ['feature', 'fix', 'bugfix', 'hotfix', 'chore', 'docs', 'refactor', 'test', 'ci', 'build'];
        $words = ['auth', 'login', 'user', 'api', 'dashboard', 'settings', 'profile', 'payment', 'search', 'cache'];
        $separators = ['-', '/', '_'];

        $branches = [];

        for ($i = 0; $i < $count; $i++) {
            $prefix = $prefixes[array_rand($prefixes)];
            $word1 = $words[array_rand($words)];
            $word2 = $words[array_rand($words)];
            $sep = $separators[array_rand($separators)];
            $number = random_int(1, 9999);

            $variants = [
                "$prefix/$word1-$word2",
                "$prefix/$word1-$number",
                "$prefix/{$word1}{$sep}{$word2}-$number",
                "$prefix/JIRA-$number",
                "$prefix/$word1",
                "dev/$word1-$word2",
                "user/developer-name/$word1",
                "experiment/$word1-$number",
            ];

            $branches[] = $variants[array_rand($variants)];
        }

        return $branches;
    }

    // ─── Property Tests: Protected branches are blocked ─────────────────────────

    /**
     * Property 7: refs/heads/main is always blocked.
     */
    public function test_main_branch_is_always_blocked(): void
    {
        $result = $this->executeBranchGuard('refs/heads/main');

        $this->assertSame(1, $result['exitCode'], 'Push to refs/heads/main should be blocked');
        $this->assertStringContainsString('blocked', $result['stderr']);
        $this->assertStringContainsString('main', $result['stderr']);
    }

    /**
     * Property 7: Random release/* branches are always blocked.
     * Generates 50 random release branch names and verifies each is blocked.
     */
    public function test_random_release_branches_are_always_blocked(): void
    {
        $releaseBranches = $this->generateRandomReleaseBranches(50);

        foreach ($releaseBranches as $branch) {
            $remoteRef = "refs/heads/$branch";
            $result = $this->executeBranchGuard($remoteRef);

            $this->assertSame(
                1,
                $result['exitCode'],
                "Push to protected branch '$remoteRef' should be blocked (exit 1), got exit {$result['exitCode']}"
            );

            $this->assertStringContainsString(
                'blocked',
                $result['stderr'],
                "Error output for '$remoteRef' should contain 'blocked'"
            );
        }
    }

    /**
     * Property 7: Random feature branches are always allowed.
     * Generates 50 random feature branch names and verifies each is allowed.
     */
    public function test_random_feature_branches_are_always_allowed(): void
    {
        $featureBranches = $this->generateRandomFeatureBranches(50);

        foreach ($featureBranches as $branch) {
            $remoteRef = "refs/heads/$branch";
            $result = $this->executeBranchGuard($remoteRef);

            $this->assertSame(
                0,
                $result['exitCode'],
                "Push to feature branch '$remoteRef' should be allowed (exit 0), got exit {$result['exitCode']}"
            );

            $this->assertEmpty(
                $result['stderr'],
                "Feature branch '$remoteRef' should produce no stderr output"
            );
        }
    }

    // ─── Property Tests: Error output contains actionable guidance ───────────────

    /**
     * Property 7: Blocked pushes contain guidance to use a feature branch.
     * Tests main + 20 random release branches.
     */
    public function test_blocked_push_contains_feature_branch_guidance(): void
    {
        $protectedRefs = ['refs/heads/main'];

        foreach ($this->generateRandomReleaseBranches(20) as $branch) {
            $protectedRefs[] = "refs/heads/$branch";
        }

        foreach ($protectedRefs as $ref) {
            $result = $this->executeBranchGuard($ref);

            $this->assertSame(1, $result['exitCode'], "Push to '$ref' should be blocked");

            $this->assertStringContainsString(
                'feature branch',
                $result['stderr'],
                "Error output for '$ref' should mention 'feature branch'"
            );

            $this->assertStringContainsString(
                'pull request',
                $result['stderr'],
                "Error output for '$ref' should mention 'pull request'"
            );
        }
    }

    /**
     * Property 7: Blocked pushes contain bypass hint.
     * Tests main + 20 random release branches.
     */
    public function test_blocked_push_contains_bypass_hint(): void
    {
        $protectedRefs = ['refs/heads/main'];

        foreach ($this->generateRandomReleaseBranches(20) as $branch) {
            $protectedRefs[] = "refs/heads/$branch";
        }

        foreach ($protectedRefs as $ref) {
            $result = $this->executeBranchGuard($ref);

            $this->assertSame(1, $result['exitCode'], "Push to '$ref' should be blocked");

            $this->assertStringContainsString(
                'LEFTHOOK_EXCLUDE=pre-push',
                $result['stderr'],
                "Error output for '$ref' should contain bypass hint 'LEFTHOOK_EXCLUDE=pre-push'"
            );
        }
    }

    // ─── Edge Cases ─────────────────────────────────────────────────────────────

    /**
     * Edge case: refs/heads/main-feature should NOT be blocked (not an exact match for main).
     */
    public function test_main_prefix_branches_are_not_blocked(): void
    {
        $nonProtectedRefs = [
            'refs/heads/main-feature',
            'refs/heads/main-fix',
            'refs/heads/maintain/something',
            'refs/heads/mainly-docs',
        ];

        foreach ($nonProtectedRefs as $ref) {
            $result = $this->executeBranchGuard($ref);

            $this->assertSame(
                0,
                $result['exitCode'],
                "Push to '$ref' should be allowed (not an exact match for 'main')"
            );
        }
    }

    /**
     * Edge case: Empty stdin (no refs pushed) should allow the push.
     */
    public function test_empty_stdin_allows_push(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            ['bash', $this->scriptPath],
            $descriptors,
            $pipes
        );

        $this->assertIsResource($process);

        // Close stdin immediately (no refs)
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, 'Empty stdin (no refs) should exit 0');
    }
}
