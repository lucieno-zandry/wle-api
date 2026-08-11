<?php

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('status')
                ->default(OrderStatus::PENDING_PAYMENT->value);
            $table->float('total');
            $table->foreignIdFor(User::class);
            $table->foreignIdFor(Address::class);
            $table->foreignId('coupon_id')->nullable();
            $table->float('coupon_discount_applied')->nullable();
            $table->softDeletes();
            $table->json('address_snapshot');
            $table->json('coupon_snapshot')->nullable();
            $table->text('notes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
