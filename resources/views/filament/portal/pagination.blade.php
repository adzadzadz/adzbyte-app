@if ($pagination['last_page'] > 1)
    <nav class="adz-portal-pagination" aria-label="{{ $label ?? 'Result' }} pages">
        <x-filament::button color="gray" wire:click="$set('page', {{ max(1, $pagination['page'] - 1) }})" :disabled="$pagination['page'] <= 1" wire:loading.attr="disabled">Previous</x-filament::button>
        <span>Page {{ $pagination['page'] }} of {{ $pagination['last_page'] }}</span>
        <x-filament::button color="gray" wire:click="$set('page', {{ $pagination['page'] + 1 }})" :disabled="$pagination['page'] >= $pagination['last_page']" wire:loading.attr="disabled">Next</x-filament::button>
    </nav>
@endif
