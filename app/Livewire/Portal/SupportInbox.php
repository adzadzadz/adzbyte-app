<?php

namespace App\Livewire\Portal;

use App\Support\Filament\SupportPage;

class SupportInbox extends SupportPage
{
    protected bool $embedded = true;

    protected string $view = 'livewire.portal.support-inbox';

    public function mount(?string $work = null): void
    {
        $this->contextWork = $work;
        $this->linkedWork = $work;
        // Embedded inbox state is independent of its parent page URL.
        $this->record = null;
        $this->page = 1;
        parent::mount();
    }
}
