<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\System\Services\AuditPolicyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ProjectProfileApplyCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'project:profile:apply
        {profile? : Profile key from config/project.php}
        {--write-env : Persist profile env overrides into env file}
        {--env-file=.env : Relative or absolute env file path}
        {--no-audit : Skip applying audit policy overrides}';

    /**
     * @var string
     */
    protected $description = 'Apply a project bootstrap profile (env defaults + audit policy defaults)';

    public function __construct(private readonly AuditPolicyService $auditPolicyService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $profileName = trim((string) ($this->argument('profile') ?: config('project.default_profile', 'base')));

        /** @var array<string, mixed> $profiles */
        $profiles = config('project.profiles', []);
        /** @var array<string, mixed>|null $profile */
        $profile = $profiles[$profileName] ?? null;

        if (! is_array($profile)) {
            $this->error(sprintf('Unknown project profile: %s', $profileName));
            $this->line('Available profiles:');

            foreach (array_keys($profiles) as $availableProfile) {
                $this->line(sprintf('  - %s', $availableProfile));
            }

            return self::FAILURE;
        }

        $description = trim((string) ($profile['description'] ?? ''));
        if ($description !== '') {
            $this->info(sprintf('Applying profile "%s": %s', $profileName, $description));
        } else {
            $this->info(sprintf('Applying profile "%s"', $profileName));
        }

        /** @var array<string, scalar|bool|int|float> $envOverrides */
        $envOverrides = is_array($profile['env'] ?? null) ? $profile['env'] : [];
        if ($envOverrides === []) {
            $this->line('No env overrides configured for this profile.');
        } else {
            $this->line(sprintf('Env overrides: %d key(s).', count($envOverrides)));
        }

        if ((bool) $this->option('write-env')) {
            $envFileOption = trim((string) $this->option('env-file'));
            if ($envFileOption === '') {
                $envFileOption = '.env';
            }

            $envPath = str_starts_with($envFileOption, '/')
                ? $envFileOption
                : base_path($envFileOption);

            $this->writeEnvOverrides($envPath, $envOverrides);
            $this->info(sprintf('Env overrides written to %s', $envPath));
        } else {
            $this->line('Preview mode for env overrides. Use --write-env to persist.');
            foreach ($envOverrides as $key => $value) {
                $this->line(sprintf('  - %s=%s', $key, $this->normalizeEnvValue($value)));
            }
        }

        if (! (bool) $this->option('no-audit')) {
            $this->applyAuditOverrides($profile);
        } else {
            $this->line('Skipping audit overrides due to --no-audit.');
        }

        $this->info('Project profile applied.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function applyAuditOverrides(array $profile): void
    {
        $auditOverrides = $profile['audit_overrides'] ?? null;
        if (! is_array($auditOverrides) || $auditOverrides === []) {
            $this->line('No audit overrides configured for this profile.');

            return;
        }

        if (! Schema::hasTable('audit_policies')) {
            $this->warn('Table "audit_policies" not found. Skipping audit overrides.');

            return;
        }

        $records = [];
        foreach ($auditOverrides as $action => $override) {
            if (! is_array($override)) {
                continue;
            }

            $records[] = [
                'action' => (string) $action,
                'enabled' => (bool) ($override['enabled'] ?? true),
                'samplingRate' => (float) ($override['samplingRate'] ?? 1.0),
                'retentionDays' => (int) ($override['retentionDays'] ?? 90),
            ];
        }

        if ($records === []) {
            $this->line('No valid audit overrides to apply.');

            return;
        }

        $result = $this->auditPolicyService->updatePolicies(null, $records);
        $this->line(sprintf(
            'Audit overrides applied: %d action(s) updated.',
            (int) $result['updated']
        ));
    }

    /**
     * @param  array<string, scalar|bool|int|float>  $overrides
     */
    private function writeEnvOverrides(string $envPath, array $overrides): void
    {
        File::ensureDirectoryExists(dirname($envPath));
        $raw = File::exists($envPath) ? File::get($envPath) : '';
        $lines = preg_split('/\R/', $raw) ?: [];
        $normalizedLines = $lines;

        foreach ($overrides as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $valueText = $this->normalizeEnvValue($value);
            $written = false;

            foreach ($normalizedLines as $index => $line) {
                if (preg_match('/^\s*'.preg_quote($normalizedKey, '/').'\s*=/', $line) === 1) {
                    $normalizedLines[$index] = sprintf('%s=%s', $normalizedKey, $valueText);
                    $written = true;
                    break;
                }
            }

            if (! $written) {
                $normalizedLines[] = sprintf('%s=%s', $normalizedKey, $valueText);
            }
        }

        $output = implode(PHP_EOL, $normalizedLines);
        if ($output !== '' && ! str_ends_with($output, PHP_EOL)) {
            $output .= PHP_EOL;
        }

        File::put($envPath, $output);
    }

    private function normalizeEnvValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return trim((string) $value);
    }
}
