<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name')->default('');
            $table->string('slug')->default('');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name')->default('');
            $table->string('slug')->default('');
            $table->text('description')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name')->default('');
            $table->string('sku')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('path')->default('');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('position')->default(0);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('order_number')->default('');
            $table->string('customer_name')->default('');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->default('');
            $table->text('shipping_address')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('pending');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('product_name')->default('');
            $table->string('variant_name')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('line_total', 12, 2)->default(0);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('method')->default('manual');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('reference')->nullable();
        });

        Schema::table('payment_events', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('event_type')->default('');
            $table->json('payload')->nullable();
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('path')->default('');
            $table->string('reference')->nullable();
            $table->string('status')->default('pending');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('key')->default('');
            $table->text('value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', fn (Blueprint $table) => $table->dropColumn(['key', 'value']));
        Schema::table('payment_proofs', fn (Blueprint $table) => $table->dropColumn(['payment_id', 'order_id', 'path', 'reference', 'status']));
        Schema::table('payment_events', fn (Blueprint $table) => $table->dropColumn(['payment_id', 'event_type', 'payload']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['order_id', 'method', 'amount', 'status', 'reference']));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['order_id', 'product_id', 'product_variant_id', 'product_name', 'variant_name', 'unit_price', 'quantity', 'line_total']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['user_id', 'order_number', 'customer_name', 'customer_email', 'customer_phone', 'shipping_address', 'notes', 'subtotal', 'shipping_fee', 'total', 'status', 'payment_status']));
        Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn(['product_id', 'path', 'alt_text', 'position']));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn(['product_id', 'name', 'sku', 'price', 'stock_quantity', 'is_active']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['category_id', 'name', 'slug', 'description', 'sku', 'base_price', 'stock_quantity', 'is_active', 'is_featured']));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn(['name', 'slug', 'description', 'image_path', 'is_active', 'position']));
    }
};
