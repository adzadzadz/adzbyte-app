<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class PackageCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (array_diff(array_keys($this->all()), ['package_code', 'offer_version', 'operation_id', 'business_name', 'email']) !== []) {
            throw ValidationException::withMessages(['checkout' => 'Unexpected checkout fields.']);
        }
        // Only trim actual strings; malformed arrays must fail validation without coercion.
        $normalized = [];
        foreach (['business_name', 'email', 'operation_id'] as $key) {
            if (is_string($this->input($key))) {
                $normalized[$key] = $key === 'business_name' ? trim($this->input($key)) : strtolower(trim($this->input($key)));
            }
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'package_code' => ['required', 'string', 'in:starter,business,growth,pickleball'],
            'offer_version' => ['required', 'string', 'max:32'],
            'operation_id' => ['required', 'string', 'uuid'],
            'business_name' => ['required', 'string', 'max:160', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'email' => ['required', 'string', 'max:254', 'email:rfc', 'not_regex:/[\x00-\x1F\x7F]/u'],
        ];
    }
}
