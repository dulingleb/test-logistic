<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\NotificationStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $secret = (string) config('notifications.webhook_secret', '');
        if ($secret === '') {
            return true;
        }

        $provided = (string) $this->header('X-Webhook-Secret', '');

        return hash_equals($secret, $provided);
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'provider_message_id' => ['required', 'string', 'max:128'],
            'status' => [
                'required',
                'string',
                Rule::in([
                    NotificationStatusEnum::Delivered->value,
                    NotificationStatusEnum::Failed->value,
                ]),
            ],
            'reason' => ['nullable', 'string', 'max:65535'],
            'occurred_at' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
