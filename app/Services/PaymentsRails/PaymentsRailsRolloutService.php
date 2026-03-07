<?php

namespace App\Services\PaymentsRails;

use App\Domains\Company\Models\Company;

class PaymentsRailsRolloutService
{
    public function defaultProvider(): string
    {
        $provider = strtolower(trim((string) config('execution.rails_rollout.default_provider', 'manual_ops')));

        return $provider !== '' ? $provider : 'manual_ops';
    }

    public function normalizeProvider(?string $provider): string
    {
        return strtolower(trim((string) $provider));
    }

    /**
     * @return array{allowed:bool,sandbox_mode:bool,stage:string,message:?string}
     */
    public function connectionPolicy(string $providerKey, ?string $companySlug): array
    {
        $provider = $this->normalizeProvider($providerKey);

        if ($provider === '' || $provider === 'null') {
            return [
                'allowed' => false,
                'sandbox_mode' => false,
                'stage' => 'not_set',
                'message' => 'Select a provider before connecting.',
            ];
        }

        if (! $this->isExternalProvider($provider)) {
            return [
                'allowed' => true,
                'sandbox_mode' => false,
                'stage' => 'manual',
                'message' => null,
            ];
        }

        if ($this->allowExternalWithoutPilot()) {
            return [
                'allowed' => true,
                'sandbox_mode' => false,
                'stage' => 'live',
                'message' => null,
            ];
        }

        if ($this->isGoLiveCompanySlug($companySlug)) {
            return [
                'allowed' => true,
                'sandbox_mode' => false,
                'stage' => 'live',
                'message' => null,
            ];
        }

        if ($this->isPilotCompanySlug($companySlug)) {
            return [
                'allowed' => true,
                'sandbox_mode' => true,
                'stage' => 'sandbox',
                'message' => null,
            ];
        }

        return [
            'allowed' => false,
            'sandbox_mode' => true,
            'stage' => 'blocked',
            'message' => 'This provider is in staged rollout. Use manual_ops for now, or ask platform admin to enable pilot/go-live for your organization.',
        ];
    }

    public function companySlugFromId(?int $companyId): ?string
    {
        if (! $companyId) {
            return null;
        }

        $slug = Company::query()->whereKey($companyId)->value('slug');

        return is_string($slug) && trim($slug) !== '' ? strtolower(trim($slug)) : null;
    }

    public function isExternalProvider(string $providerKey): bool
    {
        $provider = $this->normalizeProvider($providerKey);

        return ! in_array($provider, ['', 'null', 'manual_ops'], true);
    }

    public function isPilotCompanySlug(?string $companySlug): bool
    {
        $slug = strtolower(trim((string) $companySlug));
        if ($slug === '') {
            return false;
        }

        return in_array($slug, $this->pilotCompanySlugs(), true);
    }

    public function isGoLiveCompanySlug(?string $companySlug): bool
    {
        $slug = strtolower(trim((string) $companySlug));
        if ($slug === '') {
            return false;
        }

        return in_array($slug, $this->goLiveCompanySlugs(), true);
    }

    /**
     * @return array<int, string>
     */
    private function pilotCompanySlugs(): array
    {
        $configured = array_values(array_unique(array_filter(array_map(
            static fn (mixed $slug): string => strtolower(trim((string) $slug)),
            (array) config('execution.rails_rollout.pilot_company_slugs', [])
        ))));

        // If rollout list is empty, internal platform tenants are considered safe pilot tenants.
        if ($configured !== []) {
            return $configured;
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $slug): string => strtolower(trim((string) $slug)),
            (array) config('platform.internal_company_slugs', [])
        ))));
    }

    /**
     * @return array<int, string>
     */
    private function goLiveCompanySlugs(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $slug): string => strtolower(trim((string) $slug)),
            (array) config('execution.rails_rollout.go_live_company_slugs', [])
        ))));
    }

    private function allowExternalWithoutPilot(): bool
    {
        return (bool) config('execution.rails_rollout.allow_external_provider_without_pilot', false);
    }
}
