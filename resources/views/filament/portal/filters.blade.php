<div class="adz-portal-filters" aria-label="Filter {{ ($cases ?? false) ? 'conversations' : 'work' }}">
    <label>Status<select wire:model.live="statusFilter" class="adz-portal-select"><option value="">All statuses</option>
        @foreach (($cases ?? false) ? \App\Support\PortalOptions::CASE_STATUSES : \App\Support\PortalOptions::WORK_STATUSES as $value => $label)<option value="{{ $value }}">{{ \App\Support\PortalOptions::label($value, $staff) }}</option>@endforeach
    </select></label>
    <label>Updates<select wire:model.live="queueFilter" class="adz-portal-select"><option value="">All updates</option>
        @if (! $staff || ($cases ?? false))<option value="unread">Unread updates</option>@endif
        <option value="waiting">{{ $staff ? 'Waiting for customer' : 'Waiting for you' }}</option>
    </select></label>
    @if ($staff)
        <label>Assignment<select wire:model.live="assignmentFilter" class="adz-portal-select"><option value="">All staff</option><option value="mine">Assigned to me</option><option value="unassigned">Unassigned</option></select></label>
        <label>Customer<input type="search" wire:model.live.debounce.350ms="customerFilter" maxlength="100" placeholder="Name or email" class="adz-portal-select"></label>
        <label>Type<select wire:model.live="typeFilter" class="adz-portal-select"><option value="">All types</option>@foreach (($cases ?? false) ? \App\Support\PortalOptions::CASE_TYPES : \App\Support\PortalOptions::WORK_TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        @if ($cases ?? false)<label>Priority<select wire:model.live="priorityFilter" class="adz-portal-select"><option value="">All priorities</option>@foreach (\App\Support\PortalOptions::PRIORITIES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>@endif
    @endif
    <x-filament::button color="gray" size="sm" wire:click="clearFilters">Clear filters</x-filament::button>
</div>
