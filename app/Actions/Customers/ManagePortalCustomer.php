<?php

namespace App\Actions\Customers;

use App\Enums\PortalCapability;
use App\Exceptions\ExistingAccountRequiresAuthentication;
use App\Models\PortalEvent;
use App\Models\User;
use App\Notifications\CustomerAccountActivation;
use App\Support\PortalAccess;
use App\Support\Records\RecordVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManagePortalCustomer
{
    public function create(User $actor, string $name, string $email): User
    {
        return DB::transaction(function () use ($actor, $name, $email): User {
            $this->authorize($actor);
            try {
                $customer = app(CreateProvisionalCustomer::class)->handle($name, $email);
            } catch (ExistingAccountRequiresAuthentication) {
                throw ValidationException::withMessages(['email' => 'An account already uses this email. Find the existing customer instead.']);
            }
            $this->invite($customer);
            PortalEvent::create(['actor_id' => $actor->id, 'action' => 'customer_created', 'changes' => ['customer_id' => $customer->id]]);

            return $customer;
        }, attempts: 5);
    }

    public function resend(User $actor, int $customerId): void
    {
        DB::transaction(function () use ($actor, $customerId): void {
            $this->authorize($actor);
            $customer = self::query()->lockForUpdate()->findOrFail($customerId);
            if ($customer->hasVerifiedEmail()) {
                throw ValidationException::withMessages(['customer' => 'This customer is already activated.']);
            }
            $recent = PortalEvent::where('actor_id', $actor->id)->whereIn('action', ['customer_created', 'customer_invited'])
                ->where('created_at', '>', now()->subMinute())->where('changes->customer_id', $customerId)->exists();
            if ($recent) {
                throw ValidationException::withMessages(['customer' => 'An invitation was just queued. Please wait a minute before resending.']);
            }
            $this->invite($customer);
            PortalEvent::create(['actor_id' => $actor->id, 'action' => 'customer_invited', 'changes' => ['customer_id' => $customer->id]]);
        }, attempts: 5);
    }

    public static function query(): Builder
    {
        return RecordVisibility::query(User::role('customer'))->whereDoesntHave('roles', fn ($q) => $q->where('name', '!=', 'customer'))->whereDoesntHave('permissions');
    }

    private function authorize(User $actor): void
    {
        $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        RecordVisibility::assertVisible($fresh);
        abort_unless(PortalAccess::staff($fresh, PortalCapability::ViewCustomers) && PortalAccess::staff($fresh, PortalCapability::ManageCustomers), 403);
    }

    private function invite(User $customer): void
    {
        // The invitation and identity must commit on the same database connection.
        $connection = config('queue.connections.database.connection') ?? config('database.default');
        if (config('queue.connections.database.driver') !== 'database' || $connection !== config('database.default')) {
            throw new \LogicException('Customer invitations require the database queue on the application database connection.');
        }
        $customer->notify((new CustomerAccountActivation)->onConnection('database')->beforeCommit());
    }
}
