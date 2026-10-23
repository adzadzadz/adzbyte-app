<x-filament-panels::page>
    <p class="adz-portal-feedback">Delete removes records from normal screens while retaining the database row and its history. Restore returns the previous business status. Payment and audit history is read-only; deletion does not cancel or refund a payment.</p>
    <label class="adz-portal-field">Record type
        <select wire:model.live="type" class="adz-portal-select">
            @foreach (\App\Support\Records\RecordTypes::TYPES as $key => $definition)
                <option value="{{ $key }}">{{ $definition[1] }}</option>
            @endforeach
        </select>
    </label>
    @if ($selected)
        <x-filament::section>
            <x-slot name="heading">{{ $label }} · {{ \App\Support\Records\RecordTypes::label($selected) }}</x-slot>
            <p><strong>{{ \App\Support\Records\RecordVisibility::isDeleted($selected) ? 'Deleted' : 'Active' }}</strong> · Database reference {{ $selected->getKey() }}</p>
            @if ($selected->ui_deleted_at)
                <p>Deleted on {{ $selected->ui_deleted_at }}. Use Restore to return this record to normal screens.</p>
            @endif
            @foreach ($parents as $parent)
                <p>Hidden because its owning record is deleted. <a class="adz-portal-link" wire:navigate href="{{ $parent['url'] }}">Review and restore {{ $parent['label'] }}</a> first.</p>
            @endforeach
            <dl class="adz-data-definition">
                @foreach ($fields as $field)
                    @php
                        $value = $selected->getAttribute($field);
                        if ($value instanceof \BackedEnum) $value = $value->value;
                        elseif (is_bool($value)) $value = $value ? 'Yes' : 'No';
                        elseif (is_array($value)) $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    @endphp
                    <dt>{{ str($field)->replace('_', ' ')->title() }}</dt>
                    <dd>{{ $value ?? '—' }}</dd>
                @endforeach
            </dl>
            @if ($lifecycle->isNotEmpty())
                <details class="adz-portal-compact-details">
                    <summary>Deletion and restoration history</summary>
                    <ul>
                        @foreach ($lifecycle as $event)
                            <li>{{ ucfirst($event->action) }} · {{ $event->actor?->name ?? 'Retained user' }} · {{ $event->created_at }} · Version {{ $event->version }}</li>
                        @endforeach
                    </ul>
                    <p class="adz-portal-feedback">Latest 20 actions shown. The complete history is retained.</p>
                </details>
            @endif
        </x-filament::section>
    @endif
    {{ $this->table }}
</x-filament-panels::page>
