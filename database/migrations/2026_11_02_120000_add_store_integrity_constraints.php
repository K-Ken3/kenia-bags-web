<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unique('slug');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique('slug');
            $table->index(['status', 'price_rwf']);
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('customer_phone');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('payment_events', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['order_id']);
            $table->dropForeign(['reviewed_by']);
        });
        Schema::table('payment_events', fn (Blueprint $table) => $table->dropForeign(['payment_id']));
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['verified_by']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['product_variant_id']);
            $table->dropForeign(['variant_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['customer_phone']);
        });
        Schema::table('product_images', fn (Blueprint $table) => $table->dropForeign(['product_id']));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropForeign(['product_id']));
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropUnique(['slug']);
            $table->dropIndex(['status', 'price_rwf']);
        });
        Schema::table('categories', fn (Blueprint $table) => $table->dropUnique(['slug']));
    }
};
