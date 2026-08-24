<?php

namespace App\Services;

use App\Models\Order;
use App\Models\RefundRequest;
use App\Enums\OrderStatus;
use App\Events\OrderCancelled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderCancellationService
{
    /**
     * Cancel an order, restock items, and generate a refund request.
     */
    public function cancelOrder(Order $order, string $reason = 'Customer requested cancellation'): void
    {
        // Execute everything within a single database transaction
        DB::transaction(function () use ($order, $reason) {

            // 1. Update Order Status
            $order->status = OrderStatus::CANCELLED;
            $order->save();

            // 2. Restock Inventory Atomically
            foreach ($order->cart_items as $item) {
                // stock = stock + quantity 
                $item->variant()->increment('stock', $item->count ?? $item->quantity);
            }

            // 3. Trigger Refund Request (if a successful transaction exists)
            $refundRequest = null;
            $successfulTransaction = $order->transactions()->where('status', 'SUCCESS')->first();

            if ($successfulTransaction) {
                $refundRequest = app(RefundService::class)->requestRefund($successfulTransaction, $reason);
            }

            // 4. Dispatch Event to notify systems (Emails, DB Notifications)
            event(new OrderCancelled($order, $refundRequest, $reason));
        });
    }
}
