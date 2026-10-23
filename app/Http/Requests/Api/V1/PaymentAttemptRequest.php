<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class PaymentAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        abort_if(strlen($this->getContent()) > 4096, 413);
        $read = in_array($this->method(), ['GET', 'HEAD'], true);
        $api = $this->is('api/*');
        $body = $read ? [] : ($api ? $this->json()->all() : $this->except('_token', 'operation_id'));
        if (! $read && ! $api && is_string($body['expected_revision'] ?? null) && preg_match('/^[1-9][0-9]{0,17}$/D', $body['expected_revision'])) {
            $body['expected_revision'] = (int) $body['expected_revision'];
        }

        return ['query' => $this->query(), 'body' => $body,
            ...$read ? [] : ['operation_id' => $api ? $this->header('Idempotency-Key') : $this->input('operation_id')]];
    }

    public function rules(): array
    {
        $read = in_array($this->method(), ['GET', 'HEAD'], true);

        return ['query' => ['present', 'array', 'size:0'], 'body' => ['present', $read ? 'array' : 'array:expected_revision', ...$read ? ['size:0'] : []],
            ...$read ? [] : ['body.expected_revision' => ['required', 'integer:strict', 'min:1'], 'operation_id' => ['required', 'uuid']]];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $read = in_array($this->method(), ['GET', 'HEAD'], true);
            if (($read && $this->getContent() !== '') || (! $read && $this->is('api/*') && (! $this->isJson() || ! json_decode($this->getContent()) instanceof \stdClass))) {
                $validator->errors()->add('body', 'Send a JSON object for writes and no body for reads.');
            }
        }];
    }
}
