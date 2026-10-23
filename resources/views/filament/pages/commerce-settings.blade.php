<x-filament-panels::page>
    <p>Current version {{ $this->expectedVersion }}. Publishing creates a new version and retains accepted history.</p>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">Publish settings version</x-filament::button>
    </form>
</x-filament-panels::page>
