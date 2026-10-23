<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class PaymentOperationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        abort_if(strlen($this->getContent()) > 16384, 413);

        return ['body' => $this->isMethod('POST') ? $this->json()->all() : [], 'query' => $this->query(),
            ...$this->isMethod('POST') ? ['operation_id' => $this->header('Idempotency-Key')] : []];
    }

    public function rules(): array
    {
        return ['query' => ['present', $this->isMethod('POST') ? 'array' : 'array:event_page', ...$this->isMethod('POST') ? ['size:0'] : []],
            'query.event_page' => ['sometimes', 'integer', 'between:1,10000'],
            'body' => ['present', $this->isMethod('POST') ? 'array:event,expected_revision,note' : 'array', ...$this->isMethod('POST') ? [] : ['size:0']],
            ...$this->isMethod('POST') ? ['operation_id' => ['required', 'uuid'], 'body.event' => ['required', 'string'],
                'body.expected_revision' => ['required', 'integer:strict', 'min:1'], 'body.note' => ['required', 'string', 'max:1000']] : []];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (($this->isMethod('POST') && (! $this->isJson() || ! json_decode($this->getContent()) instanceof \stdClass))
                || (! $this->isMethod('POST') && $this->getContent() !== '')) {
                $validator->errors()->add('body', 'Use a JSON object for commands and no body for reads.');
            }
        }];
    }
}
