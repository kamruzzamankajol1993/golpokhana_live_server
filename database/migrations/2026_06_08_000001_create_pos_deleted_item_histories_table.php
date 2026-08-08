<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_deleted_item_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_detail_id')->nullable()->constrained('order_details')->nullOnDelete();
            $table->foreignId('order_kot_id')->nullable()->constrained('order_kots')->nullOnDelete();
            $table->unsignedBigInteger('food_id')->nullable();
            $table->string('product_name')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('addon_total', 12, 2)->default(0);
            $table->integer('deleted_quantity')->default(0);
            $table->integer('previous_quantity')->default(0);
            $table->integer('remaining_quantity')->default(0);
            $table->decimal('subtotal_removed', 12, 2)->default(0);
            $table->string('source')->default('cart'); // cart / ordered_item
            $table->string('cart_key')->nullable();
            $table->string('cart_item_key')->nullable();
            $table->string('order_type')->nullable();
            $table->unsignedBigInteger('table_id')->nullable();
            $table->json('addons')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'order_detail_id']);
            $table->index(['source', 'created_at']);
            $table->index('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_deleted_item_histories');
    }
};
