<x-filament-panels::page>
    <div class="adz-portal" data-commerce-preview>
        <section class="adz-portal-hero"><span class="adz-portal-status" data-status="on_hold">Under development</span><h2 class="adz-portal-heading">A simpler way to manage your purchases</h2><p class="adz-portal-intro">We’re building ecommerce into your Adzbyte Solutions portal. This is a preview of what’s coming. Checkout and online payments are not available here yet.</p></section>
        <div class="adz-portal-grid">
            @foreach (['Cart' => 'Review services and their scope in one place before submitting an order.', 'Orders' => 'Follow order review, accepted scope, and the next steps for a purchase.', 'Payments' => 'Access payment options and see confirmed payment status.', 'Subscriptions' => 'Manage recurring services once subscription billing is ready.'] as $name => $description)
                <section class="adz-portal-card"><p class="adz-portal-kicker">Planned</p><h3 class="adz-portal-title">{{ $name }}</h3><p class="adz-portal-muted">{{ $description }}</p><span class="adz-portal-status" data-status="on_hold">Under development</span></section>
            @endforeach
        </div>
        <p class="adz-portal-muted">Your projects, services and support conversations are available now. Your team will let you know when purchasing is ready.</p>
        <x-filament::button tag="a" :href="\App\Filament\Account\Pages\Projects::getUrl(panel: 'account')">View your projects</x-filament::button>
    </div>
</x-filament-panels::page>
