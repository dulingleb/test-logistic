<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationPriorityEnum;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $channel = $this->input('channel');

        $recipientRule = match ($channel) {
            NotificationChannelEnum::Sms->value => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            NotificationChannelEnum::Email->value => ['required', 'string', 'email:rfc'],
            default => ['required', 'string'],
        };

        return [
            'channel' => ['required', 'string', Rule::enum(NotificationChannelEnum::class)],
            'priority' => ['required', 'string', Rule::enum(NotificationPriorityEnum::class)],
            'message' => ['required', 'string', 'min:1', 'max:10000'],
            'recipients' => ['required', 'array', 'min:1', 'max:10000'],
            'recipients.*' => $recipientRule,
        ];
    }

    /**
     * @return array<int, Closure(Validator):void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $key = $this->header('Idempotency-Key');
                if ($key === null) {
                    return;
                }
                $length = strlen($key);
                if ($length < 1 || $length > 64) {
                    $validator->errors()->add('Idempotency-Key', 'Header must be 1-64 chars.');
                }
            },
        ];
    }

    public function idempotencyKey(): ?string
    {
        return $this->header('Idempotency-Key');
    }
}
