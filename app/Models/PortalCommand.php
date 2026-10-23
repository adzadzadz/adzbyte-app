<?php

namespace App\Models;

use App\Models\Concerns\ImmutablePortalHistory;
use Illuminate\Database\Eloquent\Model;

final class PortalCommand extends Model
{
    use ImmutablePortalHistory;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];
}
