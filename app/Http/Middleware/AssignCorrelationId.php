<?php

namespace App\Http\Middleware;

use App\Support\CorrelationContext;
use App\Support\FlowdeskLogContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignCorrelationId
{
    public function __construct(
        private readonly CorrelationContext $correlationContext,
        private readonly FlowdeskLogContext $flowdeskLogContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $headerName = (string) config('observability.correlation.header', 'X-Correlation-ID');
        $correlationId = trim((string) $request->headers->get($headerName, ''));

        if ($correlationId === '') {
            $correlationId = (string) Str::uuid();
        }

        $this->correlationContext->setCorrelationId($correlationId);
        $this->correlationContext->mergeContext([
            'request_method' => $request->method(),
            'request_path' => '/'.ltrim($request->path(), '/'),
        ]);

        $this->flowdeskLogContext->share($this->correlationContext->all());

        try {
            $response = $next($request);
        } finally {
            // PHP-FPM tears request state down naturally, but we clear the
            // singleton anyway so tests and long-running workers do not leak it.
            $this->correlationContext->clear();
        }

        $response->headers->set($headerName, $correlationId);

        return $response;
    }
}
