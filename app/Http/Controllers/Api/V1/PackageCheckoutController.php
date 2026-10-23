<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PackageCheckoutRequest;
use App\Http\Resources\PackageCheckoutResource;
use App\Models\PackageCheckout;
use App\Services\PackageCheckout\PackageCheckouts;
use App\Services\PackageCheckout\PackageOffers;
use App\Services\PackageCheckout\QrphClient;
use Illuminate\Http\Request;

class PackageCheckoutController extends Controller
{
    public function offer(string $slug, PackageOffers $offers, QrphClient $provider)
    {
        return response()->json(['data' => [...$offers->get($slug), 'available' => $offers->available($slug), 'test_mode' => ! $provider->live()]]);
    }

    public function store(PackageCheckoutRequest $request, PackageCheckouts $checkouts)
    {
        [$checkout, $created] = $checkouts->create($request->validated(), $this->proof($request));

        return (new PackageCheckoutResource($checkout))->response()->setStatusCode($created ? 201 : 200);
    }

    public function show(Request $request, string $reference, PackageCheckouts $checkouts)
    {
        return new PackageCheckoutResource($checkouts->read($reference, $this->proof($request)));
    }

    public function resolve(Request $request, string $operation, PackageCheckouts $checkouts)
    {
        $reference = PackageCheckout::query()->where('operation_id', strtolower($operation))->value('id');
        abort_unless(is_string($reference), 404);

        return new PackageCheckoutResource($checkouts->read($reference, $this->proof($request)));
    }

    private function proof(Request $request): string
    {
        $proof = $request->header('X-Checkout-Proof');
        abort_unless(is_string($proof) && preg_match('/^(?:[a-f0-9]{64}|[A-Za-z0-9_-]{43})$/D', $proof), 404);

        return $proof;
    }
}
