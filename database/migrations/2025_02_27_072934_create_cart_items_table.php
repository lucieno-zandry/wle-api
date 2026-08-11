<?php

use App\Models\User;
use App\Models\Variant;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->smallInteger('count');
            $table->foreignUuid('order_uuid')->nullable();
            $table->foreignIdFor(Variant::class);
            $table->foreignIdFor(Product::class);
            $table->float('promotion_discount_applied')->nullable();
            $table->float('total');
            $table->foreignIdFor(User::class);
            $table->json("product_snapshot");
            $table->json("variant_snapshot");
            $table->json("variant_options_snapshot");
            $table->float('unit_price');
            $table->json('applied_promotions_snapshot')
                ->nullable()
                ->comment('Snapshot of promotions that contributed to the final price');
            $table->string('active_cart_key')
                ->virtualAs("CASE WHEN order_uuid IS NULL THEN CONCAT(user_id, '-', variant_id) ELSE NULL END")
                ->nullable()
                ->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
