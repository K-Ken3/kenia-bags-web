<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index('order_id');
            $table->index(['provider', 'status', 'created_at']);
        });

        Schema::table('payment_events', function (Blueprint $table) {
            $table->index('payment_id');
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('order_items', fn (Blueprint $table) => $table->dropIndex(['order_id']));

        Schema::table('payment_proofs', fn (Blueprint $table) => $table->dropIndex(['status', 'created_at']));

        Schema::table('payment_events', fn (Blueprint $table) => $table->dropIndex(['payment_id']));

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['provider', 'status', 'created_at']);
        });
    }
};
