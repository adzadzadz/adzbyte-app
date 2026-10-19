<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class PaymentControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        abort_if(strlen($this->getContent()) > 16384, 413);
        $api = $this->is('api/*');
        $body = $api ? $this->json()->all() : $this->except('_token', 'operation_id');
        if (! $api) {
            if (is_string($body['expected_revision'] ?? null) && preg_match('/^[1-9][0-9]{0,17}$/D', $body['expected_revision'])) {
                $body['expected_revision'] = (int) $body['expected_revision'];
            }
            if (($body['note'] ?? null) === null) {
                unset($body['note']);
            }
        }

        return ['query' => $this->query(), 'body' => $body, 'operation_id' => $api ? $this->header('Idempotency-Key') : $this->input('operation_id')];
    }

    public function rules(): array
    {
        $action = $this->route('control_action', 'cancel');
        $keys = ['expected_revision', 'note', ...$action === 'recover' ? ['session'] : [], ...in_array($action, ['verify', 'expire'], true) ? ['control'] : []];

        return ['query' => ['present', 'array', 'size:0'], 'body' => ['present', 'array:'.implode(',', $keys)],
            'body.expected_revision' => ['required', 'integer:strict', 'min:1'], 'operation_id' => ['required', 'uuid'],
            'body.note' => [$action === 'cancel' ? 'sometimes' : 'required', 'string', 'max:1000', 'regex:/\S/u'],
            ...$action === 'recover' ? ['body.session' => ['required', 'string', 'regex:/^cs_[A-Za-z0-9]{1,97}$/D']] : [],
            ...in_array($action, ['verify', 'expire'], true) ? ['body.control' => [$action === 'verify' ? 'required' : 'sometimes', 'ulid']] : []];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->is('api/*') && (! $this->isJson() || ! json_decode($this->getContent()) instanceof \stdClass)) {
                $validator->errors()->add('body', 'Send a JSON object.');
            }
        }];
    }
}
