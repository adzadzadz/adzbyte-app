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
        if (array_diff(array_keys($this->all()), ['package_code', 'offer_version', 'operation_id', 'full_name', 'business_name', 'email', 'phone']) !== []) {
            throw ValidationException::withMessages(['checkout' => 'Unexpected checkout fields.']);
        }
        if (array_key_exists('full_name', $this->all()) && array_key_exists('business_name', $this->all())) {
            throw ValidationException::withMessages(['full_name' => 'Send full_name or the legacy business_name field, never both.']);
        }
        // Only trim actual strings; malformed arrays must fail validation without coercion.
        $normalized = [];
        foreach (['full_name', 'business_name', 'email', 'phone', 'operation_id'] as $key) {
            if (is_string($this->input($key))) {
                $normalized[$key] = in_array($key, ['email', 'operation_id'], true)
                    ? strtolower(trim($this->input($key))) : trim($this->input($key));
                if ($key === 'phone' && $normalized[$key] === '') {
                    $normalized[$key] = null;
                }
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
            'full_name' => ['required_without:business_name', 'string', 'max:160', 'not_regex:/[\x00-\x1F\x7F<>]/u'],
            'business_name' => ['required_without:full_name', 'string', 'max:160', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'email' => ['required', 'string', 'max:254', 'email:rfc', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[+\d ().-]+$/D',
                function ($attribute, $value, $fail): void {
                    if (! is_string($value)) {
                        return;
                    }
                    $digits = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($digits) < 7 || strlen($digits) > 15) {
                        $fail('Phone must contain between 7 and 15 digits.');
                    }
                }],
        ];
    }
}
