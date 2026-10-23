<?php

namespace App\Support\Filament;

trait PortalListFilters
{
    public string $statusFilter = '';

    public string $queueFilter = '';

    public string $assignmentFilter = '';

    public string $priorityFilter = '';

    public string $typeFilter = '';

    public string $customerFilter = '';

    protected function filterQueryString(): array
    {
        return array_fill_keys(['statusFilter', 'queueFilter', 'assignmentFilter', 'priorityFilter', 'typeFilter', 'customerFilter'], ['except' => '', 'history' => true]);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['statusFilter', 'queueFilter', 'assignmentFilter', 'priorityFilter', 'typeFilter', 'customerFilter', 'search'], true)) {
            $this->page = 1;
        }
    }

    public function clearFilters(): void
    {
        $this->reset('statusFilter', 'queueFilter', 'assignmentFilter', 'priorityFilter', 'typeFilter', 'customerFilter', 'search');
        $this->page = 1;
    }

    public function hasListFilters(): bool
    {
        return trim($this->search) !== '' || collect($this->listFilters())->contains(fn ($value) => $value !== '');
    }

    protected function listFilters(): array
    {
        return ['status' => $this->statusFilter, 'queue' => $this->queueFilter,
            'assignment' => $this->assignmentFilter, 'priority' => $this->priorityFilter,
            'type' => $this->typeFilter, 'customer' => $this->customerFilter];
    }

    public function listLocation(): array
    {
        return array_filter(['search' => $this->search, 'listPage' => $this->page,
            ...collect(array_keys($this->filterQueryString()))->mapWithKeys(fn ($key) => [$key => $this->$key])->all()], fn ($value) => $value !== '' && $value !== null);
    }
}
