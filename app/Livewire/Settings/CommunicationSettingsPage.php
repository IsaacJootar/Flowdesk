<?php

namespace App\Livewire\Settings;

use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Enums\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Communications')]
/**
 * CommunicationSettingsPage Livewire Component
 *
 * Manages company-wide communication channel settings for tenant administrators.
 * Controls which communication channels (in-app, email, SMS) are enabled and configured,
 * and defines the fallback order for message delivery when primary channels fail.
 *
 * Features:
 * - Enable/disable communication channels
 * - Mark channels as configured
 * - Set fallback priority order for message delivery
 * - Validation to ensure at least one channel remains enabled
 * - Validation to prevent duplicate channels in fallback order
 */
class CommunicationSettingsPage extends Component
{
    // Feedback properties for user notifications
    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;
    public int $feedbackKey = 0;

    // Channel enablement flags
    public bool $in_app_enabled = true;
    public bool $email_enabled = false;
    public bool $sms_enabled = false;

    // Channel configuration status
    public bool $email_configured = false;
    public bool $sms_configured = false;

    // Fallback order for message delivery (primary, secondary, tertiary)
    public string $fallback_primary = CompanyCommunicationSetting::CHANNEL_IN_APP;
    public string $fallback_secondary = CompanyCommunicationSetting::CHANNEL_EMAIL;
    public string $fallback_tertiary = CompanyCommunicationSetting::CHANNEL_SMS;

    /**
     * Initialize component with current communication settings
     */
    public function mount(): void
    {
        $this->authorizeOwner();

        $setting = $this->settingsRecord();
        $this->hydrateFromSetting($setting);
    }

    /**
     * Save communication settings with validation
     *
     * Validates channel enablement, configuration status, and fallback order uniqueness.
     * Ensures at least one channel remains enabled and configured channels are properly set.
     *
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

        // Ensure no duplicate channels in fallback order
        if (count(array_unique($fallbackOrder)) !== count($fallbackOrder)) {
            throw ValidationException::withMessages([
                'fallback_primary' => 'Fallback order must use each channel only once.',
            ]);
        }

        // Ensure at least one channel is enabled
        if (! $this->in_app_enabled && ! $this->email_enabled && ! $this->sms_enabled) {
            throw ValidationException::withMessages([
                'in_app_enabled' => 'At least one communication channel must remain enabled.',
            ]);
        }

        // Ensure enabled channels are configured
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
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->setFeedback('Communication settings updated.');
    }

    /**
     * Render the communication settings page
     */
    public function render(): View
    {
        return view('livewire.settings.communication-settings-page', [
            'channels' => CompanyCommunicationSetting::CHANNELS,
        ]);
    }

    /**
     * Get or create the communication settings record for the current company
     */
    private function settingsRecord(): CompanyCommunicationSetting
    {
        return CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => (int) \Illuminate\Support\Facades\Auth::user()->company_id],
                array_merge(
                    CompanyCommunicationSetting::defaultAttributes(),
                    ['created_by' => \Illuminate\Support\Facades\Auth::id()]
                )
            );
    }

    /**
     * Populate form properties from the settings record
     */
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

    /**
     * Set a success feedback message
     */
    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    /**
     * Authorize that the current user is an owner/admin
     *
     * @throws AuthorizationException
     */
    private function authorizeOwner(): void
    {
        if (! \Illuminate\Support\Facades\Auth::check() || \Illuminate\Support\Facades\Auth::user()->role !== UserRole::Owner->value) {
            throw new AuthorizationException('Only admin (owner) can manage communication settings.');
        }
    }
}


