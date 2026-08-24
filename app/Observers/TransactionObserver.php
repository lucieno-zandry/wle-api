<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\RefundService;

class TransactionObserver
{
    public function saved(Transaction $transaction)
    {
        $order = Order::where('uuid', $transaction->order_uuid)->first();

        if (!$order) return;

        if ($order->status === OrderStatus::CANCELLED) {
            app(RefundService::class)
                ->requestRefund($transaction, "Payment received for already cancelled order #{$order->uuid}.");
        } else if ($transaction->status === TransactionStatus::SUCCESS->value) {
            $order->status = OrderStatus::PAID;
        }

        $order->save();
    }
}
