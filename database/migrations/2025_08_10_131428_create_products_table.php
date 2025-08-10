<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('shopify_id')->unique()->nullable();
            $table->string('handle')->nullable()->index();
            $table->string('status')->nullable()->index();

            $table->string('title')->nullable()->index();
            $table->longText('description')->nullable();
            $table->string('product_type')->nullable()->index();

            $table->integer('total_inventory')->nullable();

            $table->decimal('price_min', 12, 2)->nullable();
            $table->decimal('price_max', 12, 2)->nullable();
            $table->string('currency', 8)->nullable();

            $table->string('image')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('image_variant')->nullable();

            $table->text('online_store_url')->nullable();
            $table->text('online_store_preview_url')->nullable();

            $table->string('variant_id')->nullable();
            $table->decimal('variant_price', 12, 2)->nullable();
            $table->decimal('variant_compare_at', 12, 2)->nullable();
            $table->string('variant_image')->nullable();

            $table->enum('last_sync_source', ['sync', 'webhook'])->nullable()->index();
            $table->string('last_webhook_id', 64)->nullable()->index();

            $table->timestamp('remote_updated_at')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
