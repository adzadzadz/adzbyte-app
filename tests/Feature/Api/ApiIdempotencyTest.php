<?php

namespace Tests\Feature\Api;

use App\Enums\ApiIdempotencyStatus;
use App\Models\ApiIdempotencyKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private static int $executions = 0;

    protected function setUp(): void
    {
        parent::setUp();

        self::$executions = 0;

        Route::middleware('idempotent')
            ->post('/api/v1/testing/idempotency', function (Request $request) {
                self::$executions++;

                if ($request->boolean('throw') && self::$executions === 1) {
                    throw new \RuntimeException('Temporary test exception.');
                }

                if ($request->boolean('fail') && self::$executions === 1) {
                    return response()->json(['error' => 'temporary'], Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                return response()->json([
                    'data' => [
                        'execution' => self::$executions,
                        'payload' => $request->input('payload'),
                    ],
                ], Response::HTTP_CREATED, [
                    'Location' => '/api/v1/testing/resources/'.self::$executions,
                ]);
            })
            ->name('testing.api.idempotency');
    }

    public function test_an_authenticated_mutation_requires_a_valid_idempotency_key(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/testing/idempotency', ['payload' => 'one'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'idempotency_key_required');

        $this->withHeader('Idempotency-Key', 'short')
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'one'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'invalid_idempotency_key');

        $this->assertSame(0, self::$executions);
        $this->assertDatabaseCount('api_idempotency_keys', 0);
    }

    public function test_a_completed_response_is_replayed_without_executing_the_mutation_again(): void
    {
        $this->actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => 'checkout-attempt-0001'];

        $first = $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => ['b' => 2, 'a' => 1]])
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertHeader('Location', '/api/v1/testing/resources/1');

        $second = $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => ['a' => 1, 'b' => 2]])
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertHeader('Location', '/api/v1/testing/resources/1');

        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame(1, self::$executions);
        $this->assertDatabaseCount('api_idempotency_keys', 1);
        $this->assertSame(
            ApiIdempotencyStatus::Completed,
            ApiIdempotencyKey::query()->sole()->status,
        );
    }

    public function test_reusing_a_key_for_a_different_request_returns_a_conflict(): void
    {
        $this->actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => 'checkout-attempt-0002'];

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'one'])
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'two'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'idempotency_key_reused');

        $this->assertSame(1, self::$executions);
    }

    public function test_keys_are_scoped_to_the_authenticated_principal(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $headers = ['Idempotency-Key' => 'shared-client-key'];

        $this->actingAs($firstUser)
            ->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same'])
            ->assertJsonPath('data.execution', 1);

        $this->actingAs($secondUser)
            ->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same'])
            ->assertJsonPath('data.execution', 2);

        $this->assertDatabaseCount('api_idempotency_keys', 2);
    }

    public function test_an_active_pending_request_returns_a_retryable_conflict(): void
    {
        $this->actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => 'pending-client-key'];

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same'])
            ->assertCreated();

        ApiIdempotencyKey::query()->sole()->update([
            'status' => ApiIdempotencyStatus::Pending,
            'response_status' => null,
            'response_headers' => null,
            'response_body' => null,
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same'])
            ->assertConflict()
            ->assertHeader('Retry-After', '1')
            ->assertJsonPath('error.code', 'idempotency_in_progress');

        $this->assertSame(1, self::$executions);
    }

    public function test_expired_records_allow_the_mutation_to_run_again(): void
    {
        $this->actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => 'expired-client-key'];

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same'])
            ->assertJsonPath('data.execution', 1);

        ApiIdempotencyKey::query()->sole()->update(['expires_at' => now()->subSecond()]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same'])
            ->assertJsonPath('data.execution', 2)
            ->assertHeader('Idempotency-Replayed', 'false');

        $this->assertDatabaseCount('api_idempotency_keys', 1);
    }

    public function test_stale_pending_records_allow_the_mutation_to_run_again(): void
    {
        $this->actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => 'stale-pending-key'];

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same'])
            ->assertJsonPath('data.execution', 1);

        $record = ApiIdempotencyKey::query()->sole();
        $record->timestamps = false;
        $record->forceFill([
            'status' => ApiIdempotencyStatus::Pending,
            'updated_at' => now()->subMinutes(20),
        ])->save();

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same'])
            ->assertJsonPath('data.execution', 2)
            ->assertHeader('Idempotency-Replayed', 'false');

        $this->assertDatabaseCount('api_idempotency_keys', 1);
    }

    public function test_server_failures_are_not_replayed(): void
    {
        $this->actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => 'retry-after-failure'];

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same', 'fail' => true])
            ->assertInternalServerError();

        $this->assertDatabaseCount('api_idempotency_keys', 0);

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same', 'fail' => true])
            ->assertCreated()
            ->assertJsonPath('data.execution', 2);
    }

    public function test_exceptions_clear_the_pending_record_before_the_error_contract_is_rendered(): void
    {
        $this->actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => 'retry-after-exception'];

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same', 'throw' => true])
            ->assertInternalServerError()
            ->assertJsonPath('error.code', 'server_error');

        $this->assertDatabaseCount('api_idempotency_keys', 0);

        $this->withHeaders($headers)
            ->postJson('/api/v1/testing/idempotency', ['payload' => 'same', 'throw' => true])
            ->assertCreated()
            ->assertJsonPath('data.execution', 2);
    }

    public function test_the_prune_command_deletes_only_expired_records(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['active-record-key', 'expired-record-key'] as $key) {
            $this->withHeader('Idempotency-Key', $key)
                ->postJson('/api/v1/testing/idempotency', ['payload' => $key])
                ->assertCreated();
        }

        ApiIdempotencyKey::query()
            ->where('key_hash', hash('sha256', 'expired-record-key'))
            ->update(['expires_at' => now()->subSecond()]);

        $this->artisan('api:idempotency:prune')
            ->expectsOutputToContain('Pruned 1 expired API idempotency record(s).')
            ->assertSuccessful();

        $this->assertDatabaseCount('api_idempotency_keys', 1);
        $this->assertDatabaseHas('api_idempotency_keys', [
            'key_hash' => hash('sha256', 'active-record-key'),
        ]);
    }
}
