<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use App\DTO\NewBulkDataDTO;
use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationPriorityEnum;
use PHPUnit\Framework\TestCase;

final class NewBulkDataDTOTest extends TestCase
{
    private function make(array $overrides = []): NewBulkDataDTO
    {
        return new NewBulkDataDTO(
            channel: $overrides['channel'] ?? NotificationChannelEnum::Email,
            priority: $overrides['priority'] ?? NotificationPriorityEnum::Transactional,
            message: $overrides['message'] ?? 'hello',
            recipients: $overrides['recipients'] ?? ['a@example.com', 'b@example.com'],
            idempotencyKey: $overrides['idempotencyKey'] ?? null,
        );
    }

    public function test_hash_is_deterministic_for_identical_payload(): void
    {
        $a = $this->make();
        $b = $this->make();

        self::assertSame($a->hash(), $b->hash());
    }

    public function test_hash_changes_when_message_changes(): void
    {
        $a = $this->make(['message' => 'one']);
        $b = $this->make(['message' => 'two']);

        self::assertNotSame($a->hash(), $b->hash());
    }

    public function test_hash_changes_when_recipient_order_changes(): void
    {
        $a = $this->make(['recipients' => ['x@example.com', 'y@example.com']]);
        $b = $this->make(['recipients' => ['y@example.com', 'x@example.com']]);

        self::assertNotSame(
            $a->hash(),
            $b->hash(),
            'recipient order is part of the request payload — different order must produce different hash',
        );
    }

    public function test_hash_changes_when_channel_changes(): void
    {
        $a = $this->make(['channel' => NotificationChannelEnum::Email, 'recipients' => ['z@example.com']]);
        $b = $this->make(['channel' => NotificationChannelEnum::Sms, 'recipients' => ['z@example.com']]);

        self::assertNotSame($a->hash(), $b->hash());
    }

    public function test_hash_ignores_idempotency_key(): void
    {
        $a = $this->make(['idempotencyKey' => 'key-1']);
        $b = $this->make(['idempotencyKey' => 'key-2']);

        self::assertSame(
            $a->hash(),
            $b->hash(),
            'Idempotency-Key is metadata about the request, not part of its content hash',
        );
    }
}
