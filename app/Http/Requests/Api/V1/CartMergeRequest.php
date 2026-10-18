<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class CartMergeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        $read = in_array($this->method(), ['GET', 'HEAD'], true);
        $api = $this->is('api/*');

        $body = $read ? [] : ($api ? $this->json()->all() : $this->except('_token', 'operation_id'));
        if (! $read && ! $api && ! array_key_exists('choices', $body)) {
            $body['choices'] = [];
        }

        return ['body' => $body,
            'query' => $this->query(), ...! $read ? ['operation_id' => $api ? $this->header('Idempotency-Key') : $this->input('operation_id')] : []];
    }

    public function rules(): array
    {
        $read = in_array($this->method(), ['GET', 'HEAD'], true);

        return ['query' => ['present', 'array', 'size:0'],
            'body' => ['present', $read ? 'array' : 'array:fingerprint,choices', ...$read ? ['size:0'] : []],
            ...! $read ? ['body.fingerprint' => ['required', 'regex:/^[a-f0-9]{64}$/D'], 'body.choices' => ['present', 'array'],
                'body.choices.*' => ['required', 'ulid'], 'operation_id' => ['required', 'uuid']] : []];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $read = in_array($this->method(), ['GET', 'HEAD'], true);
            if ($read && $this->getContent() !== '') {
                $validator->errors()->add('body', 'Read requests must not include a body.');
            }
            if (! $read && $this->is('api/*') && (! $this->isJson() || ! (json_decode($this->getContent()) instanceof \stdClass))) {
                $validator->errors()->add('body', 'Send an application/json object.');
            }
            $choices = $this->input('choices', []);
            foreach (is_array($choices) ? array_keys($choices) : [] as $key) {
                if (! is_string($key) || ! Str::isUlid($key)) {
                    $validator->errors()->add('choices', 'Choose from the displayed conflict groups.');
                }
            }
        }];
    }
}
