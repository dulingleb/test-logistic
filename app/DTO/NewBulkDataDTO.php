<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationPriorityEnum;
use App\Http\Requests\StoreBulkNotificationRequest;

final readonly class NewBulkDataDTO
{
    /**
     * @param  list<string>  $recipients
     */
    public function __construct(
        public NotificationChannelEnum  $channel,
        public NotificationPriorityEnum $priority,
        public string                   $message,
        public array                    $recipients,
        public ?string                  $idempotencyKey,
    ) {}

    public static function fromRequest(StoreBulkNotificationRequest $request): self
    {
        /** @var array{channel:string, priority:string, message:string, recipients:array<int,string>} $v */
        $v = $request->validated();

        return new self(
            channel: NotificationChannelEnum::from($v['channel']),
            priority: NotificationPriorityEnum::from($v['priority']),
            message: $v['message'],
            recipients: array_values($v['recipients']),
            idempotencyKey: $request->idempotencyKey(),
        );
    }

    public function hash(): string
    {
        $canonical = json_encode([
            'channel' => $this->channel->value,
            'priority' => $this->priority->value,
            'message' => $this->message,
            'recipients' => $this->recipients,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $canonical);
    }
}
