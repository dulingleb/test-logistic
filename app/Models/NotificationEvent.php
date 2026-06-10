<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'notification_id',
        'status',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'status' => NotificationStatusEnum::class,
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
