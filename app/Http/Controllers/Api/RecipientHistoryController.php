<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\Notifications\RecipientHistoryQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecipientHistoryController extends Controller
{
    public function __construct(
        private readonly RecipientHistoryQuery $query,
    ) {}

    public function show(Request $request, string $recipient_id): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 50), 200));

        return NotificationResource::collection(
            $this->query->forRecipient($recipient_id, $perPage),
        );
    }
}
