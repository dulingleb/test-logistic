<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_bulks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('channel', 16);
            $table->string('priority', 16);
            $table->text('message');
            $table->unsignedInteger('recipients_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_bulks');
    }
};
