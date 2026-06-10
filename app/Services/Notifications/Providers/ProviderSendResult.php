<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

final readonly class ProviderSendResult
{
    public function __construct(
        public string $providerMessageId,
    ) {}
}
