<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Carts\CartPrincipal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorefrontCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        return [
            'body' => $this->isRead() ? [] : $this->json()->all(),
            'query' => $this->query(),
            'proof' => $this->header('X-Cart-Proof'),
            ...! $this->isRead() ? ['operation_id' => $this->header('Idempotency-Key')] : [],
        ];
    }

    public function rules(): array
    {
        $method = $this->route()->getActionMethod();
        $bodyFields = match ($method) {
            'add' => ['product'], 'confirm' => ['versions'], default => []
        };

        return [
            'body' => ['present', $bodyFields === [] ? 'array' : 'array:'.implode(',', $bodyFields), ...$bodyFields === [] ? ['size:0'] : []],
            'query' => ['present', $method === 'resolve' ? 'array:creation_operation_id' : 'array', ...$method !== 'resolve' ? ['size:0'] : []],
            'proof' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            ...! $this->isRead() ? ['operation_id' => ['required', 'uuid']] : [],
            ...$method === 'add' ? ['body.product' => ['required', 'ulid']] : [],
            ...$method === 'confirm' ? ['body.versions' => ['required', 'array']] : [],
            ...$method === 'resolve' ? ['query.creation_operation_id' => ['required', 'uuid']] : [],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->isRead() && (! $this->isJson() || ! (json_decode($this->getContent()) instanceof \stdClass))) {
                $validator->errors()->add('body', 'Send an application/json object.');
            }
            if ($this->isRead() && $this->getContent() !== '') {
                $validator->errors()->add('body', 'Read requests must not include a body.');
            }
        }];
    }

    private function isRead(): bool
    {
        return in_array($this->method(), ['GET', 'HEAD'], true);
    }

    public function principal(): CartPrincipal
    {
        return CartPrincipal::guest($this->validated('proof'));
    }

    public function operationId(): string
    {
        return $this->validated('operation_id');
    }
}
