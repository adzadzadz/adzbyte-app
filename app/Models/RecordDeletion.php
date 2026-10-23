<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class RecordDeletion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['deleted_at' => 'immutable_datetime', 'version' => 'integer'];
    }
}
