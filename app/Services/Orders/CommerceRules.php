<?php

namespace App\Services\Orders;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CommerceRules
{
    public static function defaults(): array
    {
        return [
            'tax_mode' => 'unconfigured', 'tax_basis_points' => 0, 'document_kind' => 'order_summary',
            'required_brief' => ['goal', 'scope'], 'required_billing' => ['name', 'email', 'country'],
            'approval_days' => 30,
            'terms' => 'Services are reviewed by Admin before payment. Your accepted service scope, price and terms form the request. We may ask questions or request corrections before approval. Payment and fulfillment follow the approved scope. Contact Admin through your account about changes or cancellation; no automatic refund promise is made.',
            'privacy' => 'We use your account, project brief, billing details and messages to review and deliver requested services, communicate with you, keep necessary records and protect the service. Authorized administrators and necessary service providers may process these details. Marketing requires a separate choice. You may request access, correction or deletion through your account, subject to required accounting retention and legal holds. Active carts do not expire by time.',
            'retention' => ['draft_days' => 90, 'closed_request_days' => 365, 'financial_years' => 5, 'audit_days' => 365],
        ];
    }

    public static function validate(array $content): array
    {
        Validator::make(['content' => $content], [
            'content' => ['required', 'array:tax_mode,tax_basis_points,document_kind,required_brief,required_billing,approval_days,terms,privacy,retention'],
            'content.tax_mode' => ['required', Rule::in(['unconfigured', 'exclusive', 'inclusive'])],
            'content.tax_basis_points' => ['required', 'integer:strict', 'between:0,10000'],
            'content.document_kind' => ['required', Rule::in(['order_summary'])],
            'content.required_brief' => ['present', 'array', 'list'],
            'content.required_brief.*' => ['string', 'distinct', Rule::in(['goal', 'scope', 'website'])],
            'content.required_billing' => ['present', 'array', 'list'],
            'content.required_billing.*' => ['string', 'distinct', Rule::in(['name', 'email', 'country', 'address', 'company', 'phone'])],
            'content.approval_days' => ['required', 'integer:strict', 'between:1,365'],
            'content.terms' => ['required', 'string', 'max:50000', 'regex:/\S/u'],
            'content.privacy' => ['required', 'string', 'max:50000', 'regex:/\S/u'],
            'content.retention' => ['required', 'array:draft_days,closed_request_days,financial_years,audit_days'],
            'content.retention.draft_days' => ['required', 'integer:strict', 'between:1,3650'],
            'content.retention.closed_request_days' => ['required', 'integer:strict', 'between:1,3650'],
            'content.retention.financial_years' => ['required', 'integer:strict', 'between:1,100'],
            'content.retention.audit_days' => ['required', 'integer:strict', 'between:1,3650'],
        ])->validate();
        abort_if($content['tax_mode'] === 'unconfigured' && $content['tax_basis_points'] !== 0, 422);

        return $content;
    }

    public static function submission(array $input, array $rules): array
    {
        $validation = [
            'input' => ['required', 'array:brief,billing,accept_terms,fingerprint'],
            'input.fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'input.accept_terms' => ['required', Rule::in([true])],
            'input.brief' => ['present', 'array:goal,scope,website'],
            'input.billing' => ['present', 'array:name,email,country,address,company,phone'],
        ];
        foreach (['brief' => ['goal' => 2000, 'scope' => 10000, 'website' => 2048],
            'billing' => ['name' => 255, 'email' => 255, 'country' => 2, 'address' => 2000, 'company' => 255, 'phone' => 50]] as $group => $fields) {
            foreach ($fields as $field => $limit) {
                $validation['input.'.$group.'.'.$field] = [in_array($field, $rules['required_'.$group], true) ? 'required' : 'sometimes', 'string', 'max:'.$limit, 'regex:/\S/u'];
            }
        }
        $validation['input.billing.email'][] = 'email:rfc';
        $validation['input.billing.country'][] = 'regex:/^[A-Z]{2}$/D';
        $validation['input.brief.website'][] = 'url:http,https';
        Validator::make(['input' => $input], $validation)->validate();
        abort_unless($input['accept_terms'] === true, 422);

        return $input;
    }
}
