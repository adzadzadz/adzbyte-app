@php
    $url = $staff
        ? \App\Filament\Pages\Portal\ClientWork::getUrl([...($listContext ?? []), 'record' => $item['public_id']], panel: 'admin')
        : ($item['type'] === 'project' ? \App\Filament\Account\Pages\Projects::getUrl([...($listContext ?? []), 'record' => $item['public_id']], panel: 'account') : \App\Filament\Account\Pages\Services::getUrl([...($listContext ?? []), 'record' => $item['public_id']], panel: 'account'));
@endphp
<article class="adz-portal-card">
    <div class="adz-portal-row"><span class="adz-portal-kicker">{{ ucfirst($item['type']) }}</span><span class="adz-portal-status" data-status="{{ $item['status'] }}">{{ \App\Support\PortalOptions::label($item['status'], $staff) }}</span></div>
    <h2 class="adz-portal-title"><a wire:navigate href="{{ $url }}" class="adz-portal-link">{{ $item['title'] }}</a></h2>
    @if ($staff)<p class="adz-portal-muted">{{ $item['customer'] }}</p>@endif
    @if ($item['unread'] && ! $staff)<span class="adz-portal-unread">New update</span>@endif
    @if ($item['milestone_count'])
        <label class="adz-portal-progress-label" for="progress-{{ $item['public_id'] }}">{{ $item['completed_milestone_count'] }} of {{ $item['milestone_count'] }} milestones complete</label>
        <progress id="progress-{{ $item['public_id'] }}" class="adz-portal-progress" value="{{ $item['completed_milestone_count'] }}" max="{{ $item['milestone_count'] }}">{{ $item['completed_milestone_count'] }} / {{ $item['milestone_count'] }}</progress>
    @else<p class="adz-portal-muted">Milestones will appear here when your plan is ready.</p>@endif
    @if ($item['next_step'])<p class="adz-portal-next"><strong>Next step</strong><br>{{ $item['next_step'] }}</p>@endif
    @if ($item['target_date'])<p class="adz-portal-muted">Target: {{ \Illuminate\Support\Carbon::parse($item['target_date'])->format('M j, Y') }}</p>@endif
</article>
