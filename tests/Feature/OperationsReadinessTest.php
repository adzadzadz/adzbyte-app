<?php

namespace Tests\Feature;

use App\Http\Middleware\AddSecurityHeaders;
use App\Notifications\CustomerAccountActivation;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class OperationsReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_health_route_checks_the_booted_application_and_database(): void
    {
        $this->get('/up')->assertOk();

        $this->assertTrue(Event::hasListeners(DiagnosingHealth::class));
        $this->assertTrue(app(Kernel::class)->hasMiddleware(TrustHosts::class));
        $this->assertTrue(app(Kernel::class)->hasMiddleware(AddSecurityHeaders::class));
    }

    public function test_application_responses_include_the_generic_security_header_baseline(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_the_health_route_fails_when_the_database_check_fails(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('select 1')
            ->andThrow(new \RuntimeException('Simulated database outage.'));

        $this->get('/up')->assertServerError();
    }

    public function test_queue_work_is_dispatched_after_commit_with_bounded_activation_retries(): void
    {
        $notification = new CustomerAccountActivation;

        $this->assertTrue(config('queue.connections.database.after_commit'));
        $this->assertSame(3, $notification->tries);
        $this->assertSame(45, $notification->timeout);
        $this->assertSame([10, 30], $notification->backoff());
        $this->assertLessThan(config('queue.connections.database.retry_after'), $notification->timeout);
    }

    public function test_the_queue_health_command_passes_when_no_work_needs_attention(): void
    {
        $this->artisan('operations:queue-health')
            ->expectsOutputToContain('Queue health is good.')
            ->assertSuccessful();
    }

    public function test_the_queue_health_command_fails_for_failed_or_stale_work_without_logging_payloads(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{"private":"not logged"}',
            'exception' => 'Test exception details',
            'failed_at' => now(),
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{"private":"not logged"}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(10)->timestamp,
            'created_at' => now()->subMinutes(10)->timestamp,
        ]);

        Log::spy();

        $this->artisan('operations:queue-health')
            ->expectsOutputToContain('Queue health requires attention.')
            ->assertFailed();

        Log::shouldHaveReceived('critical')
            ->once()
            ->with('Queue health requires attention.', [
                'failed_jobs' => 1,
                'stale_jobs' => 1,
                'stale_after_seconds' => 300,
            ]);
    }

    public function test_queue_pressure_and_terminal_failures_have_structured_log_listeners(): void
    {
        $this->assertTrue(Event::hasListeners(QueueBusy::class));
        $this->assertTrue(Event::hasListeners(JobFailed::class));

        Log::spy();
        Event::dispatch(new QueueBusy('database', 'default', 26));

        $job = Mockery::mock(QueueJob::class);
        $job->shouldReceive('getQueue')->once()->andReturn('default');
        $job->shouldReceive('getJobId')->once()->andReturn('job-123');
        Event::dispatch(new JobFailed('database', $job, new \RuntimeException));

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Queue depth exceeded its configured threshold.', [
                'connection' => 'database',
                'queue' => 'default',
                'size' => 26,
            ]);

        Log::shouldHaveReceived('critical')
            ->once()
            ->with('A queued job exhausted its attempts.', [
                'connection' => 'database',
                'queue' => 'default',
                'job_id' => 'job-123',
                'exception' => \RuntimeException::class,
            ]);
    }

    public function test_queue_monitoring_and_idempotency_pruning_are_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('queue:monitor')
            ->expectsOutputToContain('operations:queue-health')
            ->expectsOutputToContain('api:idempotency:prune')
            ->assertSuccessful();
    }
}
