<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->string('request_hash', 64);
            $table->foreignUuid('bulk_id')
                ->nullable()
                ->constrained('notification_bulks')
                ->nullOnDelete();
            $table->jsonb('response')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('expires_at');

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
