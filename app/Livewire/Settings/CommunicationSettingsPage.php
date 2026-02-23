<?php

namespace App\Livewire\Settings;

use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Enums\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CommunicationSettingsPage extends Component
{
    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public bool $in_app_enabled = true;

    public bool $email_enabled = false;

    public bool $sms_enabled = false;

    public bool $email_configured = false;

    public bool $sms_configured = false;

    public string $fallback_primary = CompanyCommunicationSetting::CHANNEL_IN_APP;

    public string $fallback_secondary = CompanyCommunicationSetting::CHANNEL_EMAIL;

    public string $fallback_tertiary = CompanyCommunicationSetting::CHANNEL_SMS;

    public function mount(): void
    {
        $this->authorizeOwner();

        $setting = $this->settingsRecord();
        $this->hydrateFromSetting($setting);
    }

    /**
     * @throws ValidationException
     */
    public function save(): void
    {
        $this->authorizeOwner();
        $this->feedbackError = null;

        $this->validate([
            'in_app_enabled' => ['boolean'],
            'email_enabled' => ['boolean'],
            'sms_enabled' => ['boolean'],
            'email_configured' => ['boolean'],
            'sms_configured' => ['boolean'],
            'fallback_primary' => ['required', Rule::in(CompanyCommunicationSetting::CHANNELS)],
            'fallback_secondary' => ['required', Rule::in(CompanyCommunicationSetting::CHANNELS)],
            'fallback_tertiary' => ['required', Rule::in(CompanyCommunicationSetting::CHANNELS)],
        ]);

        $fallbackOrder = [
            $this->fallback_primary,
            $this->fallback_secondary,
            $this->fallback_tertiary,
        ];

        if (count(array_unique($fallbackOrder)) !== count($fallbackOrder)) {
            throw ValidationException::withMessages([
                'fallback_primary' => 'Fallback order must use each channel only once.',
            ]);
        }

        if (! $this->in_app_enabled && ! $this->email_enabled && ! $this->sms_enabled) {
            throw ValidationException::withMessages([
                'in_app_enabled' => 'At least one communication channel must remain enabled.',
            ]);
        }

        if ($this->email_enabled && ! $this->email_configured) {
            throw ValidationException::withMessages([
                'email_enabled' => 'Email is enabled but not configured yet. Mark it configured or disable it.',
            ]);
        }

        if ($this->sms_enabled && ! $this->sms_configured) {
            throw ValidationException::withMessages([
                'sms_enabled' => 'SMS is enabled but not configured yet. Mark it configured or disable it.',
            ]);
        }

        $setting = $this->settingsRecord();
        $setting->forceFill([
            'in_app_enabled' => (bool) $this->in_app_enabled,
            'email_enabled' => (bool) $this->email_enabled,
            'sms_enabled' => (bool) $this->sms_enabled,
            'email_configured' => (bool) $this->email_configured,
            'sms_configured' => (bool) $this->sms_configured,
            'fallback_order' => $fallbackOrder,
            'updated_by' => auth()->id(),
        ])->save();

        $this->setFeedback('Communication settings updated.');
    }

    public function render(): View
    {
        return view('livewire.settings.communication-settings-page', [
            'channels' => CompanyCommunicationSetting::CHANNELS,
        ])->layout('layouts.app', [
            'title' => 'Communications',
            'subtitle' => 'Configure organization notification channels and fallback order',
        ]);
    }

    private function settingsRecord(): CompanyCommunicationSetting
    {
        return CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => (int) auth()->user()->company_id],
                array_merge(
                    CompanyCommunicationSetting::defaultAttributes(),
                    ['created_by' => auth()->id()]
                )
            );
    }

    private function hydrateFromSetting(CompanyCommunicationSetting $setting): void
    {
        $this->in_app_enabled = (bool) $setting->in_app_enabled;
        $this->email_enabled = (bool) $setting->email_enabled;
        $this->sms_enabled = (bool) $setting->sms_enabled;
        $this->email_configured = (bool) $setting->email_configured;
        $this->sms_configured = (bool) $setting->sms_configured;

        $fallback = $setting->normalizedFallbackOrder();
        $this->fallback_primary = (string) ($fallback[0] ?? CompanyCommunicationSetting::CHANNEL_IN_APP);
        $this->fallback_secondary = (string) ($fallback[1] ?? CompanyCommunicationSetting::CHANNEL_EMAIL);
        $this->fallback_tertiary = (string) ($fallback[2] ?? CompanyCommunicationSetting::CHANNEL_SMS);
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function authorizeOwner(): void
    {
        if (! auth()->check() || auth()->user()->role !== UserRole::Owner->value) {
            throw new AuthorizationException('Only admin (owner) can manage communication settings.');
        }
    }
}

