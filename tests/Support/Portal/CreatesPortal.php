<?php

namespace Tests\Support\Portal;

use App\Actions\Portal\PortalActions;
use App\Enums\PortalCapability;
use App\Models\User;
use App\Models\WorkItem;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

trait CreatesPortal
{
    private User $portalCustomer;

    private User $portalAdmin;

    private User $portalOther;

    private function setUpPortal(): void
    {
        $this->seed(RoleSeeder::class);
        $this->portalCustomer = User::factory()->create(['name' => 'Synthetic Customer']);
        $this->portalCustomer->assignRole('customer');
        $this->portalOther = User::factory()->create(['name' => 'Other private customer']);
        $this->portalOther->assignRole('customer');
        $this->portalAdmin = User::factory()->create(['name' => 'Synthetic Team']);
        $this->portalAdmin->assignRole('administrator');
        $this->portalAdmin->givePermissionTo(array_column(PortalCapability::cases(), 'value'));
    }

    private function workInput(array $changes = []): array
    {
        return [...['customer' => $this->portalCustomer->id, 'type' => 'project', 'title' => 'Customer website project',
            'summary' => 'Scope and progress for the customer website.', 'status' => 'in_progress',
            'next_step' => 'Please share your approved copy.', 'target_date' => '2026-12-01', 'assigned_to' => $this->portalAdmin->id], ...$changes];
    }

    private function portalWork(array $changes = []): WorkItem
    {
        return app(PortalActions::class)->createWork($this->portalAdmin, $this->workInput($changes), (string) Str::uuid());
    }

    private function caseInput(array $changes = []): array
    {
        return [...['type' => 'report', 'subject' => 'A problem with my account', 'description' => 'Please help with my account access.'], ...$changes];
    }
}
