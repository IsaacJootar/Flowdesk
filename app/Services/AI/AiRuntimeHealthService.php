<?php

namespace App\Services\AI;

use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Company\Models\Company;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiRuntimeHealthService
{
    private const WINDOW_HOURS = 24;

    private const MAX_WINDOW_ROWS = 3000;

    /**
     * Runtime snapshot for platform operators.
     *
     * @return array{
     *   runtime: array{
     *     provider:string,
     *     base_url:string,
     *     timeout_seconds:int,
     *     primary_model:string,
     *     fallback_model:string,
     *     fast_model:string,
     *     allowed_models:array<int,string>
     *   },
     *   checks: array{
     *     ollama_reachable:bool|null,
     *     primary_model_loaded:bool|null,
     *     loaded_models_count:int|null,
     *     image_ocr_available:bool,
     *     pdf_text_available:bool
     *   },
     *   metrics: array{
     *     window_hours:int,
     *     analyses:int,
     *     model_assisted:int,
     *     deterministic:int,
     *     fallback_rate_percent:float,
     *     sample_truncated:bool
     *   },
     *   last_model_success:?array{
     *     time:string,
     *     company:string,
     *     engine:string,
     *     model:string,
     *     confidence:int
     *   },
     *   last_analysis:?array{
     *     time:string,
     *     company:string,
     *     engine:string,
     *     model:string,
     *     confidence:int,
     *     fallback_used:bool
     *   },
     *   recent_analyses:array<int, array{
     *     time:string,
     *     company:string,
     *     engine:string,
     *     model:string,
     *     confidence:int,
     *     fallback_used:bool
     *   }>
     * }
     */
    public function snapshot(): array
    {
        $profile = app(AiRuntimeProfileService::class)->profile();
        $runtime = (array) ($profile['runtime'] ?? []);
        $models = (array) ($profile['models'] ?? []);

        $provider = strtolower(trim((string) ($runtime['provider'] ?? '')));
        $baseUrl = rtrim((string) ($runtime['base_url'] ?? ''), '/');
        $timeoutSeconds = max(2, min(20, (int) ($runtime['request_timeout_seconds'] ?? 25)));
        $primaryModel = trim((string) ($models['primary'] ?? ''));

        $modelCheck = $this->checkModelRuntime($provider, $baseUrl, $primaryModel, $timeoutSeconds);
        $ocrStatus = app(ExpenseReceiptIntelligenceService::class)->environmentStatus();

        [$windowLogs, $sampleTruncated] = $this->windowLogs();
        $recentLogs = $this->recentLogs();
        $companyNames = $this->companyNamesForLogs($windowLogs->concat($recentLogs));

        $analyses = $windowLogs->count();
        $modelAssisted = $windowLogs->filter(fn (ActivityLog $log): bool => $this->engineForLog($log) === 'model_assisted')->count();
        $deterministic = max(0, $analyses - $modelAssisted);
        $fallbackRatePercent = $analyses > 0 ? round(($deterministic / $analyses) * 100, 1) : 0.0;

        $lastModelSuccess = $this->presentLog(
            $recentLogs->first(fn (ActivityLog $log): bool => $this->engineForLog($log) === 'model_assisted'),
            $companyNames
        );
        $lastAnalysis = $this->presentLog($recentLogs->first(), $companyNames);

        return [
            'runtime' => [
                'provider' => $provider !== '' ? $provider : 'unknown',
                'base_url' => $baseUrl,
                'timeout_seconds' => $timeoutSeconds,
                'primary_model' => $primaryModel,
                'fallback_model' => trim((string) ($models['fallback'] ?? '')),
                'fast_model' => trim((string) ($models['fast'] ?? '')),
                'allowed_models' => array_values(array_map('strval', (array) ($models['allowed'] ?? []))),
            ],
            'checks' => [
                'ollama_reachable' => $modelCheck['ollama_reachable'],
                'primary_model_loaded' => $modelCheck['primary_model_loaded'],
                'loaded_models_count' => $modelCheck['loaded_models_count'],
                'image_ocr_available' => (bool) ($ocrStatus['image_ocr_available'] ?? false),
                'pdf_text_available' => (bool) ($ocrStatus['pdf_text_available'] ?? false),
            ],
            'metrics' => [
                'window_hours' => self::WINDOW_HOURS,
                'analyses' => $analyses,
                'model_assisted' => $modelAssisted,
                'deterministic' => $deterministic,
                'fallback_rate_percent' => $fallbackRatePercent,
                'sample_truncated' => $sampleTruncated,
            ],
            'last_model_success' => $lastModelSuccess,
            'last_analysis' => $lastAnalysis,
            'recent_analyses' => $recentLogs
                ->take(8)
                ->map(fn (ActivityLog $log): ?array => $this->presentLog($log, $companyNames))
                ->filter(fn (?array $row): bool => is_array($row))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{0:Collection<int,ActivityLog>,1:bool}
     */
    private function windowLogs(): array
    {
        $since = Carbon::now()->subHours(self::WINDOW_HOURS);

        $rows = $this->baseAnalysisQuery()
            ->where('created_at', '>=', $since)
            ->latest('created_at')
            ->latest('id')
            ->limit(self::MAX_WINDOW_ROWS)
            ->get(['id', 'company_id', 'metadata', 'created_at']);

        return [$rows, $rows->count() >= self::MAX_WINDOW_ROWS];
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    private function recentLogs(): Collection
    {
        return $this->baseAnalysisQuery()
            ->latest('created_at')
            ->latest('id')
            ->limit(60)
            ->get(['id', 'company_id', 'metadata', 'created_at']);
    }

    private function baseAnalysisQuery()
    {
        // Platform monitor must bypass tenant scope to inspect cross-tenant reliability.
        return ActivityLog::query()
            ->withoutGlobalScopes()
            ->where('action', 'expense.receipt.analysis.generated');
    }

    /**
     * @param  Collection<int, ActivityLog>  $logs
     * @return array<int, string>
     */
    private function companyNamesForLogs(Collection $logs): array
    {
        $companyIds = $logs
            ->pluck('company_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($companyIds === []) {
            return [];
        }

        return Company::query()
            ->whereIn('id', $companyIds)
            ->pluck('name', 'id')
            ->map(static fn ($name): string => (string) $name)
            ->all();
    }

    /**
     * @param  array<int, string>  $companyNames
     * @return array{
     *   time:string,
     *   company:string,
     *   engine:string,
     *   model:string,
     *   confidence:int,
     *   fallback_used:bool
     * }|null
     */
    private function presentLog(?ActivityLog $log, array $companyNames): ?array
    {
        if (! $log) {
            return null;
        }

        $metadata = (array) ($log->metadata ?? []);
        $companyId = (int) $log->company_id;
        $createdAt = $log->created_at instanceof CarbonInterface
            ? $log->created_at
            : Carbon::parse((string) $log->created_at);

        return [
            'time' => (string) $createdAt->format('M d, Y H:i'),
            'company' => $companyNames[$companyId] ?? ('Company #'.$companyId),
            'engine' => $this->engineForLog($log),
            'model' => trim((string) ($metadata['ai_model'] ?? '')),
            'confidence' => max(0, min(100, (int) ($metadata['confidence'] ?? 0))),
            'fallback_used' => (bool) ($metadata['fallback_used'] ?? false),
        ];
    }

    private function engineForLog(ActivityLog $log): string
    {
        $metadata = (array) ($log->metadata ?? []);
        $engine = trim((string) ($metadata['engine'] ?? 'deterministic'));

        return $engine !== '' ? $engine : 'deterministic';
    }

    /**
     * @return array{ollama_reachable:bool|null,primary_model_loaded:bool|null,loaded_models_count:int|null}
     */
    private function checkModelRuntime(string $provider, string $baseUrl, string $primaryModel, int $timeoutSeconds): array
    {
        if ($provider !== 'ollama') {
            return [
                'ollama_reachable' => null,
                'primary_model_loaded' => null,
                'loaded_models_count' => null,
            ];
        }

        if ($baseUrl === '') {
            return [
                'ollama_reachable' => false,
                'primary_model_loaded' => false,
                'loaded_models_count' => 0,
            ];
        }

        try {
            $response = Http::timeout($timeoutSeconds)
                ->acceptJson()
                ->get($baseUrl.'/api/tags');
        } catch (Throwable) {
            return [
                'ollama_reachable' => false,
                'primary_model_loaded' => false,
                'loaded_models_count' => 0,
            ];
        }

        if (! $response->successful()) {
            return [
                'ollama_reachable' => false,
                'primary_model_loaded' => false,
                'loaded_models_count' => 0,
            ];
        }

        $models = collect((array) $response->json('models', []))
            ->map(function ($row): string {
                if (is_array($row)) {
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($name !== '') {
                        return $name;
                    }

                    return trim((string) ($row['model'] ?? ''));
                }

                return trim((string) $row);
            })
            ->filter(fn (string $name): bool => $name !== '')
            ->values();

        return [
            'ollama_reachable' => true,
            'primary_model_loaded' => $primaryModel !== '' ? $models->contains($primaryModel) : false,
            'loaded_models_count' => $models->count(),
        ];
    }
}

