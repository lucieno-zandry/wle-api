<?php

use App\Enums\TransactionTypes;
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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->timestamps();

            // Status and payload
            $table->enum('status', ['FAILED', 'PENDING', 'SUCCESS'])->default('PENDING');
            $table->json('informations')->nullable();

            // Relationships
            $table->foreignIdFor(User::class);
            $table->foreignUuid('order_uuid');
            $table->softDeletes();

            // Payment instrument details (new structure)
            $table->string('payment_method');                  // card, paypal, apple_pay, etc.
            $table->string('card_brand')->nullable();          // visa, mastercard, amex, … (only for cards)
            $table->string('payment_method_label')->nullable(); // Human-friendly label, e.g., "Visa •••• 4242"

            // Payment processing
            $table->float('amount')->default(0);

            // Transaction type and self-referential linkage
            $table->string('type')->default(TransactionTypes::toArray()[0]);
            $table->string('parent_transaction_uuid')->nullable();
            $table->foreign('parent_transaction_uuid')
                ->references('uuid')
                ->on('transactions')
                ->nullOnDelete();

            // Searchable reference (often from gateway)
            $table->string('payment_reference')->nullable();

            // Admin review tracking
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Notes for manual adjustments
            $table->text('notes')->nullable();

            // Dispute tracking
            $table->enum('dispute_status', ['OPEN', 'RESOLVED', 'LOST'])->nullable();
            $table->timestamp('dispute_opened_at')->nullable();
            $table->timestamp('dispute_resolved_at')->nullable();
            $table->text('dispute_reason')->nullable();

            // Indexes for query speed
            $table->index('status');
            $table->index('payment_method');
            $table->index('card_brand');               // useful for analytics
            $table->index('type');
            $table->index('payment_reference');
            $table->index('reviewed_at');
            $table->index('dispute_status');

            // Composite indexes for common filter combinations
            $table->index(['user_id', 'status']);
            $table->index(['order_uuid', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
