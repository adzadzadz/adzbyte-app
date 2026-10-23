<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        if (is_string($this->input('name'))) {
            $values['name'] = trim($this->input('name'));
        }
        if (is_string($this->input('email'))) {
            $values['email'] = Str::lower(trim($this->input('email')));
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email', 'max:255']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $api = $this->is('api/*');
            if ($this->query() !== [] || array_diff(array_keys($this->all()), $api ? ['name', 'email'] : ['name', 'email', '_token']) !== []) {
                $validator->errors()->add('request', 'Unexpected registration fields.');
            }
            if ($api && (! $this->isJson() || ! str_starts_with(ltrim($this->getContent()), '{') || ! json_validate($this->getContent()))) {
                $validator->errors()->add('request', 'A JSON object is required.');
            }
        }];
    }
}
