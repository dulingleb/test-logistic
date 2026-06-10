<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannelEnum;
use InvalidArgumentException;

final class ProviderRegistry
{
    /** @var array<string, NotificationProvider> */
    private array $providers = [];

    /**
     * @param  iterable<NotificationProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->channel()->value] = $provider;
        }
    }

    public function for(NotificationChannelEnum $channel): NotificationProvider
    {
        return $this->providers[$channel->value]
            ?? throw new InvalidArgumentException("No provider registered for channel {$channel->value}");
    }
}
