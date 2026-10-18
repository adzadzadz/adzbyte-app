<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\RegisterCustomerController;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PublicRegistrationTransportTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
        $this->seed(RoleSeeder::class);
        config(['app.url' => 'https://app.example.test', 'queue.default' => 'database']);
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_real_database_queue_smtp_receipt_and_activation_complete_without_a_cart(): void
    {
        $messageFile = $this->temporary.'.mail';
        $ready = $this->temporary.'.smtp-ready';
        $smtp = new Process(['node', base_path('tests/Support/smtp-sink.mjs'), $messageFile, $ready], base_path());
        $smtp->start();
        $this->processes[] = $smtp;
        $deadline = microtime(true) + 10;
        while (! is_file($ready) && microtime(true) < $deadline) {
            if ($smtp->isTerminated()) {
                $this->fail($smtp->getErrorOutput());
            }
            usleep(1000);
        }
        $this->assertFileExists($ready);
        $this->postJson('/api/v1/registration', ['name' => '[Untrusted name](https://untrusted.example) <script>unsafe()</script>', 'email' => 'transport@example.test'], ['Origin' => 'https://app.example.test'])->assertStatus(202)->assertJsonPath('data.message', RegisterCustomerController::MESSAGE);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertGuest();
        $worker = new Process([PHP_BINARY, base_path('tests/Support/registration-worker.php'), $this->database, 'queue', '', ''], base_path(), [
            ...$this->catalogWorkerEnvironment(),
            'APP_URL' => 'https://app.example.test', 'APP_KEY' => config('app.key'),
            'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => 'smtp', 'MAIL_URL' => 'null',
            'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => trim(file_get_contents($ready)), 'MAIL_USERNAME' => '', 'MAIL_PASSWORD' => '',
            'MAIL_FROM_ADDRESS' => 'noreply@example.test', 'MAIL_FROM_NAME' => 'Synthetic QA',
        ]);
        $worker->setTimeout(20);
        $this->processes[] = $worker;
        $worker->run();
        $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
        $this->assertSame(0, DB::table('jobs')->count(), $worker->getOutput().$worker->getErrorOutput());
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('carts', 0);
        $user = User::sole();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame(['customer'], $user->getRoleNames()->all());
        $this->assertFileExists($messageFile);
        $mail = quoted_printable_decode(file_get_contents($messageFile));
        $this->assertStringContainsString('transport@example.test', $mail);
        $this->assertStringContainsString('Activate your Adzbyte account', $mail);
        $this->assertStringNotContainsString('untrusted.example', $mail);
        $this->assertStringNotContainsString('<script>', $mail);
        $this->assertSame(1, preg_match('~https://app\.example\.test/activate-account/[^\s<>"\']+~', $mail, $matches));
        $url = html_entity_decode($matches[0]);
        $this->get($url)->assertOk()->assertSee('Activate your account');
        $this->post($url, ['password' => 'Synthetic-transport1!', 'password_confirmation' => 'Synthetic-transport1!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertTrue(Hash::check('Synthetic-transport1!', $user->fresh()->password));
        $this->post($url, ['password' => 'Changed-password2!', 'password_confirmation' => 'Changed-password2!'])->assertForbidden();
    }

    public function test_four_independent_registration_workers_create_one_normalized_customer(): void
    {
        $barrier = $this->temporary.'.registration-go';
        foreach (['concurrent@example.test', 'CONCURRENT@EXAMPLE.TEST', ' concurrent@example.test ', 'Concurrent@example.test'] as $email) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/registration-worker.php'), $this->database, 'race', $email, $barrier], base_path(), $this->catalogWorkerEnvironment());
            $worker->setTimeout(20);
            $worker->start();
            $this->processes[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($barrier.'.*.ready')) < 4 && microtime(true) < $deadline) {
            foreach ($this->processes as $worker) {
                if ($worker->isTerminated()) {
                    $this->fail($worker->getErrorOutput().$worker->getOutput());
                }
            }
            usleep(1000);
        }
        $this->assertCount(4, glob($barrier.'.*.ready'));
        touch($barrier);
        foreach ($this->processes as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('concurrent@example.test', User::sole()->email);
        $this->assertSame(['customer'], User::sole()->getRoleNames()->all());
        $this->assertSame(1, DB::table('model_has_roles')->count());
    }
}
