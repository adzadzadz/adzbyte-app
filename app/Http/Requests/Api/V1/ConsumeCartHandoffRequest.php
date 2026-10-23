<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConsumeCartHandoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        return ['body' => $this->json()->all(), 'query' => $this->query()];
    }

    public function rules(): array
    {
        return ['body' => ['required', 'array:token'], 'body.token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'], 'query' => ['present', 'array', 'size:0']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->isJson() || ! (json_decode($this->getContent()) instanceof \stdClass)) {
                $validator->errors()->add('body', 'Send an application/json object.');
            }
        }];
    }
}
