<?php

namespace App\Services;

use App\Domains\Requests\Models\SpendRequest;

class RequestDuplicateDetector
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   risk: 'none'|'soft'|'hard',
     *   matches: array<int, array{
     *     id: int,
     *     request_code: string,
     *     title: string,
     *     amount: int,
     *     status: string,
     *     submitted_at: string|null
     *   }>
     * }
     */
    public function analyze(
        int $companyId,
        array $input,
        ?int $excludeRequestId = null,
        int $windowDays = 30
    ): array {
        $amount = (int) ($input['amount'] ?? 0);
        $departmentId = (int) ($input['department_id'] ?? 0);
        $requestedBy = (int) ($input['requested_by'] ?? 0);
        $title = (string) ($input['title'] ?? '');
        $vendorId = $input['vendor_id'] ?? null;

        if ($amount <= 0 || $departmentId <= 0 || $requestedBy <= 0) {
            return ['risk' => 'none', 'matches' => []];
        }

        // "Duplicate" is intentionally strict: same requester + department + amount (+ vendor context) in recent window.
        $query = SpendRequest::query()
            ->where('company_id', $companyId)
            ->where('requested_by', $requestedBy)
            ->where('department_id', $departmentId)
            ->where('amount', $amount)
            ->whereIn('status', ['draft', 'in_review', 'approved', 'returned'])
            ->whereDate('created_at', '>=', now()->subDays(max(1, $windowDays))->toDateString())
            ->when(
                $vendorId === null || $vendorId === '',
                fn ($builder) => $builder->whereNull('vendor_id'),
                fn ($builder) => $builder->where('vendor_id', (int) $vendorId)
            )
            ->when($excludeRequestId, fn ($builder) => $builder->where('id', '!=', $excludeRequestId))
            ->orderByDesc('id')
            ->limit(5);

        $matches = $query->get(['id', 'request_code', 'title', 'amount', 'status', 'submitted_at'])
            ->map(fn (SpendRequest $request): array => [
                'id' => (int) $request->id,
                'request_code' => (string) $request->request_code,
                'title' => (string) $request->title,
                'amount' => (int) $request->amount,
                'status' => (string) $request->status,
                'submitted_at' => optional($request->submitted_at)->toDateTimeString(),
            ])
            ->values()
            ->all();

        if ($matches === []) {
            return ['risk' => 'none', 'matches' => []];
        }

        $normalizedTitle = $this->normalizeText($title);
        $hasExactTitle = false;
        foreach ($matches as $match) {
            if ($normalizedTitle !== '' && $this->normalizeText((string) $match['title']) === $normalizedTitle) {
                $hasExactTitle = true;
                break;
            }
        }

        return [
            'risk' => $hasExactTitle ? 'hard' : 'soft',
            'matches' => $matches,
        ];
    }

    private function normalizeText(string $value): string
    {
        $normalized = strtolower(trim($value));

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }
}
