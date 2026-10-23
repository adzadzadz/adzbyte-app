<?php

namespace App\Support\Filament;

use Illuminate\Support\Facades\Validator;

final class CategoryInput
{
    public static function content(array $data): array
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'limit_one_per_cart' => ['required', 'boolean'],
        ])->validate();
        $validated['limit_one_per_cart'] = (bool) $validated['limit_one_per_cart'];

        return $validated;
    }
}
