<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CommercePolicyVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class OrderReviewResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $review = $this->resource;
        $proposal = $review['proposal'];
        $offer = null;
        if ($proposal !== null) {
            $policy = CommercePolicyVersion::findOrFail($proposal->policy_version_id);
            $offer = ['public_id' => $proposal->public_id, 'is_current' => $review['proposal_current'], 'created_at' => $proposal->created_at->toIso8601String(),
                ...Arr::only($proposal->content, ['currency', 'subtotal_minor', 'tax_minor', 'total_minor']),
                'policy' => ['public_id' => $policy->public_id, 'version' => $policy->version, ...$policy->content],
                'lines' => array_map(fn ($line) => [
                    ...Arr::only($line['snapshot'], ['public_id', 'name', 'quantity', 'amount_minor', 'currency', 'price_version', 'terms_version']),
                    'terms' => (object) (new ProductTermsResource($line['snapshot']['terms']))->resolve($request),
                ], $proposal->content['lines'])];
        }
        $messages = $review['messages'];

        return ['order' => (new OrderResource($review['order']))->resolve($request), 'proposal' => $offer,
            'messages' => $messages->getCollection()->map(fn ($message) => $message->only(['public_id', 'audience', 'sender', 'body']) + ['created_at' => $message->created_at->toIso8601String()])->all(),
            'pagination' => ['page' => $messages->currentPage(), 'per_page' => 20, 'total' => $messages->total(), 'last_page' => $messages->lastPage()]];
    }

    public function with(Request $request): array
    {
        return ['meta' => ['api_version' => 'v1', 'csrf_token' => $request->session()->token()]];
    }
}
