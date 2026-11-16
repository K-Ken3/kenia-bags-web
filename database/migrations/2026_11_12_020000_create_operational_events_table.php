<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('severity', 16);
            $table->string('message', 255);
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['type', 'occurred_at']);
            $table->index(['severity', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_events');
    }
};
