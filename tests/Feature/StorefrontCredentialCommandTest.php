<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Carts\StorefrontCredential;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class StorefrontCredentialCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_console_creates_dedicated_identity_and_issues_hashed_narrow_expiring_credential(): void
    {
        $this->assertSame(0, Artisan::call('commerce:storefront-token', ['email' => 'synthetic-service@example.test', '--create' => true]));
        $output = Artisan::output();
        preg_match('/\b([0-9]+\|[A-Za-z0-9]+)\b/', $output, $matches);
        $this->assertArrayHasKey(1, $matches);
        $token = PersonalAccessToken::findToken($matches[1]);
        $this->assertNotNull($token);
        $this->assertSame(StorefrontCredential::abilities(), $token->abilities);
        $this->assertTrue($token->expires_at->between(now()->addDays(89), now()->addDays(91)));
        $identity = $token->tokenable;
        $this->assertSame(0, $identity->roles()->count());
        $this->assertSame(0, $identity->permissions()->count());
        $this->assertTrue($identity->hasVerifiedEmail());
        $this->assertFalse($identity->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($identity->canAccessPanel(Filament::getPanel('account')));
        $this->assertStringNotContainsString($matches[1], $token->toJson());
        $this->assertSame(0, Artisan::call('commerce:storefront-token', ['email' => $identity->email, '--name' => 'replacement', '--abilities' => 'cart:read', '--days' => '1']));
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_command_does_not_modify_existing_customers_staff_or_unverified_identities(): void
    {
        foreach ([UserRole::Customer, UserRole::Administrator, UserRole::SuperAdmin] as $role) {
            $identity = User::factory()->create();
            $identity->assignRole($role->value);
            $original = $identity->getAttributes();
            $this->artisan('commerce:storefront-token', ['email' => $identity->email])->assertFailed();
            $this->assertEquals($original, $identity->fresh()->getAttributes());
            $this->assertTrue($identity->fresh()->hasRole($role->value));
        }
        $unverified = User::factory()->unverified()->create();
        $this->artisan('commerce:storefront-token', ['email' => $unverified->email])->assertFailed();
        $this->assertNull($unverified->fresh()->email_verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_abilities_lifetime_and_existing_create_fail_without_issuing_credentials(): void
    {
        $identity = User::factory()->create();
        foreach ([
            ['--create' => true], ['--abilities' => '*'], ['--abilities' => 'cart:read,integration'],
            ['--abilities' => 'cart:read,cart:read'], ['--days' => '0'], ['--days' => '366'], ['--days' => '1.5'],
        ] as $options) {
            $this->artisan('commerce:storefront-token', ['email' => $identity->email, ...$options])->assertFailed();
        }
        $identity->givePermissionTo('ViewAny:Product');
        $this->artisan('commerce:storefront-token', ['email' => $identity->email])->assertFailed();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_dedicated_identity_creation_cannot_duplicate_a_customer_email_with_different_case(): void
    {
        $customer = User::factory()->create(['email' => 'SyntheticCustomer@Example.test']);
        $customer->assignRole(UserRole::Customer->value);
        $this->artisan('commerce:storefront-token', ['email' => 'syntheticcustomer@example.test', '--create' => true])->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertTrue($customer->fresh()->hasRole(UserRole::Customer->value));
    }

    public function test_revocation_targets_only_the_named_narrow_credential_and_leaves_other_tokens(): void
    {
        $identity = User::factory()->create();
        $target = $identity->createToken('storefront:target', StorefrontCredential::abilities());
        $other = $identity->createToken('storefront:other', ['cart:read']);
        $broad = $identity->createToken('storefront:broad', ['*']);
        $legacy = $identity->createToken('other-purpose', ['cart:read']);
        $this->artisan('commerce:revoke-storefront-token', ['id' => $target->accessToken->id])->assertSuccessful();
        $this->assertNull(PersonalAccessToken::findToken($target->plainTextToken));
        foreach ([$other, $broad, $legacy] as $retained) {
            $this->assertNotNull(PersonalAccessToken::findToken($retained->plainTextToken));
        }
        foreach ([$broad->accessToken->id, $legacy->accessToken->id, 'invalid', $target->accessToken->id] as $id) {
            $this->artisan('commerce:revoke-storefront-token', ['id' => $id])->assertFailed();
        }
        $this->assertDatabaseCount('personal_access_tokens', 3);
    }
}
