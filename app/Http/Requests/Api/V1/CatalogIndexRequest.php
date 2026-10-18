<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CatalogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        return $this->query();
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'available' => ['sometimes', 'required', 'in:0,1'],
            'include' => ['sometimes', 'required', 'in:category'],
            'category' => ['sometimes', 'required', 'ulid'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query()), array_keys($this->rules())) as $field) {
                $validator->errors()->add((string) $field, 'Unknown catalog query parameter.');
            }
        }];
    }
}
