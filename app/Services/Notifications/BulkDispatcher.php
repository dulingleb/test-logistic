<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\DTO\BulkDispatchResultDTO;
use App\DTO\NewBulkDataDTO;
use App\Enums\NotificationStatusEnum;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBulk;
use App\Models\NotificationEvent;
use App\Services\Idempotency\IdempotencyStoreService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BulkDispatcher
{
    public function __construct(
        private readonly IdempotencyStoreService $idempotency,
    ) {}

    public function dispatch(NewBulkDataDTO $data): BulkDispatchResultDTO
    {
        if ($data->idempotencyKey === null) {
            return new BulkDispatchResultDTO($this->persist($data, null), false);
        }

        $lock = Cache::lock('idempotency:lock:'.$data->idempotencyKey, 30);

        return $lock->block(5, function () use ($data): BulkDispatchResultDTO {
            $hash = $data->hash();

            $cached = $this->idempotency->find($data->idempotencyKey, $hash);
            if ($cached !== null) {
                return new BulkDispatchResultDTO($cached, true);
            }

            $response = $this->persist($data, $hash);
            $this->idempotency->cacheResponse($data->idempotencyKey, $hash, $response);

            return new BulkDispatchResultDTO($response, false);
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function persist(NewBulkDataDTO $data, ?string $hash): array
    {
        return DB::transaction(function () use ($data, $hash): array {
            $now = Carbon::now();

            $bulk = NotificationBulk::create([
                'idempotency_key' => $data->idempotencyKey,
                'channel' => $data->channel,
                'priority' => $data->priority,
                'message' => $data->message,
                'recipients_count' => count($data->recipients),
            ]);

            $notifications = [];
            $events = [];
            foreach ($data->recipients as $recipient) {
                $id = (string) Str::uuid();
                $notifications[] = [
                    'id' => $id,
                    'bulk_id' => $bulk->id,
                    'recipient_id' => $recipient,
                    'channel' => $data->channel->value,
                    'priority' => $data->priority->value,
                    'status' => NotificationStatusEnum::Queued->value,
                    'attempts' => 0,
                    'last_error' => null,
                    'payload' => null,
                    'queued_at' => $now,
                    'sent_at' => null,
                    'delivered_at' => null,
                    'failed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $events[] = [
                    'notification_id' => $id,
                    'status' => NotificationStatusEnum::Queued->value,
                    'meta' => null,
                    'occurred_at' => $now,
                ];
            }

            Notification::insert($notifications);
            NotificationEvent::insert($events);

            $response = [
                'bulk_id' => $bulk->id,
                'channel' => $bulk->channel->value,
                'priority' => $bulk->priority->value,
                'recipients_count' => $bulk->recipients_count,
                'status' => NotificationStatusEnum::Queued->value,
                'accepted_at' => $now->toIso8601String(),
            ];

            if ($data->idempotencyKey !== null && $hash !== null) {
                $this->idempotency->persistRecord(
                    $data->idempotencyKey,
                    $hash,
                    (string) $bulk->id,
                    $response,
                );
            }

            $queueName = $data->priority->queueName();
            DB::afterCommit(function () use ($notifications, $queueName): void {
                foreach ($notifications as $row) {
                    SendNotificationJob::dispatch($row['id'])->onQueue($queueName);
                }
            });

            return $response;
        });
    }
}
