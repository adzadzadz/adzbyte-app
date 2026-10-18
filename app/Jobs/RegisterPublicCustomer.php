<?php

namespace App\Jobs;

use App\Actions\Customers\RegisterPublicCustomer as RegisterCustomer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class RegisterPublicCustomer implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(private string $name, private string $email) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('public-registration:'.hash('sha256', $this->email)))->releaseAfter(5)->expireAfter(60)];
    }

    public function handle(RegisterCustomer $register): void
    {
        $register->handle($this->name, $this->email);
    }
}
