<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class RequestApprovalSlaService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function dueAtFromNow(array $metadata = [], ?CarbonInterface $from = null): CarbonImmutable
    {
        $fromTime = $from ? CarbonImmutable::instance($from) : now()->toImmutable();
        $hours = $this->stepDueHours($metadata);

        return $fromTime->addHours(max(1, $hours));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function reminderAt(CarbonInterface $dueAt, array $metadata = []): CarbonImmutable
    {
        $hoursBeforeDue = $this->reminderHoursBeforeDue($metadata);

        return CarbonImmutable::instance($dueAt)->subHours(max(0, $hoursBeforeDue));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function escalationAt(CarbonInterface $dueAt, array $metadata = []): CarbonImmutable
    {
        $graceHours = $this->escalationGraceHours($metadata);

        return CarbonImmutable::instance($dueAt)->addHours(max(0, $graceHours));
    }

    /**
     * @return array{step_due_hours:int, reminder_hours_before_due:int, escalation_grace_hours:int}
     */
    public function defaultMetadata(): array
    {
        return [
            'step_due_hours' => $this->stepDueHours([]),
            'reminder_hours_before_due' => $this->reminderHoursBeforeDue([]),
            'escalation_grace_hours' => $this->escalationGraceHours([]),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function stepDueHours(array $metadata): int
    {
        return $this->resolveInt(
            data_get($metadata, 'sla.step_due_hours'),
            (int) config('approvals.request_sla.step_due_hours', 24),
            24
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function reminderHoursBeforeDue(array $metadata): int
    {
        return $this->resolveInt(
            data_get($metadata, 'sla.reminder_hours_before_due'),
            (int) config('approvals.request_sla.reminder_hours_before_due', 6),
            6
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function escalationGraceHours(array $metadata): int
    {
        return $this->resolveInt(
            data_get($metadata, 'sla.escalation_grace_hours'),
            (int) config('approvals.request_sla.escalation_grace_hours', 6),
            6
        );
    }

    private function resolveInt(mixed $candidate, int $fallback, int $default): int
    {
        if (is_numeric($candidate)) {
            return max(0, (int) $candidate);
        }

        if ($fallback >= 0) {
            return $fallback;
        }

        return max(0, $default);
    }
}
