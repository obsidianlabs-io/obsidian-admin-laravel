<?php

declare(strict_types=1);

/**
 * Property 6: Commit Message Validation
 *
 * For any commit message string, the commit-msg hook SHALL accept it if and only if:
 * (a) it matches the Conventional Commit pattern `<type>(<scope>)?: <description>`
 *     with an allowed type, AND its subject line is ≤72 characters; OR
 * (b) it is a Git-generated merge commit starting with "Merge branch" or "Merge pull request".
 */
beforeEach(function () {
    $this->scriptPath = base_path('scripts/hooks/validate-commit-msg.sh');
    $this->tmpDir = sys_get_temp_dir().'/commit-msg-test-'.getmypid();
    if (! is_dir($this->tmpDir)) {
        mkdir($this->tmpDir, 0755, true);
    }
});

afterEach(function () {
    // Clean up temp files
    if (is_dir($this->tmpDir)) {
        array_map('unlink', glob($this->tmpDir.'/*'));
        rmdir($this->tmpDir);
    }
});

/**
 * Helper: Run the validate-commit-msg.sh script with a given message.
 * Returns the exit code.
 */
function runCommitMsgScript(string $scriptPath, string $tmpDir, string $message): int
{
    $file = $tmpDir.'/COMMIT_MSG_'.bin2hex(random_bytes(4));
    file_put_contents($file, $message."\n");

    $command = sprintf('bash %s %s 2>/dev/null', escapeshellarg($scriptPath), escapeshellarg($file));
    exec($command, $output, $exitCode);

    @unlink($file);

    return $exitCode;
}

/**
 * Helper: Generate a random valid Conventional Commit message within 72 chars.
 */
function generateValidCommitMessage(): string
{
    $types = ['feat', 'fix', 'docs', 'style', 'refactor', 'perf', 'test', 'build', 'ci', 'chore', 'revert'];
    $type = $types[array_rand($types)];

    // Optionally add a scope
    $scope = '';
    if (random_int(0, 1) === 1) {
        $scopeChars = 'abcdefghijklmnopqrstuvwxyz0123456789_-';
        $scopeLen = random_int(2, 10);
        $scopeStr = '';
        for ($i = 0; $i < $scopeLen; $i++) {
            $scopeStr .= $scopeChars[random_int(0, strlen($scopeChars) - 1)];
        }
        $scope = '('.$scopeStr.')';
    }

    // Optionally add breaking change indicator
    $breaking = random_int(0, 5) === 0 ? '!' : '';

    // Generate a description that keeps total length ≤ 72
    $prefix = $type.$scope.$breaking.': ';
    $maxDescLen = 72 - strlen($prefix);
    // Ensure at least 1 char description
    $maxDescLen = max($maxDescLen, 1);
    $descLen = random_int(1, min($maxDescLen, 50));

    $descChars = 'abcdefghijklmnopqrstuvwxyz ';
    $desc = '';
    for ($i = 0; $i < $descLen; $i++) {
        $desc .= $descChars[random_int(0, strlen($descChars) - 1)];
    }
    // Ensure description doesn't start with space (the pattern requires .+ after ": ")
    $desc = ltrim($desc);
    if ($desc === '') {
        $desc = 'update';
    }

    return $prefix.$desc;
}

/**
 * Helper: Generate a random invalid commit message.
 */
function generateInvalidCommitMessage(): string
{
    $strategy = random_int(0, 7);

    switch ($strategy) {
        case 0:
            // Missing type entirely - just a random sentence
            $words = ['updated', 'fixed', 'changed', 'modified', 'added', 'removed'];

            return $words[array_rand($words)].' something in the code';

        case 1:
            // Wrong/unknown type
            $badTypes = ['feature', 'bugfix', 'update', 'change', 'hotfix', 'wip', 'misc', 'improvement'];
            $badType = $badTypes[array_rand($badTypes)];

            return $badType.': some description';

        case 2:
            // Missing colon
            $types = ['feat', 'fix', 'docs', 'style', 'refactor'];

            return $types[array_rand($types)].' missing colon here';

        case 3:
            // Missing space after colon
            $types = ['feat', 'fix', 'docs', 'style', 'refactor'];

            return $types[array_rand($types)].':no space after colon';

        case 4:
            // Empty description after colon+space
            $types = ['feat', 'fix', 'docs', 'style', 'refactor'];

            return $types[array_rand($types)].': ';

        case 5:
            // Invalid scope characters (uppercase, special chars)
            $types = ['feat', 'fix', 'docs'];

            return $types[array_rand($types)].'(INVALID SCOPE): description';

        case 6:
            // Completely empty or whitespace
            return random_int(0, 1) === 0 ? '' : '   ';

        case 7:
            // Random gibberish
            $len = random_int(5, 30);
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 !@#$%';
            $msg = '';
            for ($i = 0; $i < $len; $i++) {
                $msg .= $chars[random_int(0, strlen($chars) - 1)];
            }

            return $msg;

        default:
            return 'invalid message';
    }
}

// --- Property Tests ---

test('Property 6: accepts 100+ random valid Conventional Commit messages', function () {
    $iterations = 120;
    $failures = [];

    for ($i = 0; $i < $iterations; $i++) {
        $message = generateValidCommitMessage();
        $exitCode = runCommitMsgScript($this->scriptPath, $this->tmpDir, $message);

        if ($exitCode !== 0) {
            $failures[] = "Iteration {$i}: Expected acceptance but got exit code {$exitCode} for: \"{$message}\" (length: ".strlen($message).')';
        }
    }

    expect($failures)->toBeEmpty(
        'Failed for '.count($failures)." of {$iterations} valid messages:\n".implode("\n", array_slice($failures, 0, 10))
    );
})->group('property', 'hooks');

test('Property 6: rejects 100+ random invalid commit messages', function () {
    $iterations = 120;
    $failures = [];

    for ($i = 0; $i < $iterations; $i++) {
        $message = generateInvalidCommitMessage();
        $exitCode = runCommitMsgScript($this->scriptPath, $this->tmpDir, $message);

        if ($exitCode === 0) {
            $failures[] = "Iteration {$i}: Expected rejection but got exit code 0 for: \"{$message}\"";
        }
    }

    expect($failures)->toBeEmpty(
        'Failed for '.count($failures)." of {$iterations} invalid messages:\n".implode("\n", array_slice($failures, 0, 10))
    );
})->group('property', 'hooks');

test('Property 6: accepts merge commit messages', function () {
    $mergeMessages = [
        'Merge branch \'feature/auth\' into main',
        'Merge branch "develop" into release/1.0',
        'Merge pull request #123 from user/feature-branch',
        'Merge pull request #9999 from org/hotfix-critical',
        'Merge branch \'hotfix/security-patch\'',
    ];

    foreach ($mergeMessages as $message) {
        $exitCode = runCommitMsgScript($this->scriptPath, $this->tmpDir, $message);
        expect($exitCode)->toBe(0, "Merge commit should be accepted: \"{$message}\"");
    }
})->group('property', 'hooks');

test('Property 6: subject line length boundary - exactly 72 chars passes', function () {
    // Build a message that is exactly 72 characters
    $prefix = 'feat: ';
    $descLen = 72 - strlen($prefix);
    $desc = str_repeat('a', $descLen);
    $message = $prefix.$desc;

    expect(strlen($message))->toBe(72);

    $exitCode = runCommitMsgScript($this->scriptPath, $this->tmpDir, $message);
    expect($exitCode)->toBe(0, "Message of exactly 72 chars should pass: \"{$message}\"");
})->group('property', 'hooks');

test('Property 6: subject line length boundary - 73 chars fails', function () {
    // Build a message that is exactly 73 characters
    $prefix = 'feat: ';
    $descLen = 73 - strlen($prefix);
    $desc = str_repeat('a', $descLen);
    $message = $prefix.$desc;

    expect(strlen($message))->toBe(73);

    $exitCode = runCommitMsgScript($this->scriptPath, $this->tmpDir, $message);
    expect($exitCode)->toBe(1, "Message of 73 chars should fail: \"{$message}\"");
})->group('property', 'hooks');

test('Property 6: all valid types are accepted', function () {
    $types = ['feat', 'fix', 'docs', 'style', 'refactor', 'perf', 'test', 'build', 'ci', 'chore', 'revert'];

    foreach ($types as $type) {
        $message = $type.': valid description';
        $exitCode = runCommitMsgScript($this->scriptPath, $this->tmpDir, $message);
        expect($exitCode)->toBe(0, "Type '{$type}' should be accepted");
    }
})->group('property', 'hooks');

test('Property 6: valid messages with scopes are accepted', function () {
    $messages = [
        'feat(auth): add two-factor authentication',
        'fix(api): resolve null pointer in user endpoint',
        'docs(readme): update installation instructions',
        'refactor(core-utils): simplify helper functions',
        'ci(github_actions): add deployment workflow',
        'perf(db-query): optimize user lookup',
        'test(unit-1): add missing coverage',
    ];

    foreach ($messages as $message) {
        $exitCode = runCommitMsgScript($this->scriptPath, $this->tmpDir, $message);
        expect($exitCode)->toBe(0, "Scoped message should be accepted: \"{$message}\"");
    }
})->group('property', 'hooks');

test('Property 6: valid messages with breaking change indicator are accepted', function () {
    $messages = [
        'feat!: remove deprecated API endpoints',
        'fix(auth)!: change token format',
        'refactor!: restructure module boundaries',
    ];

    foreach ($messages as $message) {
        $exitCode = runCommitMsgScript($this->scriptPath, $this->tmpDir, $message);
        expect($exitCode)->toBe(0, "Breaking change message should be accepted: \"{$message}\"");
    }
})->group('property', 'hooks');
