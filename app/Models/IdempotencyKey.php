<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'key',
        'request_hash',
        'bulk_id',
        'response',
        'created_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function bulk(): BelongsTo
    {
        return $this->belongsTo(NotificationBulk::class, 'bulk_id');
    }
}
