<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['is_active', 'position']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('status')->default('active');
            $table->unsignedBigInteger('price_rwf')->default(0);
            $table->index(['status', 'is_featured', 'created_at']);
            $table->index(['category_id', 'status']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('colour')->nullable();
            $table->unsignedBigInteger('price_rwf')->nullable();
            $table->unsignedInteger('reserved')->default(0);
            $table->index(['product_id', 'is_active']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('public_reference')->nullable()->unique();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('delivery_location')->nullable();
            $table->unsignedBigInteger('subtotal_rwf')->default(0);
            $table->unsignedBigInteger('delivery_fee_rwf')->default(0);
            $table->unsignedBigInteger('total_rwf')->default(0);
            $table->string('order_status')->default('awaiting_payment');
            $table->string('delivery_status')->default('not_dispatched');
            $table->timestamp('expires_at')->nullable()->index();
            $table->index(['order_status', 'payment_status']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_id')->nullable()->index();
            $table->unsignedBigInteger('unit_price_rwf')->default(0);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider')->default('manual');
            $table->string('provider_reference')->nullable();
            $table->unsignedBigInteger('amount_rwf')->default(0);
            $table->string('currency', 3)->default('RWF');
            $table->string('raw_status')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unique(['provider', 'provider_reference']);
        });

        Schema::table('payment_events', function (Blueprint $table) {
            $table->string('provider')->default('manual');
            $table->string('event_id')->nullable()->unique();
            $table->timestamp('processed_at')->nullable();
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->timestamp('uploaded_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('review_note')->nullable();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unique('key');
        });

        Schema::create('policy_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('body');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        foreach (DB::table('orders')->whereNull('public_reference')->get(['id', 'order_number', 'customer_phone', 'shipping_address', 'subtotal', 'shipping_fee', 'total', 'status', 'payment_status']) as $order) {
            DB::table('orders')->where('id', $order->id)->update([
                'public_reference' => 'KB-'.Str::upper(Str::random(20)),
                'delivery_location' => $order->shipping_address,
                'subtotal_rwf' => (int) $order->subtotal,
                'delivery_fee_rwf' => (int) $order->shipping_fee,
                'total_rwf' => (int) $order->total,
                'order_status' => $order->status === 'pending' ? 'awaiting_payment' : $order->status,
            ]);
        }

        DB::table('products')->where('price_rwf', 0)->update(['price_rwf' => DB::raw('CAST(base_price AS INTEGER)')]);

        foreach (DB::table('products')->get(['id', 'base_price', 'stock_quantity']) as $product) {
            if (! DB::table('product_variants')->where('product_id', $product->id)->exists()) {
                DB::table('product_variants')->insert([
                    'product_id' => $product->id,
                    'name' => 'Standard',
                    'colour' => null,
                    'price' => $product->base_price,
                    'price_rwf' => (int) $product->base_price,
                    'stock_quantity' => $product->stock_quantity,
                    'reserved' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                foreach (DB::table('product_variants')
                    ->where('product_id', $product->id)
                    ->whereNull('price_rwf')
                    ->get(['id', 'price']) as $variant) {
                    DB::table('product_variants')->where('id', $variant->id)->update([
                        'price_rwf' => (int) ($variant->price ?? $product->base_price),
                    ]);
                }
            }
        }

        foreach (DB::table('order_items')->where('unit_price_rwf', 0)->get(['id', 'unit_price']) as $item) {
            DB::table('order_items')->where('id', $item->id)->update(['unit_price_rwf' => (int) $item->unit_price]);
        }

        foreach (DB::table('payments')->where('amount_rwf', 0)->get(['id', 'amount']) as $payment) {
            DB::table('payments')->where('id', $payment->id)->update(['amount_rwf' => (int) $payment->amount]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('policy_pages');
        Schema::table('settings', fn (Blueprint $table) => $table->dropUnique(['key']));
        Schema::table('payment_proofs', fn (Blueprint $table) => $table->dropColumn(['uploaded_at', 'reviewed_by', 'review_note']));
        Schema::table('payment_events', fn (Blueprint $table) => $table->dropUnique(['event_id'])->dropColumn(['provider', 'event_id', 'processed_at']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropUnique(['provider', 'provider_reference'])->dropColumn(['provider', 'provider_reference', 'amount_rwf', 'currency', 'raw_status', 'verified_at', 'verified_by']));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropIndex(['variant_id'])->dropColumn(['variant_id', 'unit_price_rwf']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropUnique(['public_reference'])->dropUnique(['idempotency_key'])->dropIndex(['expires_at'])->dropIndex(['order_status', 'payment_status'])->dropColumn(['public_reference', 'idempotency_key', 'delivery_location', 'subtotal_rwf', 'delivery_fee_rwf', 'total_rwf', 'order_status', 'delivery_status', 'expires_at']));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropIndex(['product_id', 'is_active'])->dropColumn(['colour', 'price_rwf', 'reserved']));
        Schema::table('products', fn (Blueprint $table) => $table->dropIndex(['status', 'is_featured', 'created_at'])->dropIndex(['category_id', 'status'])->dropColumn(['status', 'price_rwf']));
        Schema::table('categories', fn (Blueprint $table) => $table->dropIndex(['is_active', 'position']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
