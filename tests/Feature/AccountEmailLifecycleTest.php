<?php

namespace Tests\Feature;

use App\Actions\Auth\SendPanelEmailVerification;
use App\Filament\Auth\EmailVerificationPrompt;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Pages\EditProfile;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class AccountEmailLifecycleTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
        $this->seed(RoleSeeder::class);
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
        config(['queue.default' => 'database', 'queue.connections.database.after_commit' => true]);
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

    public static function panels(): array
    {
        return [['admin', 'super_admin', '/admin'], ['account', 'customer', '']];
    }

    #[DataProvider('panels')]
    public function test_prompt_queues_first_verification_and_refresh_does_not_duplicate(string $panel, string $role, string $prefix): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole($role);
        Filament::setCurrentPanel(Filament::getPanel($panel));
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])->call('authenticate')->assertHasNoFormErrors();
        $this->get($prefix ?: '/')->assertRedirect($prefix.'/email-verification/prompt');
        $this->get($prefix.'/email-verification/prompt')->assertOk()->assertSee('has been queued');
        $this->assertDatabaseCount('jobs', 1);
        $this->get($prefix.'/email-verification/prompt')->assertOk();
        $this->assertDatabaseCount('jobs', 1);
    }

    #[DataProvider('panels')]
    public function test_wrong_panel_reset_request_preserves_existing_valid_token(string $panel, string $role, string $prefix): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $token = Password::broker()->createToken($user);
        $this->travel(61)->seconds();
        $wrong = Filament::getPanel($panel === 'admin' ? 'account' : 'admin');
        Filament::setCurrentPanel($wrong);
        Livewire::test($wrong->getRequestPasswordResetRouteAction())
            ->fillForm(['email' => $user->email])->call('request')->assertHasNoFormErrors();
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        $this->assertDatabaseCount('jobs', 0);
    }

    #[DataProvider('panels')]
    public function test_queued_verification_email_survives_worker_and_signed_link_security(string $panel, string $role, string $prefix): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole($role);
        $this->actingAs($user)->get($prefix.'/email-verification/prompt')->assertOk();
        $mail = $this->deliverEmails();
        $url = $this->mailUrl($mail, $prefix.'/email-verification/verify/');
        $this->assertStringContainsString($user->email, $mail);
        $this->get($url.'&tampered=1')->assertForbidden();
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
        $this->travelBack();
        $other = User::factory()->unverified()->create();
        $other->assignRole($role);
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user)->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_shared_cooldown_covers_refresh_resend_and_other_panel(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole(['customer', 'super_admin']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $prompt = Livewire::test(EmailVerificationPrompt::class)->assertSet('deliveryStatus', 'queued');
        $prompt->callAction('resendNotification')->assertSet('deliveryStatus', 'recent');
        $this->assertDatabaseCount('jobs', 1);
        Filament::setCurrentPanel(Filament::getPanel('account'));
        Livewire::test(EmailVerificationPrompt::class)->assertSet('deliveryStatus', 'recent');
        $this->assertDatabaseCount('jobs', 1);
        $this->travel(61)->seconds();
        Livewire::test(EmailVerificationPrompt::class)->assertSet('deliveryStatus', 'queued');
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_failed_enqueue_is_honest_and_can_retry_without_cooldown(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Event::listen(JobQueueing::class, fn () => throw new \RuntimeException('Synthetic notification failure'));
        $prompt = Livewire::test(EmailVerificationPrompt::class)->assertSet('deliveryStatus', 'failed')->assertSee('could not queue');
        $this->assertDatabaseCount('jobs', 0);
        Event::forget(JobQueueing::class);
        $prompt->callAction('resendNotification')->assertSet('deliveryStatus', 'queued');
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_verified_or_wrong_role_user_does_not_queue_verification(): void
    {
        $verified = User::factory()->create();
        $verified->assignRole('super_admin');
        $this->actingAs($verified)->get('/admin/email-verification/prompt')->assertRedirect();
        $this->assertDatabaseCount('jobs', 0);
        $customer = User::factory()->unverified()->create();
        $customer->assignRole('customer');
        $this->actingAs($customer)->get('/admin/email-verification/prompt')->assertForbidden();
        $this->assertDatabaseCount('jobs', 0);
    }

    #[DataProvider('panels')]
    public function test_password_reset_email_worker_roundtrip_and_consumed_token(string $panel, string $role, string $prefix): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $current = Filament::getPanel($panel);
        Filament::setCurrentPanel($current);
        Livewire::test($current->getRequestPasswordResetRouteAction())
            ->fillForm(['email' => strtoupper($user->email)])->call('request')->assertHasNoFormErrors();
        $this->assertDatabaseCount('jobs', 1);
        $url = $this->mailUrl($this->deliverEmails(), $prefix.'/password-reset/reset');
        $this->get($url)->assertOk();
        $this->get($url.'&tampered=1')->assertForbidden();
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $wrong = Filament::getPanel($panel === 'admin' ? 'account' : 'admin');
        Filament::setCurrentPanel($wrong);
        Livewire::test($wrong->getResetPasswordRouteAction(), ['email' => $user->email, 'token' => $query['token']])
            ->fillForm(['password' => 'Synthetic-New-password1!', 'passwordConfirmation' => 'Synthetic-New-password1!'])->call('resetPassword');
        $this->assertTrue(Password::broker()->tokenExists($user, $query['token']));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        Filament::setCurrentPanel($current);
        Livewire::test($current->getResetPasswordRouteAction(), ['email' => $user->email, 'token' => $query['token']])
            ->fillForm(['password' => 'Synthetic-New-password1!', 'passwordConfirmation' => 'Synthetic-New-password1!'])->call('resetPassword')->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('Synthetic-New-password1!', $user->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($user, $query['token']));
    }

    #[DataProvider('panels')]
    public function test_profile_email_change_requires_new_mailbox_and_old_mailbox_can_block(string $panel, string $role, string $prefix): void
    {
        $user = User::factory()->create(['email' => 'old@example.test']);
        $user->assignRole($role);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel($panel));
        Livewire::test(EditProfile::class)->fillForm(['name' => $user->name, 'email' => 'new@example.test', 'currentPassword' => 'password'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('old@example.test', $user->fresh()->email);
        $mail = $this->deliverEmails();
        $verify = $this->mailUrl($mail, '/email-change-verification/verify/');
        $block = $this->mailUrl($mail, '/email-change-verification/verify/', true);
        $this->assertStringContainsString('old@example.test', $mail);
        $this->assertStringContainsString('new@example.test', $mail);
        $this->get($block)->assertRedirect();
        $this->get($verify)->assertForbidden();
        $this->assertSame('old@example.test', $user->fresh()->email);
        Livewire::test(EditProfile::class)->fillForm(['name' => $user->name, 'email' => 'new@example.test', 'currentPassword' => 'password'])->call('save')->assertHasNoFormErrors();
        $mail = $this->deliverEmails();
        $verify = $this->mailUrl($mail, '/email-change-verification/verify/');
        $this->get($verify)->assertRedirect();
        $this->assertSame('new@example.test', $user->fresh()->email);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get($verify)->assertForbidden();
    }

    public function test_lock_release_failure_does_not_hide_committed_email(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $lock = new class
        {
            public function get(): bool
            {
                return true;
            }

            public function release(): void
            {
                throw new \RuntimeException('Synthetic lock cleanup failure');
            }
        };
        Cache::shouldReceive('lock')->once()->andReturn($lock);
        Cache::shouldReceive('has')->once()->andReturn(false);
        Cache::shouldReceive('put')->once()->andReturn(true);
        $this->assertSame('queued', app(SendPanelEmailVerification::class)->handle($user, Filament::getPanel('admin')));
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_busy_lock_does_not_send_a_duplicate_and_can_retry(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $lock = Cache::lock('auth:verification:lock:'.$user->getKey(), 10);
        $this->assertTrue($lock->get());
        Livewire::test(EmailVerificationPrompt::class)->assertSet('deliveryStatus', 'busy');
        $this->assertDatabaseCount('jobs', 0);
        $lock->release();
        Livewire::test(EmailVerificationPrompt::class)->assertSet('deliveryStatus', 'queued');
        $this->assertDatabaseCount('jobs', 1);
    }

    private function mailUrl(string $mail, string $path, bool $block = false): string
    {
        preg_match_all('~https?://localhost/[^\s<>"\']+~', $mail, $matches);
        foreach ($matches[0] as $candidate) {
            $url = html_entity_decode($candidate);
            if (str_contains($url, $path) && (str_contains(parse_url($url, PHP_URL_PATH), '/block') === $block)) {
                return $url;
            }
        }
        $this->fail('Expected panel action URL missing from captured SMTP email.');
    }

    private function deliverEmails(): string
    {
        $messageFile = $this->temporary.'.mail';
        $ready = $this->temporary.'.smtp-ready';
        @unlink($messageFile);
        @unlink($ready);
        $smtp = new Process(['node', base_path('tests/Support/smtp-sink.mjs'), $messageFile, $ready, 'append'], base_path());
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
        $worker = new Process([PHP_BINARY, base_path('tests/Support/registration-worker.php'), $this->database, 'queue', '', ''], base_path(), [
            ...$this->catalogWorkerEnvironment(), 'APP_URL' => 'http://localhost', 'APP_KEY' => config('app.key'),
            'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => 'smtp', 'MAIL_URL' => 'null',
            'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => trim(file_get_contents($ready)), 'MAIL_USERNAME' => '', 'MAIL_PASSWORD' => '',
            'MAIL_FROM_ADDRESS' => 'noreply@example.test', 'MAIL_FROM_NAME' => 'Synthetic QA',
        ]);
        $worker->setTimeout(20);
        $worker->run();
        $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(0, DB::table('failed_jobs')->count(), $worker->getOutput());
        $this->assertFileExists($messageFile);
        $mail = quoted_printable_decode(file_get_contents($messageFile));
        $smtp->stop();

        return $mail;
    }
}
