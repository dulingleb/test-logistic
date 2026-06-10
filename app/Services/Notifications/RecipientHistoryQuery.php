<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class RecipientHistoryQuery
{
    public function forRecipient(string $recipientId, int $perPage = 50): LengthAwarePaginator
    {
        return Notification::query()
            ->where('recipient_id', $recipientId)
            ->with('events')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
