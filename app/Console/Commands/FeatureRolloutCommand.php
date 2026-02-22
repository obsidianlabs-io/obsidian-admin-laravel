<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\System\Actions\FeatureFlag\ForgetFeatureFlagOverrideAction;
use App\Domains\System\Actions\FeatureFlag\PurgeFeatureFlagAction;
use App\Domains\System\Actions\FeatureFlag\SetFeatureFlagOverrideAction;
use App\Domains\System\Services\FeatureFlagService;
use App\DTOs\FeatureFlag\ForgetFeatureFlagOverrideDTO;
use App\DTOs\FeatureFlag\PurgeFeatureFlagDTO;
use App\DTOs\FeatureFlag\SetFeatureFlagOverrideDTO;
use Illuminate\Console\Command;
use Laravel\Pennant\Feature;

class FeatureRolloutCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'feature:rollout
        {feature : Feature key, e.g. menu.permission}
        {state=check : check|on|off|reset}
        {--tenant= : Tenant id for scoped rollout (empty = platform scope)}
        {--roles=* : Role code(s) for scoped rollout}
        {--global : Apply rollout to all scopes}';

    /**
     * @var string
     */
    protected $description = 'Manage Pennant feature rollout with tenant/role scope';

    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly SetFeatureFlagOverrideAction $setFeatureFlagOverrideAction,
        private readonly ForgetFeatureFlagOverrideAction $forgetFeatureFlagOverrideAction,
        private readonly PurgeFeatureFlagAction $purgeFeatureFlagAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $feature = trim((string) $this->argument('feature'));
        $state = strtolower(trim((string) $this->argument('state')));

        if ($feature === '') {
            $this->error('Feature key is required.');

            return self::FAILURE;
        }

        $this->featureFlagService->registerDefinitions();
        if (! $this->featureFlagService->hasFeatureDefinition($feature)) {
            $this->error(sprintf('Feature key "%s" is not defined.', $feature));

            return self::FAILURE;
        }

        if ($this->option('global')) {
            return $this->handleGlobal($feature, $state);
        }

        $tenantOption = trim((string) $this->option('tenant'));
        $tenantId = $tenantOption === '' ? null : max(0, (int) $tenantOption);
        $roles = array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            (array) $this->option('roles')
        ), static fn (string $value): bool => $value !== ''));
        $scope = $this->featureFlagService->scopeKey($tenantId, $roles);

        return $this->handleScoped($feature, $state, $scope);
    }

    private function handleGlobal(string $feature, string $state): int
    {
        if ($state === 'check') {
            $globalOverride = $this->featureFlagService->getStoredOverride(
                $feature,
                $this->featureFlagService->globalScopeKey()
            );
            $platformActive = is_bool($globalOverride)
                ? $globalOverride
                : Feature::for($this->featureFlagService->scopeKey(null, []))->active($feature);
            $this->info(sprintf(
                '[global-check] %s => %s',
                $feature,
                $platformActive ? 'on' : 'off'
            ));

            return self::SUCCESS;
        }

        if ($state === 'on') {
            ($this->setFeatureFlagOverrideAction)(new SetFeatureFlagOverrideDTO(
                key: $feature,
                scope: $this->featureFlagService->globalScopeKey(),
                enabled: true,
            ));
            Feature::flushCache();
            $this->info(sprintf('[global] enabled: %s', $feature));

            return self::SUCCESS;
        }

        if ($state === 'off') {
            ($this->setFeatureFlagOverrideAction)(new SetFeatureFlagOverrideDTO(
                key: $feature,
                scope: $this->featureFlagService->globalScopeKey(),
                enabled: false,
            ));
            Feature::flushCache();
            $this->info(sprintf('[global] disabled: %s', $feature));

            return self::SUCCESS;
        }

        if ($state === 'reset') {
            ($this->purgeFeatureFlagAction)(new PurgeFeatureFlagDTO(
                key: $feature,
            ));
            Feature::flushCache();
            $this->info(sprintf('[global] reset: %s', $feature));

            return self::SUCCESS;
        }

        $this->error('Invalid state. Supported: check|on|off|reset');

        return self::FAILURE;
    }

    private function handleScoped(string $feature, string $state, string $scope): int
    {
        if ($state === 'check') {
            $active = Feature::for($scope)->active($feature);
            $this->info(sprintf('[scope=%s] %s => %s', $scope, $feature, $active ? 'on' : 'off'));

            return self::SUCCESS;
        }

        if ($state === 'on') {
            ($this->setFeatureFlagOverrideAction)(new SetFeatureFlagOverrideDTO(
                key: $feature,
                scope: $scope,
                enabled: true,
            ));
            Feature::flushCache();
            $this->info(sprintf('[scope=%s] enabled: %s', $scope, $feature));

            return self::SUCCESS;
        }

        if ($state === 'off') {
            ($this->setFeatureFlagOverrideAction)(new SetFeatureFlagOverrideDTO(
                key: $feature,
                scope: $scope,
                enabled: false,
            ));
            Feature::flushCache();
            $this->info(sprintf('[scope=%s] disabled: %s', $scope, $feature));

            return self::SUCCESS;
        }

        if ($state === 'reset') {
            ($this->forgetFeatureFlagOverrideAction)(new ForgetFeatureFlagOverrideDTO(
                key: $feature,
                scope: $scope,
            ));
            Feature::flushCache();
            $this->info(sprintf('[scope=%s] reset: %s', $scope, $feature));

            return self::SUCCESS;
        }

        $this->error('Invalid state. Supported: check|on|off|reset');

        return self::FAILURE;
    }
}
