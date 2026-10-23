<?php

namespace App\Filament\Account\Pages;

use App\Services\Portal\PortalRead;
use App\Support\Filament\PortalListFilters;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Projects extends Page
{
    use PortalListFilters;

    protected static ?string $navigationLabel = 'Projects';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected string $view = 'filament.portal.work';

    protected string $workType = 'project';

    protected function queryString(): array
    {
        return $this->filterQueryString();
    }

    #[Url]
    public ?string $record = null;

    #[Url]
    public int $page = 1;

    #[Url]
    public int $listPage = 1;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        if ($this->record !== null) {
            app(PortalRead::class)->markWorkRead(auth()->user(), $this->record);
        }
    }

    public function getTitle(): string
    {
        return $this->record ? 'Project & service details' : ($this->workType === 'project' ? 'Your projects' : 'Your services');
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    protected function getViewData(): array
    {
        $read = app(PortalRead::class);

        return ['staff' => false, 'workType' => $this->workType,
            'work' => $this->record ? $read->work(auth()->user(), $this->record, false, $this->page) : null,
            'listing' => $this->record ? null : $read->works(auth()->user(), $this->workType, false, $this->page, $this->search, $this->listFilters())];
    }
}
