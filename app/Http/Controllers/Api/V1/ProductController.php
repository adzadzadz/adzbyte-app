<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CatalogDetailRequest;
use App\Http\Requests\Api\V1\CatalogIndexRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Services\Catalog\PublicCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProductController
{
    public function index(CatalogIndexRequest $request, PublicCatalog $catalog): JsonResponse
    {
        $input = $request->validated();
        $page = $catalog->paginate((int) ($input['per_page'] ?? 20), (int) ($input['page'] ?? 1),
            isset($input['available']) ? $input['available'] === '1' : null, $input['category'] ?? null);
        $link = fn (int $number): string => '/api/v1/products?'.http_build_query([
            'per_page' => $page->perPage(),
            ...isset($input['available']) ? ['available' => $input['available']] : [],
            ...isset($input['include']) ? ['include' => $input['include']] : [],
            ...isset($input['category']) ? ['category' => $input['category']] : [],
            'page' => $number,
        ]);

        return $this->respond($request, [
            'data' => $page->getCollection()->map(fn ($product) => (new ProductResource($product))->resolve($request))->all(),
            'meta' => [
                'api_version' => 'v1', 'current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'last_page' => $page->lastPage(),
            ],
            'links' => [
                'next' => $page->hasMorePages() && $page->currentPage() < 10000 ? $link($page->currentPage() + 1) : null,
                'prev' => $page->currentPage() > 1 ? $link($page->currentPage() - 1) : null,
            ],
        ]);
    }

    public function show(CatalogDetailRequest $request, PublicCatalog $catalog, string $product): JsonResponse
    {
        return $this->respond($request, [
            'data' => (new ProductResource($catalog->find($product)))->resolve($request),
            'meta' => ['api_version' => 'v1'],
        ]);
    }

    private function respond(Request $request, array $payload): JsonResponse
    {
        $response = response()->json($payload);
        $response->setEtag(hash('sha256', $response->getContent()));
        $response->headers->set('Cache-Control', 'public, no-cache, must-revalidate');
        $response->isNotModified($request);

        return $response;
    }
}
