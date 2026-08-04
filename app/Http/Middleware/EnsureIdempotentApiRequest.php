<?php

namespace App\Http\Middleware;

use App\Enums\ApiIdempotencyStatus;
use App\Http\Responses\ApiErrorResponse;
use App\Models\ApiIdempotencyKey;
use Closure;
use Illuminate\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnsureIdempotentApiRequest
{
    private const HEADER = 'Idempotency-Key';

    /** @var list<string> */
    private const MUTATION_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::MUTATION_METHODS, true)) {
            return ApiErrorResponse::make(
                'idempotency_not_supported',
                'Idempotency keys are supported only for mutation requests.',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $key = $request->header(self::HEADER);

        if (! is_string($key) || $key === '') {
            return ApiErrorResponse::make(
                'idempotency_key_required',
                'An Idempotency-Key header is required.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (! $this->isValidKey($key)) {
            return ApiErrorResponse::make(
                'invalid_idempotency_key',
                'The Idempotency-Key must contain 8 to 255 URL-safe characters.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user = $request->user();

        if ($user === null) {
            return ApiErrorResponse::make(
                'unauthenticated',
                'Authentication is required.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        try {
            $scopeHash = $this->scopeHash($request, $user::class, (string) $user->getAuthIdentifier());
            $keyHash = hash('sha256', $key);
            $fingerprint = $this->requestFingerprint($request);
        } catch (JsonException) {
            return ApiErrorResponse::make(
                'request_fingerprint_failed',
                'The request body could not be prepared for idempotent processing.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $lockName = "api-idempotency:{$scopeHash}:{$keyHash}";

        try {
            return Cache::lock(
                $lockName,
                max(1, (int) config('api.idempotency.lock_seconds', 300)),
            )->block(
                max(0, (int) config('api.idempotency.lock_wait_seconds', 5)),
                fn (): Response => $this->process($request, $next, $scopeHash, $keyHash, $fingerprint),
            );
        } catch (LockTimeoutException) {
            return $this->inProgressResponse();
        }
    }

    private function process(
        Request $request,
        Closure $next,
        string $scopeHash,
        string $keyHash,
        string $fingerprint,
    ): Response {
        $record = ApiIdempotencyKey::query()
            ->where('scope_hash', $scopeHash)
            ->where('key_hash', $keyHash)
            ->first();

        if ($record !== null && $this->isExpiredOrStale($record)) {
            $record->delete();
            $record = null;
        }

        if ($record !== null) {
            if (! hash_equals($record->request_fingerprint, $fingerprint)) {
                return ApiErrorResponse::make(
                    'idempotency_key_reused',
                    'This Idempotency-Key was already used for a different request.',
                    Response::HTTP_CONFLICT,
                );
            }

            if ($record->status === ApiIdempotencyStatus::Completed) {
                return $this->replay($record);
            }

            return $this->inProgressResponse();
        }

        $record = ApiIdempotencyKey::query()->create([
            'scope_hash' => $scopeHash,
            'key_hash' => $keyHash,
            'request_fingerprint' => $fingerprint,
            'status' => ApiIdempotencyStatus::Pending,
            'expires_at' => now()->addHours(
                max(1, (int) config('api.idempotency.ttl_hours', 24)),
            ),
        ]);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $record->delete();

            throw $exception;
        }

        $body = $response->getContent();

        if ($response->getStatusCode() >= 500 || $body === false) {
            $record->delete();

            return $response;
        }

        $record->forceFill([
            'status' => ApiIdempotencyStatus::Completed,
            'response_status' => $response->getStatusCode(),
            'response_headers' => $this->replayableHeaders($response),
            'response_body' => $body,
        ])->save();

        $response->headers->set('Idempotency-Replayed', 'false');

        return $response;
    }

    private function replay(ApiIdempotencyKey $record): Response
    {
        $response = response(
            $record->response_body ?? '',
            $record->response_status ?? Response::HTTP_OK,
            $record->response_headers ?? [],
        );

        $response->headers->set('Idempotency-Replayed', 'true');

        return $response;
    }

    private function inProgressResponse(): Response
    {
        return ApiErrorResponse::make(
            'idempotency_in_progress',
            'A request with this Idempotency-Key is already being processed.',
            Response::HTTP_CONFLICT,
            headers: ['Retry-After' => '1'],
        );
    }

    private function isExpiredOrStale(ApiIdempotencyKey $record): bool
    {
        if ($record->expires_at->isPast()) {
            return true;
        }

        return $record->status === ApiIdempotencyStatus::Pending
            && $record->updated_at->lte(now()->subSeconds(
                max(1, (int) config('api.idempotency.pending_timeout_seconds', 900)),
            ));
    }

    private function isValidKey(string $key): bool
    {
        $length = strlen($key);

        return $length >= 8
            && $length <= 255
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/D', $key) === 1;
    }

    private function scopeHash(Request $request, string $userType, string $userId): string
    {
        $route = $request->route();
        $routeIdentity = $route?->getName() ?? $route?->uri() ?? $request->path();

        return hash('sha256', implode('|', [
            $userType,
            $userId,
            $request->method(),
            $routeIdentity,
        ]));
    }

    /**
     * @throws JsonException
     */
    private function requestFingerprint(Request $request): string
    {
        $contentType = strtolower(trim(strtok((string) $request->header('Content-Type'), ';')));
        $input = [
            'query' => $request->query->all(),
            'body' => $request->request->all(),
            'files' => $this->fingerprintFiles($request->allFiles()),
            'content_type' => $contentType,
        ];

        return hash('sha256', json_encode(
            $this->canonicalize($input),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * @param  array<string, UploadedFile|array<mixed>>  $files
     * @return array<string, mixed>
     */
    private function fingerprintFiles(array $files): array
    {
        $fingerprints = [];

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $fingerprints[$key] = $this->fingerprintFiles($file);

                continue;
            }

            $path = $file->getRealPath();
            $fingerprints[$key] = [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getClientMimeType(),
                'sha256' => is_string($path) ? hash_file('sha256', $path) : null,
            ];
        }

        return $fingerprints;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }

    /**
     * @return array<string, string>
     */
    private function replayableHeaders(Response $response): array
    {
        $headers = [];

        foreach (['Content-Type', 'Location', 'ETag'] as $name) {
            $value = $response->headers->get($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
