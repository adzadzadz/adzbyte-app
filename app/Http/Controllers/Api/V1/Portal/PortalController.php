<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Actions\Portal\PortalActions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Portal\SupportCaseResource;
use App\Http\Resources\Api\V1\Portal\WorkResource;
use App\Services\Portal\PortalRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PortalController extends Controller
{
    public function works(Request $request, PortalRead $read): JsonResponse
    {
        $this->query($request, ['type', 'page', 'search']);
        $input = $request->validate(['type' => ['nullable', 'in:project,service'], 'page' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'search' => ['sometimes', 'string', 'max:100']]);

        return response()->json(['data' => $read->works($request->user(), $input['type'] ?? null, false, (int) ($input['page'] ?? 1), $input['search'] ?? '')]);
    }

    public function work(Request $request, string $reference, PortalRead $read): WorkResource
    {
        $this->query($request, ['page']);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);

        return new WorkResource($read->work($request->user(), $reference, false, (int) $request->query('page', 1)));
    }

    public function cases(Request $request, PortalRead $read): JsonResponse
    {
        $this->query($request, ['page', 'search']);
        $input = $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'search' => ['sometimes', 'string', 'max:100']]);

        return response()->json(['data' => $read->cases($request->user(), false, (int) ($input['page'] ?? 1), $input['search'] ?? '')]);
    }

    public function supportCase(Request $request, string $reference, PortalRead $read): SupportCaseResource
    {
        $this->query($request, ['page']);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);

        return new SupportCaseResource($read->supportCase($request->user(), $reference, false, (int) $request->query('page', 1)));
    }

    public function store(Request $request, PortalActions $actions, PortalRead $read): SupportCaseResource
    {
        $this->query($request, []);
        $case = $actions->createCase($request->user(), $request->all(), (string) $request->header('Idempotency-Key'));

        return new SupportCaseResource($read->supportCase($request->user(), $case->public_id));
    }

    public function reply(Request $request, string $reference, PortalActions $actions, PortalRead $read): SupportCaseResource
    {
        $this->query($request, []);
        $case = $actions->reply($request->user(), $reference, $request->all(), (string) $request->header('Idempotency-Key'));

        return new SupportCaseResource($read->supportCase($request->user(), $case->public_id));
    }

    public function categories(Request $request, PortalRead $read): JsonResponse
    {
        $this->query($request, []);

        return response()->json(['data' => $read->categories()]);
    }

    public function read(Request $request, string $reference, PortalRead $read): JsonResponse
    {
        $this->query($request, []);
        if ($request->all() !== []) {
            throw ValidationException::withMessages(['input' => 'This action takes no fields.']);
        }
        if ($request->routeIs('api.v1.portal.works.read')) {
            $read->markWorkRead($request->user(), $reference);
        } else {
            $read->markCaseRead($request->user(), $reference);
        }

        return response()->json(['data' => ['read' => true]]);
    }

    private function query(Request $request, array $keys): void
    {
        if (array_diff(array_keys($request->query()), $keys)) {
            throw ValidationException::withMessages(['query' => 'Unexpected query parameters.']);
        }
    }
}
