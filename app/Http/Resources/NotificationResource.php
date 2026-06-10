<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Notification $resource
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'recipient_id' => $this->resource->recipient_id,
            'channel' => $this->resource->channel->value,
            'priority' => $this->resource->priority->value,
            'status' => $this->resource->status->value,
            'attempts' => $this->resource->attempts,
            'last_error' => $this->resource->last_error,
            'queued_at' => $this->resource->queued_at?->toIso8601String(),
            'sent_at' => $this->resource->sent_at?->toIso8601String(),
            'delivered_at' => $this->resource->delivered_at?->toIso8601String(),
            'failed_at' => $this->resource->failed_at?->toIso8601String(),
            'events' => NotificationEventResource::collection(
                $this->whenLoaded('events'),
            ),
        ];
    }
}
