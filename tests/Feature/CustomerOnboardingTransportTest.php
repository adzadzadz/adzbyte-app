<?php

namespace Tests\Feature;

use App\Filament\Auth\EmailVerificationPrompt;
use App\Filament\Pages\Portal\ClientWork;
use App\Filament\Pages\Portal\Customers;
use App\Models\User;
use App\Models\WorkItem;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\Support\Portal\CreatesPortal;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class CustomerOnboardingTransportTest extends TestCase
{
    use CreatesPortal, UsesIsolatedCatalogDatabase;

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
        $this->setUpPortal();
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

    public function test_admin_invitation_arrives_activation_and_fresh_login_reveal_only_assigned_projects_and_services(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->portalAdmin);
        Livewire::test(Customers::class)->callAction('createCustomer', [
            'name' => 'Invited Customer', 'email' => 'INVITED@example.test',
        ])->assertHasNoActionErrors();
        $customer = User::where('email', 'invited@example.test')->sole();
        $this->assertFalse($customer->hasVerifiedEmail());
        $this->assertSame(['customer'], $customer->getRoleNames()->all());
        $this->assertSame(0, $customer->permissions()->count());
        $this->assertDatabaseCount('jobs', 1);

        foreach (['project' => 'Invited website project', 'service' => 'Invited monthly service'] as $type => $title) {
            Livewire::withQueryParams(['customer' => $customer->id])->test(ClientWork::class)
                ->mountAction('createWork')->assertSchemaStateSet(['customer' => $customer->id])
                ->fillForm($this->workInput(['customer' => $customer->id, 'type' => $type, 'title' => $title]))
                ->callMountedAction()->assertHasNoActionErrors();
            $this->assertSame($customer->id, WorkItem::where('title', $title)->sole()->user_id);
        }
        $private = $this->portalWork(['customer' => $this->portalOther->id, 'title' => 'Other customer confidential project']);
        $this->post('/admin/logout')->assertRedirect();
        $this->assertGuest();
        $this->get('/')->assertRedirect('/login');

        $mail = $this->deliverInvitation();
        $this->assertStringContainsString('invited@example.test', $mail);
        $this->assertStringContainsString('Activate your Adzbyte Solutions account', $mail);
        $this->assertStringContainsString('Set password and activate account', $mail);
        $this->assertSame(1, preg_match('~https://app\.example\.test/activate-account/[^\s<>"\']+~', $mail, $matches));
        $url = html_entity_decode($matches[0]);
        $this->get($url.'&tampered=1')->assertForbidden();
        $this->get($url)->assertOk()->assertSee('Activate your account');
        $password = 'Synthetic-invited-password1!';
        $this->post($url, ['password' => $password, 'password_confirmation' => $password])->assertRedirect('/');
        $this->assertAuthenticatedAs($customer);
        $this->assertTrue($customer->fresh()->hasVerifiedEmail());
        $this->assertTrue(Hash::check($password, $customer->fresh()->password));
        $this->post($url, ['password' => $password, 'password_confirmation' => $password])->assertForbidden();

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
        Filament::setCurrentPanel(Filament::getPanel('account'));
        Livewire::test(Login::class)->fillForm(['email' => $customer->email, 'password' => $password])
            ->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($customer);
        $this->get('/')->assertOk()->assertSee('Invited website project')->assertSee('Invited monthly service')
            ->assertDontSee('Other customer confidential project')->assertDontSeeLivewire(EmailVerificationPrompt::class);
        $this->get('/projects')->assertOk()->assertSee('Invited website project')->assertDontSee('Invited monthly service');
        $this->get('/services')->assertOk()->assertSee('Invited monthly service')->assertDontSee('Invited website project');
        foreach (WorkItem::where('user_id', $customer->id)->get() as $work) {
            $path = $work->type === 'project' ? '/projects' : '/services';
            $this->get($path.'?record='.$work->public_id)->assertOk()->assertSee($work->title)->assertSee($work->next_step);
        }
        $this->get('/projects?record='.$private->public_id)->assertNotFound();
        $this->get('/admin')->assertForbidden();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    private function deliverInvitation(): string
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
        $this->assertSame(0, DB::table('jobs')->count(), 'Invitation was not drained by the independent worker.');
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertFileExists($messageFile);

        return quoted_printable_decode(file_get_contents($messageFile));
    }
}
