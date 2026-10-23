@if ($pagination['last_page'] > 1)
    <nav class="adz-portal-pagination" aria-label="{{ $label ?? 'Result' }} pages">
        <x-filament::button color="gray" wire:click="$set('{{ $property ?? 'page' }}', {{ max(1, $pagination['page'] - 1) }})" :disabled="$pagination['page'] <= 1" wire:loading.attr="disabled">{{ ($property ?? '') === 'threadPage' ? 'Newer messages' : 'Previous' }}</x-filament::button>
        <span>Page {{ $pagination['page'] }} of {{ $pagination['last_page'] }}</span>
        <x-filament::button color="gray" wire:click="$set('{{ $property ?? 'page' }}', {{ $pagination['page'] + 1 }})" :disabled="$pagination['page'] >= $pagination['last_page']" wire:loading.attr="disabled">{{ ($property ?? '') === 'threadPage' ? 'Older messages' : 'Next' }}</x-filament::button>
        @if (($property ?? '') === 'threadPage' && $pagination['page'] > 1)<x-filament::button color="gray" wire:click="$set('threadPage', 1)">Latest messages</x-filament::button>@endif
    </nav>
@endif
