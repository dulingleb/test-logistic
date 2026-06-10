<?php

declare(strict_types=1);

namespace App\Services\Idempotency;

use App\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class IdempotencyStoreService
{
    public function __construct(
        private readonly int $ttlSeconds = 86400,
    ) {}

    /**
     * Returns the cached response body if the key was already processed
     * with the same request hash. Throws on hash mismatch.
     *
     * @return array<string,mixed>|null
     */
    public function find(string $key, string $requestHash): ?array
    {
        $cached = Cache::get($this->cacheKey($key));
        if (is_array($cached)) {
            $this->assertHashMatches($key, $requestHash, (string) $cached['request_hash']);

            /** @var array<string,mixed> $body */
            $body = $cached['response'];

            return $body;
        }

        $record = IdempotencyKey::find($key);
        if ($record === null) {
            return null;
        }
        if ($record->expires_at !== null && $record->expires_at->isPast()) {
            $record->delete();

            return null;
        }
        $this->assertHashMatches($key, $requestHash, $record->request_hash);

        /** @var array<string,mixed> $response */
        $response = $record->response ?? [];

        Cache::put(
            $this->cacheKey($key),
            ['request_hash' => $record->request_hash, 'response' => $response],
            $record->expires_at ?? Carbon::now()->addSeconds($this->ttlSeconds),
        );

        return $response;
    }

    /**
     * Persist DB record. MUST be called inside the same transaction as the bulk write.
     *
     * @param  array<string,mixed>  $responseBody
     */
    public function persistRecord(string $key, string $requestHash, string $bulkId, array $responseBody): void
    {
        IdempotencyKey::create([
            'key' => $key,
            'request_hash' => $requestHash,
            'bulk_id' => $bulkId,
            'response' => $responseBody,
            'created_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addSeconds($this->ttlSeconds),
        ]);
    }

    /**
     * Warm Redis after the DB transaction has committed. Best-effort.
     *
     * @param  array<string,mixed>  $responseBody
     */
    public function cacheResponse(string $key, string $requestHash, array $responseBody): void
    {
        Cache::put(
            $this->cacheKey($key),
            ['request_hash' => $requestHash, 'response' => $responseBody],
            Carbon::now()->addSeconds($this->ttlSeconds),
        );
    }

    private function assertHashMatches(string $key, string $expected, string $actual): void
    {
        if (! hash_equals($expected, $actual)) {
            throw new IdempotencyConflictException($key);
        }
    }

    private function cacheKey(string $key): string
    {
        return 'idempotency:'.$key;
    }
}
