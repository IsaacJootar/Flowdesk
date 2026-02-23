<?php

namespace App\Domains\Company\Models;

use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyCommunicationSetting extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';

    /**
     * @var array<int, string>
     */
    public const CHANNELS = [
        self::CHANNEL_IN_APP,
        self::CHANNEL_EMAIL,
        self::CHANNEL_SMS,
    ];

    protected $fillable = [
        'company_id',
        'in_app_enabled',
        'email_enabled',
        'sms_enabled',
        'email_configured',
        'sms_configured',
        'fallback_order',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'email_configured' => 'boolean',
            'sms_configured' => 'boolean',
            'fallback_order' => 'array',
            'metadata' => 'array',
        ];
    }

    public static function defaultAttributes(): array
    {
        return [
            'in_app_enabled' => true,
            'email_enabled' => false,
            'sms_enabled' => false,
            'email_configured' => false,
            'sms_configured' => false,
            'fallback_order' => self::CHANNELS,
            'metadata' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function selectableChannels(): array
    {
        $channels = [];

        if ((bool) $this->in_app_enabled) {
            $channels[] = self::CHANNEL_IN_APP;
        }

        if ((bool) $this->email_enabled && (bool) $this->email_configured) {
            $channels[] = self::CHANNEL_EMAIL;
        }

        if ((bool) $this->sms_enabled && (bool) $this->sms_configured) {
            $channels[] = self::CHANNEL_SMS;
        }

        return $channels;
    }

    /**
     * @return array<int, string>
     */
    public function normalizedFallbackOrder(): array
    {
        $current = array_values(array_filter(
            (array) $this->fallback_order,
            fn ($channel): bool => in_array((string) $channel, self::CHANNELS, true)
        ));

        $unique = [];
        foreach ($current as $channel) {
            $channel = (string) $channel;
            if (! in_array($channel, $unique, true)) {
                $unique[] = $channel;
            }
        }

        foreach (self::CHANNELS as $channel) {
            if (! in_array($channel, $unique, true)) {
                $unique[] = $channel;
            }
        }

        return $unique;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

