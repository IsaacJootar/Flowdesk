<?php

namespace App\Services\Mono;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies a Nigerian bank account number via Mono's Account Lookup API
 * before Flowdesk initiates any payout.
 *
 * This is the primary defence against failed transfers caused by stale or
 * mistyped vendor bank details.  The MonoPayoutExecutionAdapter calls this
 * automatically — no manual invocation needed in the payout flow.
 *
 * It can also be called independently from vendor management screens to
 * validate bank details at the point of entry.
 *
 * API reference: https://docs.mono.co/api/account-lookup
 * Endpoint:      POST /v1/lookup/account-number
 * Auth header:   mono-sec-key: sk_live_...
 *
 * Return shape:
 *   [
 *     'valid'        => bool,
 *     'account_name' => string|null,   // e.g. "JOHN DOE"
 *     'account_number'=> string|null,
 *     'bank_name'    => string|null,
 *     'bank_code'    => string|null,
 *     'message'      => string,        // human-readable result or error
 *   ]
 */
class MonoAccountVerificationService
{
    /**
     * Verify that an account number is valid and return the account name.
     *
     * @return array{valid:bool,account_name:string|null,account_number:string|null,bank_name:string|null,bank_code:string|null,message:string}
     */
    public function verify(string $accountNumber, string $bankCode): array
    {
        $secretKey = trim((string) config('execution.providers.mono.secret_key', ''));
        $baseUrl   = rtrim((string) config('execution.providers.mono.base_url', 'https://api.withmono.com'), '/');

        if ($secretKey === '') {
            // If Mono is not configured we skip verification rather than blocking the payout.
            // The adapter will still proceed; account validation is best-effort when unconfigured.
            Log::warning('MonoAccountVerificationService: mono secret_key not configured, skipping verification.', [
                'account_number' => $accountNumber,
                'bank_code'      => $bankCode,
            ]);

            return [
                'valid'          => true,
                'account_name'   => null,
                'account_number' => $accountNumber,
                'bank_name'      => null,
                'bank_code'      => $bankCode,
                'message'        => 'Mono not configured — account verification skipped.',
            ];
        }

        $accountNumber = trim($accountNumber);
        $bankCode      = trim($bankCode);

        if ($accountNumber === '' || $bankCode === '') {
            return [
                'valid'          => false,
                'account_name'   => null,
                'account_number' => $accountNumber,
                'bank_name'      => null,
                'bank_code'      => $bankCode,
                'message'        => 'Account number and bank code are required for verification.',
            ];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['mono-sec-key' => $secretKey])
                ->acceptJson()
                ->asJson()
                ->post($baseUrl . '/v1/lookup/account-number', [
                    'account_number' => $accountNumber,
                    'bank_code'      => $bankCode,
                ]);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            $responseStatus = strtolower(trim((string) ($json['status'] ?? '')));
            $accepted = in_array($responseStatus, ['successful', 'success'], true);

            if (! $response->successful() || ! $accepted) {
                return [
                    'valid'          => false,
                    'account_name'   => null,
                    'account_number' => $accountNumber,
                    'bank_name'      => null,
                    'bank_code'      => $bankCode,
                    'message'        => (string) ($json['message'] ?? 'Account number could not be verified.'),
                ];
            }

            $data        = is_array($json['data'] ?? null) ? (array) $json['data'] : [];
            $accountName = trim((string) ($data['name'] ?? $data['account_name'] ?? ''));
            $bankName    = trim((string) ($data['institution'] ?? $data['bank_name'] ?? $data['bank'] ?? ''));

            return [
                'valid'          => true,
                'account_name'   => $accountName !== '' ? $accountName : null,
                'account_number' => $accountNumber,
                'bank_name'      => $bankName !== '' ? $bankName : null,
                'bank_code'      => $bankCode,
                'message'        => 'Account verified successfully.',
            ];
        } catch (Throwable $exception) {
            report($exception);

            // Network/DNS failures are non-fatal — log and allow payout to proceed.
            // We don't want Mono API downtime to block all payouts.
            Log::warning('MonoAccountVerificationService: exception during verification, allowing payout to proceed.', [
                'account_number' => $accountNumber,
                'bank_code'      => $bankCode,
                'error'          => $exception->getMessage(),
            ]);

            return [
                'valid'          => true,
                'account_name'   => null,
                'account_number' => $accountNumber,
                'bank_name'      => null,
                'bank_code'      => $bankCode,
                'message'        => 'Verification service unavailable — proceeding without confirmation.',
            ];
        }
    }
}
