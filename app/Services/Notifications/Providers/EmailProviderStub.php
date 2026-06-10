<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannelEnum;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class EmailProviderStub implements NotificationProvider
{
    use SimulatesAsyncDelivery;
    use SimulatesFailures;
    use SimulatesLatency;

    public function channel(): NotificationChannelEnum
    {
        return NotificationChannelEnum::Email;
    }

    public function send(Notification $notification, string $message): ProviderSendResult
    {
        $this->maybeFail('email');
        $this->maybeDelay();

        $providerMessageId = 'email_'.Str::uuid()->toString();

        Log::channel('notifications')->info('provider.send', [
            'event' => 'provider.send',
            'provider' => 'email-stub',
            'channel' => 'email',
            'notification_id' => $notification->id,
            'recipient_id' => $notification->recipient_id,
            'provider_message_id' => $providerMessageId,
            'message_bytes' => strlen($message),
        ]);

        $this->scheduleDeliveryCallback('email', $providerMessageId);

        return new ProviderSendResult($providerMessageId);
    }
}
