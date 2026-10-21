<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CustomerAccountActivation;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Markdown;
use Tests\TestCase;

class AutomatedMailBrandingTest extends TestCase
{
    public function test_existing_auth_notifications_render_the_shared_footer_once_without_sending(): void
    {
        config(['app.name' => 'Adzbyte Solutions', 'mail.from.address' => 'support@adzbyte.com',
            'mail.from.name' => 'Adzbyte Solutions', 'app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        $user = new User(['name' => 'Synthetic Person', 'email' => 'synthetic@example.test']);
        $user->id = 987;
        $reset = new ResetPassword('synthetic-reset-token');
        $reset->url = 'https://app.example.test/account/password-reset/synthetic-reset-token';
        foreach ([$reset, new CustomerAccountActivation] as $notification) {
            $message = $notification->toMail($user);
            $html = (string) $message->render();
            $this->assertSame(1, substr_count($html, 'https://adzbyte.com/images/adzbyte-logo-transparent.png'));
            $this->assertStringContainsString('alt="Adzbyte Solutions"', $html);
            $this->assertStringContainsString('mailto:support@adzbyte.com', $html);
            $this->assertStringContainsString('https://m.me/adzbytesolutions', $html);
            $this->assertStringContainsString('https://www.facebook.com/adzbytesolutions/', $html);
            $this->assertStringContainsString('#00d9bf', $html);
            $this->assertSame(1, substr_count($html, 'Adzbyte Solutions. All rights reserved.'));
            $this->assertStringNotContainsString('&copy;', $html);
        }
    }

    public function test_plain_text_automated_footer_uses_the_same_contact_destinations(): void
    {
        $text = (string) app(Markdown::class)->renderText('notifications::email', [
            'level' => 'info', 'greeting' => 'Hello Synthetic Person', 'introLines' => ['Synthetic notification.'],
            'outroLines' => [], 'actionText' => null, 'actionUrl' => null, 'displayableActionUrl' => null,
            'salutation' => null,
        ]);
        $this->assertSame(1, substr_count($text, 'Adzbyte Solutions. All rights reserved.'));
        $this->assertStringContainsString('support@adzbyte.com', $text);
        $this->assertStringContainsString('https://m.me/adzbytesolutions', $text);
        $this->assertStringContainsString('https://www.facebook.com/adzbytesolutions/', $text);
    }
}
