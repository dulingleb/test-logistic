<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class BulkDispatchResultDTO
{
    /**
     * @param  array<string,mixed>  $body
     */
    public function __construct(
        public array $body,
        public bool $replayed,
    ) {}
}
