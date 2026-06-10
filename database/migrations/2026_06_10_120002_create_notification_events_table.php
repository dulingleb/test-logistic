<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('notification_id')
                ->constrained('notifications')
                ->cascadeOnDelete();
            $table->string('status', 16);
            $table->jsonb('meta')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();

            $table->index(['notification_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};
