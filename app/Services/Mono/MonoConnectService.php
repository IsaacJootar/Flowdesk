<?php

namespace App\Services\Mono;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client for the Mono Connect API.
 *
 * Mono Connect gives Flowdesk read access to a linked bank account —
 * transactions, balance, and account metadata.  This powers:
 *
 *   1. Live bank statement feed  → replaces manual CSV import in Treasury
 *   2. Real bank balance         → powers Treasury cash position dashboard
 *   3. Account metadata          → enriches the MonoConnectAccount record
 *
 * A MonoConnectAccount record (created after the tenant links their bank
 * via the Mono Connect widget) stores the `mono_account_id` which all
 * requests in this service require.
 *
 * API reference: https://docs.mono.co/api/connect
 * Base URL:      https://api.withmono.com
 * Auth header:   mono-sec-key: sk_live_...
 *
 * Mono Connect amounts are in KOBO (minor units).
 * The ImportMonoStatementService converts kobo → integer kobo when writing
 * BankStatementLine records (consistent with how the CSV importer works).
 */
class MonoConnectService
{
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = trim((string) config('execution.providers.mono.secret_key', ''));
        $this->baseUrl   = rtrim((string) config('execution.providers.mono.base_url', 'https://api.withmono.com'), '/');
    }

    /**
     * Fetch account metadata and current balance for a linked account.
     *
     * Returns:
     *   [
     *     'ok'              => bool,
     *     'account_id'      => string,
     *     'account_name'    => string,
     *     'account_number'  => string,
     *     'institution'     => string,
     *     'currency'        => string,       // e.g. 'NGN'
     *     'balance_kobo'    => int,           // balance in kobo
     *     'balance_synced_at' => Carbon|null,
     *     'message'         => string,
     *   ]
     *
     * @return array<string,mixed>
     */
    public function fetchAccountInfo(string $monoAccountId): array
    {
        if ($this->secretKey === '') {
            return $this->errorResult($monoAccountId, 'Mono Connect is not configured. Missing secret key.');
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['mono-sec-key' => $this->secretKey])
                ->acceptJson()
                ->get($this->baseUrl . '/v2/accounts/' . rawurlencode($monoAccountId));

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            if (! $response->successful()) {
                return $this->errorResult(
                    $monoAccountId,
                    (string) ($json['message'] ?? 'Failed to fetch account info from Mono Connect.')
                );
            }

            $data    = is_array($json['data'] ?? null) ? (array) $json['data'] : $json;
            $account = is_array($data['account'] ?? null) ? (array) $data['account'] : $data;

            return [
                'ok'               => true,
                'account_id'       => $monoAccountId,
                'account_name'     => (string) ($account['name']        ?? ''),
                'account_number'   => (string) ($account['accountNumber'] ?? $account['account_number'] ?? ''),
                'institution'      => (string) ($account['institution']['name'] ?? $account['institution'] ?? ''),
                'currency'         => strtoupper((string) ($account['currency'] ?? 'NGN')),
                // Mono returns balance in kobo
                'balance_kobo'     => (int) ($account['balance'] ?? $data['balance'] ?? 0),
                'balance_synced_at'=> Carbon::now(),
                'message'          => 'Account info fetched successfully.',
                'raw'              => $json,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResult($monoAccountId, 'Exception fetching account info: ' . $exception->getMessage());
        }
    }

    /**
     * Fetch transactions for a date range from a linked Mono Connect account.
     *
     * Results are raw Mono transaction objects.  The ImportMonoStatementService
     * is responsible for normalizing and persisting them as BankStatementLine rows.
     *
     * @param  string      $monoAccountId  Mono account ID from MonoConnectAccount.mono_account_id
     * @param  Carbon      $from           Start of the date range (inclusive)
     * @param  Carbon      $to             End of the date range (inclusive)
     * @param  int         $limit          Max transactions to return (Mono default: 10, max: 100)
     * @return array{ok:bool,transactions:array<int,array<string,mixed>>,total:int,message:string}
     */
    public function fetchTransactions(string $monoAccountId, Carbon $from, Carbon $to, int $limit = 100): array
    {
        if ($this->secretKey === '') {
            return ['ok' => false, 'transactions' => [], 'total' => 0, 'message' => 'Mono Connect is not configured.'];
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders(['mono-sec-key' => $this->secretKey])
                ->acceptJson()
                ->get($this->baseUrl . '/v2/accounts/' . rawurlencode($monoAccountId) . '/transactions', [
                    'start'    => $from->format('d-m-Y'),    // Mono date format: dd-mm-yyyy
                    'end'      => $to->format('d-m-Y'),
                    'limit'    => min($limit, 100),
                    'paginate' => 'false',
                ]);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            if (! $response->successful()) {
                return [
                    'ok'           => false,
                    'transactions' => [],
                    'total'        => 0,
                    'message'      => (string) ($json['message'] ?? 'Failed to fetch transactions from Mono Connect.'),
                ];
            }

            $data = is_array($json['data'] ?? null) ? (array) $json['data'] : [];
            $txns = [];

            // Mono may return paged or direct array
            if (isset($data['data']) && is_array($data['data'])) {
                $txns = (array) $data['data'];
            } elseif (array_is_list($data)) {
                $txns = $data;
            }

            return [
                'ok'           => true,
                'transactions' => $txns,
                'total'        => count($txns),
                'message'      => sprintf('Fetched %d transaction(s) from Mono Connect.', count($txns)),
                'raw'          => $json,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok'           => false,
                'transactions' => [],
                'total'        => 0,
                'message'      => 'Exception fetching transactions: ' . $exception->getMessage(),
            ];
        }
    }

    /**
     * Exchange a Mono Connect auth code (from the Connect widget) for an account ID.
     *
     * This is the first step after the tenant completes the Mono Connect widget flow.
     * The `code` from the widget's onSuccess callback must be exchanged within 30 minutes.
     *
     * @return array{ok:bool,account_id:string|null,message:string}
     */
    public function exchangeAuthCode(string $code): array
    {
        if ($this->secretKey === '') {
            return ['ok' => false, 'account_id' => null, 'message' => 'Mono Connect is not configured.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['mono-sec-key' => $this->secretKey])
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl . '/v2/accounts/auth', [
                    'code' => trim($code),
                ]);

            $json = $response->json();
            if (! is_array($json)) {
                $json = ['raw_body' => $response->body()];
            }

            if (! $response->successful()) {
                return [
                    'ok'         => false,
                    'account_id' => null,
                    'message'    => (string) ($json['message'] ?? 'Failed to exchange Mono auth code.'),
                ];
            }

            $data      = is_array($json['data'] ?? null) ? (array) $json['data'] : $json;
            $accountId = trim((string) ($data['id'] ?? $data['account_id'] ?? ''));

            if ($accountId === '') {
                return ['ok' => false, 'account_id' => null, 'message' => 'Mono did not return an account ID.'];
            }

            return [
                'ok'         => true,
                'account_id' => $accountId,
                'message'    => 'Auth code exchanged successfully.',
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'account_id' => null, 'message' => 'Exception exchanging Mono auth code: ' . $exception->getMessage()];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function errorResult(string $accountId, string $message): array
    {
        return [
            'ok'               => false,
            'account_id'       => $accountId,
            'account_name'     => '',
            'account_number'   => '',
            'institution'      => '',
            'currency'         => '',
            'balance_kobo'     => 0,
            'balance_synced_at'=> null,
            'message'          => $message,
        ];
    }
}
