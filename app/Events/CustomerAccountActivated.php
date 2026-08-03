<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerAccountActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user) {}
}
