<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class OrderReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! in_array($this->method(), ['GET', 'HEAD'], true) && ! $this->is('api/*')) {
            $this->session()->put('commerce.review_input_owner', [$this->user()->id, $this->route('order')]);
        }
    }

    public function validationData(): array
    {
        abort_if(strlen($this->getContent()) > 262144, 413);
        $read = in_array($this->method(), ['GET', 'HEAD'], true);
        $body = $read ? [] : ($this->is('api/*') ? $this->json()->all() : $this->except('_token', 'operation_id'));
        if (! $read && ! $this->is('api/*')) {
            if (is_string($body['expected_revision'] ?? null) && preg_match('/^[1-9][0-9]{0,17}$/D', $body['expected_revision'])) {
                $body['expected_revision'] = (int) $body['expected_revision'];
            }
            if (array_key_exists('accept_terms', $body)) {
                $body['accept_terms'] = $body['accept_terms'] === '1';
            }
            foreach (['brief' => ['goal', 'scope', 'website'], 'billing' => ['name', 'email', 'country', 'address', 'company', 'phone']] as $group => $fields) {
                foreach ($fields as $field) {
                    if (is_array($body[$group] ?? null) && array_key_exists($field, $body[$group]) && $body[$group][$field] === null) {
                        unset($body[$group][$field]);
                    }
                }
            }
        }

        return ['body' => $body, 'query' => $this->query(), ...$read ? [] : ['operation_id' => $this->is('api/*') ? $this->header('Idempotency-Key') : $this->input('operation_id')]];
    }

    public function rules(): array
    {
        $read = in_array($this->method(), ['GET', 'HEAD'], true);
        $acceptance = ! $read && in_array($this->route('command'), ['resubmit', 'accept_proposal'], true);
        $command = $this->route('command');
        $keys = match ($command) {
            'approve' => ['expected_revision'],
            'resubmit' => ['expected_revision', 'brief', 'billing', 'accept_terms'],
            'accept_proposal' => ['expected_revision', 'brief', 'billing', 'accept_terms', 'proposal'],
            null => ['expected_version', 'content'],
            default => ['expected_revision', 'message'],
        };

        return ['query' => ['present', $read ? 'array:page' : 'array', ...$read ? [] : ['size:0']],
            'query.page' => ['sometimes', 'integer', 'between:1,10000'], 'body' => ['present', $read ? 'array' : 'array:'.implode(',', $keys), ...$read ? ['size:0'] : []],
            ...$read ? [] : ['operation_id' => ['required', 'uuid']],
            ...! $read && $command !== null ? ['body.expected_revision' => ['required', 'integer:strict', 'min:1']] : [],
            ...$acceptance ? ['body.brief' => ['present', 'array'], 'body.billing' => ['present', 'array']] : [],
            ...$acceptance ? ['body.accept_terms' => ['required', 'accepted']] : []];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $read = in_array($this->method(), ['GET', 'HEAD'], true);
            if ($read && $this->getContent() !== '') {
                $validator->errors()->add('body', 'Read requests must not include a body.');
            }
            if (! $read && $this->is('api/*') && (! $this->isJson() || ! json_decode($this->getContent()) instanceof \stdClass)) {
                $validator->errors()->add('body', 'Send a JSON object.');
            }
            if (! $read && $this->is('api/*') && in_array($this->route('command'), ['resubmit', 'accept_proposal'], true)) {
                $body = json_decode($this->getContent());
                if (! ($body->brief ?? null) instanceof \stdClass || ! ($body->billing ?? null) instanceof \stdClass) {
                    $validator->errors()->add('body', 'Brief and billing must be objects.');
                }
            }
        }];
    }
}
