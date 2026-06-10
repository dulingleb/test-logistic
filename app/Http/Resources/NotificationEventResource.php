<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NotificationEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read NotificationEvent $resource
 */
class NotificationEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource->status->value,
            'occurred_at' => $this->resource->occurred_at?->toIso8601String(),
            'meta' => $this->resource->meta,
        ];
    }
}
