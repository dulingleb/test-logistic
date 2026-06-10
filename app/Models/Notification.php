<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationPriorityEnum;
use App\Enums\NotificationStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'bulk_id',
        'recipient_id',
        'channel',
        'priority',
        'status',
        'attempts',
        'last_error',
        'payload',
        'provider_message_id',
        'queued_at',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected $casts = [
        'channel' => NotificationChannelEnum::class,
        'priority' => NotificationPriorityEnum::class,
        'status' => NotificationStatusEnum::class,
        'attempts' => 'integer',
        'payload' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function bulk(): BelongsTo
    {
        return $this->belongsTo(NotificationBulk::class, 'bulk_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(NotificationEvent::class)->orderBy('occurred_at');
    }
}
