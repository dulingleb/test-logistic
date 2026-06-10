<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannelEnum;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class SmsProviderStub implements NotificationProvider
{
    use SimulatesAsyncDelivery;
    use SimulatesFailures;
    use SimulatesLatency;

    public function channel(): NotificationChannelEnum
    {
        return NotificationChannelEnum::Sms;
    }

    public function send(Notification $notification, string $message): ProviderSendResult
    {
        $this->maybeFail('sms');
        $this->maybeDelay();

        $providerMessageId = 'sms_'.Str::uuid()->toString();

        Log::channel('notifications')->info('provider.send', [
            'event' => 'provider.send',
            'provider' => 'sms-stub',
            'channel' => 'sms',
            'notification_id' => $notification->id,
            'recipient_id' => $notification->recipient_id,
            'provider_message_id' => $providerMessageId,
            'message_bytes' => strlen($message),
        ]);

        $this->scheduleDeliveryCallback('sms', $providerMessageId);

        return new ProviderSendResult($providerMessageId);
    }
}
