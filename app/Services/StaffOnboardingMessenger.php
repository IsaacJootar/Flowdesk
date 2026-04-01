<?php

namespace App\Services;

use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Models\User;
use App\Mail\StaffWelcomeMail;
use App\Services\RequestCommunication\DeliveryResult;
use App\Services\RequestCommunication\Sms\SmsProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class StaffOnboardingMessenger
{
    public function __construct(
        private readonly SmsProvider $smsProvider,
        private readonly ActivityLogger $activityLogger,
        private readonly TransactionalEmailSender $transactionalEmailSender,
    ) {
    }

    /**
     * @param  array<int, string>|null  $selectedChannels
     */
    public function sendWelcomeCredentials(
        User $actor,
        User $staff,
        string $temporaryPassword,
        ?array $selectedChannels = null
    ): void
    {
        $companyId = (int) $actor->company_id;
        $companyName = (string) ($actor->company?->name ?? 'Flowdesk');

        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                array_merge(
                    CompanyCommunicationSetting::defaultAttributes(),
                    [
                        'created_by' => (int) $actor->id,
                        'updated_by' => (int) $actor->id,
                    ]
                )
            );

        $results = [];
        $selectableChannels = $settings->selectableChannels();
        $selected = $selectedChannels === null
            ? $selectableChannels
            : array_values(array_unique(array_intersect(
                array_map('strval', $selectedChannels),
                [
                    CompanyCommunicationSetting::CHANNEL_EMAIL,
                    CompanyCommunicationSetting::CHANNEL_SMS,
                ]
            )));

        $emailSelected = in_array(CompanyCommunicationSetting::CHANNEL_EMAIL, $selected, true);
        $smsSelected = in_array(CompanyCommunicationSetting::CHANNEL_SMS, $selected, true);

        if (! $emailSelected && ! $smsSelected) {
            $results['email'] = DeliveryResult::skipped('Email channel not selected for onboarding.');
            $results['sms'] = DeliveryResult::skipped('SMS channel not selected for onboarding.');
        } elseif ($emailSelected && in_array(CompanyCommunicationSetting::CHANNEL_EMAIL, $selectableChannels, true)) {
            $results['email'] = $this->sendEmail($staff, $companyName, $temporaryPassword);
        } else {
            $results['email'] = DeliveryResult::skipped(
                $emailSelected
                    ? 'Email channel disabled or not configured.'
                    : 'Email channel not selected for onboarding.'
            );
        }

        if ($smsSelected && in_array(CompanyCommunicationSetting::CHANNEL_SMS, $selectableChannels, true)) {
            $results['sms'] = $this->sendSms($staff, $companyName, $temporaryPassword);
        } else {
            $results['sms'] = DeliveryResult::skipped(
                $smsSelected
                    ? 'SMS channel disabled or not configured.'
                    : 'SMS channel not selected for onboarding.'
            );
        }

        $this->activityLogger->log(
            action: 'identity.user.onboarding.notified',
            entityType: User::class,
            entityId: (int) $staff->id,
            metadata: [
                'staff_email' => (string) ($staff->email ?? ''),
                'staff_phone' => (string) ($staff->phone ?? ''),
                'selected_channels' => Arr::values($selected),
                'results' => array_map(
                    fn (DeliveryResult $result): array => [
                        'status' => $result->status,
                        'message' => $result->message,
                        'metadata' => $result->metadata,
                    ],
                    $results
                ),
            ],
            companyId: $companyId,
            userId: (int) $actor->id,
        );
    }

    private function sendEmail(User $staff, string $companyName, string $temporaryPassword): DeliveryResult
    {
        $email = trim((string) ($staff->email ?? ''));
        if ($email === '') {
            return DeliveryResult::failed('Email delivery failed: staff email is missing.');
        }

        $deliver = function () use ($staff, $companyName, $temporaryPassword, $email): DeliveryResult {
            try {
                $deliveryMetadata = $this->transactionalEmailSender->sendMailable(
                    $email,
                    new StaffWelcomeMail($staff, $temporaryPassword, $companyName),
                    [
                        'idempotency_key' => 'staff-onboarding-'.$staff->id,
                        'tags' => ['onboarding', 'staff'],
                    ]
                );
            } catch (Throwable $exception) {
                report($exception);

                return DeliveryResult::failed('Email delivery failed while sending.', [
                    'error' => $exception->getMessage(),
                ]);
            }

            return DeliveryResult::sent('Email onboarding message delivered.', $deliveryMetadata);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(function () use ($deliver): void {
                $deliver();
            });

            return DeliveryResult::sent('Email onboarding queued after commit.', [
                'deferred' => true,
            ]);
        }

        return $deliver();
    }

    private function sendSms(User $staff, string $companyName, string $temporaryPassword): DeliveryResult
    {
        $phone = trim((string) ($staff->phone ?? ''));
        if ($phone === '') {
            return DeliveryResult::failed('SMS delivery failed: staff phone is missing.');
        }

        $message = $this->buildSmsBody($staff, $companyName, $temporaryPassword);

        $send = function () use ($phone, $message, $staff): DeliveryResult {
            return $this->smsProvider->send($phone, $message, [
                'purpose' => 'staff_onboarding',
                'staff_user_id' => (int) $staff->id,
                'company_id' => (int) $staff->company_id,
            ]);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(function () use ($send): void {
                $send();
            });

            return DeliveryResult::sent('SMS onboarding queued after commit.', [
                'deferred' => true,
            ]);
        }

        return $send();
    }

    private function buildSmsBody(User $staff, string $companyName, string $temporaryPassword): string
    {
        $username = trim((string) ($staff->email ?? ''));

        return "Welcome to {$companyName} on Flowdesk. Username: {$username}. Temp password: {$temporaryPassword}. Change password after first login.";
    }
}
