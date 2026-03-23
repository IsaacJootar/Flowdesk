<?php

namespace App\Services\Observability;

use App\Support\CorrelationContext;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorTrackingReporter
{
    public function __construct(
        private readonly CorrelationContext $correlationContext,
    ) {
    }

    public function report(Throwable $exception): void
    {
        if (! $this->shouldReport($exception)) {
            return;
        }

        $payload = $this->payload($exception);
        $channel = (string) config('observability.error_tracking.log_channel', 'error-tracking');

        Log::channel($channel)->critical('Flowdesk exception captured for error tracking.', $payload);

        $webhookUrl = trim((string) config('observability.error_tracking.webhook_url', ''));
        if ($webhookUrl === '') {
            return;
        }

        try {
            $request = Http::asJson()->timeout(
                max(1, (int) config('observability.error_tracking.timeout_seconds', 3))
            );

            $token = trim((string) config('observability.error_tracking.token', ''));
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $request->post($webhookUrl, $payload)->throw();
        } catch (Throwable $reportingException) {
            $logLevel = $reportingException instanceof RequestException ? 'warning' : 'error';

            Log::log($logLevel, 'Flowdesk error tracking webhook delivery failed.', [
                'correlation_id' => $payload['correlation_id'] ?? null,
                'reason' => $reportingException->getMessage(),
            ]);
        }
    }

    private function shouldReport(Throwable $exception): bool
    {
        if (! (bool) config('observability.error_tracking.enabled', true)) {
            return false;
        }

        $allowedEnvironments = (array) config('observability.error_tracking.environments', ['production']);
        if (! in_array(app()->environment(), $allowedEnvironments, true)) {
            return false;
        }

        foreach ((array) config('observability.error_tracking.ignored_exceptions', []) as $className) {
            if (is_string($className) && class_exists($className) && $exception instanceof $className) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Throwable $exception): array
    {
        /** @var Request|null $request */
        $request = app()->bound('request') ? app('request') : null;
        $user = Auth::user();
        $companyId = $user?->company_id ?: ($request?->user()?->company_id);

        return [
            'captured_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'release' => (string) config('app.version', 'unknown'),
            'correlation_id' => $this->correlationContext->correlationId(),
            'company_id' => $companyId ? (int) $companyId : null,
            'user_id' => $user?->id ?: $request?->user()?->id,
            'exception_class' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'request' => $request ? [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route' => optional($request->route())->getName(),
            ] : null,
        ];
    }
}
