<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('POST') && ! $this->is('api/*') && $this->hasSession() && $this->user() !== null) {
            $this->session()->put('commerce.checkout_input_owner', $this->user()->id);
        }
    }

    public function validationData(): array
    {
        abort_if(strlen($this->getContent()) > 262144, 413);
        $read = in_array($this->method(), ['GET', 'HEAD'], true);
        $api = $this->is('api/*');
        $body = $read ? [] : ($api ? $this->json()->all() : $this->except('_token', 'operation_id'));
        if (! $read && ! $api) {
            $body['accept_terms'] = ($body['accept_terms'] ?? null) === '1';
            foreach (['brief' => ['goal', 'scope', 'website'], 'billing' => ['name', 'email', 'country', 'address', 'company', 'phone']] as $group => $fields) {
                foreach ($fields as $field) {
                    if (is_array($body[$group] ?? null) && array_key_exists($field, $body[$group]) && $body[$group][$field] === null) {
                        unset($body[$group][$field]);
                    }
                }
            }
        }

        return ['body' => $body, 'query' => $this->query(), ...! $read ? ['operation_id' => $api ? $this->header('Idempotency-Key') : $this->input('operation_id')] : []];
    }

    public function rules(): array
    {
        $read = in_array($this->method(), ['GET', 'HEAD'], true);
        $listing = $this->routeIs('api.v1.orders.index', 'orders.index');

        return [
            'query' => ['present', $listing ? 'array:page,per_page' : 'array', ...$listing ? [] : ['size:0']],
            ...$listing ? ['query.page' => ['sometimes', 'integer', 'between:1,10000'], 'query.per_page' => ['sometimes', 'integer', 'between:1,50']] : [],
            'body' => ['present', $read ? 'array' : 'array:brief,billing,accept_terms,fingerprint', ...$read ? ['size:0'] : []],
            ...! $read ? ['body.brief' => ['present', 'array'], 'body.billing' => ['present', 'array'],
                'body.accept_terms' => ['required', 'boolean'], 'body.fingerprint' => ['required', 'regex:/^[a-f0-9]{64}$/D'], 'operation_id' => ['required', 'uuid']] : [],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $read = in_array($this->method(), ['GET', 'HEAD'], true);
            if ($read && $this->getContent() !== '') {
                $validator->errors()->add('body', 'Read requests must not include a body.');
            }
            if (! $read && $this->is('api/*')) {
                $decoded = json_decode($this->getContent());
                if (! $this->isJson() || ! $decoded instanceof \stdClass || ! ($decoded->brief ?? null) instanceof \stdClass || ! ($decoded->billing ?? null) instanceof \stdClass) {
                    $validator->errors()->add('body', 'Send a JSON object with brief and billing objects.');
                }
            }
        }];
    }
}
