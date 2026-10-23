<article class="adz-portal-card">
    <div class="adz-portal-row"><span class="adz-portal-kicker">{{ ucfirst($item['type']) }}</span><span class="adz-portal-status" data-status="{{ $item['status'] }}">{{ \App\Support\PortalOptions::label($item['status'], $staff) }}</span></div>
    <h2 class="adz-portal-title"><a class="adz-portal-link" href="{{ $staff ? \App\Filament\Pages\Portal\CustomerSupport::getUrl(['record' => $item['public_id']], panel: 'admin') : \App\Filament\Account\Pages\Support::getUrl(['record' => $item['public_id']], panel: 'account') }}">{{ $item['subject'] }}</a></h2>
    @if ($staff)<p class="adz-portal-muted">{{ $item['customer'] }}</p>@endif
    @if ($item['unread'])<span class="adz-portal-unread">New activity</span>@endif
    @if ($item['work'])<p class="adz-portal-muted">{{ $item['work']['title'] }}</p>@endif
    <p class="adz-portal-muted">Updated {{ \Illuminate\Support\Carbon::parse($item['updated_at'])->timezone('Asia/Manila')->format('M j, Y · g:i a') }} PHT</p>
</article>
