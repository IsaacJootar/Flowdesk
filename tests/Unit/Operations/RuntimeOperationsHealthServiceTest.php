<?php

namespace Tests\Unit\Operations;

use App\Services\Operations\RuntimeOperationsHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RuntimeOperationsHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_health_service_reports_scheduler_and_queue_metrics(): void
    {
        $service = app(RuntimeOperationsHealthService::class);
        $service->recordSchedulerHeartbeat();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['job' => 'ExampleJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subHour()->timestamp,
            'created_at' => now()->subHour()->timestamp,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['job' => 'ExampleJob']),
            'exception' => 'Example failure',
            'failed_at' => now(),
        ]);

        $summary = $service->summary();

        $this->assertTrue((bool) $summary['available']);
        $this->assertNotNull($summary['scheduler_heartbeat_at']);
        $this->assertSame(1, (int) $summary['failed_jobs_total']);
        $this->assertSame(1, (int) $summary['queued_jobs_total']);
        $this->assertSame(1, (int) $summary['stale_jobs_total']);
    }
}
