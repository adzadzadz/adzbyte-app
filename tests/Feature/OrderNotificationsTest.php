<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Orders\OrderActions;
use App\Actions\Orders\OrderReviewActions;
use App\Enums\OrderCapability;
use App\Jobs\SendOrderNotification;
use App\Models\OrderMessage;
use App\Models\OrderNotificationDelivery;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderReviewUpdated;
use App\Services\Carts\CartPrincipal;
use App\Services\Orders\OrderNotifications;
use Database\Seeders\RoleSeeder;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class OrderNotificationsTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    private array $processes = [];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
        $this->seed(RoleSeeder::class);
        config(['queue.default' => 'database', 'app.url' => 'https://app.example.test']);
        $this->admin = User::factory()->create(['email' => 'admin@example.test']);
        $this->admin->assignRole('administrator');
        $this->admin->givePermissionTo(array_column(OrderCapability::cases(), 'value'));
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $p) {
            if ($p->isRunning()) {
                $p->stop();
            }
        }
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_actual_database_queue_smtp_receipts_skip_notes_and_duplicate_commands(): void
    {
        [$customer, $order] = $this->submitted();
        $a = app(OrderReviewActions::class);
        $uuid = (string) Str::uuid();
        $input = ['expected_revision' => 1, 'message' => 'PUBLIC brief must not be in mail'];
        $order = $a->staff($this->admin, $order->public_id, 'clarify', $input, $uuid);
        $a->staff($this->admin, $order->public_id, 'clarify', $input, $uuid);
        $a->staff($this->admin, $order->public_id, 'note', ['expected_revision' => 2, 'message' => 'PRIVATE INTERNAL secret'], (string) Str::uuid());
        $this->assertDatabaseCount('order_notification_deliveries', 2);
        $this->assertDatabaseCount('jobs', 2);
        $messageFile = $this->temporary.'.review-mail';
        $ready = $this->temporary.'.smtp-ready';
        $sink = new Process(['node', base_path('tests/Support/smtp-sink.mjs'), $messageFile, $ready, 'append'], base_path());
        $sink->start();
        $this->processes[] = $sink;
        $deadline = microtime(true) + 10;
        while (! is_file($ready) && microtime(true) < $deadline) {
            if ($sink->isTerminated()) {
                $this->fail($sink->getErrorOutput());
            }
            usleep(1000);
        }
        $this->assertFileExists($ready);
        $worker = new Process([PHP_BINARY, base_path('tests/Support/registration-worker.php'), $this->database, 'queue', '', ''], base_path(), [...$this->catalogWorkerEnvironment(), 'APP_URL' => 'https://app.example.test', 'APP_KEY' => config('app.key'), 'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => 'smtp', 'MAIL_URL' => 'null', 'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => trim(file_get_contents($ready)), 'MAIL_USERNAME' => '', 'MAIL_PASSWORD' => '', 'MAIL_FROM_ADDRESS' => 'noreply@example.test', 'MAIL_FROM_NAME' => 'Synthetic QA']);
        $worker->setTimeout(20);
        $this->processes[] = $worker;
        $worker->run();
        $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertSame(2, OrderNotificationDelivery::whereNotNull('sent_at')->count());
        $mail = quoted_printable_decode(file_get_contents($messageFile));
        $this->assertSame(2, substr_count($mail, '--MESSAGE--'));
        $this->assertStringContainsString('/admin/orders/'.$order->public_id, $mail);
        $this->assertStringContainsString('/orders/'.$order->public_id, $mail);
        foreach (['PRIVATE INTERNAL', 'PUBLIC brief', 'Synthetic requested scope', 'buyer@example.test'] as $private) {
            $this->assertStringNotContainsString($private, $mail);
        }
        app(OrderNotifications::class)->deliver(OrderNotificationDelivery::first()->id);
        $this->assertSame(2, substr_count(file_get_contents($messageFile), '--MESSAGE--'));
    }

    public function test_delivery_failure_retry_exhaustion_and_explicit_recovery_retain_receipts(): void
    {
        [$customer,$order] = $this->submitted();
        $delivery = OrderNotificationDelivery::sole();
        $notifications = app(OrderNotifications::class);
        Event::listen(NotificationSending::class, fn () => throw new \RuntimeException('Synthetic mail failure'));
        try {
            $notifications->deliver($delivery->id);
            $this->fail('Expected transport failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic mail failure', $e->getMessage());
        }
        $this->assertNull($delivery->fresh()->sent_at);
        Event::forget(NotificationSending::class);
        Notification::fake();
        (new SendOrderNotification($delivery->id))->failed(new \RuntimeException('Synthetic exhaustion'));
        $this->assertNotNull($delivery->fresh()->failed_at);
        $this->assertSame(1, $delivery->fresh()->failure_count);
        $this->assertSame(0, $notifications->recover());
        $this->assertSame(1, $notifications->recover(true));
        $notifications->deliver($delivery->id);
        $notifications->deliver($delivery->id);
        Notification::assertSentToTimes($this->admin, OrderReviewUpdated::class, 1);
        $this->assertNotNull($delivery->fresh()->sent_at);
        $this->assertNotNull($delivery->fresh()->last_failed_at);
        $this->assertSame(1, $delivery->fresh()->failure_count);
    }

    public function test_real_queue_worker_exhausts_three_smtp_failures_before_explicit_recovery(): void
    {
        $this->submitted();
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = substr(strrchr($address, ':'), 1);
        foreach (range(1, 3) as $attempt) {
            // Advance only the disposable queue's eligibility; the real job
            // retains its attempts, configured three tries, and backoff policy.
            DB::table('jobs')->update(['available_at' => time()]);
            $worker = new Process([PHP_BINARY, base_path('tests/Support/registration-worker.php'), $this->database, 'queue', '', ''], base_path(), [
                ...$this->catalogWorkerEnvironment(), 'APP_URL' => 'https://app.example.test', 'APP_KEY' => config('app.key'),
                'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => 'smtp', 'MAIL_URL' => 'null', 'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => $port,
                'MAIL_USERNAME' => '', 'MAIL_PASSWORD' => '', 'MAIL_FROM_ADDRESS' => 'noreply@example.test', 'MAIL_FROM_NAME' => 'Synthetic QA',
            ]);
            $worker->setTimeout(20);
            $this->processes[] = $worker;
            $worker->run();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $this->assertDatabaseCount('jobs', $attempt === 3 ? 0 : 1);
            $this->assertDatabaseCount('failed_jobs', $attempt === 3 ? 1 : 0);
        }
        $delivery = OrderNotificationDelivery::sole();
        $this->assertNull($delivery->sent_at);
        $this->assertNotNull($delivery->failed_at);
        $this->assertSame(1, $delivery->failure_count);
        $this->assertSame(0, app(OrderNotifications::class)->recover());
        $this->assertSame(1, app(OrderNotifications::class)->recover(true));
        $this->assertDatabaseCount('jobs', 1);
        $this->assertNotNull($delivery->fresh()->last_failed_at);
    }

    public function test_revoked_reviewer_is_suppressed_and_transaction_failure_creates_no_delivery(): void
    {
        [$customer,$order] = $this->submitted();
        $delivery = OrderNotificationDelivery::sole();
        Notification::fake();
        $this->admin->revokePermissionTo(OrderCapability::View->value);
        app(OrderNotifications::class)->deliver($delivery->id);
        Notification::assertNothingSent();
        $this->assertNotNull($delivery->fresh()->suppressed_at);
        $this->admin->givePermissionTo(OrderCapability::View->value);
        OrderMessage::creating(fn () => throw new \RuntimeException('Synthetic message failure'));
        try {
            app(OrderReviewActions::class)->staff($this->admin, $order->public_id, 'clarify', ['expected_revision' => 1, 'message' => 'Clarify'], (string) Str::uuid());
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic message failure', $e->getMessage());
        }
        $this->assertDatabaseCount('order_notification_deliveries', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('order_events', 1);
        $this->assertSame('pending', $order->fresh()->approval_status);
    }

    public function test_missing_dispatch_is_recovered_once_within_the_recovery_window(): void
    {
        [$customer,$order] = $this->submitted();
        DB::table('jobs')->delete();
        $delivery = OrderNotificationDelivery::sole();
        $delivery->forceFill(['queued_at' => now()->subMinutes(6)])->save();
        $this->artisan('commerce:recover-order-notifications')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('commerce:recover-order-notifications')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
    }

    private function submitted(): array
    {
        $customer = $this->customerCart();
        $a = app(OrderActions::class);
        $order = $a->submit($customer, ['brief' => ['goal' => 'Synthetic goal', 'scope' => 'Synthetic requested scope'], 'billing' => ['name' => 'Synthetic buyer', 'email' => 'buyer@example.test', 'country' => 'PH'], 'accept_terms' => true, 'fingerprint' => $a->review($customer)['fingerprint']], (string) Str::uuid());

        return [$customer, $order];
    }

    private function customerCart(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $product = Product::factory()->published()->create();
        $product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic concurrent scope']]);
        $product->is_available = true;
        $product->save();
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $carts = app(CartActions::class);
        $cart = $carts->create($guest, (string) Str::uuid());
        $carts->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $preview = $carts->mergePreview($customer, $guest, $cart['public_id']);
        $carts->attach($customer, $guest, $cart['public_id'], (string) Str::uuid(), $preview['fingerprint']);

        return $customer;
    }
}
