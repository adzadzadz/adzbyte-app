<?php

namespace App\Support\Filament;

use Closure;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait HandlesPortalCommands
{
    #[Locked]
    public array $operationIds = [];

    #[Locked]
    public bool $commandConflict = false;

    #[Locked]
    public array $conflictSnapshot = [];

    #[Locked]
    public ?string $conflictingCommand = null;

    protected function conflictNotice(): View
    {
        return View::make('filament.portal.conflict')->visible(fn () => $this->commandConflict);
    }

    public function reviewLatestVersion(): void
    {
        abort_unless($this->commandConflict && method_exists($this, 'conflictRecord'), 409);
        $latest = $this->conflictRecord();
        if ($latest['revision'] !== ($this->conflictSnapshot['revision'] ?? null)) {
            $this->conflictSnapshot = $latest;

            return;
        }
        $this->expectedRevision = $latest['revision'];
        if ($this->conflictingCommand === 'reply' && property_exists($this, 'replyReference') && $this->replyReference === $this->record) {
            $this->replyExpectedRevision = $latest['revision'];
        }
        $this->commandConflict = false;
        if ($this->conflictingCommand !== null) {
            $this->operationIds[$this->conflictingCommand] = (string) Str::uuid();
        }
        $this->conflictingCommand = null;
    }

    protected function operationId(string $command): string
    {
        return $this->operationIds[$command] ??= (string) Str::uuid();
    }

    protected function runCommand(string $command, Closure $action, string $success): mixed
    {
        try {
            $result = $action($this->operationId($command));
        } catch (ValidationException|HttpException $exception) {
            if ($exception instanceof HttpException && $exception->getStatusCode() !== 409) {
                throw $exception;
            }
            if ($exception instanceof HttpException && method_exists($this, 'conflictRecord')) {
                $this->commandConflict = true;
                $this->conflictingCommand = $command;
                $this->conflictSnapshot = $this->conflictRecord();
            }
            Notification::make()->danger()->title('Update not saved')
                ->body($exception instanceof ValidationException ? implode(' ', $exception->validator->errors()->all()) : $exception->getMessage())->send();
            throw new Halt;
        }
        $this->operationIds[$command] = (string) Str::uuid();
        $this->commandConflict = false;
        $this->conflictSnapshot = [];
        $this->conflictingCommand = null;
        Notification::make()->success()->title($success)->send();

        return $result;
    }
}
