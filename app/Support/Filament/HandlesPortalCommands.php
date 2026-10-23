<?php

namespace App\Support\Filament;

use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait HandlesPortalCommands
{
    #[Locked]
    public array $operationIds = [];

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
            Notification::make()->danger()->title('Update not saved')
                ->body($exception instanceof ValidationException ? implode(' ', $exception->validator->errors()->all()) : $exception->getMessage())->send();
            throw new Halt;
        }
        $this->operationIds[$command] = (string) Str::uuid();
        Notification::make()->success()->title($success)->send();

        return $result;
    }
}
